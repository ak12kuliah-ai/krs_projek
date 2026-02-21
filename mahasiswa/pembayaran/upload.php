<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('mahasiswa');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

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

$history = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  if (!isset($_FILES['bukti']) || !is_array($_FILES['bukti'])) {
    flash_set('global', 'File bukti pembayaran wajib diupload.', 'warning');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  $f = $_FILES['bukti'];
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash_set('global', 'Upload gagal. Pastikan file dipilih dengan benar.', 'danger');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  $maxSize = 5 * 1024 * 1024; // 5MB
  if (($f['size'] ?? 0) > $maxSize) {
    flash_set('global', 'Ukuran file terlalu besar. Maks 5MB.', 'warning');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  $origName = (string)($f['name'] ?? '');
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  $allowedExt = ['jpg','jpeg','png','webp','pdf'];

  if (!in_array($ext, $allowedExt, true)) {
    flash_set('global', 'Format file tidak didukung. Gunakan JPG/PNG/WEBP/PDF.', 'warning');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  // tujuan upload
  $uploadDir = __DIR__ . '/../../assets/uploads/';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
  }

  $safeName = 'pay_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $destAbs = $uploadDir . $safeName;

  if (!move_uploaded_file((string)$f['tmp_name'], $destAbs)) {
    flash_set('global', 'Gagal menyimpan file upload.', 'danger');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }

  $filePathDb = 'assets/uploads/' . $safeName;

  try {
    $st = $pdo->prepare("
      INSERT INTO pembayaran (user_id, file_path, status)
      VALUES (?, ?, 'pending')
    ");
    $st->execute([$userId, $filePathDb]);

    flash_set('global', 'Bukti pembayaran berhasil diupload. Status: pending.', 'success');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  } catch (Throwable $e) {
    // kalau DB gagal, hapus file agar tidak nyampah
    @unlink($destAbs);
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal menyimpan data pembayaran.', 'danger');
    redirect(url('mahasiswa/pembayaran/upload.php'));
  }
}

// ambil riwayat pembayaran
try {
  $st = $pdo->prepare("
    SELECT id, file_path, status, created_at
    FROM pembayaran
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
  ");
  $st->execute([$userId]);
  $history = $st->fetchAll();
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
        <h2 style="margin:0;">Pembayaran</h2>
        <div style="color:#666;margin-top:6px;">Upload bukti pembayaran (status awal: pending).</div>
      </div>
      <a class="btn" href="<?= e(url('mahasiswa/index.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;max-width:900px;">
      <h3 style="margin:0 0 10px;">Upload Bukti</h3>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div style="display:grid;grid-template-columns:1fr;gap:10px;">
          <div>
            <label>File Bukti (JPG/PNG/WEBP/PDF, max 5MB)</label>
            <input type="file" name="bukti" required
                   accept=".jpg,.jpeg,.png,.webp,.pdf"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;background:#fff;">
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn" type="submit">Upload</button>
        </div>
      </form>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Riwayat Pembayaran</h3>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">ID</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Status</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Tanggal</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Bukti</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$history): ?>
              <tr><td colspan="4" style="padding:12px;color:#666;">Belum ada riwayat.</td></tr>
            <?php else: ?>
              <?php foreach ($history as $h): ?>
                <?php
                  $path = (string)$h['file_path'];
                  $safe = (str_starts_with($path, 'assets/uploads/') && !str_contains($path, '..'));
                  $link = $safe ? url($path) : '#';
                ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$h['id'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= badge((string)$h['status']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$h['created_at']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <?php if ($safe): ?>
                      <a class="btn" href="<?= e($link) ?>" target="_blank" rel="noopener">Buka</a>
                    <?php else: ?>
                      <span style="color:#666;">-</span>
                    <?php endif; ?>
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
