FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p /var/www/html/uploads/signatures \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod +x /var/www/html/railway-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/var/www/html/railway-entrypoint.sh"]
