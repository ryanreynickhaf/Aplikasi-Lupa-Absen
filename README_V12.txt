Aplikasi Lupa Absen Railway v12 - Settings SQL Fix

Perbaikan utama:
- Memperbaiki fatal error menu Pengaturan akibat `SHOW COLUMNS FROM settings LIKE ?`.
- settings.php sekarang memakai INFORMATION_SCHEMA.COLUMNS.
- bin/init_db.php juga sudah memakai mekanisme yang sama.
- Mempertahankan konfigurasi Railway, healthcheck, volume, MySQL, Yusrizal/Erna, dan manajemen pengguna.

Update penuh:
Upload seluruh isi folder ini ke root repository GitHub dan replace file lama.
Jangan hapus Variables, MySQL service, atau volume Railway.
