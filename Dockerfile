FROM php:8.4-fpm-alpine AS php-base

WORKDIR /var/www/html

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
ENV LOG_LEVEL=error
ENV QUEUE_CONNECTION=database
ENV QUEUE_NAMES=webhooks,integrations,reminders,billing

RUN apk add --no-cache \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    su-exec \
    unzip \
    zip \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    opcache \
    pcntl \
    posix \
    pdo_mysql \
    pdo_pgsql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php-base AS runtime

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/bootstrap-runtime.sh /usr/local/bin/bootstrap-runtime
COPY docker/start-app.sh /usr/local/bin/start-app
COPY docker/start-queue.sh /usr/local/bin/start-queue
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php-fpm/zz-kynex-www.conf /usr/local/etc/php-fpm.d/zz-kynex-www.conf

RUN chmod +x /usr/local/bin/bootstrap-runtime /usr/local/bin/start-app /usr/local/bin/start-queue /usr/local/bin/start-scheduler \
    && mkdir -p bootstrap/cache \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 9000

CMD ["/usr/local/bin/start-app"]
