FROM php:8.2-fpm-alpine

RUN apk add --no-cache postgresql-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
