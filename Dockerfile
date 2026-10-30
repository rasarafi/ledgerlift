# Production-style runtime for the new Laravel app: nginx + PHP-FPM, PHP 8.4.
FROM serversideup/php:8.4-fpm-nginx-bookworm-v5.0.5

ENV TZ=UTC \
    PHP_OPCACHE_ENABLE=1

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi

# First-start demo data for both apps (app/Console/Commands/DemoSeed.php).
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
