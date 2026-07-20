<?php
require_once __DIR__.'/app/layout.php'; require_login();
$admin=is_admin();$own=$admin?null:require_operator_employee_id();
$id=(int)($_GET['id']??$_POST['id']??0);
if(!$admin){$id=$own;}
if(!$admin && !$id){http_response_code(403);exit('Akun operator belum terhubung ke data pegawai.');}
$row=['name'=>'','nip'=>'','grade'=>'','position'=>'','signature_path'=>null,'active'=>1];
if($id){$st=db()->prepare('SELECT * FROM employees WHERE id=?');$st->execute([$id]);$row=$st->fetch()?:$row;require_employee_access($id);}
elseif(!$admin){http_response_code(403);exit('Akses ditolak.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  try{
    if(!$admin) require_employee_access($id);
    $name=trim((string)($_POST['name']??'')); if($name==='') throw new RuntimeException('Nama wajib diisi.');
    $sig=upload_signature('signature',$row['signature_path']);
    $active=$admin?(isset($_POST['active'])?1:0):(int)$row['active'];
    $data=[$name,trim((string)($_POST['nip']??'')),trim((string)($_POST['grade']??'')),trim((string)($_POST['position']??'')),$sig,$active];
    if($id){$st=db()->prepare('UPDATE employees SET name=?,nip=NULLIF(?,""),grade=NULLIF(?,""),position=NULLIF(?,""),signature_path=?,active=? WHERE id=?');$st->execute([...$data,$id]);}
    else{$st=db()->prepare('INSERT INTO employees(name,nip,grade,position,signature_path,active) VALUES(?,NULLIF(?,""),NULLIF(?,""),NULLIF(?,""),?,?)');$st->execute($data);$id=(int)db()->lastInsertId();}
    $account=ensure_employee_user_account(db(),$id,$name,(string)(getenv('EMPLOYEE_DEFAULT_PASSWORD') ?: 'SubditPE2026'));
    $up=db()->prepare('UPDATE users SET name=? WHERE employee_id=?');$up->execute([$name,$id]);
    if(!$admin && (int)(current_user()['id']??0)>0){$_SESSION['user']['name']=$name;}
    log_activity('save','employee',$id,$name);
    $msg='Data pegawai berhasil disimpan.';if($account['created'])$msg.=' Akun otomatis dibuat: username '.e($account['username']).', password awal SubditPE2026.';
    flash('success',$msg);redirect('employees.php');
  }catch(Throwable $e){$error=$e->getMessage();}
}
page_header($admin?($id?'Ubah Pegawai':'Tambah Pegawai'):'Ubah Data Saya','employees');if($error)echo '<div class="alert error">'.e($error).'</div>';?>
<div class="card"><form method="post" enctype="multipart/form-data"><?=csrf_input()?><input type="hidden" name="id" value="<?=$id?>"><div class="form-grid"><div class="field full"><label>Nama lengkap dan gelar</label><input name="name" value="<?=e($_POST['name']??$row['name'])?>" required></div><div class="field"><label>NIP</label><input name="nip" value="<?=e($_POST['nip']??$row['nip'])?>"></div><div class="field"><label>Pangkat/Gol</label><input name="grade" value="<?=e($_POST['grade']??$row['grade'])?>"></div><div class="field full"><label>Jabatan</label><input name="position" value="<?=e($_POST['position']??$row['position'])?>"></div><div class="field full"><label>Upload tanda tangan (PNG/JPG, maksimal 5 MB)</label><input type="file" name="signature" accept="image/png,image/jpeg"><?php if($row['signature_path']):?><img class="signature-preview" src="<?=e($row['signature_path'])?>" alt="TTD saat ini"><?php endif;?></div><?php if($admin):?><div class="field full"><label><input type="checkbox" name="active" <?=$row['active']?'checked':''?>> Pegawai aktif</label></div><?php else:?><div class="alert full"><b>Akses operator:</b> Anda hanya dapat mengubah data profil dan tanda tangan milik sendiri.</div><?php endif;?></div><div class="form-actions"><a class="btn ghost" href="employees.php">Kembali</a><button class="btn">Simpan</button></div></form></div><?php page_footer();
