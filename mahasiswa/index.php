<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('mahasiswa');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$u = auth_user();
$userId = (int)($u['id'] ?? 0);

function badge(string $s): string {
  $s = strtolower($s);
  return match ($s) {
    'pending' => '<span style="padding:4px 8px;border-radius:999px;background:#fff3cd;">PENDING</span>',
    'approved' => '<span style="padding:4px 8px;border-radius:999px;background:#d1e7dd;">APPROVED</span>',
    'rejected' => '<span style="padding:4px 8px;border-radius:999px;background:#f8d7da;">REJECTED</span>',
    default => '<span style="padding:4px 8px;border-radius:999px;background:#e8f0fe;">'.e($s).'</span>',
  };
}

$profile = [];
$activeSemester = null;
$latestPayment = null;

try {
  // profil mahasiswa + dosen wali
  $st = $pdo->prepare("
    SELECT
      m.npm, m.prodi, m.semester_aktif, m.angkatan,
      uw.name AS dosen_wali_nama
    FROM mahasiswa m
    LEFT JOIN dosen dw ON dw.id = m.dosen_wali_id
    LEFT JOIN users uw ON uw.id = dw.user_id
    WHERE m.user_id = ?
    LIMIT 1
  ");
  $st->execute([$userId]);
  $profile = $st->fetch() ?: [];

  // semester aktif sistem
  $activeSemester = get_active_semester($pdo);

  // pembayaran terakhir
  $st = $pdo->prepare("
    SELECT id, status, created_at
    FROM pembayaran
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $st->execute([$userId]);
  $latestPayment = $st->fetch() ?: null;

} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
}
?>

<?php include __DIR__ . '/../partials/header.php'; ?>
<?php include __DIR__ . '/../partials/navbar.php'; ?>
<?php include __DIR__ . '/../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Dashboard Mahasiswa</h2>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Profil</h3>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div><div style="font-size:12px;color:#666;">Nama</div><div><b><?= e($u['name'] ?? '-') ?></b></div></div>
        <div><div style="font-size:12px;color:#666;">Email</div><div><?= e($u['email'] ?? '-') ?></div></div>

        <div><div style="font-size:12px;color:#666;">NPM</div><div><?= e((string)($profile['npm'] ?? '-')) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Prodi</div><div><?= e((string)($profile['prodi'] ?? '-')) ?></div></div>

        <div><div style="font-size:12px;color:#666;">Angkatan</div><div><?= e((string)($profile['angkatan'] ?? '-')) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Semester Aktif (otomatis)</div><div><b><?= e((string)($profile['semester_aktif'] ?? '-')) ?></b></div></div>

        <div style="grid-column:1 / -1;">
          <div style="font-size:12px;color:#666;">Dosen Wali</div>
          <div><?= $profile['dosen_wali_nama'] ? '<b>'.e($profile['dosen_wali_nama']).'</b>' : '<span style="color:#666;">Belum diset oleh admin</span>' ?></div>
        </div>
      </div>
    </section>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px;">
      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <h3 style="margin:0 0 10px;">Semester Aktif Sistem</h3>
        <div style="margin-top:8px;font-size:18px;font-weight:700;">
          <?= $activeSemester ? e($activeSemester['nama'].' - '.$activeSemester['periode']) : '-' ?>
        </div>
      </section>

      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <h3 style="margin:0 0 10px;">Status Pembayaran Terakhir</h3>
        <?php if (!$latestPayment): ?>
          <div style="color:#666;">Belum ada upload bukti pembayaran.</div>
        <?php else: ?>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?= badge((string)$latestPayment['status']) ?>
            <span style="color:#666;font-size:12px;">(<?= e((string)$latestPayment['created_at']) ?>)</span>
          </div>
        <?php endif; ?>

        <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
          <a class="btn" href="<?= e(url('mahasiswa/pembayaran/upload.php')) ?>">Upload / Riwayat</a>
          <a class="btn" href="<?= e(url('mahasiswa/jadwal/index.php')) ?>">Lihat Jadwal</a>
        </div>
      </section>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
