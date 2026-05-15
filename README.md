# Notification Service

Микросервис уведомлений: bulk-рассылка SMS и Email через брокер сообщений с приоритезацией транзакционного трафика, идемпотентностью входящего API и отслеживанием статусов доставки.

## Стек

- PHP 8.4, Laravel 12
- PostgreSQL 18 (хранилище)
- Redis 8 (кэш / идемпотентность)
- RabbitMQ 4 + `vladimir-yuldashev/laravel-queue-rabbitmq` (очереди `notifications.high` / `notifications.low`)
- Mailpit (SMTP для локальной разработки)
- `spatie/laravel-data` (DTO/валидация), `dedoc/scramble` (OpenAPI), Pint, Larastan, PHPUnit, Rector

## Архитектура

```
HTTP API ─┬─► CreateNotificationBatch ─► PG (batch + N notifications) ─► RabbitMQ ─► Worker ─► Provider (SMS/Email)
          │                                                                                     │
          └─► EnsureIdempotency (Redis SETNX)                              status: queued → sent ─► (callback) ─► delivered
                                                                                     └─► transient → retry / permanent → failed
```

- **Bulk API**: `POST /api/v1/notifications` принимает `{ channel, priority?, message, recipients[] }`, требует `Idempotency-Key` и возвращает `202 { batch_id, accepted }`.
- **История подписчика**: `GET /api/v1/subscribers/{id}/notifications` с фильтрами `status`, `channel` и пагинацией.
- **Имитация webhook провайдера** (только `local` / `testing`): `POST /api/v1/_internal/provider-callback` переводит `sent → delivered | failed`.
- **Воркер** (`SendNotificationJob`): `SELECT FOR UPDATE` → провайдер → запись статуса + `notification_attempts`. At-least-once через manual ack + idempotent re-check по `notification_id`.
- **Retry**: `--tries=5 --backoff=5,15,60,300,900` (значения заданы в команде воркера). Permanent ошибки — мгновенный `failed` без повторов.
- **Outbox-lite**: запись в БД коммитится первой, далее dispatch + `published_at`. Команда `notifications:reconcile` (Schedule everyMinute, withoutOverlapping) переопубликовывает `queued` без `published_at` старше grace-периода.

## Запуск

```bash
cp .env.example .env
docker compose up -d --build
```

Поднимутся сервисы: `app` (php-fpm), `nginx`, `worker`, `scheduler`, `postgres`, `redis`, `rabbitmq`, `mailpit`. Миграции выполняются автоматически при старте `app` (при `RUN_MIGRATIONS=1`).

| Назначение      | URL                          |
|-----------------|------------------------------|
| API             | http://localhost:8080        |
| OpenAPI         | http://localhost:8080/docs/api |
| RabbitMQ UI     | http://localhost:15672 (app/secret) |
| Mailpit UI      | http://localhost:8025        |

## Переменные окружения

| Имя | Назначение | По умолчанию |
|-----|------------|--------------|
| `QUEUE_CONNECTION` | Драйвер очереди | `rabbitmq` |
| `MOCK_PROVIDER_FAILURE_RATE` | Доля случайных transient-ошибок мока (0..1) | `0` |
| `MOCK_PROVIDER_LATENCY_MS` | Имитация задержки шлюза, мс | `0` |
| `MOCK_EMAIL_USE_MAILPIT` | Реально отправлять письма через Mailpit | `false` |
| `IDEMPOTENCY_TTL_SECONDS` | TTL снимка ответа по `Idempotency-Key` | `86400` |

Спец-адреса мок-провайдеров:
- `*fail*` → permanent failure (без retry)
- `*flaky*` → transient failure (вызывает retry)
- `*slow*` → искусственная задержка

## Примеры запросов

Старт массовой рассылки:

```bash
curl -X POST http://localhost:8080/api/v1/notifications \
  -H 'Content-Type: application/json' \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "channel": "email",
    "priority": "high",
    "message": "Привет!",
    "recipients": [
      {"id": "user-1", "address": "ivan@example.com"},
      {"id": "user-2", "address": "petr@example.com"}
    ]
  }'
```

История подписчика:

```bash
curl 'http://localhost:8080/api/v1/subscribers/user-1/notifications?status=delivered&channel=email'
```

Имитация webhook провайдера (только в local/testing):

```bash
curl -X POST http://localhost:8080/api/v1/_internal/provider-callback \
  -H 'Content-Type: application/json' \
  -d '{"notification_id": "<uuid>", "status": "delivered"}'
```

## Тесты

```bash
# полный прогон
docker compose exec app php artisan test --compact

# только unit
docker compose exec app php artisan test --testsuite=Unit

# только интеграционные (используют реальные Postgres / Redis / RabbitMQ)
docker compose exec app php artisan test --testsuite=Integration
```

Интеграционные тесты переопределяют имена очередей на `test.notifications.*`, чтобы не конкурировать с фоновым `worker`-контейнером.

## Качество кода

Все проверки заведены как composer-скрипты:

```bash
composer lint        # pint --format agent (применить стиль)
composer lint:test   # pint --test (только проверить)
composer stan        # phpstan analyse (level 8 + larastan)
composer rector      # rector process (применить)
composer rector:test # rector process --dry-run
composer check       # lint:test + stan + rector:test одной командой
```

## Полезные команды

```bash
docker compose exec app php artisan notifications:reconcile   # ручной запуск reconciler
docker compose exec app php artisan route:list --except-vendor
docker compose exec -e DB_DATABASE=notification_service_test app php artisan migrate:fresh
docker compose logs -f worker                                 # логи воркера
```
