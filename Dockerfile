# ----------- Composer Stage -----------
FROM composer:2.8 AS vendor

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize

# ----------- App Stage -----------
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install dependencies
RUN apk add --no-cache \
    bash \
    curl \
    icu-libs \
    libxml2 \
    libzip \
    oniguruma \
    mariadb-connector-c \
    supervisor \
    tzdata \
    linux-headers \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    mariadb-connector-c-dev \
    openssl-dev \
    pcre-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    intl \
    zip \
    bcmath \
    opcache \
    && pecl install redis \
    && pecl install swoole \
    && docker-php-ext-enable redis swoole \
    && apk del .build-deps

# Copy app
COPY --from=vendor /var/www/html /var/www/html

# Permissions
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Entrypoint scripts (IRV-style: storage setup, migrations, cache, all-in-one supervisord)
COPY docker-entrypoint.sh /usr/local/bin/
COPY docker-worker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-worker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
# Default: app only. For single-container (app+worker+scheduler), set RUN_WORKER_AND_SCHEDULER=1
CMD ["php", "/var/www/html/artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]