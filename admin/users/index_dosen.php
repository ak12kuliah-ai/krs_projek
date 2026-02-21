<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$rows = [];
try {
  $st = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.created_at, d.nidn, d.prodi
    FROM users u
    LEFT JOIN dosen d ON d.user_id = u.id
    WHERE u.role='dosen'
    ORDER BY u.id DESC
  ");
  $st->execute();
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
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
        <h2 style="margin:0;">Dosen</h2>
        <div style="color:#666;margin-top:6px;">Daftar user role <b>dosen</b>.</div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Kembali</a>
        <a class="btn" href="<?= e(url('admin/users/add.php?role=dosen')) ?>">+ Tambah Dosen</a>
      </div>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:860px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Nama</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Email</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">NIDN</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Prodi</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6" style="padding:12px;color:#666;">Belum ada dosen.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['name']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['email']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['nidn']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['prodi']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="<?= e(url('admin/users/edit.php?id='.(int)$r['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin/users/delete.php')) ?>" style="margin:0;">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="back" value="index_dosen.php">
                      <button class="btn" type="submit" onclick="return confirm('Hapus dosen ini?')">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
