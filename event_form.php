<?php
require_once __DIR__.'/app/layout.php';
require_login();

$id=(int)($_GET['id']??$_POST['id']??0);
$row=[
    'employee_id'=>'',
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
    $row=$st->fetch()?:$row;
}

$emps=db()->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $emp=(int)($_POST['employee_id']??0);
        $eventDate=trim($_POST['event_date']??'');
        if(!$emp) throw new RuntimeException('Pilih pegawai terlebih dahulu.');
        if(!valid_iso_date($eventDate)) throw new RuntimeException('Tanggal kejadian tidak valid.');

        $automaticLetterDate=next_working_day($eventDate);
        $postedLetterDate=trim($_POST['letter_date']??'');
        $letterDate=$postedLetterDate!==''?normalize_working_day($postedLetterDate):$automaticLetterDate;
        if($letterDate<=$eventDate) $letterDate=$automaticLetterDate;

        $cat=$_POST['category']??'';
        $status=$_POST['approval_status']??'pending';
        if(!isset(categories()[$cat])||!isset(approval_labels()[$status])){
            throw new RuntimeException('Kategori atau status tidak valid.');
        }
        if($status==='rejected'&&trim($_POST['rejection_note']??'')===''){
            throw new RuntimeException('Catatan alasan wajib diisi apabila tidak disetujui.');
        }

        $data=[
            $emp,
            $eventDate,
            $letterDate,
            $cat,
            $_POST['event_time'],
            trim($_POST['app_name']),
            trim($_POST['reason']),
            trim($_POST['letter_number'])?:null,
            $status,
            trim($_POST['rejection_note'])?:null,
            current_user()['id'],
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
        if(!empty($row['letter_date'])&&valid_iso_date((string)$row['letter_date'])){
            $row['letter_date']=normalize_working_day((string)$row['letter_date']);
        }
    }
}

page_header($id?'Ubah Kejadian':'Input Kejadian',$id?'events':'new');
if($error) echo '<div class="alert error">'.e($error).'</div>';
?>
<div class="grid two-col">
  <div class="card">
    <div id="count-warning"></div>
    <form method="post">
      <?=csrf_input()?>
      <input type="hidden" name="id" value="<?=$id?>">
      <div class="form-grid">
        <div class="field full">
          <label>Nama pegawai</label>
          <select id="employee_id" name="employee_id" required>
            <option value="">Pilih pegawai</option>
            <?php foreach($emps as $e): ?>
              <option value="<?=$e['id']?>" <?=((int)($row['employee_id']??0)===$e['id'])?'selected':''?>><?=e($e['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Tanggal kejadian</label>
          <input id="event_date" type="date" name="event_date" value="<?=e($row['event_date'])?>" required>
        </div>

        <div class="field">
          <label>Tanggal surat — hari kerja</label>
          <input id="letter_date" type="date" name="letter_date" value="<?=e($row['letter_date'])?>" required>
          <small class="help" id="letter-date-note">Otomatis memilih hari kerja berikutnya, Senin–Jumat.</small>
        </div>

        <div class="field full">
          <label>Kategori</label>
          <div class="radio-grid">
            <?php foreach(categories() as $key=>$label): ?>
              <label class="radio-card">
                <input type="radio" name="category" value="<?=e($key)?>" <?=$row['category']===$key?'checked':''?>>
                <span><?=e(ucfirst($label))?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label>Pukul</label>
          <input type="time" name="event_time" value="<?=e(substr($row['event_time'],0,5))?>" required>
        </div>

        <div class="field">
          <label>Aplikasi absensi</label>
          <input name="app_name" value="<?=e($row['app_name'])?>" required>
        </div>

        <div class="field full">
          <label>Alasan/keterangan</label>
          <textarea name="reason" required><?=e($row['reason'])?></textarea>
        </div>

        <div class="field">
          <label>Nomor surat</label>
          <input name="letter_number" value="<?=e($row['letter_number'])?>" placeholder="Contoh: UM.01.02/SSPJJ/123">
        </div>

        <div class="field">
          <label>Status persetujuan</label>
          <select name="approval_status">
            <?php foreach(approval_labels() as $k=>$v): ?>
              <option value="<?=$k?>" <?=$row['approval_status']===$k?'selected':''?>><?=e($v)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field full">
          <label>Catatan alasan tidak disetujui</label>
          <textarea name="rejection_note"><?=e($row['rejection_note'])?></textarea>
        </div>
      </div>

      <div class="form-actions">
        <a class="btn ghost" href="events.php">Batal</a>
        <button class="btn">Simpan &amp; Buat Surat</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="section-title"><h2>Aturan Surat</h2></div>
    <div class="alert"><b>Kategori:</b> kategori yang dipilih tetap normal, sedangkan tiga kategori lain otomatis dicoret.</div>
    <div class="alert"><b>Persetujuan:</b> jika disetujui, kata “tidak disetujui” dicoret. Jika tidak disetujui, kata “Disetujui” dicoret.</div>
    <div class="alert"><b>Tanggal surat:</b> otomatis dibuat pada hari kerja berikutnya. Kejadian Senin–Kamis menghasilkan surat pada hari berikutnya; kejadian Jumat, Sabtu, atau Minggu menghasilkan surat pada hari Senin.</div>
    <div class="help">Tanggal dapat diubah ke hari kerja lain. Apabila Sabtu atau Minggu dipilih, sistem otomatis memindahkannya ke hari Senin berikutnya.</div>
  </div>
</div>

<script>
(function(){
  const eventInput=document.getElementById('event_date');
  const letterInput=document.getElementById('letter_date');
  const note=document.getElementById('letter-date-note');
  const dayNames=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

  function parseLocal(value){
    if(!/^\d{4}-\d{2}-\d{2}$/.test(value||'')) return null;
    const [y,m,d]=value.split('-').map(Number);
    const date=new Date(y,m-1,d);
    return Number.isNaN(date.getTime())?null:date;
  }
  function iso(date){
    return [date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');
  }
  function nextWorkingDay(value){
    const date=parseLocal(value); if(!date) return '';
    do{ date.setDate(date.getDate()+1); }while(date.getDay()===0||date.getDay()===6);
    return iso(date);
  }
  function normalizeWorkingDay(value){
    const date=parseLocal(value); if(!date) return '';
    while(date.getDay()===0||date.getDay()===6) date.setDate(date.getDate()+1);
    return iso(date);
  }
  function updateNote(message){
    const date=parseLocal(letterInput.value);
    note.textContent=message||(date?`Tanggal surat: ${dayNames[date.getDay()]}, ${letterInput.value}.`:'Otomatis memilih hari kerja berikutnya, Senin–Jumat.');
  }

  eventInput.addEventListener('change',function(){
    letterInput.value=nextWorkingDay(this.value);
    updateNote('Tanggal surat otomatis dipindahkan ke hari kerja berikutnya.');
  });

  letterInput.addEventListener('change',function(){
    const normalized=normalizeWorkingDay(this.value);
    if(normalized&&normalized!==this.value){
      this.value=normalized;
      updateNote('Tanggal akhir pekan otomatis dipindahkan ke hari Senin berikutnya.');
    }else{
      updateNote();
    }
  });

  updateNote();
})();
</script>
<?php page_footer(); ?>
