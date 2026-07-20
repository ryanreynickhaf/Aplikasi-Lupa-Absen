<?php
require_once __DIR__.'/app/layout.php';
require_admin();

/**
 * Pastikan kolom penandatangan PLT. Direktur tersedia juga pada database lama.
 * Aman dijalankan berulang kali karena hanya menambah kolom yang belum ada.
 */
function ensure_director_settings_columns(): void {
    $pdo = db();
    $columns = [
        'director_name' => "VARCHAR(200) NOT NULL DEFAULT 'Erna Wijayanti, S.T., M.Sc.'",
        'director_nip' => "VARCHAR(30) NOT NULL DEFAULT '198005082005022001'",
        'director_position' => "VARCHAR(255) NOT NULL DEFAULT 'PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan'",
        'director_signature_path' => "VARCHAR(255) NULL",
    ];

    $check = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'settings'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    foreach ($columns as $column => $definition) {
        $check->execute([$column]);
        if (!$check->fetchColumn()) {
            // Nama kolom/definisi berasal dari array statis di atas, bukan input pengguna.
            $pdo->exec("ALTER TABLE `settings` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

ensure_director_settings_columns();
// Naikkan nama default lama menjadi nama lengkap bergelar, tanpa menimpa nama yang pernah diubah admin.
db()->exec("UPDATE settings SET director_name='Erna Wijayanti, S.T., M.Sc.' WHERE id=1 AND TRIM(director_name)='Erna Wijayanti'");
$row = settings();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $bossSig = upload_signature('boss_signature', $row['boss_signature_path'] ?? null);
        $directorSig = upload_signature('director_signature', $row['director_signature_path'] ?? null);

        $st = db()->prepare(
            'UPDATE settings SET
                office_city=?,
                boss_name=?,
                boss_nip=?,
                boss_position=?,
                boss_signature_path=?,
                director_name=?,
                director_nip=?,
                director_position=?,
                director_signature_path=?,
                max_absences=?
             WHERE id=1'
        );

        $st->execute([
            trim((string)$_POST['office_city']),
            trim((string)$_POST['boss_name']),
            trim((string)$_POST['boss_nip']),
            trim((string)$_POST['boss_position']),
            $bossSig,
            trim((string)$_POST['director_name']),
            trim((string)$_POST['director_nip']),
            trim((string)$_POST['director_position']),
            $directorSig,
            max(1, (int)$_POST['max_absences']),
        ]);

        log_activity('save', 'settings', 1, 'Memperbarui penandatangan Kepala Subdirektorat dan PLT. Direktur');
        flash('success', 'Pengaturan berhasil disimpan.');
        redirect('settings.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header('Pengaturan', 'settings');
if ($error) {
    echo '<div class="alert error">'.e($error).'</div>';
}
?>

<div class="card">
<form method="post" enctype="multipart/form-data">
<?=csrf_input()?>

<div class="form-grid">
    <div class="field">
        <label>Kota surat</label>
        <input name="office_city" value="<?=e($row['office_city'] ?? 'Jakarta')?>" required>
    </div>

    <div class="field">
        <label>Maksimal lupa absen per bulan</label>
        <input type="number" min="1" name="max_absences" value="<?=e((string)($row['max_absences'] ?? 4))?>" required>
    </div>

    <div class="field full" style="margin-top:8px;padding-top:14px;border-top:1px solid #d9dde8">
        <div style="font-weight:800;color:#253573;font-size:15px">Penandatangan untuk pegawai/staf</div>
        <div class="help">Dipakai di sisi kanan surat untuk seluruh pegawai selain Kepala Subdirektorat.</div>
    </div>

    <div class="field full">
        <label>Nama Kepala Subdirektorat</label>
        <input name="boss_name" value="<?=e($row['boss_name'] ?? 'Yusrizal Kurniawan')?>" required>
    </div>

    <div class="field">
        <label>NIP Kepala Subdirektorat</label>
        <input name="boss_nip" value="<?=e($row['boss_nip'] ?? '197903032005021003')?>" required>
    </div>

    <div class="field">
        <label>Jabatan</label>
        <input name="boss_position" value="<?=e($row['boss_position'] ?? 'Kepala Subdirektorat Pemantauan dan Evaluasi')?>" required>
    </div>

    <div class="field full">
        <label>Upload tanda tangan Kepala Subdirektorat (PNG/JPG)</label>
        <input type="file" name="boss_signature" accept="image/png,image/jpeg">
        <div class="help">TTD ini muncul jika status <b>Disetujui</b> dan pegawai yang lupa absen bukan Kepala Subdirektorat.</div>
        <?php if (!empty($row['boss_signature_path'])): ?>
            <img class="signature-preview" src="<?=e($row['boss_signature_path'])?>" alt="TTD Kepala Subdirektorat">
        <?php endif; ?>
    </div>

    <div class="field full" style="margin-top:12px;padding-top:14px;border-top:1px solid #d9dde8">
        <div style="font-weight:800;color:#253573;font-size:15px">Penandatangan khusus jika Kepala Subdirektorat yang lupa absen</div>
        <div class="help">Jika Pak Yusrizal/Kepala Subdirektorat menjadi pegawai yang lupa absen, sisi kanan surat otomatis memakai data dan TTD Bu Erna berikut.</div>
    </div>

    <div class="field full">
        <label>Nama PLT. Direktur</label>
        <input name="director_name" value="<?=e($row['director_name'] ?? 'Erna Wijayanti, S.T., M.Sc.')?>" required>
    </div>

    <div class="field">
        <label>NIP PLT. Direktur</label>
        <input name="director_nip" value="<?=e($row['director_nip'] ?? '198005082005022001')?>" required>
    </div>

    <div class="field">
        <label>Jabatan</label>
        <input name="director_position" value="<?=e($row['director_position'] ?? 'PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan')?>" required>
    </div>

    <div class="field full">
        <label>Upload tanda tangan Erna Wijayanti, S.T., M.Sc. / PLT. Direktur (PNG/JPG)</label>
        <input type="file" name="director_signature" accept="image/png,image/jpeg">
        <div class="help">TTD Bu Erna hanya muncul jika yang lupa absen adalah Kepala Subdirektorat dan status surat <b>Disetujui</b>.</div>
        <?php if (!empty($row['director_signature_path'])): ?>
            <img class="signature-preview" src="<?=e($row['director_signature_path'])?>" alt="TTD PLT. Direktur">
        <?php endif; ?>
    </div>
</div>

<div class="form-actions">
    <button class="btn">Simpan Pengaturan</button>
</div>
</form>
</div>

<?php page_footer();
