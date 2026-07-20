<?php
declare(strict_types=1);

/**
 * Generator DOCX berbasis TEMPLATE WORD ASLI.
 * Tujuan: hasil unduhan mempertahankan layout, font, margin, tabel, dan posisi
 * dari dokumen "Format Lupa Absen Dit. SSPJJ New 2026" seakurat mungkin.
 *
 * Margin template asli:
 * - Atas   : 1 cm
 * - Kanan  : 2 cm
 * - Bawah  : 0,48 cm
 * - Kiri   : 3 cm
 * Ukuran: A4 Portrait.
 */

function docx_x(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docx_template_run(string $text, array $opts=[]): string {
    $props='<w:rFonts w:ascii="Arial" w:eastAsia="Arial" w:hAnsi="Arial" w:cs="Arial"/>';
    $props.='<w:sz w:val="22"/><w:szCs w:val="22"/>';
    if(!empty($opts['bold'])) $props.='<w:b/>';
    if(!empty($opts['underline'])) $props.='<w:u w:val="single"/>';
    if(!empty($opts['strike'])) $props.='<w:strike/>';
    $space=($text!==trim($text) || str_contains($text,'  '))?' xml:space="preserve"':'';
    return '<w:r><w:rPr>'.$props.'</w:rPr><w:t'.$space.'>'.docx_x($text).'</w:t></w:r>';
}

/** Ganti isi sebuah paragraf template berdasarkan w14:paraId, tetapi pertahankan w:pPr asli. */
function docx_replace_paragraph(string $xml, string $paraId, string $runsXml): string {
    $id=preg_quote($paraId,'~');
    $pattern='~(<w:p\\b[^>]*w14:paraId="'.$id.'"[^>]*>)(.*?)(</w:p>)~s';
    return preg_replace_callback($pattern,function($m) use($runsXml){
        $pPr='';
        if(preg_match('~<w:pPr>.*?</w:pPr>~s',$m[2],$pm)) $pPr=$pm[0];
        return $m[1].$pPr.$runsXml.$m[3];
    },$xml,1) ?? $xml;
}

function docx_remove_paragraph(string $xml,string $paraId): string {
    $id=preg_quote($paraId,'~');
    return preg_replace('~<w:p\\b[^>]*w14:paraId="'.$id.'"[^>]*>.*?</w:p>~s','',$xml,1) ?? $xml;
}

function docx_image_info(?string $relativePath,string $baseDir): ?array {
    if(!$relativePath) return null;
    $path=$baseDir.'/'.ltrim($relativePath,'/');
    if(!is_file($path)) return null;
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    $ext=match($mime){'image/png'=>'png','image/jpeg'=>'jpeg',default=>null};
    if(!$ext) return null;
    return ['path'=>$path,'ext'=>$ext,'mime'=>$mime];
}

function docx_image_dims(string $path,float $maxWmm,float $maxHmm): array {
    $size=@getimagesize($path);
    if(!$size || $size[0]<=0 || $size[1]<=0) return [(int)round($maxWmm*36000),(int)round($maxHmm*36000)];
    // pixels hanya dipakai sebagai rasio aspek; hasil akhir dibatasi dalam mm.
    $ratio=$size[0]/$size[1];
    $w=$maxWmm; $h=$w/$ratio;
    if($h>$maxHmm){$h=$maxHmm;$w=$h*$ratio;}
    return [(int)round($w*36000),(int)round($h*36000)];
}

/**
 * Floating image agar TTD mengisi ruang kosong template TANPA mendorong nama/NIP ke bawah.
 */
function docx_floating_image_run(string $rid,int $cx,int $cy,int $docPrId,string $name,int $yOffset=0): string {
    return '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251658240" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
        .'<wp:simplePos x="0" y="0"/>'
        .'<wp:positionH relativeFrom="column"><wp:align>center</wp:align></wp:positionH>'
        .'<wp:positionV relativeFrom="paragraph"><wp:posOffset>'.$yOffset.'</wp:posOffset></wp:positionV>'
        .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
        .'<wp:wrapNone/><wp:docPr id="'.$docPrId.'" name="'.docx_x($name).'"/>'
        .'<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><pic:nvPicPr><pic:cNvPr id="0" name="'.docx_x($name).'"/><pic:cNvPicPr/></pic:nvPicPr>'
        .'<pic:blipFill><a:blip r:embed="'.docx_x($rid).'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        .'</pic:pic></a:graphicData></a:graphic>'
        .'</wp:anchor></w:drawing></w:r>';
}

function docx_add_relationship(string $relsXml,string $rid,string $target): string {
    $rel='<Relationship Id="'.docx_x($rid).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="'.docx_x($target).'"/>';
    return str_replace('</Relationships>',$rel.'</Relationships>',$relsXml);
}

function docx_ensure_content_type(string $xml,string $ext,string $mime): string {
    if(preg_match('~<Default\\s+Extension="'.preg_quote($ext,'~').'"\\s+ContentType=~i',$xml)) return $xml;
    return str_replace('</Types>','<Default Extension="'.docx_x($ext).'" ContentType="'.docx_x($mime).'"/></Types>',$xml);
}

function docx_grade_for_letter(string $grade): string {
    $grade=trim($grade);
    if(preg_match('/^([^()]+?)\\s*\\(/u',$grade,$m)) return trim($m[1]);
    return $grade;
}

function build_letter_docx(array $event,array $employee,array $set,string $outputPath,string $baseDir): void {
    $template=$baseDir.'/templates/format_lupa_absen_asli.docx';
    if(!is_file($template)) throw new RuntimeException('Template Word asli tidak ditemukan.');

    @unlink($outputPath);
    $zipPath=$outputPath.'.zip';
    @unlink($zipPath);
    if(!copy($template,$zipPath)) throw new RuntimeException('Template Word tidak dapat disalin.');

    $zip=new PharData($zipPath);
    $document=$zip['word/document.xml']->getContent();
    $rels=$zip['word/_rels/document.xml.rels']->getContent();
    $types=$zip['[Content_Types].xml']->getContent();

    // Nomor surat: pertahankan indentasi dan spacing paragraf asli.
    $numberRuns=docx_template_run('Nomor').docx_template_run(' : ');
    if(trim((string)($event['letter_number']??''))!=='') $numberRuns.=docx_template_run((string)$event['letter_number']);
    $document=docx_replace_paragraph($document,'12B4BA3A',$numberRuns);

    // Identitas - isi tabel asli, sehingga ukuran kolom dan jarak tetap sama.
    $document=docx_replace_paragraph($document,'633C3554',docx_template_run((string)($employee['name']??'')));
    $document=docx_replace_paragraph($document,'575CC735',docx_template_run((string)($employee['nip']??'')));
    $document=docx_replace_paragraph($document,'462673CD',docx_template_run(docx_grade_for_letter((string)($employee['grade']??''))));
    $document=docx_replace_paragraph($document,'5C82D1AF',docx_template_run((string)($employee['position']??'')));

    // Kalimat kejadian dengan pencoretan 3 kategori yang tidak dipilih.
    $statement=docx_template_run('Dengan ini menerangkan bahwa pada hari ');
    $statement.=docx_template_run(date_long((string)$event['event_date']));
    $statement.=docx_template_run(', saya ');
    $first=true;
    foreach(categories() as $key=>$label){
        if(!$first) $statement.=docx_template_run(' / ');
        $statement.=docx_template_run($label,['strike'=>$key!==($event['category']??'')]);
        $first=false;
    }
    $statement.=docx_template_run('*) karena: '.strtolower((string)($event['reason']??'')).' pada pukul '.substr((string)($event['event_time']??''),0,5).' melalui aplikasi '.(string)($event['app_name']??'Satu Bravo'));
    $document=docx_replace_paragraph($document,'6190D2F9',$statement);

    $approver=right_approver_for_employee($employee,$set);
    $signatureNames=signature_display_names($employee,$approver);

    // Blok tanda tangan memakai tabel asli agar posisi kiri/kanan sama seperti dokumen sumber.
    $document=docx_replace_paragraph($document,'27FC066D',docx_template_run((string)($set['office_city']??'Jakarta').', '.date_formal((string)$event['letter_date'])));
    $status=(string)($event['approval_status']??'pending');
    if($status==='approved'){
        $approval=docx_template_run('Disetujui/').docx_template_run('tidak disetujui',['strike'=>true]).docx_template_run(' oleh');
    }elseif($status==='rejected'){
        $approval=docx_template_run('Disetujui',['strike'=>true]).docx_template_run('/tidak disetujui oleh');
    }else{
        $approval=docx_template_run('Disetujui/tidak disetujui oleh');
    }
    $document=docx_replace_paragraph($document,'0A8F4610',$approval);

    $approverPosition=trim((string)($approver['position']??''));
    if($approverPosition==='Kepala Subdirektorat Pemantauan dan Evaluasi'){
        $approverLine1='Kepala Subdirektorat ';
        $approverLine2='Pemantauan dan Evaluasi,';
    }elseif(stripos($approverPosition,'Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan')!==false){
        $prefix=str_starts_with(strtoupper($approverPosition),'PLT.') ? 'PLT. ' : '';
        $approverLine1=$prefix.'Direktur Sistem dan Strategi';
        $approverLine2='Penyelenggaraan Jalan dan Jembatan,';
    }else{
        $parts=preg_split('/\s+/', $approverPosition) ?: [$approverPosition];
        $mid=max(1,(int)ceil(count($parts)/2));
        $approverLine1=implode(' ',array_slice($parts,0,$mid));
        $approverLine2=implode(' ',array_slice($parts,$mid)).',';
    }
    $document=docx_replace_paragraph($document,'5DC7679B',docx_template_run($approverLine1));
    $document=docx_replace_paragraph($document,'4A47CF86',docx_template_run($approverLine2));
    $document=docx_replace_paragraph($document,'2A1D094E',docx_template_run((string)$signatureNames['employee'],['underline'=>true]));
    $document=docx_replace_paragraph($document,'4E77428F',docx_template_run('NIP. '.(string)($employee['nip']??'')));
    $document=docx_replace_paragraph($document,'511776B0',docx_template_run((string)$signatureNames['approver'],['underline'=>true]));
    $document=docx_replace_paragraph($document,'63C2F6F0',docx_template_run('NIP. '.(string)($approver['nip']??'')));

    // Catatan penolakan: jika ada, masukkan ke paragraf titik-titik; jika kosong, biarkan format asli.
    $note=trim((string)($event['rejection_note']??''));
    if($note!==''){
        $document=docx_replace_paragraph($document,'1A058787',docx_template_run($note));
    }

    // TTD pegawai: tampil jika tersedia. Floating image tidak mengubah posisi teks/template.
    $empImg=docx_image_info($employee['signature_path']??null,$baseDir);
    if($empImg){
        $rid='rIdEmployeeSignature';
        [$cx,$cy]=docx_image_dims($empImg['path'],38,20);
        $document=docx_replace_paragraph($document,'2E7F7BEF',docx_floating_image_run($rid,$cx,$cy,901,'Tanda tangan pegawai',0));
        $media='employee_signature.'.$empImg['ext'];
        $zip['word/media/'.$media]=file_get_contents($empImg['path']);
        $rels=docx_add_relationship($rels,$rid,'media/'.$media);
        $types=docx_ensure_content_type($types,$empImg['ext'],$empImg['mime']);
    }

    // TTD sisi kanan hanya muncul jika status Disetujui.
    // Pegawai biasa -> Kepala Subdirektorat; Kepala Subdirektorat -> PLT. Direktur.
    $approverImg=$status==='approved' ? docx_image_info($approver['signature_path']??null,$baseDir) : null;
    if($approverImg){
        $rid='rIdRightApproverSignature';
        [$cx,$cy]=docx_image_dims($approverImg['path'],42,18);
        $document=docx_replace_paragraph($document,'22322AE3',docx_floating_image_run($rid,$cx,$cy,902,'Tanda tangan '.(string)($approver['name']??'penandatangan'),0));
        $media='right_approver_signature.'.$approverImg['ext'];
        $zip['word/media/'.$media]=file_get_contents($approverImg['path']);
        $rels=docx_add_relationship($rels,$rid,'media/'.$media);
        $types=docx_ensure_content_type($types,$approverImg['ext'],$approverImg['mime']);
    }

    $zip['word/document.xml']=$document;
    $zip['word/_rels/document.xml.rels']=$rels;
    $zip['[Content_Types].xml']=$types;
    unset($zip);

    if(!@rename($zipPath,$outputPath)){
        @unlink($zipPath);
        throw new RuntimeException('File DOCX tidak dapat diselesaikan.');
    }
}
