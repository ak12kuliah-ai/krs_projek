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

function parse_codes(string $csv): array {
  $parts = array_map('trim', explode(',', $csv));
  $parts = array_filter($parts, fn($v) => $v !== '');
  return array_values(array_unique($parts));
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('global', 'ID draft tidak valid.', 'warning');
  redirect(url('dosen/validasi/index.php'));
}

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

// ambil draft + data mahasiswa
$st = $pdo->prepare("
  SELECT
    kd.*,
    u.name AS nama_mhs,
    u.email AS email_mhs,
    m.npm, m.prodi, m.angkatan
  FROM krs_draft kd
  JOIN users u ON u.id = kd.user_id
  LEFT JOIN mahasiswa m ON m.user_id = kd.user_id
  WHERE kd.id = ?
    AND kd.semester_id = ?
    AND kd.dosen_wali_id = ?
  LIMIT 1
");
$st->execute([$id, $semesterId, $dosenId]);
$draft = $st->fetch();

if (!$draft) {
  flash_set('global', 'Draft tidak ditemukan / bukan untuk dosen wali kamu.', 'warning');
  redirect(url('dosen/validasi/index.php'));
}

$status = strtolower((string)$draft['status']);
$csv = (string)($draft['kode_kelas_text'] ?? '');
$codes = parse_codes($csv);

// POST approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('dosen/validasi/detail.php?id='.$id));
  }

  $action = strtolower(trim((string)($_POST['action'] ?? '')));

  if ($status !== 'submitted') {
    flash_set('global', 'KRS sudah diproses. Status sekarang: '.$status, 'warning');
    redirect(url('dosen/validasi/detail.php?id='.$id));
  }

  if ($action === 'approve') {
    try {
        $pdo->beginTransaction();

        // 1. Kunci draft agar tidak dobel proses (Locking)
        $st = $pdo->prepare("
            SELECT id, status, kode_kelas_text
            FROM krs_draft
            WHERE id=? AND semester_id=? AND dosen_wali_id=?
            FOR UPDATE
        ");
        $st->execute([$id, $semesterId, $dosenId]);
        $d = $st->fetch();

        if (!$d) {
            $pdo->rollBack();
            flash_set('global', 'Draft tidak ditemukan.', 'warning');
            redirect(url('dosen/validasi/index.php'));
        }

        if (strtolower((string)$d['status']) !== 'submitted') {
            $pdo->rollBack();
            flash_set('global', 'Tidak bisa approve. Status sekarang: '.$d['status'], 'warning');
            redirect(url('dosen/validasi/detail.php?id='.$id));
        }

        $codes = parse_codes((string)($d['kode_kelas_text'] ?? ''));
        if (!$codes) {
            $pdo->rollBack();
            flash_set('global', 'Draft kosong, tidak bisa approve.', 'warning');
            redirect(url('dosen/validasi/detail.php?id='.$id));
        }

        // 2. Lock baris jadwal_kelas yang terpilih untuk validasi kuota
        $ph = implode(',', array_fill(0, count($codes), '?'));
        $params = array_merge([$semesterId], $codes);

        $st = $pdo->prepare("
            SELECT id, kode_kelas, kuota
            FROM jadwal_kelas
            WHERE semester_id=? AND kode_kelas IN ($ph)
            FOR UPDATE
        ");
        $st->execute($params);
        $kelasRows = $st->fetchAll();

        // 3. Validasi: Semua kode harus ditemukan
        if (count($kelasRows) !== count($codes)) {
            $pdo->rollBack();
            flash_set('global', 'Ada kode kelas yang tidak ditemukan di jadwal_kelas semester aktif.', 'danger');
            redirect(url('dosen/validasi/detail.php?id='.$id));
        }

        // 4. Validasi: Cek kuota cukup
        foreach ($kelasRows as $kr) {
            if ((int)$kr['kuota'] <= 0) {
                $pdo->rollBack();
                flash_set('global', 'Kuota habis untuk kelas: '.$kr['kode_kelas'], 'danger');
                redirect(url('dosen/validasi/detail.php?id='.$id));
            }
        }

        // 5. Eksekusi: Kurangi kuota tiap kelas
        $st = $pdo->prepare("
            UPDATE jadwal_kelas
            SET kuota = kuota - 1
            WHERE semester_id=? AND kode_kelas=?
        ");
        foreach ($codes as $code) {
            $st->execute([$semesterId, $code]);
        }

        // 6. Update Status KRS
        $st = $pdo->prepare("UPDATE krs_draft SET status='approved' WHERE id=? AND dosen_wali_id=?");
        $st->execute([$id, $dosenId]);

        $pdo->commit();
        flash_set('global', 'KRS di-approve dan kuota kelas berkurang.', 'success');
        redirect(url('dosen/validasi/detail.php?id='.$id));

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal approve.', 'danger');
        redirect(url('dosen/validasi/detail.php?id='.$id));
    }
}

  if ($action === 'reject') {
    $st = $pdo->prepare("UPDATE krs_draft SET status='rejected' WHERE id=? AND dosen_wali_id=?");
    $st->execute([$id, $dosenId]);
    flash_set('global', 'KRS di-reject (mahasiswa bisa revisi).', 'warning');
    redirect(url('dosen/validasi/detail.php?id='.$id));
  }

  flash_set('global', 'Aksi tidak valid.', 'warning');
  redirect(url('dosen/validasi/detail.php?id='.$id));
}

// detail jadwal dari kode_kelas_text
$rows = [];
if ($codes) {
  $ph = implode(',', array_fill(0, count($codes), '?'));
  $params = array_merge([$semesterId], $codes);

  $st = $pdo->prepare("
    SELECT semester_ke, mata_kuliah, kode_kelas, sks, dosen, hari, jam_mulai, jam_selesai, ruangan
    FROM jadwal_kelas
    WHERE semester_id=? AND kode_kelas IN ($ph)
    ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai, mata_kuliah
  ");
  $st->execute($params);
  $rows = $st->fetchAll();
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
        <h2 style="margin:0;">Detail Validasi KRS</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
          · Status: <b><?= e($status) ?></b>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('dosen/index.php')) ?>">Dashboard</a>
        <a class="btn" href="<?= e(url('dosen/validasi/index.php')) ?>">List Validasi</a>
      </div>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Mahasiswa</h3>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div><div style="font-size:12px;color:#666;">Nama</div><div><b><?= e((string)$draft['nama_mhs']) ?></b></div></div>
        <div><div style="font-size:12px;color:#666;">Email</div><div><?= e((string)$draft['email_mhs']) ?></div></div>
        <div><div style="font-size:12px;color:#666;">NPM</div><div><?= e((string)($draft['npm'] ?? '-')) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Prodi</div><div><?= e((string)($draft['prodi'] ?? '-')) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Angkatan</div><div><?= e((string)($draft['angkatan'] ?? '-')) ?></div></div>
        <div><div style="font-size:12px;color:#666;">Total SKS</div><div><b><?= (int)$draft['total_sks'] ?></b></div></div>
      </div>

      <div style="margin-top:12px;">
        <div style="font-size:12px;color:#666;">Kode Kelas (text)</div>
        <div style="font-family:ui-monospace,Consolas,monospace;background:#f7f7f7;border:1px solid #eee;padding:10px;border-radius:10px;">
          <?= e((string)($draft['kode_kelas_text'] ?? '-')) ?>
        </div>
      </div>

      <?php if ($status === 'submitted'): ?>
        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <form method="post" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="action" value="approve">
            <button class="btn" type="submit" onclick="return confirm('Approve KRS ini?')">Approve</button>
          </form>

          <form method="post" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="action" value="reject">
            <button class="btn" type="submit" onclick="return confirm('Reject KRS ini? Mahasiswa bisa revisi.')">Reject</button>
          </form>
        </div>
      <?php else: ?>
        <div style="margin-top:12px;color:#666;">KRS sudah diproses (status: <b><?= e($status) ?></b>).</div>
      <?php endif; ?>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Detail Jadwal (jadwal_kelas)</h3>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:1100px;">
          <thead>
            <tr>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Sem</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Hari</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Jam</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Mata Kuliah</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kode</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">SKS</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen</th>
              <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Ruangan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="8" style="padding:12px;color:#666;">Tidak ada data jadwal.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$r['semester_ke'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['hari']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['jam_mulai']) ?>-<?= e((string)$r['jam_selesai']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['mata_kuliah']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= e((string)$r['kode_kelas']) ?></b></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$r['sks'] ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['dosen']) ?></td>
                  <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$r['ruangan']) ?></td>
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
