PEMBARUAN V14 - PEMBATASAN AKSES OPERATOR

1. Status persetujuan hanya dapat diubah oleh Admin.
   - Operator melihat status tetapi kontrol tidak dapat diklik.
   - Backend juga mengabaikan manipulasi approval_status dari request operator.

2. Operator hanya dapat mengakses data pegawai yang terhubung ke akunnya sendiri.
   Pembatasan berlaku pada:
   - Dashboard
   - Input/Ubah Kejadian
   - Kalender Rekap
   - Riwayat & Surat
   - Pratinjau surat
   - Unduh Word
   - Ekspor CSV
   - Hapus kejadian
   - Data Pegawai

3. Menu Data Pegawai untuk Operator hanya menampilkan data dirinya sendiri.
   Operator dapat:
   - mengubah nama, NIP, pangkat/golongan, jabatan;
   - upload/mengganti TTD sendiri.
   Operator tidak dapat menonaktifkan pegawainya sendiri.

4. Admin tetap memiliki akses ke seluruh pegawai dan seluruh status persetujuan.

5. Keamanan diterapkan di sisi server, bukan hanya menyembunyikan pilihan pada tampilan.

6. Tanggal surat tetap mengikuti hari kerja; kejadian Jumat menghasilkan tanggal surat Senin berikutnya.
