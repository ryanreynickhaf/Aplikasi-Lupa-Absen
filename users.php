<?php
require_once __DIR__ . '/app/layout.php';
require_admin();

$pdo = db();
$error = '';

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
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'operator';

            if ($name === '') throw new RuntimeException('Nama wajib diisi.');
            if ($username === '') throw new RuntimeException('Username wajib diisi.');
            if (strlen($password) < 8) throw new RuntimeException('Kata sandi minimal 8 karakter.');

            $st = $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)');
            $st->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
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
                $st = $pdo->prepare('UPDATE users SET name=?,username=?,role=?,password_hash=? WHERE id=?');
                $st->execute([$name, $username, $role, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
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
    $st = $pdo->prepare('SELECT id,name,username,role,created_at FROM users WHERE id=?');
    $st->execute([$editId]);
    $editUser = $st->fetch() ?: null;
}

$rows = $pdo->query('SELECT id,name,username,role,created_at FROM users ORDER BY name')->fetchAll();
page_header('Pengguna', 'users');
if ($error) echo '<div class="alert error">' . e($error) . '</div>';
?>
<div class="grid two-col">
  <div class="card">
    <div class="section-title">
      <h2>Daftar pengguna</h2>
      <span class="badge info"><?=count($rows)?> pengguna</span>
    </div>
    <div class="alert" style="font-size:12px">
      <strong>Catatan keamanan:</strong> kata sandi lama tidak dapat ditampilkan karena disimpan sebagai hash satu arah. Admin dapat <strong>mereset kata sandi</strong> melalui tombol Ubah. Kata sandi baru bisa dilihat/sembunyikan saat diketik.
    </div>
    <div class="table-wrap">
      <table class="data" style="min-width:760px">
        <thead>
          <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Kata sandi</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?=e($r['name'])?></td>
            <td><?=e($r['username'])?></td>
            <td><span class="badge <?=$r['role']==='admin'?'info':'ok'?>"><?=e(ucfirst($r['role']))?></span></td>
            <td><span class="help">•••••••• (terenkripsi)</span></td>
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
        <?php if ($editUser): ?><div class="help">Password yang tersimpan tidak bisa dibaca kembali. Isi kolom ini hanya untuk mereset password.</div><?php endif; ?>
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
  if (!input || !button) return;
  button.addEventListener('click', function(){
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    button.textContent = visible ? 'Lihat' : 'Sembunyikan';
  });
})();
</script>
<?php page_footer();
