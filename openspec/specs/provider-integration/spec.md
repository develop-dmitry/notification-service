### Requirement: Контракт провайдера доставки

Система SHALL определять интерфейс `NotificationProvider` с методом `send(Notification): ProviderResult`, где `ProviderResult` содержит результат отправки (`accepted`, `transient_failure`, `permanent_failure`), идентификатор сообщения у провайдера и опциональное описание ошибки. Конкретные провайдеры MUST резолвиться из сервис-контейнера по каналу (`sms`, `email`).

#### Scenario: Резолвинг провайдера по каналу
- **WHEN** в коде запрашивается провайдер для канала `email`
- **THEN** контейнер возвращает реализацию `EmailProvider`, реализующую `NotificationProvider`

### Requirement: Мок-провайдеры для SMS и Email

Система SHALL поставлять классы-заглушки `MockSmsProvider` и `MockEmailProvider`, имитирующие реальные шлюзы. Поведение MUST конфигурироваться через переменные окружения (`MOCK_PROVIDER_FAILURE_RATE`, `MOCK_PROVIDER_LATENCY_MS`) и спец-адреса: адрес, содержащий `fail` — постоянная ошибка; адрес, содержащий `flaky` — временная ошибка; адрес, содержащий `slow` — повышенная задержка.

#### Scenario: Спец-адрес fail
- **WHEN** уведомление отправляется на адрес `fail@example.com`
- **THEN** мок-провайдер возвращает `permanent_failure` без задержки

#### Scenario: Спец-адрес flaky
- **WHEN** уведомление отправляется на адрес `flaky@example.com`
- **THEN** мок-провайдер возвращает `transient_failure`, что инициирует retry на уровне очереди

#### Scenario: Успешная отправка
- **WHEN** уведомление отправляется на обычный адрес `user@example.com` при нулевом `MOCK_PROVIDER_FAILURE_RATE`
- **THEN** мок-провайдер возвращает `accepted` с непустым идентификатором сообщения

### Requirement: Записывание попыток

Система SHALL после каждого вызова провайдера записывать в БД увеличение `attempts_count` и `last_error` для уведомления, а также — если включён детальный аудит — отдельную запись в `notification_attempts`.

#### Scenario: Логирование попытки
- **WHEN** воркер вызвал провайдера и получил `transient_failure`
- **THEN** у уведомления `attempts_count` увеличен на 1, поле `last_error` содержит текст ошибки
