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

if [ "${CONTAINER_ROLE:-web}" = "web" ] && [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
    php artisan migrate --force --no-interaction
fi

case "${CONTAINER_ROLE:-web}" in
    web)
        exec supervisord -c /etc/supervisord.conf
        ;;
    queue)
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=300
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8090}"
        ;;
    *)
        exec "$@"
        ;;
esac
