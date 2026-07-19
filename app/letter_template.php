<?php
require_once __DIR__.'/bootstrap.php';

function category_sentence(string $selected): string {
    $out=[];
    foreach(categories() as $key=>$label) {
        $out[]=$key===$selected
            ? '<span class="selected-category">'.e($label).'</span>'
            : '<span class="strike">'.e($label).'</span>';
    }
    return implode(' / ',$out);
}

function approval_decision(string $status): string {
    if($status==='approved') return 'Disetujui/<span class="strike">tidak disetujui</span> oleh';
    if($status==='rejected') return '<span class="strike">Disetujui</span>/tidak disetujui oleh';
    return 'Disetujui/tidak disetujui oleh';
}


function letter_grade_display(string $grade): string {
    $grade=trim($grade);
    if(preg_match('/^([^()]+?)\s*\(/u',$grade,$m)) return trim($m[1]);
    return $grade;
}

function approver_position_lines(string $position): string {
    $position=trim($position);
    if($position==='Kepala Subdirektorat Pemantauan dan Evaluasi'){
        return 'Kepala Subdirektorat<br>Pemantauan dan Evaluasi';
    }
    if(stripos($position,'Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan')!==false){
        $prefix=str_starts_with(strtoupper($position),'PLT.') ? 'PLT. ' : '';
        return e($prefix.'Direktur Sistem dan Strategi').'<br>'.e('Penyelenggaraan Jalan dan Jembatan');
    }
    $parts=preg_split('/\s+/', $position) ?: [$position];
    $mid=max(1,(int)ceil(count($parts)/2));
    return e(implode(' ',array_slice($parts,0,$mid))).'<br>'.e(implode(' ',array_slice($parts,$mid)));
}

function letter_html(array $event,array $employee,array $set): string {
    $logo=image_data_uri('assets/img/logo_pu.jpeg');
    $empSig=image_data_uri($employee['signature_path']??null);
    $approver=right_approver_for_employee($employee,$set);

    // TTD penandatangan sisi kanan hanya muncul setelah surat disetujui.
    $approverSig=$event['approval_status']==='approved'
        ? image_data_uri($approver['signature_path']??null)
        : '';

    ob_start(); ?>
<div class="paper Section1">
  <table class="letterhead" role="presentation">
    <tr>
      <td class="letterhead-logo"><?php if($logo):?><img src="<?=$logo?>" alt="Logo Kementerian Pekerjaan Umum"><?php endif;?></td>
      <td class="letterhead-text">
        <div class="head-ministry">KEMENTERIAN PEKERJAAN UMUM</div>
        <div class="head-directorate">DIREKTORAT JENDERAL BINA MARGA</div>
        <div class="head-unit">DIREKTORAT SISTEM DAN STRATEGI PENYELENGGARAAN JALAN DAN JEMBATAN</div>
        <p>Jl. Pattimura No. 20 Kebayoran Baru, Jakarta Selatan 12110, Telepon (021) 7200281, Surel direktoratsspjjbm@pu.go.id</p>
      </td>
    </tr>
  </table>

  <div class="letter-title"><h2>SURAT PERMOHONAN IZIN/PEMBERITAHUAN</h2></div>
  <div class="letter-number">Nomor : <?=e($event['letter_number']??'')?></div>

  <div class="letter-body">
    <p class="intro-line">Yang bertandatangan di bawah ini</p>
    <table class="ident" role="presentation">
      <tr><td>Nama</td><td>:</td><td><?=e($employee['name'])?></td></tr>
      <tr><td>NIP</td><td>:</td><td><?=e($employee['nip']??'')?></td></tr>
      <tr><td>Pangkat/Gol</td><td>:</td><td><?=e(letter_grade_display((string)($employee['grade']??'')))?></td></tr>
      <tr><td>Jabatan</td><td>:</td><td><?=e($employee['position']??'')?></td></tr>
    </table>

    <p class="statement">Dengan ini menerangkan bahwa pada hari <?=e(date_long($event['event_date']))?>, saya <?=category_sentence($event['category'])?>*) karena: <?=e(strtolower($event['reason']))?> pada pukul <?=e(substr($event['event_time'],0,5))?> melalui aplikasi <?=e($event['app_name'])?>.</p>

    <table class="signature-table" role="presentation">
      <tr class="approval-meta">
        <td>&nbsp;</td>
        <td>
          <div><?=e($set['office_city']??'Jakarta')?>, <?=e(date_formal($event['letter_date']))?></div>
          <div class="approval-line"><?=approval_decision($event['approval_status'])?></div>
        </td>
      </tr>
      <tr class="role-row">
        <td>Pegawai yang bersangkutan,</td>
        <td><?=approver_position_lines((string)($approver['position']??''))?>,</td>
      </tr>
      <tr class="sign-row">
        <td><?php if($empSig):?><img class="sign-img" src="<?=$empSig?>" alt="Tanda tangan pegawai"><?php endif;?></td>
        <td><?php if($approverSig):?><img class="sign-img boss-sign-img" src="<?=$approverSig?>" alt="Tanda tangan <?=e($approver['name']??'penandatangan')?>"><?php endif;?></td>
      </tr>
      <tr class="name-row">
        <td><?=e(plain_name($employee['name']))?></td>
        <td><?=e(plain_name((string)($approver['name']??'')))?></td>
      </tr>
      <tr class="nip-row">
        <td>NIP. <?=e($employee['nip']??'')?></td>
        <td>NIP. <?=e($approver['nip']??'')?></td>
      </tr>
    </table>

    <div class="notes">
      <span class="notes-label">Catatan Alasan Tidak Disetujui :</span>
      <?php if(!empty($event['rejection_note'])):?>
        <p><?=nl2br(e($event['rejection_note']))?></p>
      <?php else:?>
        <div class="notes-line"></div><div class="notes-line"></div><div class="notes-line"></div>
      <?php endif;?>
    </div>
  </div>
</div>
<?php return ob_get_clean(); }
