# Dev-only image. No production/FPM variant — see CaseTrack Docker setup notes.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        nodejs \
        npm \
        default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && php -m | grep -qi sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
