#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"
READY_FILE="/tmp/lupa-absen-ready"

rm -f "$READY_FILE"

# Pastikan mod_php hanya memakai MPM prefork.
a2dismod -f mpm_event >/dev/null 2>&1 || true
a2dismod -f mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Hilangkan peringatan FQDN Apache.
echo "ServerName 0.0.0.0" > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null 2>&1 || true

# Railway menyuntikkan PORT. Apache harus mendengarkan pada port tersebut.
sed -ri "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/uploads/signatures
chown -R www-data:www-data /var/www/html/uploads || true

echo "Menjalankan Apache lebih dulu pada 0.0.0.0:${APP_PORT} ..."
apache2-foreground &
APACHE_PID=$!

cleanup() {
  kill -TERM "$APACHE_PID" 2>/dev/null || true
  wait "$APACHE_PID" 2>/dev/null || true
}
trap cleanup INT TERM

# Pastikan proses Apache benar-benar masih hidup sebelum melanjutkan.
sleep 1
if ! kill -0 "$APACHE_PID" 2>/dev/null; then
  echo "Apache gagal dijalankan." >&2
  wait "$APACHE_PID" || true
  exit 1
fi

# Inisialisasi/migrasi database dilakukan setelah web server sudah listening.
# /health.php akan mengembalikan 503 sampai langkah ini selesai, lalu 200.
echo "Menyiapkan database ..."
if php /var/www/html/bin/init_db.php; then
  touch "$READY_FILE"
  echo "Database siap. Aplikasi siap menerima trafik."
else
  echo "Inisialisasi database gagal. Menghentikan Apache." >&2
  cleanup
  exit 1
fi

wait "$APACHE_PID"
