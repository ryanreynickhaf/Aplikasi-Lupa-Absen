<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/docx_builder.php';
require_login();

$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT a.*,e.name,e.nip,e.grade,e.position,e.signature_path FROM attendance_events a JOIN employees e ON e.id=a.employee_id WHERE a.id=?');
$st->execute([$id]);
$row=$st->fetch();
if(!$row) exit('Surat tidak ditemukan.');

$employee=[
    'name'=>$row['name'],
    'nip'=>$row['nip'],
    'grade'=>$row['grade'],
    'position'=>$row['position'],
    'signature_path'=>$row['signature_path']
];

$tmp=tempnam(sys_get_temp_dir(),'lupa_absen_docx_');
if($tmp===false) exit('Tidak dapat membuat file Word sementara.');
@unlink($tmp);
$tmp.='.docx';

try{
    build_letter_docx($row,$employee,settings(),$tmp,__DIR__);
    if(!is_file($tmp) || filesize($tmp)<1000) throw new RuntimeException('File Word hasil generator tidak valid.');

    $name='Surat_Lupa_Absen_'.preg_replace('/[^a-zA-Z0-9_-]+/','_',plain_name($employee['name'])).'_'.$row['event_date'].'.docx';

    // Pastikan tidak ada spasi, notice, warning, atau HTML yang ikut masuk sebelum biner DOCX.
    while(ob_get_level()>0){ @ob_end_clean(); }
    clearstatcache(true,$tmp);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: '.(string)filesize($tmp));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $fp=fopen($tmp,'rb');
    if($fp===false) throw new RuntimeException('File Word tidak dapat dibaca.');
    fpassthru($fp);
    fclose($fp);
    exit;
}catch(Throwable $e){
    while(ob_get_level()>0){ @ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Gagal membuat file Word.\n\n".$e->getMessage();
}finally{
    @unlink($tmp);
}
