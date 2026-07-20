<?php
require_once __DIR__.'/app/bootstrap.php';
if(current_user()) redirect('index.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $st=db()->prepare('SELECT * FROM users WHERE username=?'); $st->execute([trim($_POST['username']??'')]); $u=$st->fetch();
    if($u && password_verify($_POST['password']??'',$u['password_hash'])){ session_regenerate_id(true); $_SESSION['user']=['id'=>$u['id'],'employee_id'=>$u['employee_id']??null,'name'=>$u['name'],'username'=>$u['username'],'role'=>$u['role']]; redirect('index.php'); }
    $error='Username atau kata sandi salah.';
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="login-body"><div class="login-card"><div class="login-brand"><img src="assets/img/logo_pu.jpeg"><h1>Aplikasi Lupa Absen</h1><p>Direktorat Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan</p></div><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post"><div class="field"><label>Username</label><input name="username" autocomplete="username" required autofocus></div><div class="field" style="margin-top:12px"><label>Kata sandi</label><input type="password" name="password" autocomplete="current-password" required></div><div class="form-actions"><button class="btn" style="width:100%">Masuk</button></div></form></div></body></html>
