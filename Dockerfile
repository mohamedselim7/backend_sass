# Production image: php-fpm + nginx + per-queue workers under supervisord.
# Local development: use `php artisan serve` outside Docker, or build with --target dev.
FROM php:8.4-fpm AS base
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip nginx supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM base AS dev
COPY . .
RUN composer install --optimize-autoloader
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM base AS production
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/iden.conf
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN rm -f .env && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache
ENV AI_WORKERS=2 DEFAULT_WORKERS=1
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost:8080/api/health || exit 1
# Config/route caches are built at start so they use the runtime environment.
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan event:cache && exec supervisord -n -c /etc/supervisor/supervisord.conf"]
