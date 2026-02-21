<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

// Default value biar aman kalau tabel belum ada
$activeSemester = '-';
$userCounts = ['admin' => 0, 'mahasiswa' => 0, 'dosen' => 0, 'total' => 0];
$pendingPayments = [];

try {
  // Semester aktif
  $stmt = $pdo->query("SELECT nama FROM semester WHERE is_active = 1 LIMIT 1");
  $row = $stmt->fetch();
  if ($row && !empty($row['nama'])) $activeSemester = $row['nama'];

  // Count users per role
  $stmt = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
  foreach ($stmt->fetchAll() as $r) {
    $role = (string)$r['role'];
    $cnt  = (int)$r['c'];
    if (isset($userCounts[$role])) $userCounts[$role] = $cnt;
    $userCounts['total'] += $cnt;
  }

  // Pembayaran pending (ambil 5 terakhir)
  $stmt = $pdo->prepare("
    SELECT p.id, u.name, u.email, p.status, p.created_at
    FROM pembayaran p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
    LIMIT 5
  ");
  $stmt->execute();
  $pendingPayments = $stmt->fetchAll();
} catch (Throwable $e) {
  // Kalau schema belum lengkap, dashboard tetap tampil.
  if (APP_DEBUG) {
    flash_set('global', 'DB error: ' . $e->getMessage(), 'warning');
  }
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Admin Dashboard</h2>
    <p style="margin-top:6px;">Semester aktif: <b><?= e($activeSemester) ?></b></p>

    <!-- Cards ringkas -->
    <div style="display:grid;grid-template-columns:repeat(4, minmax(0,1fr));gap:12px;margin:14px 0;">
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Total Users</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$userCounts['total'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Admin</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$userCounts['admin'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Mahasiswa</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$userCounts['mahasiswa'] ?></div>
      </div>
      <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Dosen</div>
        <div style="font-size:22px;font-weight:700;"><?= (int)$userCounts['dosen'] ?></div>
      </div>
    </div>

    <!-- tombol cepat -->
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 18px;">
      <a class="btn" href="<?= e(url('admin/semester/index.php')) ?>">Kelola Semester Aktif</a>
      <a class="btn" href="<?= e(url('admin/penjadwalan/index.php')) ?>">Kelola Penjadwalan</a>
      <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Kelola Users</a>
      <a class="btn" href="<?= e(url('admin/verifikasi/index.php')) ?>">Verifikasi Pembayaran</a>
    </div>

    <!-- Pembayaran pending -->
    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <h3 style="margin:0;">Pembayaran Pending (terbaru)</h3>
        <a class="btn" href="<?= e(url('admin/verifikasi/index.php')) ?>">Lihat Semua</a>
      </div>

      <div style="overflow:auto;margin-top:10px;">
        <table style="width:100%;border-collapse:collapse;min-width:640px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">ID</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Nama</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Email</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Status</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Tanggal</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$pendingPayments): ?>
              <tr>
                <td colspan="6" style="padding:12px;color:#666;">Tidak ada pembayaran pending.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($pendingPayments as $p): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$p['id'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($p['name']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($p['email']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($p['status']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$p['created_at']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <a class="btn" href="<?= e(url('admin/verifikasi/detail.php?id=' . (int)$p['id'])) ?>">Detail</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p style="margin-top:14px;color:#666;font-size:12px;">
      Catatan: tombol ☰ di navbar akan men-toggle sidebar (mobile) karena sudah ditangani oleh <code>assets/js/script.js</code>.
    </p>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
