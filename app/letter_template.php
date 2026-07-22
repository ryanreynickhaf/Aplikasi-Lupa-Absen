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
    [$line1,$line2]=approver_position_two_lines($position);
    if($line2==='') return e($line1);
    return e($line1).'<br>'.e($line2);
}

function letter_html(array $event,array $employee,array $set): string {
    $logo=image_data_uri('assets/img/logo_pu.jpeg');
    $empSig=image_data_uri($employee['signature_path']??null);
    $approver=right_approver_for_employee($employee,$set);
    $signatureNames=signature_display_names($employee,$approver);

    // TTD penandatangan sisi kanan hanya muncul setelah surat disetujui.
    $approverSig=$event['approval_status']==='approved'
        ? image_data_uri($approver['signature_path']??null)
        : '';

    ob_start(); ?>
<div class="paper Section1">
  <!--
    KOP PRESISI BERDASARKAN TEMPLATE WORD ASLI:
    - Logo asli di DOCX berukuran 720000 x 720000 EMU = 20 x 20 mm.
    - Kolom logo pada tabel asli 1152 twips = ±20,32 mm.
    - Garis dimulai dari kolom teks (bukan di bawah logo) dan berada tepat
      pada tinggi bawah logo, sehingga garis dan bagian bawah logo sejajar.
  -->
  <table class="letterhead" role="presentation" style="width:100%;border-collapse:collapse;table-layout:fixed;border-bottom:0">
    <tr style="height:20mm">
      <td class="letterhead-logo" style="width:20.32mm;height:20mm;padding:0;vertical-align:top;text-align:left;border:0">
        <?php if($logo):?><img src="<?=$logo?>" alt="Logo Kementerian Pekerjaan Umum" style="display:block;width:20mm;height:20mm;max-width:20mm;max-height:20mm;object-fit:contain;margin:0"><?php endif;?>
      </td>
      <td class="letterhead-text" style="position:relative;height:20mm;padding:0 0 1mm 0;vertical-align:top;text-align:center;border:0">
        <div class="head-ministry">KEMENTERIAN PEKERJAAN UMUM</div>
        <div class="head-directorate">DIREKTORAT JENDERAL BINA MARGA</div>
        <div class="head-unit">DIREKTORAT SISTEM DAN STRATEGI PENYELENGGARAAN JALAN DAN JEMBATAN</div>
        <p>Jl. Pattimura No. 20 Kebayoran Baru, Jakarta Selatan 12110, Telepon (021) 7200281, Surel direktoratsspjjbm@pu.go.id</p>
        <div aria-hidden="true" style="position:absolute;left:0;right:0;bottom:0;height:0;border-bottom:1px solid #111"></div>
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
        <td><?=e($signatureNames['employee'])?></td>
        <td><?=e($signatureNames['approver'])?></td>
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
