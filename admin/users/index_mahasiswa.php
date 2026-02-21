<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

/**
 * 1) HANDLE SET DOSEN WALI (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/users/index_mahasiswa.php'));
  }

  $action = strtolower(trim((string)($_POST['action'] ?? '')));
  if ($action === 'set_wali') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $dosenWaliId = (int)($_POST['dosen_wali_id'] ?? 0); // 0 = unset

    if ($userId <= 0) {
      flash_set('global', 'User ID tidak valid.', 'warning');
      redirect(url('admin/users/index_mahasiswa.php'));
    }

    try {
      // validasi dosen_wali_id jika bukan 0
      if ($dosenWaliId !== 0) {
        $st = $pdo->prepare("SELECT id FROM dosen WHERE id=? LIMIT 1");
        $st->execute([$dosenWaliId]);
        $ok = $st->fetch();
        if (!$ok) {
          flash_set('global', 'Dosen wali tidak ditemukan.', 'warning');
          redirect(url('admin/users/index_mahasiswa.php'));
        }
      }

      $st = $pdo->prepare("UPDATE mahasiswa SET dosen_wali_id = ? WHERE user_id = ?");
      $st->execute([$dosenWaliId === 0 ? null : $dosenWaliId, $userId]);

      flash_set('global', 'Dosen wali berhasil diset.', 'success');
      redirect(url('admin/users/index_mahasiswa.php'));
    } catch (Throwable $e) {
      flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal set dosen wali.', 'danger');
      redirect(url('admin/users/index_mahasiswa.php'));
    }
  }
}

/**
 * 2) DATA DOSEN UNTUK DROPDOWN
 */
$dosenList = [];
try {
  $st = $pdo->query("
    SELECT d.id AS dosen_id, u.name AS dosen_nama, d.nidn
    FROM dosen d
    JOIN users u ON u.id = d.user_id
    ORDER BY u.name ASC
  ");
  $dosenList = $st->fetchAll();
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error dosen: '.$e->getMessage(), 'warning');
}

/**
 * 3) LIST MAHASISWA + DOSEN WALI (JOIN)
 */
$rows = [];
try {
  $st = $pdo->prepare("
    SELECT
      u.id, u.name, u.email, u.created_at,
      m.npm, m.prodi, m.semester_aktif, m.angkatan, m.dosen_wali_id,
      uw.name AS dosen_wali_nama
    FROM users u
    LEFT JOIN mahasiswa m ON m.user_id = u.id
    LEFT JOIN dosen dw ON dw.id = m.dosen_wali_id
    LEFT JOIN users uw ON uw.id = dw.user_id
    WHERE u.role='mahasiswa'
    ORDER BY u.id DESC
  ");
  $st->execute();
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
        <h2 style="margin:0;">Mahasiswa</h2>
        <div style="color:#666;margin-top:6px;">Set dosen wali dilakukan dari halaman ini.</div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Kembali</a>
        <a class="btn" href="<?= e(url('admin/users/add.php?role=mahasiswa')) ?>">+ Tambah Mahasiswa</a>
      </div>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:1200px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Nama</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Email</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">NPM</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Prodi</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Angkatan</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Sem Aktif</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen Wali</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Set Dosen Wali</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="10" style="padding:12px;color:#666;">Belum ada mahasiswa.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['name']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['email']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['npm']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['prodi']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['angkatan']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['semester_aktif']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <?= $r['dosen_wali_nama'] ? '<b>'.e($r['dosen_wali_nama']).'</b>' : '<span style="color:#666;">-</span>' ?>
                  </td>

                  <!-- SET DOSEN WALI -->
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                    <form method="post" style="margin:0;display:flex;gap:8px;align-items:center;">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="set_wali">
                      <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">

                      <select name="dosen_wali_id" style="padding:8px;border:1px solid #ddd;border-radius:10px;min-width:220px;">
                        <option value="0">-- Kosongkan --</option>
                        <?php foreach ($dosenList as $d): ?>
                          <?php $selected = ((int)$r['dosen_wali_id'] === (int)$d['dosen_id']) ? 'selected' : ''; ?>
                          <option value="<?= (int)$d['dosen_id'] ?>" <?= $selected ?>>
                            <?= e($d['dosen_nama']) ?><?= $d['nidn'] ? ' ('.$d['nidn'].')' : '' ?>
                          </option>
                        <?php endforeach; ?>
                      </select>

                      <button class="btn" type="submit">Set</button>
                    </form>
                  </td>

                  <!-- AKSI -->
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="<?= e(url('admin/users/edit.php?id='.(int)$r['id'])) ?>">Edit</a>

                    <form method="post" action="<?= e(url('admin/users/delete.php')) ?>" style="margin:0;">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="back" value="index_mahasiswa.php">
                      <button class="btn" type="submit" onclick="return confirm('Hapus mahasiswa ini?')">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div style="color:#666;font-size:12px;margin-top:10px;">
        Catatan: dosen wali disimpan di <code>mahasiswa.dosen_wali_id</code> (FK ke <code>dosen.id</code>).
      </div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
