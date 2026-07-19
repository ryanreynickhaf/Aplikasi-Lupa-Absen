# Aplikasi Lupa Absen Railway v9 Full Stable

Paket ini dibuat untuk mengganti isi repository GitHub aplikasi secara keseluruhan dengan satu versi yang konsisten.

## Penting
- Jangan hapus service MySQL Railway.
- Jangan hapus Volume yang sudah terpasang ke `/var/www/html/uploads`.
- Environment Variables Railway tetap disimpan di Railway, bukan di repository.
- `config.php` pada paket membaca environment variable Railway; jangan menaruh password database langsung di GitHub.

## Cara update repository GitHub
1. Backup repository lama bila diperlukan.
2. Upload seluruh isi folder paket ini ke root repository GitHub dan replace file yang namanya sama.
3. Pastikan file infrastruktur berikut ikut ada di root repository:
   - `Dockerfile`
   - `railway-entrypoint.sh`
   - `railway.toml`
   - `health.php`
4. Commit changes satu kali.
5. Tunggu Railway build dan deploy deployment terbaru.
6. Jangan memicu deployment kedua selama deployment pertama masih `DEPLOYING`.

## Konfigurasi Railway yang diharapkan
Variables service aplikasi:
- `MYSQLHOST=${{MySQL.MYSQLHOST}}`
- `MYSQLPORT=${{MySQL.MYSQLPORT}}`
- `MYSQLDATABASE=${{MySQL.MYSQLDATABASE}}`
- `MYSQLUSER=${{MySQL.MYSQLUSER}}`
- `MYSQLPASSWORD=${{MySQL.MYSQLPASSWORD}}`
- `PORT=8080` (opsional bila Railway sudah menetapkan PORT; target port domain harus sama)
- `APP_NAME=Aplikasi Lupa Absen`
- `APP_TIMEZONE=Asia/Jakarta`

Volume aplikasi:
- mount path `/var/www/html/uploads`

Public domain:
- target port `8080` bila target port dikunci manual.

Healthcheck:
- path `/health.php`

## Logika penandatangan
- Pegawai biasa: sisi kanan surat memakai Yusrizal Kurniawan.
- Yusrizal Kurniawan / Kepala Subdirektorat yang lupa absen: sisi kanan otomatis memakai Erna Wijayanti.
- TTD penandatangan kanan muncul hanya saat status `Disetujui`.

## Pengaturan
Menu Pengaturan menyediakan dua kelompok data:
1. Kepala Subdirektorat / Yusrizal Kurniawan.
2. PLT. Direktur / Erna Wijayanti.

Data default Erna:
- Nama: Erna Wijayanti
- NIP: 198005082005022001
- Jabatan: PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan
