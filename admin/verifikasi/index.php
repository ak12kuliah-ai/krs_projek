<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$status = strtolower(trim($_GET['status'] ?? 'pending'));
$allowedStatus = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatus, true)) $status = 'pending';

$rows = [];
try {
  if ($status === 'all') {
    $st = $pdo->prepare("
      SELECT p.id, p.status, p.created_at, u.name, u.email, m.npm, m.prodi
      FROM pembayaran p
      JOIN users u ON u.id = p.user_id
      LEFT JOIN mahasiswa m ON m.user_id = u.id
      ORDER BY p.created_at DESC
      LIMIT 100
    ");
    $st->execute();
  } else {
    $st = $pdo->prepare("
      SELECT p.id, p.status, p.created_at, u.name, u.email, m.npm, m.prodi
      FROM pembayaran p
      JOIN users u ON u.id = p.user_id
      LEFT JOIN mahasiswa m ON m.user_id = u.id
      WHERE p.status = ?
      ORDER BY p.created_at DESC
      LIMIT 100
    ");
    $st->execute([$status]);
  }

  $rows = $st->fetchAll();
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
}

function badge(string $s): string {
  return match ($s) {
    'pending' => '<span style="padding:4px 8px;border-radius:999px;background:#fff3cd;">PENDING</span>',
    'approved' => '<span style="padding:4px 8px;border-radius:999px;background:#d1e7dd;">APPROVED</span>',
    'rejected' => '<span style="padding:4px 8px;border-radius:999px;background:#f8d7da;">REJECTED</span>',
    default => '<span style="padding:4px 8px;border-radius:999px;background:#e8f0fe;">'.$s.'</span>',
  };
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
        <h2 style="margin:0;">Verifikasi Pembayaran</h2>
        <div style="color:#666;margin-top:6px;">Filter: <b><?= e($status) ?></b></div>
      </div>
      <a class="btn" href="<?= e(url('admin/dashboard/index.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('admin/verifikasi/index.php?status=pending')) ?>">Pending</a>
        <a class="btn" href="<?= e(url('admin/verifikasi/index.php?status=approved')) ?>">Approved</a>
        <a class="btn" href="<?= e(url('admin/verifikasi/index.php?status=rejected')) ?>">Rejected</a>
        <a class="btn" href="<?= e(url('admin/verifikasi/index.php?status=all')) ?>">All</a>
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:980px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">ID</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Nama</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Email</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">NPM</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Prodi</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Status</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Tanggal</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="8" style="padding:12px;color:#666;">Tidak ada data.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$r['id'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['name']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['email']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['npm']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['prodi']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= badge((string)$r['status']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['created_at']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <a class="btn" href="<?= e(url('admin/verifikasi/detail.php?id='.(int)$r['id'])) ?>">Detail</a>
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
