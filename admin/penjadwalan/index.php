<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$active = null;
try {
  $active = $pdo->query("SELECT id, nama, periode FROM semester WHERE is_active=1 LIMIT 1")->fetch();
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
}

if (!$active) {
  flash_set('global', 'Belum ada semester aktif. Set dulu di Kelola Semester.', 'warning');
  redirect(url('admin/semester/index.php'));
}

$semesterId = (int)$active['id'];
$periode = (string)$active['periode'];

$allowed = ($periode === 'ganjil') ? [1,3,5,7] : [2,4,6,8];
$labelKelompok = ($periode === 'ganjil') ? 'Ganjil (1,3,5,7)' : 'Genap (2,4,6,8)';
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Penjadwalan</h2>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-bottom:12px;">
      <div style="font-size:12px;color:#666;">Semester aktif (penentu sistem)</div>
      <div style="font-size:18px;font-weight:700;">
        <?= e($active['nama'].' - '.$active['periode']) ?>
      </div>
      <div style="color:#666;margin-top:6px;">
        Yang tampil hanya semester mahasiswa: <b><?= e($labelKelompok) ?></b>
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
      <h3 style="margin:0 0 10px;">Pilih Semester Mahasiswa</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach ($allowed as $k): ?>
          <a class="btn" href="<?= e(url('admin/penjadwalan/detail.php?semester_ke='.$k)) ?>">
            Semester <?= $k ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
