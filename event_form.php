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

        $eventTime=trim($_POST['event_time']??'');
        if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$eventTime)) throw new RuntimeException('Pukul tidak valid.');

        // Nomor surat hanya dapat dibuat/diubah Admin.
        // Operator tidak dapat menyisipkan nomor surat melalui request manual.
        if($admin){
            $letterNumber=trim($_POST['letter_number']??'');
            $letterNumber=$letterNumber!==''?$letterNumber:null;
        }else{
            $letterNumber=$id?($row['letter_number']??null):null;
        }

        $data=[
            $emp,$eventDate,$letterDate,$cat,$eventTime,trim($_POST['app_name']),trim($_POST['reason']),
            $letterNumber,$status,$rejectionNote!==''?$rejectionNote:null,current_user()['id'],
        ];

        if($id){
            $st=db()->prepare('UPDATE attendance_events SET employee_id=?,event_date=?,letter_date=?,category=?,event_time=?,app_name=?,reason=?,letter_number=?,approval_status=?,rejection_note=?,created_by=? WHERE id=?');
            $st->execute([...$data,$id]);
        }else{
            $st=db()->prepare('INSERT INTO attendance_events(employee_id,event_date,letter_date,category,event_time,app_name,reason,letter_number,approval_status,rejection_note,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute($data);
            $id=(int)db()->lastInsertId();
        }
        log_activity('save','attendance_event',$id,'Surat '.($letterNumber??''));
        flash('success','Kejadian berhasil disimpan. Tanggal surat ditetapkan pada hari kerja.');
        redirect('letter.php?id='.$id);
    }catch(Throwable $e){
        $error=$e->getMessage();
        $row=array_merge($row,$_POST);
        if(!$admin){
            $row['employee_id']=$ownEmployeeId;
            $row['letter_number']=$id?($existing['letter_number']??null):null;
            $row['approval_status']=$id?($existing['approval_status']??'pending'):'pending';
            $row['rejection_note']=$id?($existing['rejection_note']??null):null;
        }
    }
}


$timeValue=substr((string)($row['event_time']??'18:30'),0,5);
if(!preg_match('/^(\d{2}):(\d{2})$/',$timeValue,$tm)){
    $timeValue='18:30';
    $tm=[null,'18','30'];
}
$selectedHour=$tm[1];
$selectedMinute=$tm[2];

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
<div class="field"><label>Pukul</label>
  <button type="button" class="clock-picker-trigger" id="clock-picker-trigger" aria-haspopup="dialog" aria-controls="clock-picker-modal">
    <span id="clock-picker-display"><?=e($timeValue)?></span><span class="clock-icon" aria-hidden="true">◷</span>
  </button>
  <input type="hidden" id="event_time" name="event_time" value="<?=e($timeValue)?>">
  <small class="help">Klik kolom pukul untuk memilih jam pada tampilan jam.</small>
</div>
<div class="field"><label>Aplikasi absensi</label><input name="app_name" value="<?=e($row['app_name'])?>" required></div>
<div class="field full"><label>Alasan/keterangan</label><textarea name="reason" required><?=e($row['reason'])?></textarea></div>
<div class="field"><label>Nomor surat</label><?php if($admin): ?><input name="letter_number" value="<?=e($row['letter_number'])?>" placeholder="Contoh: UM.01.02/SSPJJ/123"><?php else: ?><input value="<?=e($row['letter_number']??'')?>" placeholder="Diisi oleh Admin" disabled><small class="help">Hanya Admin yang dapat mengisi atau mengubah nomor surat.</small><?php endif; ?></div>
<div class="field"><label>Status persetujuan</label><?php if($admin): ?><select name="approval_status"><?php foreach(approval_labels() as $k=>$v): ?><option value="<?=$k?>" <?=$row['approval_status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach; ?></select><?php else: ?><select disabled><option><?=e(approval_labels()[$row['approval_status']]??'Menunggu')?></option></select><small class="help">Hanya Admin yang dapat mengubah status persetujuan.</small><?php endif; ?></div>
<div class="field full"><label>Catatan alasan tidak disetujui</label><?php if($admin): ?><textarea name="rejection_note"><?=e($row['rejection_note'])?></textarea><?php else: ?><textarea disabled placeholder="Diisi oleh Admin apabila surat tidak disetujui"><?=e($row['rejection_note'])?></textarea><small class="help">Hanya Admin yang dapat mengisi catatan alasan tidak disetujui.</small><?php endif; ?></div>
</div><div class="form-actions"><a class="btn ghost" href="events.php">Batal</a><button class="btn">Simpan &amp; Buat Surat</button></div></form></div>
<div class="card"><div class="section-title"><h2>Aturan Surat</h2></div><div class="alert"><b>Persetujuan:</b> hanya Admin yang dapat menetapkan Disetujui atau Tidak Disetujui.</div><div class="alert"><b>Akses Operator:</b> operator hanya dapat mengakses data dan surat miliknya sendiri.</div><div class="alert"><b>Tanggal surat:</b> otomatis pada hari kerja berikutnya; Jumat menjadi Senin.</div></div></div>

<div class="clock-modal-backdrop" id="clock-picker-modal" hidden>
  <div class="clock-modal" role="dialog" aria-modal="true" aria-labelledby="clock-dialog-title">
    <div class="clock-dialog-title" id="clock-dialog-title">Pilih pukul</div>
    <div class="clock-header">
      <button type="button" class="clock-value active" id="clock-hour-value">07</button>
      <span class="clock-colon">:</span>
      <button type="button" class="clock-value" id="clock-minute-value">00</button>
      <div class="clock-period">
        <button type="button" data-period="AM">AM</button>
        <button type="button" data-period="PM">PM</button>
      </div>
    </div>
    <div class="clock-face" id="clock-face" aria-label="Pilihan waktu"></div>
    <div class="clock-actions">
      <button type="button" class="clock-text-btn" id="clock-cancel">Batal</button>
      <button type="button" class="clock-text-btn primary" id="clock-ok">OK</button>
    </div>
  </div>
</div>
<script>
(function(){
  const eventInput=document.getElementById('event_date'),letterInput=document.getElementById('letter_date'),note=document.getElementById('letter-date-note');
  function p(v){if(!/^\d{4}-\d{2}-\d{2}$/.test(v||''))return null;const [y,m,d]=v.split('-').map(Number);return new Date(y,m-1,d)}
  function iso(d){return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-')}
  function next(v){const d=p(v);if(!d)return'';do{d.setDate(d.getDate()+1)}while(d.getDay()===0||d.getDay()===6);return iso(d)}
  eventInput?.addEventListener('change',function(){letterInput.value=next(this.value);note.textContent='Tanggal surat otomatis dipindahkan ke hari kerja berikutnya.'});

  const trigger=document.getElementById('clock-picker-trigger');
  const modal=document.getElementById('clock-picker-modal');
  const face=document.getElementById('clock-face');
  const hidden=document.getElementById('event_time');
  const display=document.getElementById('clock-picker-display');
  const hourValue=document.getElementById('clock-hour-value');
  const minuteValue=document.getElementById('clock-minute-value');
  const cancelBtn=document.getElementById('clock-cancel');
  const okBtn=document.getElementById('clock-ok');
  const periodBtns=[...document.querySelectorAll('[data-period]')];
  let mode='hour', hour12=6, minuteVal=30, period='PM';
  let original={hour12,minuteVal,period};

  function parseHidden(){
    const m=(hidden?.value||'18:30').match(/^(\d{2}):(\d{2})$/);
    let h=m?Number(m[1]):18; minuteVal=m?Number(m[2]):30;
    period=h>=12?'PM':'AM'; hour12=h%12||12;
  }
  function two(v){return String(v).padStart(2,'0')}
  function to24(){let h=hour12%12;if(period==='PM')h+=12;return two(h)+':'+two(minuteVal)}
  function updateHeader(){
    hourValue.textContent=two(hour12); minuteValue.textContent=two(minuteVal);
    hourValue.classList.toggle('active',mode==='hour'); minuteValue.classList.toggle('active',mode==='minute');
    periodBtns.forEach(b=>b.classList.toggle('active',b.dataset.period===period));
  }
  function renderFace(){
    face.innerHTML='';
    const values=mode==='hour'?[12,1,2,3,4,5,6,7,8,9,10,11]:[0,5,10,15,20,25,30,35,40,45,50,55];
    values.forEach((v,i)=>{
      const angle=(i*30-90)*Math.PI/180;
      const r=42;
      const b=document.createElement('button');
      b.type='button'; b.className='clock-number'; b.textContent=mode==='minute'?two(v):String(v);
      b.style.left=(50+r*Math.cos(angle))+'%'; b.style.top=(50+r*Math.sin(angle))+'%';
      const selected=mode==='hour'?v===hour12:v===minuteVal;
      if(selected)b.classList.add('selected');
      b.addEventListener('click',()=>{
        if(mode==='hour'){hour12=v;mode='minute';updateHeader();renderFace();}
        else{minuteVal=v;updateHeader();renderFace();}
      });
      face.appendChild(b);
    });
    const hand=document.createElement('div'); hand.className='clock-hand';
    const index=mode==='hour'?(hour12%12):Math.round(minuteVal/5)%12;
    hand.style.transform='translateY(-50%) rotate('+(index*30-90)+'deg)';
    face.appendChild(hand);
    const dot=document.createElement('div');dot.className='clock-center-dot';face.appendChild(dot);
  }
  function openPicker(){
    parseHidden(); original={hour12,minuteVal,period}; mode='hour';updateHeader();renderFace();modal.hidden=false;document.body.classList.add('clock-open');
  }
  function closePicker(){modal.hidden=true;document.body.classList.remove('clock-open');}
  trigger?.addEventListener('click',openPicker);
  hourValue?.addEventListener('click',()=>{mode='hour';updateHeader();renderFace();});
  minuteValue?.addEventListener('click',()=>{mode='minute';updateHeader();renderFace();});
  periodBtns.forEach(b=>b.addEventListener('click',()=>{period=b.dataset.period;updateHeader();}));
  cancelBtn?.addEventListener('click',()=>{hour12=original.hour12;minuteVal=original.minuteVal;period=original.period;closePicker();});
  okBtn?.addEventListener('click',()=>{const v=to24();hidden.value=v;display.textContent=v;closePicker();});
  modal?.addEventListener('click',e=>{if(e.target===modal)closePicker();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)closePicker();});
  parseHidden();display.textContent=hidden.value;
})();
</script>
<?php page_footer(); ?>
