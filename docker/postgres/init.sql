-- Дополнительная БД для тестов (создаётся при первом запуске контейнера postgres)
SELECT 'CREATE DATABASE notification_service_test OWNER app'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'notification_service_test')\gexec
