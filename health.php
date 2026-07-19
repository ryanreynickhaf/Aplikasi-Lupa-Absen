<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
http_response_code(200);
echo json_encode([
    'ok' => true,
    'service' => 'Aplikasi Lupa Absen',
    'time' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
