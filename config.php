<?php
return [
    'db_host' => getenv('MYSQLHOST') ?: '127.0.0.1',
    'db_port' => getenv('MYSQLPORT') ?: '3306',
    'db_name' => getenv('MYSQLDATABASE') ?: 'lupa_absen',
    'db_user' => getenv('MYSQLUSER') ?: 'root',
    'db_pass' => getenv('MYSQLPASSWORD') ?: '',
    'app_name' => getenv('APP_NAME') ?: 'Aplikasi Lupa Absen',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Jakarta',
];
