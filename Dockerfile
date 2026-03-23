# ----------- Composer Stage -----------
FROM composer:2.8 AS vendor

WORKDIR /app
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
FROM php:8.2-apache

WORKDIR /app

ENV QUEUE_WORKER_PROCESSES=2 \
    QUEUE_WORKER_SLEEP_SECONDS=1 \
    QUEUE_WORKER_TRIES=3 \
    QUEUE_WORKER_TIMEOUT_SECONDS=120 \
    QUEUE_WORKER_MAX_TIME_SECONDS=3600 \
    QUEUE_WORKER_MEMORY_MB=256

# Apache: enable mod_rewrite, headers
RUN a2enmod rewrite headers

# PHP upload limits
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 40M" >> /usr/local/etc/php/conf.d/uploads.ini

# PHP extensions for Laravel + Redis (no Swoole - Apache serves directly)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    curl \
    default-mysql-client \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring intl zip bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy app
COPY --from=vendor /app /app

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Apache config for Laravel public/
ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Entrypoint scripts (IRV-style: storage setup, migrations, cache, all-in-one supervisord)
COPY docker-entrypoint.sh /usr/local/bin/
COPY docker-worker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-worker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
# Default: app only. For single-container (app+worker+scheduler), set RUN_WORKER_AND_SCHEDULER=1
CMD ["apache2-foreground"]
