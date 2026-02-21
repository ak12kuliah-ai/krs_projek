<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('global', 'ID pembayaran tidak valid.', 'warning');
  redirect(url('admin/verifikasi/index.php'));
}

function is_safe_upload_path(string $path): bool {
  $path = ltrim($path, '/');
  // hanya izinkan dari assets/uploads/
  if (!str_starts_with($path, 'assets/uploads/')) return false;
  // blok traversal sederhana
  if (str_contains($path, '..')) return false;
  return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/verifikasi/detail.php?id='.$id));
  }

  $action = strtolower(trim($_POST['action'] ?? ''));
  if (!in_array($action, ['approve', 'reject'], true)) {
    flash_set('global', 'Aksi tidak valid.', 'warning');
    redirect(url('admin/verifikasi/detail.php?id='.$id));
  }

  $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

  try {
    // update status
    $st = $pdo->prepare("UPDATE pembayaran SET status=? WHERE id=?");
    $st->execute([$newStatus, $id]);

    flash_set('global', 'Status pembayaran berhasil diubah menjadi: '.$newStatus, 'success');
    redirect(url('admin/verifikasi/detail.php?id='.$id));
  } catch (Throwable $e) {
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal update status.', 'danger');
    redirect(url('admin/verifikasi/detail.php?id='.$id));
  }
}

// fetch detail
$row = null;
try {
  $st = $pdo->prepare("
    SELECT p.id, p.user_id, p.file_path, p.status, p.created_at,
           u.name, u.email, m.npm, m.prodi
    FROM pembayaran p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN mahasiswa m ON m.user_id = u.id
    WHERE p.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $row = $st->fetch();

  if (!$row) {
    flash_set('global', 'Data pembayaran tidak ditemukan.', 'danger');
    redirect(url('admin/verifikasi/index.php'));
  }
} catch (Throwable $e) {
  flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Terjadi kesalahan.', 'danger');
  redirect(url('admin/verifikasi/index.php'));
}

$filePath = (string)($row['file_path'] ?? '');
$canOpenFile = ($filePath !== '' && is_safe_upload_path($filePath));
$fileUrl = $canOpenFile ? url(ltrim($filePath, '/')) : '';
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
      <div>
        <h2 style="margin:0;">Detail Verifikasi</h2>
        <div style="color:#666;margin-top:6px;">ID Pembayaran: <b><?= (int)$row['id'] ?></b></div>
      </div>
      <a class="btn" href="<?= e(url('admin/verifikasi/index.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Data Mahasiswa</h3>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div><div style="font-size:12px;color:#666;">Nama</div><div><b><?= e($row['name']) ?></b></div></div>
        <div><div style="font-size:12px;color:#666;">Email</div><div><?= e($row['email']) ?></div></div>
        <div><div style="font-size:12px;color:#666;">NPM</div><div><?= e((string)$row['npm']) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Prodi</div><div><?= e((string)$row['prodi']) ?></div></div>
      </div>

      <div style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div><div style="font-size:12px;color:#666;">Status</div><div><b><?= e((string)$row['status']) ?></b></div></div>
        <div><div style="font-size:12px;color:#666;">Tanggal Upload</div><div><?= e((string)$row['created_at']) ?></div></div>
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Bukti Pembayaran</h3>

      <?php if (!$canOpenFile): ?>
        <div style="color:#666;">
          Bukti pembayaran tidak ditemukan / path tidak aman.<br>
          file_path di DB: <code><?= e($filePath) ?></code>
        </div>
      <?php else: ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <a class="btn" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">Buka Bukti</a>
          <span style="color:#666;font-size:12px;">Path: <code><?= e($filePath) ?></code></span>
        </div>

        <div style="margin-top:12px;">
          <?php
            $lower = strtolower($filePath);
            $isImg = str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg') || str_ends_with($lower, '.png') || str_ends_with($lower, '.webp');
          ?>
          <?php if ($isImg): ?>
            <img src="<?= e($fileUrl) ?>" alt="Bukti Pembayaran" style="max-width:100%;border:1px solid #eee;border-radius:12px;">
          <?php else: ?>
            <div style="color:#666;">Preview hanya untuk gambar. Untuk PDF/dll klik tombol “Buka Bukti”.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Aksi</h3>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if ((string)$row['status'] !== 'approved'): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button class="btn" type="submit" onclick="return confirm('Approve pembayaran ini?')">Approve</button>
          </form>
        <?php endif; ?>

        <?php if ((string)$row['status'] !== 'rejected'): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <button class="btn" type="submit" onclick="return confirm('Reject pembayaran ini?')">Reject</button>
          </form>
        <?php endif; ?>
      </div>

      <div style="color:#666;font-size:12px;margin-top:10px;">
        Approve/Reject akan mengubah <code>pembayaran.status</code> menjadi <code>approved</code> atau <code>rejected</code>.
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
