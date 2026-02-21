<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token  = $_POST['_csrf'] ?? '';

  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/semester/index.php'));
  }

  try {
    if ($action === 'add') {
      $nama = trim($_POST['nama'] ?? '');
      $periode = strtolower(trim($_POST['periode'] ?? ''));

      if ($nama === '') {
        flash_set('global', 'Nama tahun ajaran wajib diisi (contoh 2025/2026).', 'warning');
        redirect(url('admin/semester/index.php'));
      }
      if (!in_array($periode, ['ganjil','genap'], true)) {
        flash_set('global', 'Periode harus ganjil atau genap.', 'warning');
        redirect(url('admin/semester/index.php'));
      }

      $stmt = $pdo->prepare("INSERT INTO semester (nama, periode, is_active) VALUES (?, ?, 0)");
      $stmt->execute([$nama, $periode]);

      flash_set('global', 'Semester berhasil ditambahkan.', 'success');
      redirect(url('admin/semester/index.php'));
    }

    if ($action === 'open') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        flash_set('global', 'ID semester tidak valid.', 'warning');
        redirect(url('admin/semester/index.php'));
      }

      $pdo->beginTransaction();

      // set hanya 1 aktif
      $pdo->exec("UPDATE semester SET is_active = 0");
      $stmt = $pdo->prepare("UPDATE semester SET is_active = 1 WHERE id = ?");
      $stmt->execute([$id]);

      // ambil semester aktif yang baru
      $stmt = $pdo->prepare("SELECT id, nama, periode FROM semester WHERE id=? LIMIT 1");
      $stmt->execute([$id]);
      $activeNow = $stmt->fetch();

      if (!$activeNow) {
        $pdo->rollBack();
        flash_set('global', 'Semester tidak ditemukan.', 'danger');
        redirect(url('admin/semester/index.php'));
      }

      // OPSI B: sync semester_aktif semua mahasiswa
      sync_semester_aktif_semua_mahasiswa($pdo, (string)$activeNow['nama'], (string)$activeNow['periode']);

      $pdo->commit();

      flash_set('global', 'Semester aktif di-set. Semester mahasiswa (semester_aktif) telah dihitung otomatis dari angkatan.', 'success');
      redirect(url('admin/semester/index.php'));
    }

    if ($action === 'close') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        flash_set('global', 'ID semester tidak valid.', 'warning');
        redirect(url('admin/semester/index.php'));
      }

      $stmt = $pdo->prepare("UPDATE semester SET is_active = 0 WHERE id = ?");
      $stmt->execute([$id]);

      flash_set('global', 'Semester berhasil ditutup (nonaktif).', 'success');
      redirect(url('admin/semester/index.php'));
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('global', APP_DEBUG ? ('DB error: ' . $e->getMessage()) : 'Terjadi kesalahan.', 'danger');
    redirect(url('admin/semester/index.php'));
  }
}

// GET list semester + active
$semesters = [];
$active = null;
try {
  $semesters = $pdo->query("SELECT id, nama, periode, is_active, created_at FROM semester ORDER BY id DESC")->fetchAll();
  $active = $pdo->query("SELECT id, nama, periode FROM semester WHERE is_active=1 LIMIT 1")->fetch();
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
    <h2 style="margin-top:0;">Kelola Semester</h2>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-bottom:14px;">
      <div style="font-size:12px;color:#666;">Semester aktif saat ini</div>
      <div style="font-size:18px;font-weight:700;">
        <?= $active ? e($active['nama'].' - '.$active['periode']) : '-' ?>
      </div>
      <div style="color:#666;font-size:12px;margin-top:6px;">
        Saat semester aktif di-set, sistem otomatis menghitung <code>mahasiswa.semester_aktif</code> dari <code>angkatan</code>.
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-bottom:14px;">
      <h3 style="margin:0 0 10px;">Tambah Semester (Tahun Ajaran + Periode)</h3>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">

        <div style="min-width:240px;">
          <label>Tahun Ajaran</label>
          <input name="nama" placeholder="contoh: 2025/2026" required
                 style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
        </div>

        <div style="min-width:180px;">
          <label>Periode</label>
          <select name="periode" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
            <option value="ganjil">ganjil</option>
            <option value="genap">genap</option>
          </select>
        </div>

        <button class="btn" type="submit">Tambah</button>
      </form>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
      <h3 style="margin:0 0 10px;">Daftar Semester</h3>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:760px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">ID</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Tahun Ajaran</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Periode</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Status</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$semesters): ?>
              <tr><td colspan="5" style="padding:12px;color:#666;">Belum ada data semester.</td></tr>
            <?php else: ?>
              <?php foreach ($semesters as $s): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$s['id'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($s['nama']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($s['periode']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <?= ((int)$s['is_active']===1) ? '<b style="color:green;">AKTIF</b>' : '<span style="color:#666;">Nonaktif</span>' ?>
                  </td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ((int)$s['is_active']===1): ?>
                      <form method="post" style="margin:0;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn" type="submit">Tutup</button>
                      </form>
                    <?php else: ?>
                      <form method="post" style="margin:0;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="open">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn" type="submit">Set Aktif</button>
                      </form>
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
