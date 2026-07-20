# Aplikasi Lupa Absen Railway v10 — Healthcheck Stable

Versi ini memperbaiki masalah deployment yang lama berada pada `Performing healthchecks` / `service unavailable`.

Perubahan utama:
- Apache dinyalakan **sebelum** proses inisialisasi/migrasi database.
- `/health.php` sudah dapat dijangkau segera setelah Apache hidup.
- Selama database belum siap, `/health.php` memberi HTTP 503 dengan status `starting`.
- Setelah database siap, file readiness dibuat dan `/health.php` memberi HTTP 200.
- Apache tetap menggunakan `mpm_prefork` saja.
- Apache tetap mengikuti environment variable `PORT` Railway.
- Peringatan `ServerName` Apache disenyapkan.
- Semua fitur aplikasi v9 tetap dipertahankan.

## Cara update repository GitHub
1. Ekstrak ZIP.
2. Upload seluruh isi folder ini ke root repository GitHub `Aplikasi-Lupa-Absen`.
3. Replace file lama dengan file dari paket ini.
4. Commit satu kali.
5. Jangan hapus service MySQL, Variables, atau Volume Railway.
6. Pastikan Volume aplikasi tetap mounted ke `/var/www/html/uploads`.
7. Railway akan redeploy otomatis.

## Pengaturan Railway yang perlu dipertahankan
- Healthcheck Path: `/health.php`
- Start Command: kosongkan jika sebelumnya pernah diisi manual (biarkan Dockerfile/ENTRYPOINT yang menjalankan aplikasi).
- Public Networking target port: gunakan `PORT` Railway / target port yang sudah berhasil sebelumnya.

## Catatan diagnosis
Jika deployment masih gagal, buka **Deploy Logs** (bukan Build Logs) pada deployment terbaru. Cari baris setelah:
- `Menjalankan Apache lebih dulu...`
- `Menyiapkan database...`

Log tersebut akan menunjukkan apakah masalahnya ada pada Apache atau koneksi MySQL.
