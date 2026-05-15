## 1. Конфигурация Laravel

- [ ] 1.1 В `.env.example` добавить переменные мок-провайдеров: `MOCK_PROVIDER_FAILURE_RATE=0`, `MOCK_PROVIDER_LATENCY_MS=0`, `MOCK_EMAIL_USE_MAILPIT=false`, `IDEMPOTENCY_TTL_SECONDS=86400`.
- [ ] 1.2 В `bootstrap/app.php` зарегистрировать middleware `EnsureIdempotency` (alias для группы маршрутов) и при необходимости — глобальные обработчики исключений API (JSON-ответы).

## 2. Модель данных и миграции

- [ ] 2.1 Миграция `notification_batches`: uuid pk, channel, priority, message (text), idempotency_key (nullable, unique), timestamps.
- [ ] 2.2 Миграция `notifications`: uuid pk, batch_id fk, recipient_id, recipient_address, channel, priority, status enum, attempts_count int (default 0), last_error text null, provider_message_id null, published_at null, sent_at/delivered_at/failed_at null, timestamps; индексы (recipient_id, created_at desc), (status), partial по queued.
- [ ] 2.3 Миграция `notification_attempts`: id, notification_id fk, attempt_no, result, error, created_at.
- [ ] 2.4 Eloquent-модели `NotificationBatch`, `Notification`, `NotificationAttempt` с casts() и связями.
- [ ] 2.5 Фабрики и сидеры для использования в тестах.

## 3. Доменный слой

- [ ] 3.1 Enum-ы: `Channel { sms, email }`, `Priority { high, low }` с методом `queue(): string` (`notifications.high` / `notifications.low`), `NotificationStatus { queued, sent, delivered, failed }`.
- [ ] 3.2 Статус-машина `NotificationStatusTransition` с разрешёнными переходами и методом `apply()`; запрещённые переходы — no-op.
- [ ] 3.3 Контракт `NotificationProvider` и DTO `ProviderResult` (через `spatie/laravel-data`) с вариантами `accepted | transient_failure | permanent_failure`.
- [ ] 3.4 Класс `MockSmsProvider`: имитация задержки, спец-адреса `*fail*`/`*flaky*`/`*slow*`, поведение по `MOCK_PROVIDER_FAILURE_RATE`.
- [ ] 3.5 Класс `MockEmailProvider`: те же правила; при `MOCK_EMAIL_USE_MAILPIT=true` шлёт реальное письмо через Laravel Mail в Mailpit.
- [ ] 3.6 Зарегистрировать резолвинг провайдера по каналу в `AppServiceProvider`.
- [ ] 3.7 Исключения `TransientDeliveryException`, `PermanentDeliveryException`.

## 4. Прикладной слой и use-cases

- [ ] 4.1 DTO `CreateBatchData` (`spatie/laravel-data`) с правилами валидации: `channel`, `priority` (default `low`), `message` (required, max length), `recipients[]` (required, min 1, max N, каждая: `id`, `address` валиден для канала).
- [ ] 4.2 Use-case `CreateNotificationBatch`: транзакционно создаёт batch + N notifications в статусе `queued`, далее `Bus::dispatch(...)->afterCommit()` для каждого уведомления в нужную очередь по `Priority::queue()`. После публикации проставляет `published_at`.
- [ ] 4.3 Job `SendNotificationJob` (queue = очередь приоритета, tries и backoff — из команды воркера): `SELECT ... FOR UPDATE`, проверка `status=queued` (иначе ack & return), вызов провайдера, мапинг результата в переходы статусов, запись в `notification_attempts`, инкремент `attempts_count`.
- [ ] 4.4 В job: ловим `PermanentDeliveryException` → `status=failed` без re-throw; `TransientDeliveryException` → re-throw для retry; метод `failed()` — финальный `failed` с `last_error`.
- [ ] 4.5 Use-case `RecordProviderCallback`: валидация перехода через статус-машину, обновление `delivered_at`/`failed_at`/`last_error`.
- [ ] 4.6 Команда `notifications:reconcile` (artisan): ищет `queued` с `published_at IS NULL AND created_at < now() - 60s`, переопубликовывает.
- [ ] 4.7 Зарегистрировать reconciler в `routes/console.php` через `Schedule::command('notifications:reconcile')->everyMinute()->withoutOverlapping()`.

## 5. HTTP-слой

- [ ] 5.1 Контроллер `Api\V1\NotificationBatchController@store` + FormRequest/Data + ресурс ответа `BatchAcceptedResource` (202).
- [ ] 5.2 Контроллер `Api\V1\SubscriberNotificationsController@index` с пагинацией и фильтрами `status`, `channel`; ресурс `NotificationResource`.
- [ ] 5.3 Контроллер `Api\V1\Internal\ProviderCallbackController@store` (имитация webhook). Роут регистрируется в `routes/api.php` только при `app()->environment(['local', 'testing'])` — в production эндпоинт не существует.
- [ ] 5.4 Middleware `EnsureIdempotency`: парсинг `Idempotency-Key` (UUID v4), `SETNX` в Redis с маркером, сравнение body-хеша, 409 при конфликте, 400 при отсутствии заголовка на bulk-эндпоинте; сохранение снимка ответа с TTL `IDEMPOTENCY_TTL_SECONDS`.
- [ ] 5.5 Маршруты `routes/api.php` с группой `/api/v1` и middleware идемпотентности там, где нужно.
- [ ] 5.6 Глобальный JSON-обработчик ошибок валидации/доменных исключений.
- [ ] 5.7 Прогнать Scramble и проверить, что схема описывает все эндпоинты.

## 6. Тесты

- [ ] 6.1 В phpunit.xml для testsuite `Integration` переопределить `QUEUE_CONNECTION=rabbitmq` (текущее значение `sync` непригодно для проверки реального пайплайна); обеспечить очистку RabbitMQ-очередей и тестовой БД (`notification_service_test`) перед каждым тестом (например, через trait `RefreshDatabase` + `purgeQueues()` в `setUp`).
- [ ] 6.2 Тест: `POST /api/v1/notifications` создаёт batch и N уведомлений в `queued`, публикует N задач в очередь по приоритету.
- [ ] 6.3 Тест: воркер обрабатывает задачу → status `sent`, провайдер (Spy/Fake) был вызван с правильными аргументами.
- [ ] 6.4 Тест: `POST /_internal/provider-callback` переводит `sent → delivered`.
- [ ] 6.5 Тест: transient fail → retry → success после 3 попыток; `attempts_count=3`.
- [ ] 6.6 Тест: permanent fail (спец-адрес `*fail*`) → `failed` без повторов.
- [ ] 6.7 Тест: исчерпание попыток → `failed`, `last_error` заполнен.
- [ ] 6.8 Тест: повторный POST с тем же `Idempotency-Key` и тем же body → 202 с тем же `batch_id`, новые задачи не публикуются.
- [ ] 6.9 Тест: `Idempotency-Key` с изменённым body → 409.
- [ ] 6.10 Тест: транзакционная задача обрабатывается раньше маркетинговых, лежащих в `notifications.low`.
- [ ] 6.11 Тест: `GET /subscribers/{id}/notifications` отдаёт корректную историю, сортировку и фильтры.
- [ ] 6.12 Тест: reconciler переопубликовывает `queued` без `published_at`.
- [ ] 6.13 Юнит-тесты: статус-машина (запрещённые переходы — no-op), мок-провайдеры (поведение спец-адресов), middleware идемпотентности.

## 7. Качество кода

- [ ] 7.1 `vendor/bin/pint --format agent` — без замечаний.
- [ ] 7.2 `vendor/bin/phpstan analyse` (larastan) — уровень из конфига, без ошибок.
- [ ] 7.3 `php artisan test --compact` — все тесты зелёные.

## 8. Документация

- [ ] 8.1 Обновить `README.md`: краткое описание архитектуры, запуск (`docker compose up -d --build`), переменные окружения, примеры `curl` для всех эндпоинтов, ссылки на `/docs/api`, RabbitMQ UI (15672) и Mailpit UI (8025), как запускать тесты (`docker compose exec app php artisan test`).
- [ ] 8.2 Экспорт OpenAPI / Postman-коллекция в `docs/` (можно сгенерировать из Scramble).
- [ ] 8.3 Финальная проверка: `docker compose up -d --build` поднимает всё, тесты проходят, README актуален, `git status` чистый.
