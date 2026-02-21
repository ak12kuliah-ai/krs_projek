<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('dosen');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$u = auth_user();
$userId = (int)($u['id'] ?? 0);

// dosen.id
$st = $pdo->prepare("SELECT id FROM dosen WHERE user_id=? LIMIT 1");
$st->execute([$userId]);
$dosen = $st->fetch();
if (!$dosen) {
  flash_set('global', 'Profil dosen tidak ditemukan.', 'danger');
  redirect(url('dosen/index.php'));
}
$dosenId = (int)$dosen['id'];

$active = get_active_semester($pdo);
if (!$active) {
  flash_set('global', 'Belum ada semester aktif sistem.', 'warning');
  redirect(url('dosen/index.php'));
}
$semesterId = (int)$active['id'];

$rows = [];
try {
  $st = $pdo->prepare("
    SELECT
      kd.id AS draft_id,
      kd.total_sks,
      kd.submitted_at,
      u.name AS nama_mhs,
      u.email AS email_mhs,
      m.npm,
      m.prodi,
      m.angkatan
    FROM krs_draft kd
    JOIN users u ON u.id = kd.user_id
    LEFT JOIN mahasiswa m ON m.user_id = kd.user_id
    WHERE kd.semester_id = ?
      AND kd.dosen_wali_id = ?
      AND kd.status = 'submitted'
    ORDER BY kd.submitted_at DESC, kd.id DESC
  ");
  $st->execute([$semesterId, $dosenId]);
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
        <h2 style="margin:0;">Validasi KRS</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
        </div>
      </div>
      <a class="btn" href="<?= e(url('dosen/index.php')) ?>">Dashboard</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Pengajuan (Submitted)</h3>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:1100px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Waktu Ajukan</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Mahasiswa</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">NPM</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Prodi</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Angkatan</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Total SKS</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" style="padding:12px;color:#666;">Belum ada pengajuan.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['submitted_at'] ?? '-')) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <b><?= e((string)$r['nama_mhs']) ?></b><br>
                    <span style="color:#666;font-size:12px;"><?= e((string)$r['email_mhs']) ?></span>
                  </td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['npm'] ?? '-')) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['prodi'] ?? '-')) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['angkatan'] ?? '-')) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= (int)$r['total_sks'] ?></b></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <a class="btn" href="<?= e(url('dosen/validasi/detail.php?id='.(int)$r['draft_id'])) ?>">Lihat</a>
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
