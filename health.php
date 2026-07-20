<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$readyFile = '/tmp/lupa-absen-ready';
$ready = is_file($readyFile);
http_response_code($ready ? 200 : 503);

echo json_encode([
    'ok' => $ready,
    'service' => 'Aplikasi Lupa Absen',
    'status' => $ready ? 'ready' : 'starting',
    'time' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
