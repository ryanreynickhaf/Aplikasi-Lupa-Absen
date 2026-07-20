<?php
require_once __DIR__.'/app/layout.php';
require_login();

$admin=is_admin();
$ownEmployeeId=$admin?null:require_operator_employee_id();
$id=(int)($_GET['id']??$_POST['id']??0);
$row=[
    'employee_id'=>$ownEmployeeId??'',
    'event_date'=>date('Y-m-d'),
    'letter_date'=>next_working_day(date('Y-m-d')),
    'category'=>'missing_out',
    'event_time'=>'18:30',
    'app_name'=>'Satu Bravo',
    'reason'=>'Lupa absen',
    'letter_number'=>'',
    'approval_status'=>'pending',
    'rejection_note'=>'',
];

if($id){
    $st=db()->prepare('SELECT * FROM attendance_events WHERE id=?');
    $st->execute([$id]);
    $existing=$st->fetch();
    if(!$existing){ http_response_code(404); exit('Kejadian tidak ditemukan.'); }
    require_employee_access((int)$existing['employee_id']);
    $row=$existing;
}

if($admin){
    $emps=db()->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();
}else{
    $st=db()->prepare('SELECT * FROM employees WHERE id=? AND active=1');
    $st->execute([$ownEmployeeId]);
    $emps=$st->fetchAll();
    if(!$emps){ http_response_code(403); exit('Data pegawai untuk akun ini tidak ditemukan atau tidak aktif.'); }
    $row['employee_id']=$ownEmployeeId;
}
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $emp=$admin?(int)($_POST['employee_id']??0):(int)$ownEmployeeId;
        if(!$emp) throw new RuntimeException('Pilih pegawai terlebih dahulu.');
        require_employee_access($emp);

        $eventDate=trim($_POST['event_date']??'');
        if(!valid_iso_date($eventDate)) throw new RuntimeException('Tanggal kejadian tidak valid.');
        $automaticLetterDate=next_working_day($eventDate);
        $postedLetterDate=trim($_POST['letter_date']??'');
        $letterDate=$postedLetterDate!==''?normalize_working_day($postedLetterDate):$automaticLetterDate;
        if($letterDate<=$eventDate) $letterDate=$automaticLetterDate;

        $cat=$_POST['category']??'';
        if(!isset(categories()[$cat])) throw new RuntimeException('Kategori tidak valid.');

        // Status persetujuan hanya dapat diubah Admin.
        if($admin){
            $status=$_POST['approval_status']??'pending';
            if(!isset(approval_labels()[$status])) throw new RuntimeException('Status persetujuan tidak valid.');
            $rejectionNote=trim($_POST['rejection_note']??'');
            if($status==='rejected'&&$rejectionNote==='') throw new RuntimeException('Catatan alasan wajib diisi apabila tidak disetujui.');
        }else{
            $status=$id?(string)$row['approval_status']:'pending';
            $rejectionNote=$id?(string)($row['rejection_note']??''):'';
        }

        $data=[
            $emp,$eventDate,$letterDate,$cat,$_POST['event_time'],trim($_POST['app_name']),trim($_POST['reason']),
            trim($_POST['letter_number'])?:null,$status,$rejectionNote!==''?$rejectionNote:null,current_user()['id'],
        ];

        if($id){
            $st=db()->prepare('UPDATE attendance_events SET employee_id=?,event_date=?,letter_date=?,category=?,event_time=?,app_name=?,reason=?,letter_number=?,approval_status=?,rejection_note=?,created_by=? WHERE id=?');
            $st->execute([...$data,$id]);
        }else{
            $st=db()->prepare('INSERT INTO attendance_events(employee_id,event_date,letter_date,category,event_time,app_name,reason,letter_number,approval_status,rejection_note,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute($data);
            $id=(int)db()->lastInsertId();
        }
        log_activity('save','attendance_event',$id,'Surat '.($_POST['letter_number']??''));
        flash('success','Kejadian berhasil disimpan. Tanggal surat ditetapkan pada hari kerja.');
        redirect('letter.php?id='.$id);
    }catch(Throwable $e){
        $error=$e->getMessage();
        $row=array_merge($row,$_POST);
        if(!$admin) $row['employee_id']=$ownEmployeeId;
    }
}

page_header($id?'Ubah Kejadian':'Input Kejadian',$id?'events':'new');
if($error) echo '<div class="alert error">'.e($error).'</div>';
?>
<div class="grid two-col"><div class="card"><div id="count-warning"></div><form method="post"><?=csrf_input()?><input type="hidden" name="id" value="<?=$id?>"><div class="form-grid">
<div class="field full"><label>Nama pegawai</label>
<?php if($admin): ?><select id="employee_id" name="employee_id" required><option value="">Pilih pegawai</option><?php foreach($emps as $emp): ?><option value="<?=$emp['id']?>" <?=((int)($row['employee_id']??0)===(int)$emp['id'])?'selected':''?>><?=e($emp['name'])?></option><?php endforeach; ?></select>
<?php else: $emp=$emps[0]; ?><select id="employee_id" disabled><option value="<?=$emp['id']?>" selected><?=e($emp['name'])?></option></select><input type="hidden" name="employee_id" value="<?=$emp['id']?>"><small class="help">Akun operator hanya dapat membuat dan melihat surat atas nama sendiri.</small><?php endif; ?></div>
<div class="field"><label>Tanggal kejadian</label><input id="event_date" type="date" name="event_date" value="<?=e($row['event_date'])?>" required></div>
<div class="field"><label>Tanggal surat — hari kerja</label><input id="letter_date" type="date" name="letter_date" value="<?=e($row['letter_date'])?>" required><small class="help" id="letter-date-note">Otomatis memilih hari kerja berikutnya.</small></div>
<div class="field full"><label>Kategori</label><div class="radio-grid"><?php foreach(categories() as $key=>$label): ?><label class="radio-card"><input type="radio" name="category" value="<?=e($key)?>" <?=$row['category']===$key?'checked':''?>><span><?=e(ucfirst($label))?></span></label><?php endforeach; ?></div></div>
<div class="field"><label>Pukul</label><input type="time" name="event_time" value="<?=e(substr((string)$row['event_time'],0,5))?>" required></div>
<div class="field"><label>Aplikasi absensi</label><input name="app_name" value="<?=e($row['app_name'])?>" required></div>
<div class="field full"><label>Alasan/keterangan</label><textarea name="reason" required><?=e($row['reason'])?></textarea></div>
<div class="field"><label>Nomor surat</label><input name="letter_number" value="<?=e($row['letter_number'])?>" placeholder="Contoh: UM.01.02/SSPJJ/123"></div>
<div class="field"><label>Status persetujuan</label><?php if($admin): ?><select name="approval_status"><?php foreach(approval_labels() as $k=>$v): ?><option value="<?=$k?>" <?=$row['approval_status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach; ?></select><?php else: ?><select disabled><option><?=e(approval_labels()[$row['approval_status']]??'Menunggu')?></option></select><small class="help">Hanya Admin yang dapat mengubah status persetujuan.</small><?php endif; ?></div>
<div class="field full"><label>Catatan alasan tidak disetujui</label><?php if($admin): ?><textarea name="rejection_note"><?=e($row['rejection_note'])?></textarea><?php else: ?><textarea disabled><?=e($row['rejection_note'])?></textarea><?php endif; ?></div>
</div><div class="form-actions"><a class="btn ghost" href="events.php">Batal</a><button class="btn">Simpan &amp; Buat Surat</button></div></form></div>
<div class="card"><div class="section-title"><h2>Aturan Surat</h2></div><div class="alert"><b>Persetujuan:</b> hanya Admin yang dapat menetapkan Disetujui atau Tidak Disetujui.</div><div class="alert"><b>Akses Operator:</b> operator hanya dapat mengakses data dan surat miliknya sendiri.</div><div class="alert"><b>Tanggal surat:</b> otomatis pada hari kerja berikutnya; Jumat menjadi Senin.</div></div></div>
<script>
(function(){const eventInput=document.getElementById('event_date'),letterInput=document.getElementById('letter_date'),note=document.getElementById('letter-date-note');function p(v){if(!/^\d{4}-\d{2}-\d{2}$/.test(v||''))return null;const [y,m,d]=v.split('-').map(Number);return new Date(y,m-1,d)}function iso(d){return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-')}function next(v){const d=p(v);if(!d)return'';do{d.setDate(d.getDate()+1)}while(d.getDay()===0||d.getDay()===6);return iso(d)}eventInput?.addEventListener('change',function(){letterInput.value=next(this.value);note.textContent='Tanggal surat otomatis dipindahkan ke hari kerja berikutnya.'});})();
</script>
<?php page_footer(); ?>
