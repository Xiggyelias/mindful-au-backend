FROM composer:2.8 AS vendor

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload \
    --classmap-authoritative \
    --no-dev \
    --no-interaction \
    && php artisan package:discover --ansi --no-interaction

FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    ca-certificates \
    curl \
    fcgi \
    icu-libs \
    libxml2 \
    libpq \
    libzip \
    mariadb-connector-c \
    oniguruma \
    sqlite-libs \
    su-exec \
    supervisor \
    tzdata \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    curl-dev \
    icu-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    linux-headers \
    mariadb-connector-c-dev \
    oniguruma-dev \
    sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    curl \
    dom \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-aucms.ini
# Replace the default pool file instead of creating a duplicate [www] pool.
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

COPY --from=vendor /var/www/html /var/www/html

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/public \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=errorlog \
    LOG_LEVEL=info \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]
