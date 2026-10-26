# Local/self-hosted image for the API and its workers (Laravel Cloud builds its own).
FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pcntl sockets bcmath zip \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize && php artisan package:discover --ansi

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
