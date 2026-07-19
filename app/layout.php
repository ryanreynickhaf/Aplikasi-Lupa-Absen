<?php
require_once __DIR__.'/bootstrap.php';
function page_header(string $title, string $active=''): void {
    global $config; $user=current_user(); $flash=take_flash();
    ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> — <?=e($config['app_name']??'Aplikasi Lupa Absen')?></title>
<link rel="stylesheet" href="assets/css/style.css"></head><body>
<div class="app-shell">
<aside class="sidebar">
  <div class="brand"><img src="assets/img/logo_pu.jpeg" alt="PU"><div><strong>Aplikasi Lupa Absen</strong><small>Subdirektorat Pemantauan dan Evaluasi</small></div></div>
  <nav>
    <a class="<?=$active==='dashboard'?'active':''?>" href="index.php">▦ Dashboard</a>
    <a class="<?=$active==='new'?'active':''?>" href="event_form.php">＋ Input Kejadian</a>
    <a class="<?=$active==='calendar'?'active':''?>" href="calendar.php">▣ Kalender Rekap</a>
    <a class="<?=$active==='events'?'active':''?>" href="events.php">☷ Riwayat & Surat</a>
    <a class="<?=$active==='employees'?'active':''?>" href="employees.php">♙ Data Pegawai</a>
    <?php if(($user['role']??'')==='admin'): ?>
    <a class="<?=$active==='settings'?'active':''?>" href="settings.php">⚙ Pengaturan</a>
    <a class="<?=$active==='users'?'active':''?>" href="users.php">◉ Pengguna</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom"><span><?=e($user['name']??'')?></span><small><?=e(ucfirst($user['role']??''))?></small><a href="account.php">Ubah kata sandi</a><a href="logout.php">Keluar</a></div>
</aside>
<main><header class="topbar"><button class="mobile-nav" type="button" onclick="document.body.classList.toggle('menu-open')">☰</button><h1><?=e($title)?></h1><span><?=e(date_formal(date('Y-m-d')))?></span></header>
<div class="content">
<?php if($flash): ?><div class="no-print alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>
<?php
}
function page_footer(): void { ?>
</div></main></div><script src="assets/js/app.js"></script></body></html><?php }
