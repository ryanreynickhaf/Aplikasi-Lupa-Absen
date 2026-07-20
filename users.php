<?php
require_once __DIR__ . '/app/layout.php';
require_admin();

$pdo = db();
$error = '';

/**
 * Password login tetap memakai password_hash satu arah.
 * Kolom password_cipher hanya menyimpan salinan terenkripsi agar Admin dapat melihat
 * password yang dibuat/reset setelah fitur ini aktif.
 */
function users_password_vault_key(): string {
    global $config;
    $raw = trim((string)(getenv('PASSWORD_VAULT_KEY') ?: ($config['db_pass'] ?? '')));
    if ($raw === '') {
        throw new RuntimeException('PASSWORD_VAULT_KEY belum tersedia dan kunci fallback database kosong.');
    }
    return hash('sha256', $raw, true);
}

function users_password_vault_encrypt(string $plain): string {
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', users_password_vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Gagal mengenkripsi password untuk tampilan Admin.');
    return base64_encode($iv . $tag . $cipher);
}

function users_password_vault_decrypt(?string $payload): string {
    $payload = trim((string)$payload);
    if ($payload === '') return '';
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', users_password_vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function users_ensure_password_vault_column(PDO $pdo): void {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_cipher' LIMIT 1");
    $st->execute();
    if (!$st->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_cipher TEXT NULL AFTER password_hash");
    }
}

function users_backfill_known_default_passwords(PDO $pdo): void {
    $defaultPassword = (string)(getenv('EMPLOYEE_DEFAULT_PASSWORD') ?: 'SubditPE2026');
    $rows = $pdo->query("SELECT id,password_hash,password_cipher,role FROM users WHERE role='operator'")->fetchAll();
    $up = $pdo->prepare('UPDATE users SET password_cipher=? WHERE id=?');
    foreach ($rows as $row) {
        if (!empty($row['password_cipher'])) continue;
        if (password_verify($defaultPassword, (string)$row['password_hash'])) {
            $up->execute([users_password_vault_encrypt($defaultPassword), (int)$row['id']]);
        }
    }
}

users_ensure_password_vault_column($pdo);
users_backfill_known_default_passwords($pdo);

function user_count_admins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
}

function user_friendly_error(Throwable $e): string {
    if ($e instanceof PDOException && ($e->getCode() === '23000' || str_contains(strtolower($e->getMessage()), 'duplicate'))) {
        return 'Username sudah digunakan. Gunakan username lain.';
    }
    return $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create';

    try {
        if ($action === 'sync_employees') {
            $defaultPassword = (string)(getenv('EMPLOYEE_DEFAULT_PASSWORD') ?: 'SubditPE2026');
            $result = sync_all_employee_accounts($pdo, $defaultPassword);
            // Akun baru yang memakai password default dapat langsung ditampilkan ke Admin.
            users_backfill_known_default_passwords($pdo);
            log_activity('sync', 'user', null, 'Sinkronisasi akun pegawai; akun baru: ' . count($result['created']));
            flash('success', count($result['created']) . ' akun pegawai baru dibuat. Akun yang sudah ada tidak diubah.');
            redirect('users.php');
        }

        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'operator';

            if ($name === '') throw new RuntimeException('Nama wajib diisi.');
            if ($username === '') throw new RuntimeException('Username wajib diisi.');
            if (strlen($password) < 8) throw new RuntimeException('Kata sandi minimal 8 karakter.');

            $st = $pdo->prepare('INSERT INTO users(name,username,password_hash,password_cipher,role) VALUES(?,?,?,?,?)');
            $st->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), users_password_vault_encrypt($password), $role]);
            $newId = (int)$pdo->lastInsertId();
            log_activity('create', 'user', $newId, 'Menambahkan pengguna ' . $username);
            flash('success', 'Pengguna berhasil ditambahkan.');
            redirect('users.php');
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $newPassword = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'operator';

            $st = $pdo->prepare('SELECT id,name,username,role FROM users WHERE id=?');
            $st->execute([$id]);
            $existing = $st->fetch();
            if (!$existing) throw new RuntimeException('Pengguna tidak ditemukan.');
            if ($name === '') throw new RuntimeException('Nama wajib diisi.');
            if ($username === '') throw new RuntimeException('Username wajib diisi.');
            if ($newPassword !== '' && strlen($newPassword) < 8) throw new RuntimeException('Kata sandi baru minimal 8 karakter.');

            $isSelf = $id === (int)(current_user()['id'] ?? 0);
            if ($isSelf && $role !== 'admin') {
                throw new RuntimeException('Peran akun administrator yang sedang digunakan tidak dapat diturunkan menjadi operator.');
            }
            if ($existing['role'] === 'admin' && $role !== 'admin' && user_count_admins($pdo) <= 1) {
                throw new RuntimeException('Minimal harus ada satu akun Admin.');
            }

            if ($newPassword !== '') {
                $st = $pdo->prepare('UPDATE users SET name=?,username=?,role=?,password_hash=?,password_cipher=? WHERE id=?');
                $st->execute([$name, $username, $role, password_hash($newPassword, PASSWORD_DEFAULT), users_password_vault_encrypt($newPassword), $id]);
            } else {
                $st = $pdo->prepare('UPDATE users SET name=?,username=?,role=? WHERE id=?');
                $st->execute([$name, $username, $role, $id]);
            }

            if ($isSelf) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['role'] = $role;
            }

            log_activity('update', 'user', $id, 'Memperbarui pengguna ' . $username . ($newPassword !== '' ? ' dan mereset kata sandi' : ''));
            flash('success', $newPassword !== '' ? 'Pengguna dan kata sandi berhasil diperbarui.' : 'Pengguna berhasil diperbarui.');
            redirect('users.php');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)(current_user()['id'] ?? 0)) {
                throw new RuntimeException('Akun yang sedang digunakan tidak dapat dihapus.');
            }

            $st = $pdo->prepare('SELECT id,name,username,role FROM users WHERE id=?');
            $st->execute([$id]);
            $existing = $st->fetch();
            if (!$existing) throw new RuntimeException('Pengguna tidak ditemukan.');
            if ($existing['role'] === 'admin' && user_count_admins($pdo) <= 1) {
                throw new RuntimeException('Admin terakhir tidak dapat dihapus.');
            }

            log_activity('delete', 'user', $id, 'Menghapus pengguna ' . $existing['username']);
            $st = $pdo->prepare('DELETE FROM users WHERE id=?');
            $st->execute([$id]);
            flash('success', 'Pengguna berhasil dihapus.');
            redirect('users.php');
        }
    } catch (Throwable $e) {
        $error = user_friendly_error($e);
    }
}

$editUser = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $st = $pdo->prepare('SELECT id,employee_id,name,username,role,created_at FROM users WHERE id=?');
    $st->execute([$editId]);
    $editUser = $st->fetch() ?: null;
}

$rows = $pdo->query('SELECT id,employee_id,name,username,role,password_cipher,created_at FROM users ORDER BY name')->fetchAll();
page_header('Pengguna', 'users');
if ($error) echo '<div class="alert error">' . e($error) . '</div>';
?>
<div class="grid two-col">
  <div class="card">
    <div class="section-title">
      <h2>Daftar pengguna</h2>
      <div class="actions-inline">
        <span class="badge info"><?=count($rows)?> pengguna</span>
        <form method="post" style="display:inline">
          <?=csrf_input()?>
          <input type="hidden" name="action" value="sync_employees">
          <button class="btn secondary small" type="submit">Sinkronkan Akun Pegawai</button>
        </form>
      </div>
    </div>
    <div class="alert" style="font-size:12px">
      <strong>Akun pegawai otomatis:</strong> username dari <strong>nama depan</strong> dan password awal <strong>SubditPE2026</strong>.<br>
      <strong>Password dapat dilihat Admin:</strong> login tetap menggunakan hash satu arah. Salinan password terakhir yang dibuat/reset disimpan <strong>terenkripsi</strong> khusus untuk tampilan Admin. Password lama yang sudah diubah sebelum fitur ini aktif tidak dapat dipulihkan dan akan tampil <em>Tidak tersedia</em> sampai direset.<br>
      <strong>Keamanan:</strong> sebaiknya tambahkan Railway Variable <code>PASSWORD_VAULT_KEY</code> dengan nilai acak yang panjang dan jangan diubah setelah digunakan.
    </div>
    <div class="table-wrap">
      <table class="data" style="min-width:820px">
        <thead>
          <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Password</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $plainPassword = users_password_vault_decrypt($r['password_cipher'] ?? '');
          $pwdId = 'pwd_' . (int)$r['id'];
        ?>
          <tr>
            <td><?=e($r['name'])?></td>
            <td><?=e($r['username'])?></td>
            <td><span class="badge <?=$r['role']==='admin'?'info':'ok'?>"><?=e(ucfirst($r['role']))?></span></td>
            <td>
              <?php if ($plainPassword !== ''): ?>
                <div class="actions-inline" style="gap:6px;flex-wrap:nowrap">
                  <span id="<?=$pwdId?>" class="password-display" data-password="<?=e($plainPassword)?>" data-hidden="1">••••••••</span>
                  <button class="btn secondary small password-eye" type="button" data-target="<?=$pwdId?>" aria-label="Lihat password" title="Lihat / sembunyikan password">👁️</button>
                </div>
              <?php else: ?>
                <span class="help">Tidak tersedia — reset password</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="actions-inline">
                <a class="btn secondary small" href="users.php?edit=<?=$r['id']?>">Ubah</a>
                <?php if ((int)$r['id'] !== (int)(current_user()['id'] ?? 0)): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus pengguna <?=e(addslashes($r['username']))?>? Tindakan ini tidak dapat dibatalkan.');">
                  <?=csrf_input()?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <button class="btn danger small" type="submit">Hapus</button>
                </form>
                <?php else: ?>
                  <span class="badge warn">Akun aktif</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="section-title">
      <h2><?=$editUser ? 'Ubah pengguna' : 'Tambah pengguna'?></h2>
      <?php if ($editUser): ?><a class="btn ghost small" href="users.php">Batal</a><?php endif; ?>
    </div>

    <form method="post">
      <?=csrf_input()?>
      <input type="hidden" name="action" value="<?=$editUser ? 'update' : 'create'?>">
      <?php if ($editUser): ?><input type="hidden" name="id" value="<?=$editUser['id']?>"><?php endif; ?>

      <div class="field">
        <label>Nama</label>
        <input name="name" value="<?=e($editUser['name'] ?? '')?>" required>
      </div>

      <div class="field" style="margin-top:10px">
        <label>Username</label>
        <input name="username" value="<?=e($editUser['username'] ?? '')?>" required autocomplete="off">
      </div>

      <div class="field" style="margin-top:10px">
        <label><?=$editUser ? 'Kata sandi baru (kosongkan jika tidak diubah)' : 'Kata sandi'?></label>
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px">
          <input id="userPassword" type="password" name="password" minlength="8" <?=$editUser ? '' : 'required'?> autocomplete="new-password">
          <button class="btn secondary" type="button" id="togglePassword" style="white-space:nowrap">Lihat</button>
        </div>
        <?php if ($editUser): ?><div class="help">Isi kolom ini untuk mengganti password. Password baru akan tersimpan terenkripsi agar dapat dilihat Admin.</div><?php endif; ?>
      </div>

      <div class="field" style="margin-top:10px">
        <label>Peran</label>
        <select name="role">
          <option value="operator" <?=($editUser['role'] ?? 'operator')==='operator'?'selected':''?>>Operator</option>
          <option value="admin" <?=($editUser['role'] ?? '')==='admin'?'selected':''?>>Admin</option>
        </select>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit"><?=$editUser ? 'Simpan Perubahan' : 'Tambah'?></button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const input = document.getElementById('userPassword');
  const button = document.getElementById('togglePassword');
  if (input && button) {
    button.addEventListener('click', function(){
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.textContent = visible ? 'Lihat' : 'Sembunyikan';
    });
  }

  document.querySelectorAll('.password-eye').forEach(function(btn){
    btn.addEventListener('click', function(){
      const el = document.getElementById(btn.dataset.target);
      if (!el) return;
      const hidden = el.dataset.hidden === '1';
      el.textContent = hidden ? el.dataset.password : '••••••••';
      el.dataset.hidden = hidden ? '0' : '1';
      btn.textContent = hidden ? '🙈' : '👁️';
      btn.setAttribute('aria-label', hidden ? 'Sembunyikan password' : 'Lihat password');
    });
  });
})();
</script>
<?php page_footer();
