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

const MAX_SKS = 24;

function parse_codes(string $csv): array {
  $parts = array_map('trim', explode(',', $csv));
  $parts = array_filter($parts, fn($v) => $v !== '');
  return array_values(array_unique($parts));
}
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
  flash_set('global', 'Belum ada semester aktif sistem.', 'warning');
  redirect(url('mahasiswa/index.php'));
}
$semesterId = (int)$active['id'];

// dosen wali mahasiswa
$st = $pdo->prepare("
  SELECT m.dosen_wali_id, uw.name AS dosen_wali_nama
  FROM mahasiswa m
  LEFT JOIN dosen dw ON dw.id = m.dosen_wali_id
  LEFT JOIN users uw ON uw.id = dw.user_id
  WHERE m.user_id=? LIMIT 1
");
$st->execute([$userId]);
$mhs = $st->fetch() ?: [];
$dosenWaliId = (int)($mhs['dosen_wali_id'] ?? 0);
$dosenWaliNama = (string)($mhs['dosen_wali_nama'] ?? '-');

// draft
$st = $pdo->prepare("SELECT * FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
$st->execute([$userId, $semesterId]);
$draft = $st->fetch();

if (!$draft) {
  flash_set('global', 'Draft belum dibuat.', 'warning');
  redirect(url('mahasiswa/krs/add.php'));
}

$status = strtolower((string)$draft['status']);
$csv = (string)($draft['kode_kelas_text'] ?? '');
$totalStored = (int)($draft['total_sks'] ?? 0);
$codes = parse_codes($csv);

// HANDLE submit / withdraw (buttons cuma di sini)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('mahasiswa/krs/detail.php'));
  }

  $action = strtolower(trim((string)($_POST['action'] ?? '')));

  if ($action === 'submit') {
    if (!in_array($status, ['draft','rejected'], true)) {
      flash_set('global', 'Tidak bisa ajukan. Status: '.$status, 'warning');
      redirect(url('mahasiswa/krs/detail.php'));
    }
    if ($csv === '') {
      flash_set('global', 'Draft masih kosong.', 'warning');
      redirect(url('mahasiswa/krs/detail.php'));
    }
    if ($dosenWaliId <= 0) {
      flash_set('global', 'Dosen wali belum diset admin.', 'warning');
      redirect(url('mahasiswa/krs/detail.php'));
    }
    if ($totalStored > MAX_SKS) {
      flash_set('global', 'Total SKS melebihi '.MAX_SKS.'.', 'warning');
      redirect(url('mahasiswa/krs/detail.php'));
    }

    $st = $pdo->prepare("UPDATE krs_draft SET status='submitted', dosen_wali_id=?, submitted_at=NOW() WHERE id=?");
    $st->execute([$dosenWaliId, (int)$draft['id']]);

    flash_set('global', 'Berhasil diajukan (submitted).', 'success');
    redirect(url('mahasiswa/krs/detail.php'));
  }

  if ($action === 'withdraw') {
    if (!in_array($status, ['submitted', 'approved'], true)) {
        flash_set('global', 'Tarik ajukan hanya bisa saat status submitted/approved.', 'warning');
        redirect(url('mahasiswa/krs/detail.php'));
    }

    try {
        $pdo->beginTransaction();

        // 1. Lock Draft KRS
        $st = $pdo->prepare("SELECT id, status, kode_kelas_text FROM krs_draft WHERE id=? AND user_id=? AND semester_id=? FOR UPDATE");
        $st->execute([(int)$draft['id'], $userId, $semesterId]);
        $d = $st->fetch();

        if (!$d) {
            $pdo->rollBack();
            flash_set('global', 'Draft tidak ditemukan.', 'warning');
            redirect(url('mahasiswa/krs/detail.php'));
        }

        $curStatus = strtolower((string)$d['status']);
        $codes = parse_codes((string)($d['kode_kelas_text'] ?? ''));

        // 2. Jika status approved, kembalikan kuota ke jadwal_kelas
        if ($curStatus === 'approved' && $codes) {
            $ph = implode(',', array_fill(0, count($codes), '?'));
            $params = array_merge([$semesterId], $codes);

            // Lock baris kelas agar sinkron
            $st = $pdo->prepare("
                SELECT kode_kelas
                FROM jadwal_kelas
                WHERE semester_id=? AND kode_kelas IN ($ph)
                FOR UPDATE
            ");
            $st->execute($params);

            // Tambah kembali kuota (+1)
            $stUp = $pdo->prepare("UPDATE jadwal_kelas SET kuota = kuota + 1 WHERE semester_id=? AND kode_kelas=?");
            foreach ($codes as $code) {
                $stUp->execute([$semesterId, $code]);
            }
        }

        // 3. Reset status menjadi draft kembali
        $st = $pdo->prepare("UPDATE krs_draft SET status='draft', submitted_at=NULL WHERE id=?");
        $st->execute([(int)$draft['id']]);

        $pdo->commit();
        flash_set('global', 'Tarik ajukan berhasil. Status kembali ke draft.', 'success');
        redirect(url('mahasiswa/krs/detail.php'));

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal tarik ajukan.', 'danger');
        redirect(url('mahasiswa/krs/detail.php'));
    }
}

  flash_set('global', 'Aksi tidak dikenal.', 'warning');
  redirect(url('mahasiswa/krs/detail.php'));
}

// tampilkan detail jadwal dari kode_kelas
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
        <h2 style="margin:0;">Detail Draft KRS</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
          · Status: <?= badge_status($status) ?>
          · Dosen wali: <b><?= e($dosenWaliNama) ?></b>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('mahasiswa/krs/index.php')) ?>">Summary</a>
        <a class="btn" href="<?= e(url('mahasiswa/krs/add.php')) ?>">Kelola Draft</a>
        <button class="btn" onclick="window.print()">Print</button>
      </div>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="font-size:12px;color:#666;">Kode Kelas (text)</div>
      <div style="font-family:ui-monospace,Consolas,monospace;background:#f7f7f7;border:1px solid #eee;padding:10px;border-radius:10px;">
        <?= e($csv !== '' ? $csv : '-') ?>
      </div>

      <div style="margin-top:10px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
        <div>
          <div style="font-size:12px;color:#666;">Total SKS</div>
          <div style="font-size:20px;font-weight:700;"><?= (int)$totalStored ?> / <?= (int)MAX_SKS ?></div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php if (in_array($status, ['draft','rejected'], true)): ?>
            <form method="post" style="margin:0;">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="submit">
              <button class="btn" type="submit" onclick="return confirm('Ajukan draft ke dosen wali?')">Ajukan</button>
            </form>
            <?php elseif (in_array($status, ['submitted','approved'], true)): ?>
              <form method="post" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="withdraw">
                <button class="btn" type="submit"
                        onclick="return confirm('Tarik ajukan? Status akan kembali ke DRAFT dan dosen perlu approve lagi.')">
                  Tarik Ajukan
                </button>
              </form>
            <?php endif; ?>
        </div>
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <h3 style="margin:0 0 10px;">Detail dari jadwal_kelas (berdasarkan kode_kelas)</h3>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:1000px;">
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
              <tr><td colspan="8" style="padding:12px;color:#666;">Belum ada data.</td></tr>
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
