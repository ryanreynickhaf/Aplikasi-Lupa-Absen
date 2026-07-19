FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Mod_php harus berjalan dengan satu MPM saja. Paksa prefork dan matikan MPM lain.
RUN set -eux; \
    a2dismod -f mpm_event || true; \
    a2dismod -f mpm_worker || true; \
    a2enmod mpm_prefork rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/uploads/signatures \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod +x /var/www/html/railway-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/var/www/html/railway-entrypoint.sh"]
