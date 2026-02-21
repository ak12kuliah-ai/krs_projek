<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('mahasiswa');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$u = auth_user();
$userId = (int)($u['id'] ?? 0);

function badge_status(string $s): string {
  $s = strtolower($s);
  return match ($s) {
    'draft' => '<span style="padding:4px 8px;border-radius:999px;background:#e8f0fe;">DRAFT</span>',
    'submitted' => '<span style="padding:4px 8px;border-radius:999px;background:#fff3cd;">SUBMITTED</span>',
    'approved' => '<span style="padding:4px 8px;border-radius:999px;background:#d1e7dd;">APPROVED</span>',
    'rejected' => '<span style="padding:4px 8px;border-radius:999px;background:#f8d7da;">REJECTED</span>',
    default => '<span style="padding:4px 8px;border-radius:999px;background:#eee;">'.e($s).'</span>',
  };
}

$active = get_active_semester($pdo);
if (!$active) {
  flash_set('global', 'Belum ada semester aktif sistem. Minta admin set semester aktif dulu.', 'warning');
  redirect(url('mahasiswa/index.php'));
}
$semesterId = (int)$active['id'];

$profile = [];
$draft = null;

try {
  // semester mahasiswa + dosen wali
  $st = $pdo->prepare("
    SELECT
      m.semester_aktif,
      uw.name AS dosen_wali_nama
    FROM mahasiswa m
    LEFT JOIN dosen dw ON dw.id = m.dosen_wali_id
    LEFT JOIN users uw ON uw.id = dw.user_id
    WHERE m.user_id=?
    LIMIT 1
  ");
  $st->execute([$userId]);
  $profile = $st->fetch() ?: [];

  // draft KRS untuk semester aktif sistem
  $st = $pdo->prepare("SELECT id, total_sks, status, created_at, submitted_at FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
  $st->execute([$userId, $semesterId]);
  $draft = $st->fetch() ?: null;

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
        <h2 style="margin:0;">KRS (Summary)</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif sistem: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
        </div>
      </div>
      <a class="btn" href="<?= e(url('mahasiswa/index.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Semester Mahasiswa</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Semester Aktif Sistem</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Total SKS</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Status</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen Wali</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= (int)($profile['semester_aktif'] ?? 0) ?></b></td>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($active['nama'].' - '.$active['periode']) ?></td>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= (int)($draft['total_sks'] ?? 0) ?></b> / 24</td>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= $draft ? badge_status((string)$draft['status']) : '<span style="color:#666;">-</span>' ?></td>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($profile['dosen_wali_nama'] ?? '-')) ?></td>
              <td style="padding:10px;border-bottom:1px solid #f3f3f3;display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn" href="<?= e(url('mahasiswa/krs/add.php')) ?>">Kelola Draft</a>
                <a class="btn" href="<?= e(url('mahasiswa/krs/detail.php')) ?>">Detail</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style="color:#666;font-size:12px;margin-top:10px;">
        Index ini hanya summary. Untuk memilih mata kuliah/kode kelas, buka <b>Kelola Draft</b>.
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
