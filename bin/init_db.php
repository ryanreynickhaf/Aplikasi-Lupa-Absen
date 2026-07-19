<?php
declare(strict_types=1);

$host=getenv('MYSQLHOST') ?: '';
$port=getenv('MYSQLPORT') ?: '3306';
$name=getenv('MYSQLDATABASE') ?: '';
$user=getenv('MYSQLUSER') ?: '';
$pass=getenv('MYSQLPASSWORD') ?: '';

if ($host==='' || $name==='' || $user==='') {
    fwrite(STDERR, "Database belum terhubung. Pastikan MYSQLHOST, MYSQLPORT, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD tersedia.\n");
    exit(1);
}

$dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$pdo=null;
$last=null;
for($i=1;$i<=30;$i++){
    try{
        $pdo=new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        break;
    }catch(Throwable $e){
        $last=$e;
        fwrite(STDERR,"Menunggu MySQL... percobaan {$i}/30\n");
        sleep(2);
    }
}
if(!$pdo){
    fwrite(STDERR,"Gagal terhubung ke MySQL: ".($last?->getMessage() ?? 'unknown')."\n");
    exit(1);
}

$schema=file_get_contents(dirname(__DIR__).'/database.sql');
if($schema===false) throw new RuntimeException('database.sql tidak ditemukan.');
foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$schema))) as $sql){
    $pdo->exec($sql);
}

// Migrasi aman untuk database Railway lama: tambahkan pengaturan PLT. Direktur jika belum ada.
$settingsColumns=[
    'director_name'=>"VARCHAR(200) NOT NULL DEFAULT 'Erna Wijayanti'",
    'director_nip'=>"VARCHAR(30) NOT NULL DEFAULT '198005082005022001'",
    'director_position'=>"VARCHAR(255) NOT NULL DEFAULT 'PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan'",
    'director_signature_path'=>"VARCHAR(255) NULL",
];
foreach($settingsColumns as $column=>$definition){
    $check=$pdo->prepare('SHOW COLUMNS FROM settings LIKE ?');
    $check->execute([$column]);
    if(!$check->fetch()){
        $pdo->exec("ALTER TABLE settings ADD COLUMN `{$column}` {$definition}");
        fwrite(STDOUT,"Kolom settings.{$column} ditambahkan.\n");
    }
}

$adminUser=trim((string)(getenv('ADMIN_USERNAME') ?: 'admin'));
$adminName=trim((string)(getenv('ADMIN_NAME') ?: 'Administrator'));
$adminPass=(string)(getenv('ADMIN_PASSWORD') ?: '');

$count=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if($count===0){
    if(strlen($adminPass)<8){
        fwrite(STDERR,"ADMIN_PASSWORD wajib diisi minimal 8 karakter sebelum deployment pertama.\n");
        exit(1);
    }
    $st=$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)');
    $st->execute([$adminName,$adminUser,password_hash($adminPass,PASSWORD_DEFAULT),'admin']);
    fwrite(STDOUT,"Akun admin awal dibuat: {$adminUser}\n");
}

$count=(int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
if($count===0){
    $seed=[
        ['Mohammad Taufiq Malik Alghani, S.T.', '199703172022031009', 'III/b (Penata Muda Tingkat I)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Abdul Hamdani, S.Kom.', '', 'IX (Mahir/Ahli Pertama)', 'Pranata Komputer Ahli Pertama', null],
        ['Putri Cendikiawati, S.T.', '', 'III/b (Penata Muda Tingkat I)', 'Perencana Ahli Pertama', null],
        ['Helyadi Pongtiku Tendeng, S.T.', '', 'IX (Mahir/Ahli Pertama)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Yani', '', 'V (Pemula)', 'Pengadministrasi Perkantoran', null],
        ['Luthfi Maulid Sukmana, S.T., M.T.', '199209182018021001', 'III/c (Penata)', 'Penata Kelola Jalan dan Jembatan Ahli Muda', null],
        ['Khoirul Basyar, S.Pd', '', 'III/a (Penata Muda)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Firman Alvansius Situmorang, S.T.', '', 'III/a (Penata Muda)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Rakhmat Shafly Syabana, S.T.', '199701022022031006', 'III/b (Penata Muda Tingkat I)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Henny Kusumawardhani, S.Kom.', '198003232005022001', 'III/d (Penata Tingkat I)', 'Analis Monitoring', null],
        ['Sylvia Dwi Lestari, A.Md', '', 'III/a (Penata Muda)', 'Pengelola Data', null],
        ['Ryan Reynickha Fatullah, S.T.', '199501152023211008', 'IX (Mahir/Ahli Pertama)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', 'assets/signatures/ryan.png'],
        ['Adi Wisaka, S.T.', '199903212025061003', 'III/a (Penata Muda)', 'Penata Kelola Jalan dan Jembatan Ahli Pertama', null],
        ['Niana Fitriasari, S.T.', '', 'III/d (Penata Tingkat I)', 'Karyasiswa Master dan Doktoral', null],
        ['Fitri Ambarwati, S.T,MT', '', 'III/d (Penata Tingkat I)', 'Penata Kelola Jalan dan Jembatan Ahli Muda', null],
        ['Yusrizal Kurniawan, S.T., M.Sc., M.Eng.', '197903032005021003', 'IV/a (Pembina)', 'Kepala Subdirektorat Pemantauan dan Evaluasi', null]
    ];
    $st=$pdo->prepare('INSERT INTO employees(name,nip,grade,position,signature_path,active) VALUES(?,?,?,?,?,1)');
    foreach($seed as $row){$st->execute($row);}
    fwrite(STDOUT,"Data pegawai awal dibuat.\n");
}

fwrite(STDOUT,"Database siap.\n");
