<?php
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
echo "Installer lokal dinonaktifkan pada versi Railway. Database dibuat otomatis saat service pertama kali dijalankan.\n";
