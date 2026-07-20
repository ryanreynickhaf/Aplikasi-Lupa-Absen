<?php
require_once __DIR__.'/app/layout.php';
require_login();
$error='';

function account_password_vault_key(): string {
    global $config;
    $raw=trim((string)(getenv('PASSWORD_VAULT_KEY') ?: ($config['db_pass'] ?? '')));
    if($raw==='') throw new RuntimeException('PASSWORD_VAULT_KEY belum tersedia dan kunci fallback database kosong.');
    return hash('sha256',$raw,true);
}
function account_password_vault_encrypt(string $plain): string {
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',account_password_vault_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false) throw new RuntimeException('Gagal mengenkripsi password.');
    return base64_encode($iv.$tag.$cipher);
}
function account_ensure_password_vault_column(PDO $pdo): void {
    $st=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_cipher' LIMIT 1");
    $st->execute();
    if(!$st->fetchColumn()) $pdo->exec("ALTER TABLE users ADD COLUMN password_cipher TEXT NULL AFTER password_hash");
}

$pdo=db();
account_ensure_password_vault_column($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $st=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');
        $st->execute([current_user()['id']]);
        $hash=$st->fetchColumn();
        if(!password_verify($_POST['current_password']??'',$hash)) $error='Kata sandi saat ini salah.';
        elseif(strlen($_POST['new_password']??'')<8) $error='Kata sandi baru minimal 8 karakter.';
        elseif(($_POST['new_password']??'')!==($_POST['confirm_password']??'')) $error='Konfirmasi kata sandi tidak sama.';
        else{
            $newPassword=(string)$_POST['new_password'];
            $st=$pdo->prepare('UPDATE users SET password_hash=?,password_cipher=? WHERE id=?');
            $st->execute([password_hash($newPassword,PASSWORD_DEFAULT),account_password_vault_encrypt($newPassword),current_user()['id']]);
            flash('success','Kata sandi berhasil diubah.');
            redirect('account.php');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
page_header('Ubah Kata Sandi');
if($error)echo '<div class="alert error">'.e($error).'</div>';
?><div class="card" style="max-width:650px"><form method="post"><?=csrf_input()?><div class="field"><label>Kata sandi saat ini</label><input type="password" name="current_password" required></div><div class="field" style="margin-top:12px"><label>Kata sandi baru</label><input type="password" name="new_password" minlength="8" required></div><div class="field" style="margin-top:12px"><label>Konfirmasi kata sandi</label><input type="password" name="confirm_password" minlength="8" required></div><div class="form-actions"><button class="btn">Simpan</button></div></form></div><?php page_footer();
