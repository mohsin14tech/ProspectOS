FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev unzip \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-scripts \
    && php -d memory_limit=512M bin/console tailwind:build --minify

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
