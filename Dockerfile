FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:20-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-cli-bookworm

WORKDIR /var/www/html

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
ENV PORT=8080
ENV QUEUE_CONNECTION=database
ENV QUEUE_NAMES=webhooks,integrations,reminders

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libicu-dev \
    libonig-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    supervisor \
    unzip \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/start-container.sh /usr/local/bin/start-container
COPY docker/start-queue.sh /usr/local/bin/start-queue
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/start-container /usr/local/bin/start-queue /usr/local/bin/start-scheduler \
    && mkdir -p bootstrap/cache \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["/usr/local/bin/start-container"]
