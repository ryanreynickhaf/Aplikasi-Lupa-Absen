<?php
require_once __DIR__.'/app/bootstrap.php'; require_login();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('events.php');verify_csrf();$id=(int)($_POST['id']??0);$st=db()->prepare('DELETE FROM attendance_events WHERE id=?');$st->execute([$id]);log_activity('delete','attendance_event',$id);flash('success','Kejadian berhasil dihapus.');redirect('events.php');
