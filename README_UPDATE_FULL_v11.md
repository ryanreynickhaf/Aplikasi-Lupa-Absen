# Aplikasi Lupa Absen Railway v11 — SQL Migration Fix

Versi ini memperbaiki kegagalan startup Railway pada `bin/init_db.php`.

## Akar masalah
Versi sebelumnya memakai:

```php
SHOW COLUMNS FROM settings LIKE ?
```

Saat PDO memakai native prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`), MySQL menolak placeholder pada bentuk perintah tersebut sehingga proses init database berhenti dan container gagal healthcheck.

## Perbaikan
Pengecekan kolom sekarang memakai `INFORMATION_SCHEMA.COLUMNS`:

```sql
SELECT 1
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'settings'
  AND COLUMN_NAME = ?
LIMIT 1
```

Placeholder sekarang hanya dipakai untuk nilai data (`COLUMN_NAME`) sehingga aman dan kompatibel dengan native prepared statements.

## Cara update full
1. Ekstrak ZIP v11.
2. Upload seluruh isi folder ke root repository GitHub `Aplikasi-Lupa-Absen`.
3. Replace file lama yang namanya sama.
4. Commit satu kali.
5. Jangan hapus service MySQL, volume MySQL, volume aplikasi, atau Variables Railway.
6. Tunggu deployment hingga healthcheck sukses.

## Cara update minimal
Cukup replace file:

`bin/init_db.php`

## Log yang diharapkan

```text
Menjalankan Apache lebih dulu pada 0.0.0.0:8080 ...
Menyiapkan database ...
Kolom settings.director_name ditambahkan.        # hanya jika belum ada
Kolom settings.director_nip ditambahkan.         # hanya jika belum ada
Kolom settings.director_position ditambahkan.    # hanya jika belum ada
Kolom settings.director_signature_path ditambahkan. # hanya jika belum ada
Database siap.
Aplikasi siap menerima trafik.
```
