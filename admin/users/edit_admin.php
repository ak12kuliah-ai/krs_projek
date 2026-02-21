<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('global', 'ID user tidak valid.', 'warning');
  redirect(url('admin/users/index_admin.php'));
}

// ambil user admin
$st = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id=? LIMIT 1");
$st->execute([$id]);
$u = $st->fetch();

if (!$u || (string)$u['role'] !== 'admin') {
  flash_set('global', 'User admin tidak ditemukan.', 'danger');
  redirect(url('admin/users/index_admin.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/users/edit_admin.php?id='.$id));
  }

  $name  = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  if ($name === '' || $email === '') {
    flash_set('global', 'Nama dan email wajib diisi.', 'warning');
    redirect(url('admin/users/edit_admin.php?id='.$id));
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('global', 'Format email tidak valid.', 'warning');
    redirect(url('admin/users/edit_admin.php?id='.$id));
  }

  try {
    if ($pass !== '') {
      if (strlen($pass) < 6) {
        flash_set('global', 'Password minimal 6 karakter (atau kosongkan jika tidak ganti).', 'warning');
        redirect(url('admin/users/edit_admin.php?id='.$id));
      }
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $st = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=? WHERE id=? AND role='admin'");
      $st->execute([$name, $email, $hash, $id]);
    } else {
      $st = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=? AND role='admin'");
      $st->execute([$name, $email, $id]);
    }

    flash_set('global', 'Admin berhasil diupdate.', 'success');
    redirect(url('admin/users/index_admin.php'));
  } catch (Throwable $e) {
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal update admin.', 'danger');
    redirect(url('admin/users/edit_admin.php?id='.$id));
  }
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
  <main class="content">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
      <div>
        <h2 style="margin:0;">Edit Admin</h2>
        <div style="color:#666;margin-top:6px;">ID: <b><?= (int)$u['id'] ?></b></div>
      </div>
      <a class="btn" href="<?= e(url('admin/users/index_admin.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;max-width:900px;margin-top:12px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div>
            <label>Nama*</label>
            <input name="name" required value="<?= e((string)$u['name']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>
          <div>
            <label>Email*</label>
            <input name="email" type="email" required value="<?= e((string)$u['email']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>
          <div>
            <label>Password (kosongkan jika tidak ganti)</label>
            <input name="password" type="password"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn" type="submit">Update</button>
          <a class="btn" href="<?= e(url('admin/users/index_admin.php')) ?>">Batal</a>
        </div>
      </form>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>