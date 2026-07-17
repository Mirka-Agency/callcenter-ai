#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/private/livewire-tmp \
    storage/logs \
    bootstrap/cache \
    database

chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true

if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite 2>/dev/null || true
fi

php artisan package:discover --ansi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

ROLE="${CONTAINER_ROLE:-web}"

if [ "$ROLE" = "web" ] && [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
    php artisan migrate --force --no-interaction
    php artisan integrations:sync-meta-definitions --no-interaction
fi

case "$ROLE" in
    web)
        exec supervisord -c /etc/supervisord.conf
        ;;
    queue)
        exec /usr/local/bin/queue-worker
        ;;
    scheduler)
        exec /usr/local/bin/scheduler
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8090}"
        ;;
    *)
        exec "$@"
        ;;
esac
