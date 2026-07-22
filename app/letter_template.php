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

    $approverSig=$event['approval_status']==='approved'
        ? image_data_uri($approver['signature_path']??null)
        : '';

    ob_start(); ?>
<div class="paper Section1">

  <!--
    KOP SURAT disesuaikan dengan template Word asli:
    - Lebar tabel asli 9514 twips = ±167,82 mm
    - Kolom logo 1152 twips = 20,32 mm
    - Logo asli 720000 EMU = 20 x 20 mm
    - Garis asli mulai ±21,31 mm dari kiri dan panjang ±141 mm
    - Font: Arial 18 / 12 / 10 / 7,5 pt
    - Baris ke-3 memakai line spacing 1,0667 sesuai XML Word
  -->
  <div class="letterhead-wrap"
       style="position:relative;width:167.82mm;height:20.35mm;margin:0;padding:0;overflow:visible;font-family:Arial,sans-serif">
    <table class="letterhead" role="presentation"
           style="width:167.82mm;height:19.63mm;border-collapse:collapse;table-layout:fixed;border:0;margin:0;padding:0">
      <colgroup>
        <col style="width:20.32mm">
        <col style="width:147.50mm">
      </colgroup>
      <tr style="height:19.63mm">
        <td class="letterhead-logo"
            style="width:20.32mm;height:19.63mm;padding:0;margin:0;vertical-align:top;text-align:left;border:0;position:relative">
          <?php if($logo):?>
            <img src="<?=$logo?>"
                 alt="Logo Kementerian Pekerjaan Umum"
                 style="position:absolute;left:-1.45mm;top:.18mm;display:block;width:20mm;height:20mm;max-width:none;object-fit:contain">
          <?php endif;?>
        </td>

        <td class="letterhead-text"
            style="position:relative;width:147.50mm;height:20mm;padding:0;margin:0;vertical-align:top;border:0;font-family:Arial,sans-serif;color:#000">
          <p class="head-ministry"
             style="position:absolute;left:0;right:0;top:1.15mm;font-family:Arial,sans-serif;font-size:18pt;font-weight:400;line-height:1;margin:0;padding:0;text-align:center;white-space:nowrap">
            KEMENTERIAN PEKERJAAN UMUM
          </p>

          <p class="head-directorate"
             style="position:absolute;left:0;right:0;top:8.27mm;font-family:Arial,sans-serif;font-size:12pt;font-weight:400;line-height:1;margin:0;padding:0;text-align:center;white-space:nowrap">
            DIREKTORAT JENDERAL BINA MARGA
          </p>

          <p class="head-unit"
             style="position:absolute;left:0;right:0;top:12.88mm;font-family:Arial,sans-serif;font-size:10pt;font-weight:700;line-height:1;margin:0;padding:0;text-align:center;white-space:nowrap;letter-spacing:-0.5pt">
            DIREKTORAT SISTEM DAN STRATEGI PENYELENGGARAAN JALAN DAN JEMBATAN
          </p>

          <p class="head-address"
             style="position:absolute;left:0;right:0;top:17.12mm;font-family:Arial,sans-serif;font-size:7.5pt;font-weight:400;line-height:1;margin:0;padding:0;text-align:left;white-space:nowrap">
            Jl. Pattimura No. 20 Kebayoran Baru, Jakarta Selatan 12110, Telepon (021) 7200281, Surel direktoratsspjjbm@pu.go.id
          </p>
        </td>
      </tr>
    </table>

    <!-- Garis horizontal mengikuti posisi dan panjang shape garis pada Word asli. -->
    <div aria-hidden="true"
         style="position:absolute;left:21.31mm;top:20.00mm;width:141mm;height:0;border-top:1pt solid #000"></div>
  </div>

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
