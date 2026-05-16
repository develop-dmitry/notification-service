#!/bin/sh
set -e

php artisan rabbitmq:queue-declare notifications.high --durable=1
php artisan rabbitmq:queue-declare notifications.low  --durable=1

exec php artisan queue:work rabbitmq \
  --queue=notifications.high,notifications.low \
  --tries=5 --backoff=5,15,60,300,900 \
  --max-time=3600 --sleep=1
