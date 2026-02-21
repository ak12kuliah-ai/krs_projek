<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$counts = ['admin' => 0, 'dosen' => 0, 'mahasiswa' => 0, 'total' => 0];

try {
  $st = $pdo->query("SELECT role, COUNT(*) c FROM users GROUP BY role");
  foreach ($st->fetchAll() as $r) {
    $role = (string)$r['role'];
    $cnt  = (int)$r['c'];
    if (isset($counts[$role])) $counts[$role] = $cnt;
    $counts['total'] += $cnt;
  }
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
    <h2 style="margin-top:0;">Kelola Users</h2>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:14px 0;">
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Total</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$counts['total'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Admin</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$counts['admin'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Dosen</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$counts['dosen'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Mahasiswa</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$counts['mahasiswa'] ?></div>
      </div>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
      <h3 style="margin:0 0 10px;">Aksi Cepat</h3>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('admin/users/index_admin.php')) ?>">List Admin</a>
        <a class="btn" href="<?= e(url('admin/users/index_dosen.php')) ?>">List Dosen</a>
        <a class="btn" href="<?= e(url('admin/users/index_mahasiswa.php')) ?>">List Mahasiswa</a>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
        <a class="btn" href="<?= e(url('admin/users/add.php?role=admin')) ?>">+ Tambah Admin</a>
        <a class="btn" href="<?= e(url('admin/users/add.php?role=dosen')) ?>">+ Tambah Dosen</a>
        <a class="btn" href="<?= e(url('admin/users/add.php?role=mahasiswa')) ?>">+ Tambah Mahasiswa</a>
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
