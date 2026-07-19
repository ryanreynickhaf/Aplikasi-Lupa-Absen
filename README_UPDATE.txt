HOTFIX AMAN RAILWAY v8

Paket ini hanya berisi 2 file aplikasi yang memang perlu diperbarui:
1. app/bootstrap.php  -> aturan Yusrizal sebagai pegawai menggunakan Erna sebagai penandatangan kanan.
2. settings.php       -> form pengaturan data dan upload TTD Erna.

JANGAN mengganti Dockerfile, railway-entrypoint.sh, railway.toml, health.php, atau file infrastruktur Railway yang sudah berjalan.

Cara pakai:
- Upload/replace app/bootstrap.php di repo GitHub.
- Upload/replace settings.php di repo GitHub.
- Commit changes.
- Tunggu Railway deployment sampai sukses.
