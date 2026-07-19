#!/bin/sh
set -eu

# Railway memberikan port dinamis melalui environment variable PORT.
# Apache image bawaan listen di port 80, jadi kita sesuaikan saat container start.
APP_PORT="${PORT:-8080}"

echo "Menyiapkan Apache pada 0.0.0.0:${APP_PORT} ..."
sed -ri "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/uploads/signatures
chown -R www-data:www-data /var/www/html/uploads || true

# Inisialisasi database dilakukan sebelum Apache dibuka.
# Jika variabel MySQL belum benar, log deployment akan menjelaskan penyebabnya.
php /var/www/html/bin/init_db.php

echo "Database siap. Menjalankan Apache pada port ${APP_PORT} ..."
exec apache2-foreground
