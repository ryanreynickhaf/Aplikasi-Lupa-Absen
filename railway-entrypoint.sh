#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"

echo "Memastikan hanya Apache MPM prefork yang aktif ..."
a2dismod -f mpm_event >/dev/null 2>&1 || true
a2dismod -f mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "MPM aktif:"
apache2ctl -M 2>/dev/null | grep 'mpm_.*_module' || true

echo "Menyiapkan Apache pada 0.0.0.0:${APP_PORT} ..."
sed -ri "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/uploads/signatures
chown -R www-data:www-data /var/www/html/uploads || true

php /var/www/html/bin/init_db.php

echo "Database siap. Menjalankan Apache pada port ${APP_PORT} ..."
exec apache2-foreground
