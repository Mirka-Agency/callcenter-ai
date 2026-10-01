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

# Vite writes public/hot during local `npm run dev`. If that file is present in
# production, @vite serves http://[::1]:5173 and charts/JS never load.
rm -f public/hot

php artisan package:discover --ansi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Several analysis workers run in parallel. SQLite locks the whole file, so
# more than one analysis worker there fails jobs with "database is locked".
if [ -z "${ANALYSIS_QUEUE_WORKERS:-}" ]; then
    ANALYSIS_QUEUE_WORKERS=3
fi
case "$ANALYSIS_QUEUE_WORKERS" in
    ''|*[!0-9]*) ANALYSIS_QUEUE_WORKERS=3 ;;
esac
if [ "$ANALYSIS_QUEUE_WORKERS" -lt 1 ]; then
    ANALYSIS_QUEUE_WORKERS=1
fi
if [ "$DB_CONNECTION" = "sqlite" ] && [ "$ANALYSIS_QUEUE_WORKERS" -gt 1 ]; then
    echo "SQLite queue: capping ANALYSIS_QUEUE_WORKERS at 1" >&2
    ANALYSIS_QUEUE_WORKERS=1
fi
export ANALYSIS_QUEUE_WORKERS

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
    ami)
        exec php artisan voip:ami-listen --reconnect-delay="${AMI_RECONNECT_DELAY:-5}"
        ;;
    *)
        exec "$@"
        ;;
esac
