FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

FROM php:8.2.35-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

RUN { \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=100M'; \
    echo 'memory_limit=256M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Serve the app at the site root.
COPY HaverFotografie.php /var/www/html/index.php
COPY shoot-request.php /var/www/html/shoot-request.php
COPY --from=dependencies /app/vendor /var/www/html/vendor
COPY admin/index.php /var/www/html/admin/index.php
COPY logo.png /var/www/html/logo.png
COPY favicon.svg /var/www/html/favicon.svg
