<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile = dirname(__DIR__) . '/config.php';
if (!file_exists($configFile)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

function db(): PDO {
    static $pdo;
    global $config;
    if (!$pdo) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) redirect('login.php'); }
function require_admin(): void { require_login(); if ((current_user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses ditolak.'); } }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Sesi formulir tidak valid. Muat ulang halaman.'); } }
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type','message'); }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function categories(): array { return [
    'late' => 'terlambat masuk kerja',
    'early_leave' => 'pulang sebelum waktunya',
    'missing_in' => 'tidak mengisi daftar hadir kedatangan',
    'missing_out' => 'tidak mengisi daftar hadir kepulangan',
]; }
function category_codes(): array { return ['late'=>'TM','early_leave'=>'PSW','missing_in'=>'HKD','missing_out'=>'HKP']; }
function approval_labels(): array { return ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Tidak Disetujui']; }
function indo_months(): array { return [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; }
function indo_days(): array { return [0=>'Minggu',1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jum’at',6=>'Sabtu']; }
function date_formal(string $date): string { $t=strtotime($date); return date('j',$t).' '.indo_months()[(int)date('n',$t)].' '.date('Y',$t); }
function date_long(string $date): string { $t=strtotime($date); return indo_days()[(int)date('w',$t)].' tanggal '.date_formal($date); }
function valid_iso_date(string $date): bool {
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    return $dt!==false && $dt->format('Y-m-d')===$date;
}
function normalize_working_day(string $date): string {
    if (!valid_iso_date($date)) throw new RuntimeException('Format tanggal tidak valid.');
    $dt=new DateTimeImmutable($date);
    while ((int)$dt->format('N')>=6) $dt=$dt->modify('+1 day');
    return $dt->format('Y-m-d');
}
function next_working_day(string $date): string {
    if (!valid_iso_date($date)) throw new RuntimeException('Format tanggal kejadian tidak valid.');
    $dt=(new DateTimeImmutable($date))->modify('+1 day');
    while ((int)$dt->format('N')>=6) $dt=$dt->modify('+1 day');
    return $dt->format('Y-m-d');
}
function plain_name(string $name): string { return trim(explode(',', $name)[0]); }

/**
 * Tentukan penandatangan di sisi kanan surat.
 * Pegawai biasa -> Kepala Subdirektorat.
 * Jika Kepala Subdirektorat yang lupa absen -> PLT. Direktur (Erna Wijayanti).
 */
function right_approver_for_employee(array $employee, array $set): array {
    $employeeNip=trim((string)($employee['nip']??''));
    $employeePosition=trim((string)($employee['position']??''));
    $bossNip=trim((string)($set['boss_nip']??''));

    $isHeadSubdirectorate=(
        ($employeeNip!=='' && $bossNip!=='' && hash_equals($bossNip,$employeeNip))
        || stripos($employeePosition,'Kepala Subdirektorat')!==false
    );

    if($isHeadSubdirectorate){
        return [
            'kind'=>'director',
            'name'=>(string)($set['director_name']??'Erna Wijayanti'),
            'nip'=>(string)($set['director_nip']??'198005082005022001'),
            'position'=>(string)($set['director_position']??'PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan'),
            'signature_path'=>$set['director_signature_path']??null,
        ];
    }

    return [
        'kind'=>'boss',
        'name'=>(string)($set['boss_name']??'Yusrizal Kurniawan'),
        'nip'=>(string)($set['boss_nip']??'197903032005021003'),
        'position'=>(string)($set['boss_position']??'Kepala Subdirektorat Pemantauan dan Evaluasi'),
        'signature_path'=>$set['boss_signature_path']??null,
    ];
}

function settings(): array { $row=db()->query('SELECT * FROM settings WHERE id=1')->fetch(); return $row ?: []; }
function month_count(int $employeeId, string $date, ?int $excludeId=null): int {
    $sql='SELECT COUNT(*) FROM attendance_events WHERE employee_id=? AND DATE_FORMAT(event_date, "%Y-%m")=DATE_FORMAT(?, "%Y-%m")';
    $params=[$employeeId,$date];
    if ($excludeId) { $sql.=' AND id<>?'; $params[]=$excludeId; }
    $st=db()->prepare($sql); $st->execute($params); return (int)$st->fetchColumn();
}
function count_status(int $count, int $max=4): array {
    if ($count>$max) return ['danger','Melebihi batas',"$count kali — melewati maksimal $max kali"];
    if ($count===$max) return ['danger','Batas tercapai',"$count dari maksimal $max kali"];
    if ($count===$max-1) return ['warn','Mendekati batas',"$count kali — tersisa 1 kali"];
    return ['ok','Aman',"$count dari maksimal $max kali"];
}
function log_activity(string $action, string $entity, ?int $entityId, string $detail=''): void {
    $u=current_user(); if (!$u) return;
    $st=db()->prepare('INSERT INTO activity_logs(user_id,action,entity,entity_id,detail) VALUES(?,?,?,?,?)');
    $st->execute([$u['id'],$action,$entity,$entityId,$detail]);
}
function upload_signature(string $field, ?string $oldPath=null): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return $oldPath;
    $f=$_FILES[$field];
    if ($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Upload tanda tangan gagal.');
    if ($f['size']>5*1024*1024) throw new RuntimeException('Ukuran tanda tangan maksimal 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg'][$mime]??null;
    if (!$ext || !getimagesize($f['tmp_name'])) throw new RuntimeException('Gunakan gambar PNG atau JPG yang valid.');
    $name='ttd_'.bin2hex(random_bytes(10)).'.'.$ext;
    $dir=dirname(__DIR__).'/uploads/signatures';
    if (!is_dir($dir)) mkdir($dir,0775,true);
    if (!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('File tanda tangan tidak dapat disimpan.');
    if ($oldPath && str_starts_with($oldPath,'uploads/signatures/')) { $old=dirname(__DIR__).'/'.$oldPath; if (is_file($old)) @unlink($old); }
    return 'uploads/signatures/'.$name;
}
function image_data_uri(?string $relativePath): string {
    if (!$relativePath) return '';
    $full=dirname(__DIR__).'/'.ltrim($relativePath,'/');
    if (!is_file($full)) return '';
    $mime=mime_content_type($full) ?: 'image/png';
    return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($full));
}
