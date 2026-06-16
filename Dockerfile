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
# dump-autoload must not run Laravel scripts: .env is not in the image (see .dockerignore),
# so `artisan package:discover` would fail during the image build.
RUN composer dump-autoload --optimize --no-scripts

# ----------- App Stage -----------
FROM php:8.2-apache-bookworm

WORKDIR /app

# Apache: enable mod_rewrite, headers
RUN a2enmod rewrite headers

# PHP upload limits
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 40M" >> /usr/local/etc/php/conf.d/uploads.ini

# System dependencies
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Retries=5 update \
    && apt-get install -y --no-install-recommends \
    zip \
    unzip \
    curl \
    default-mysql-client \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions (handled via install-php-extensions for robust configuration & clean-up)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql mbstring intl zip bcmath gd redis

# Copy app
COPY --from=vendor /app /app

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Apache config for Laravel public/
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
COPY docker/apache/laravel.conf /etc/apache2/conf-available/laravel.conf
RUN a2enconf laravel

# Entrypoint scripts (IRV-style: storage setup, migrations, cache, all-in-one supervisord)
COPY docker-entrypoint.sh /usr/local/bin/
COPY docker-worker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-worker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
# Default: app only. For single-container (app+worker+scheduler), set RUN_WORKER_AND_SCHEDULER=1
CMD ["apache2-foreground"]
