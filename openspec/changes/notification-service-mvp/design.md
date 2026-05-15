## Context

Сервис на Laravel 12 / PHP 8.4. Инфраструктурная обвязка уже готова и не требует доработки:

- `docker-compose.yml` поднимает: `app` (php-fpm, собирается из `docker/php/Dockerfile`), `nginx` (порт 8080, конфиг `docker/nginx/default.conf`), `worker` (`php artisan queue:work rabbitmq --queue=notifications.high,notifications.low --tries=5 --backoff=5,15,60,300,900 --max-time=3600 --sleep=1`), `scheduler` (`php artisan schedule:work`), `postgres:18-alpine`, `redis:8-alpine`, `rabbitmq:4-management-alpine`, `mailpit`. У всех зависимых сервисов настроены healthcheck-и, `app` ждёт их через `condition: service_healthy`.
- В `.env.example` уже выставлены: `DB_CONNECTION=pgsql`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=rabbitmq`, `RABBITMQ_*`, `MAIL_*` (Mailpit).
- В `composer.json` уже подключены: `vladimir-yuldashev/laravel-queue-rabbitmq`, `spatie/laravel-data`, `dedoc/scramble`, phpredis в `.env.example`. `larastan`, `pint`, `phpunit`, `rector` — в dev.
- `bootstrap/app.php` уже регистрирует роутинг `routes/api.php` и health-эндпоинт `/up`.
- Контейнер `app` на старте при `RUN_MIGRATIONS=1` выполняет миграции (см. `docker/php/Dockerfile` и entrypoint).

Этот документ описывает прикладную архитектуру поверх готовой инфраструктуры.

## Goals / Non-Goals

**Goals:**
- Полностью реализовать бизнес-требования: bulk-API, приоритезация транзакционных сообщений, статусы и история на подписчика.
- Обеспечить надёжность: at-least-once через persistent-сообщения RabbitMQ и manual ack, exactly-once-effect через идемпотентную проверку статуса в воркере под `SELECT FOR UPDATE`.
- Поддержать retry с экспоненциальным backoff (значения уже заданы в команде воркера).
- Чистая, тестируемая архитектура (Domain / Application / Infrastructure / Http).
- Покрытие основных сценариев интеграционными тестами на реальных Postgres/Redis/RabbitMQ.
- OpenAPI на `/docs/api` (Scramble).

**Non-Goals:**
- Реальная интеграция со шлюзами SMS/Email — только мок-классы (Email-мок при желании может публиковать в Mailpit для наглядности).
- Аутентификация и многотенантность.
- UI/Dashboard.
- Полноценная observability-стэк (Prometheus/Grafana). Достаточно структурированных логов и `/up`.
- Изменение инфраструктуры compose, переименование уже существующих очередей, замена nginx+php-fpm на что-то иное.

## Decisions

### 1. Брокер и очереди

**Что есть:** RabbitMQ 4 + драйвер `vladimir-yuldashev/laravel-queue-rabbitmq`, две очереди — `notifications.high` (высокий приоритет, транзакционные) и `notifications.low` (фоновый, маркетинговые). Воркер уже стартует с `--queue=notifications.high,notifications.low` — порядок очередей в этой команде определяет приоритет: воркер всегда сначала опустошает `high`, и только потом берёт `low`.

**Что добавляем:** в `config/queue.php` для драйвера `rabbitmq` зафиксировать два «именованных» queue-options блока (durable, persistent, prefetch=1). Маппинг приоритета сообщения на имя очереди — на стороне приложения: метод `Priority::queue()` возвращает `notifications.high` или `notifications.low`.

**Persistence:** все сообщения публикуются `delivery_mode=2`, очереди — durable. Ack — manual, только после успешной обработки.

### 2. Доменная модель и схема БД

Таблицы (PostgreSQL 18):

- `notification_batches` — заголовок массовой рассылки: `id (uuid)`, `channel (sms|email)`, `priority (high|low)`, `message (text)`, `idempotency_key (nullable, unique)`, `created_at`, `updated_at`.
- `notifications` — одно сообщение на одного получателя: `id (uuid)`, `batch_id (fk)`, `recipient_id`, `recipient_address`, `channel`, `priority`, `status (queued|sent|delivered|failed)`, `attempts_count`, `last_error`, `provider_message_id`, `published_at`, `created_at`, `updated_at`, `sent_at`, `delivered_at`, `failed_at`. Индексы: `(recipient_id, created_at desc)`, `(status)`, частичный `(status) where status='queued'` — для reconciler.
- `notification_attempts` — журнал попыток: `id`, `notification_id`, `attempt_no`, `result`, `error`, `created_at`.

Идемпотентность входящего API — Redis: ключ `idem:{key}` → JSON со снимком ответа и хешем тела запроса, TTL 24ч. На уровне обработки в воркере идемпотентным ключом служит `notifications.id` — повторная доставка той же job делает no-op, если статус не `queued`.

### 3. Жизненный цикл и переходы статусов

```
[API] -> создать batch + N notifications (status=queued) -> опубликовать N job в RabbitMQ
         (запись в БД коммитится первой, публикация — после; на сбой публикации работает reconciler)

[Worker] consume job(notification_id):
  - SELECT ... FOR UPDATE notification
  - if status != queued -> ack & return        // exactly-once-effect
  - call provider.send()
  - on accepted: status=sent, provider_message_id, sent_at
  - on transient error: throw -> Laravel queue retry с backoff
  - on permanent error: status=failed, ack без повторов
  - запись в notification_attempts
```

**Доставка `delivered`:** мок-провайдер либо сразу возвращает `accepted`, либо асинхронно вызывает локальный эндпоинт `POST /api/v1/_internal/provider-callback` (имитация webhook), который переводит `sent → delivered`. Для тестов этот эндпоинт вызывается из теста явно.

**Outbox vs прямая публикация:** используем «запись + публикация после commit» (Laravel job dispatch after commit) и фоновый reconciler. У уведомления есть поле `published_at`. Reconciler — артизан-команда `notifications:reconcile`, запускается планировщиком (`scheduler` уже в compose) каждую минуту: ищет уведомления со `status='queued' AND published_at IS NULL AND created_at < now() - interval '60s'` и переопубликовывает их.

### 4. Retry-стратегия

Используется встроенный Laravel `tries` + `backoff` — значения уже выставлены в команде воркера: `--tries=5 --backoff=5,15,60,300,900`. Job наследует эти параметры. Permanent errors (невалидный email/phone от мока) — бросаем `PermanentDeliveryException`, ловим в самом job и переводим в `failed` без повтора. По исчерпании попыток в Laravel срабатывает `failed()`-метод job, который тоже выставляет `failed` и пишет `last_error`.

### 5. Идемпотентность API

Middleware `EnsureIdempotency` для группы маршрутов bulk-эндпоинта: читает `Idempotency-Key` (UUID v4), `SETNX` в Redis (`idem:{key}` с маркером «в процессе»), при существующем ключе сравнивает хеш тела:

- совпало → отдаёт сохранённый снимок ответа,
- не совпало → 409,
- ключ есть, но снимка ещё нет (другой запрос в полёте) → 425 Too Early либо 409.

После успешной обработки middleware сохраняет статус-код, заголовки и тело ответа в Redis с TTL=24ч. Запросы без заголовка для bulk-эндпоинта возвращают 400.

### 6. Провайдеры

Интерфейс `App\Domain\Notifications\Contracts\NotificationProvider`:

```php
public function send(Notification $n): ProviderResult;
```

Реализации: `MockSmsProvider`, `MockEmailProvider`. Поведение управляется конфигом:

- `MOCK_PROVIDER_FAILURE_RATE` (0..1) — вероятность transient-ошибки;
- `MOCK_PROVIDER_LATENCY_MS` — имитация задержки;
- спец-адреса: `*fail*` → permanent, `*flaky*` → transient, `*slow*` → задержка;
- `MockEmailProvider` опционально шлёт реальное письмо через Mailpit (через стандартный Laravel Mail) — удобно для ручного смок-теста через `http://localhost:8025`.

Резолвинг провайдера — через сервис-контейнер по каналу (`channel('sms') -> MockSmsProvider`, `channel('email') -> MockEmailProvider`).

### 7. API-контракт (v1)

- `POST /api/v1/notifications` — старт рассылки. Body: `{ channel, priority?, message, recipients: [{ id, address }] }`. Header: `Idempotency-Key`. Response 202: `{ batch_id, accepted: N }`.
- `GET /api/v1/subscribers/{id}/notifications` — список уведомлений подписчика с фильтрами `status`, `channel` и пагинацией.
- `POST /api/v1/_internal/provider-callback` — имитация webhook от провайдера для перехода `sent → delivered|failed`. Регистрируется только в окружениях `local` и `testing`: роут добавляется в `routes/api.php` под условием `app()->environment(['local', 'testing'])`. В production эндпоинт отсутствует (404) и не виден в `route:list`. Для реальных провайдеров позднее будут отдельные защищённые webhook-эндпоинты с проверкой подписи.
- `GET /up` — health (уже есть из `bootstrap/app.php`).
- OpenAPI: `dedoc/scramble` сгенерирует `/docs/api` по FormRequest/Resources/Data DTO.

### 8. Тестирование

- **Интеграционные** PHPUnit-тесты гоняются внутри docker-compose окружения (`docker compose exec app vendor/bin/phpunit`). RabbitMQ, Postgres, Redis — реальные сервисы compose.
- Покрываемые сценарии:
  1. POST /notifications → batch создан, N сообщений `queued`, N job опубликованы в правильную очередь по приоритету.
  2. Воркер обрабатывает job → статус `sent`, мок-провайдер вызван с правильными аргументами.
  3. Provider callback → `delivered`.
  4. Transient fail → retry → eventual success.
  5. Permanent fail → `failed` без повторов.
  6. Повторный POST с тем же Idempotency-Key → 202 + тот же batch_id, без новых job.
  7. Idempotency-Key с другим телом → 409.
  8. Транзакционная рассылка обгоняет маркетинговую при общей загрузке.
  9. GET /subscribers/{id}/notifications возвращает корректные статусы и историю.
- **Юнит-тесты**: маппинг DTO, статус-машина, мок-провайдеры, middleware идемпотентности.

### 9. Запуск

Команда не меняется: `docker compose up -d --build`. Точки наблюдения:

- App: `http://localhost:8080`
- OpenAPI: `http://localhost:8080/docs/api`
- RabbitMQ UI: `http://localhost:15672` (app/secret)
- Mailpit UI: `http://localhost:8025`

## Risks / Trade-offs

- **[Гонка между записью в БД и публикацией в RabbitMQ]** → используется `Bus::dispatch()->afterCommit()` + reconciler по `published_at IS NULL`. Альтернатива (отдельная outbox-таблица) даёт более строгие гарантии, но добавляет код без выигрыша для текущих SLO.
- **[Сложность отладки интеграционных тестов с реальным брокером]** → в тестах используем polling с тайм-аутом и явный вызов воркера через `Queue::pop()`/`work-once` команду вместо ожидания фонового сервиса.
- **[Недоступность Redis блокирует API]** → отдаём 503; для текущих требований приемлемо, можно добавить fallback в БД позже.
- **[Exactly-once гарантирован эффективно, не строго]** → достигается select-for-update + проверкой статуса; повторная доставка одной и той же job не приводит к повторной отправке.
- **[Scramble может не охватить все DTO/Resources]** → подправим атрибутами/PHPDoc там, где автогенерация дала пробелы.

## Migration Plan

Миграции БД накатываются автоматически контейнером `app` при `RUN_MIGRATIONS=1`. Откат — `php artisan migrate:rollback` либо пересоздание volumes (`docker compose down -v`). Никаких breaking-изменений во внешних контрактах нет — это первая версия API.

## Open Questions

- Слать ли реальные письма через Mailpit из `MockEmailProvider` по умолчанию или только под отдельным флагом? Предлагаемое решение: по флагу `MOCK_EMAIL_USE_MAILPIT=true`, по умолчанию выключено (чтобы не шумело в тестах).
- Нужна ли отдельная таблица `idempotency_keys` в БД как страховка к Redis? Сейчас: нет, Redis 24ч TTL достаточно.
