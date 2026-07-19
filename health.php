<?php
header('Content-Type: application/json; charset=UTF-8');
try{
    require_once __DIR__.'/app/bootstrap.php';
    db()->query('SELECT 1');
    echo json_encode(['ok'=>true,'service'=>'Aplikasi Lupa Absen']);
}catch(Throwable $e){
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'database_unavailable']);
}
