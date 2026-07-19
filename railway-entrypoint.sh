#!/bin/sh
set -eu
mkdir -p /var/www/html/uploads/signatures
chown -R www-data:www-data /var/www/html/uploads || true
php /var/www/html/bin/init_db.php
exec apache2-foreground
