# CapRover's Docker daemon sends docker.io pulls to docker.arvancloud.ir, and that
# host does not resolve on the server. These registries are pulled directly.
FROM registry.docker.ir/library/php:8.5.12-cli-alpine3.24 AS vendor

RUN apk add --no-cache \
    git \
    icu-dev \
    libzip-dev \
    unzip \
    $PHPIZE_DEPS \
    && docker-php-ext-install intl zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/* /tmp/*

COPY --from=registry.docker.ir/library/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN composer config -g repos.packagist composer https://package-mirror.liara.ir/repository/composer/ 

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-gd

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-gd

FROM registry.docker.ir/library/node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
COPY public ./public
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

FROM registry.docker.ir/library/php:8.5.12-fpm-alpine3.24 AS runtime

# Alpine 3.24 dropped postgresql-dev; libpq-dev provides headers for pdo_pgsql.
# Built into the official PHP 8.5 image — do not reinstall:
# mbstring, pdo_sqlite, and opcache (always loaded in 8.5; no opcache.so).
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    libpq-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    linux-headers \
    wget \
    ffmpeg \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    gd \
    intl \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/* /tmp/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-timeouts.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/bin/queue-worker /usr/local/bin/queue-worker
COPY docker/bin/scheduler /usr/local/bin/scheduler
COPY docker/bin/healthcheck /usr/local/bin/healthcheck
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/queue-worker /usr/local/bin/scheduler /usr/local/bin/healthcheck

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Never ship the Vite HMR marker; @vite would point browsers at :5173.
RUN rm -f public/hot \
    && find public -name '._*' -delete

RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www-data:www-data storage bootstrap/cache database

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CONTAINER_ROLE=web \
    RUN_MIGRATIONS=true

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD /usr/local/bin/healthcheck

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
