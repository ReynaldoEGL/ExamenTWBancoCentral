# Imagen para desplegar el panel Laravel en Coolify.
# Compatible con Laravel 13 y PHP 8.3+; si el proyecto exige otra versión,
# alinee ambas imágenes y el requisito PHP de composer.json.
FROM php:8.4-cli AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM php:8.4-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring bcmath \
    && a2enmod rewrite headers \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 10000
CMD ["apache2-foreground"]
