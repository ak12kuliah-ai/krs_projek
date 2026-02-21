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
function join_codes(array $codes): string {
  $codes = array_values(array_unique(array_filter(array_map('trim', $codes), fn($v)=>$v!=='')));
  return implode(',', $codes);
}
function overlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool {
  return ($aStart < $bEnd) && ($aEnd > $bStart);
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
$periode = strtolower(trim((string)$active['periode']));
$allowedSem = ($periode === 'ganjil') ? [1,3,5,7] : [2,4,6,8];

$filterSem = (int)($_GET['sem'] ?? 0);
if ($filterSem !== 0 && !in_array($filterSem, $allowedSem, true)) $filterSem = 0;

// pastikan draft ada (kalau belum, buat)
$st = $pdo->prepare("SELECT semester_aktif FROM mahasiswa WHERE user_id=? LIMIT 1");
$st->execute([$userId]);
$mhs = $st->fetch() ?: [];
$mhsSemester = (int)($mhs['semester_aktif'] ?? 0);

$draft = null;
$st = $pdo->prepare("SELECT * FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
$st->execute([$userId, $semesterId]);
$draft = $st->fetch();

if (!$draft) {
  $st = $pdo->prepare("
    INSERT INTO krs_draft (user_id, semester_id, semester_ke, kode_kelas_text, total_sks, status)
    VALUES (?, ?, ?, '', 0, 'draft')
  ");
  $st->execute([$userId, $semesterId, max(0,$mhsSemester)]);
  $st = $pdo->prepare("SELECT * FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
  $st->execute([$userId, $semesterId]);
  $draft = $st->fetch();
}

$draftStatus = strtolower((string)($draft['status'] ?? 'draft'));
$canEdit = in_array($draftStatus, ['draft','rejected'], true);

$codes = parse_codes((string)($draft['kode_kelas_text'] ?? ''));
$totalSks = (int)($draft['total_sks'] ?? 0);

// ambil detail kelas yang sudah dipilih (berdasarkan kode_kelas)
$selectedRows = [];
$selectedByMk = [];
if ($codes) {
  $ph = implode(',', array_fill(0, count($codes), '?'));
  $params = array_merge([$semesterId], $codes);
  $st = $pdo->prepare("
    SELECT semester_ke, mata_kuliah, kode_kelas, sks, dosen, hari, jam_mulai, jam_selesai, ruangan
    FROM jadwal_kelas
    WHERE semester_id=? AND kode_kelas IN ($ph)
  ");
  $st->execute($params);
  $selectedRows = $st->fetchAll();

  foreach ($selectedRows as $r) $selectedByMk[(string)$r['mata_kuliah']] = $r;
}

// HANDLE POST tambah kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('mahasiswa/krs/add.php'.($filterSem?('?sem='.$filterSem):'')));
  }
  if (!$canEdit) {
    flash_set('global', 'Draft terkunci. Status: '.$draftStatus, 'warning');
    redirect(url('mahasiswa/krs/detail.php'));
  }

  $jadwalId = (int)($_POST['jadwal_id'] ?? 0);
  if ($jadwalId <= 0) {
    flash_set('global', 'Jadwal tidak valid.', 'warning');
    redirect(url('mahasiswa/krs/add.php'));
  }

  try {
    $pdo->beginTransaction();

    // ambil jadwal baru (harus semester aktif + semester_ke allowed)
    $st = $pdo->prepare("
      SELECT id, semester_id, semester_ke, mata_kuliah, kode_kelas, sks, hari, jam_mulai, jam_selesai
      FROM jadwal_kelas
      WHERE id=? LIMIT 1
    ");
    $st->execute([$jadwalId]);
    $j = $st->fetch();

    if (!$j || (int)$j['semester_id'] !== $semesterId || !in_array((int)$j['semester_ke'], $allowedSem, true)) {
      $pdo->rollBack();
      flash_set('global', 'Kelas tidak sesuai periode aktif.', 'warning');
      redirect(url('mahasiswa/krs/add.php'));
    }

    $kode = (string)$j['kode_kelas'];
    $mkNew = (string)$j['mata_kuliah'];
    $sksNew = (int)$j['sks'];

    // duplikat kode
    if (in_array($kode, $codes, true)) {
      $pdo->rollBack();
      flash_set('global', 'Kelas ini sudah dipilih.', 'warning');
      redirect(url('mahasiswa/krs/add.php'));
    }

    // 1 MK tidak boleh 2
    foreach ($selectedRows as $r) {
      if ((string)$r['mata_kuliah'] === $mkNew) {
        $pdo->rollBack();
        flash_set('global', 'Mata kuliah "'.$mkNew.'" sudah dipilih ('.$r['kode_kelas'].').', 'warning');
        redirect(url('mahasiswa/krs/add.php'));
      }
    }

    // bentrok jadwal
    foreach ($selectedRows as $r) {
      if ((string)$r['hari'] !== (string)$j['hari']) continue;
      if (overlap((string)$r['jam_mulai'], (string)$r['jam_selesai'], (string)$j['jam_mulai'], (string)$j['jam_selesai'])) {
        $pdo->rollBack();
        flash_set('global', 'Bentrok dengan '.$r['mata_kuliah'].' ('.$r['kode_kelas'].').', 'danger');
        redirect(url('mahasiswa/krs/add.php'));
      }
    }

    // max sks
    if (($totalSks + $sksNew) > MAX_SKS) {
      $pdo->rollBack();
      flash_set('global', 'Total SKS akan melebihi '.MAX_SKS.'.', 'warning');
      redirect(url('mahasiswa/krs/add.php'));
    }

    // update draft
    $codes2 = $codes;
    $codes2[] = $kode;

    $newCsv = join_codes($codes2);
    $newTotal = $totalSks + $sksNew;

    $st = $pdo->prepare("UPDATE krs_draft SET kode_kelas_text=?, total_sks=? WHERE id=?");
    $st->execute([$newCsv, $newTotal, (int)$draft['id']]);

    $pdo->commit();

    flash_set('global', 'Berhasil tambah: '.$mkNew.' ('.$kode.')', 'success');
    redirect(url('mahasiswa/krs/add.php'.($filterSem?('?sem='.$filterSem):'')));
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal menambah draft.', 'danger');
    redirect(url('mahasiswa/krs/add.php'));
  }
}

// ambil jadwal yang bisa dipilih (allowed semester ke)
$params = [$semesterId];
$whereSem = '';
if ($filterSem) {
  $whereSem = " AND semester_ke = ?";
  $params[] = $filterSem;
} else {
  $in = implode(',', array_fill(0, count($allowedSem), '?'));
  $whereSem = " AND semester_ke IN ($in)";
  $params = array_merge($params, $allowedSem);
}

$st = $pdo->prepare("
  SELECT id, semester_ke, mata_kuliah, kode_kelas, sks, dosen, hari, jam_mulai, jam_selesai, ruangan
  FROM jadwal_kelas
  WHERE semester_id=? $whereSem
  ORDER BY semester_ke, mata_kuliah, kode_kelas, FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai
");
$st->execute($params);
$jadwal = $st->fetchAll();

// group per mata_kuliah
$grouped = [];
foreach ($jadwal as $j) $grouped[(string)$j['mata_kuliah']][] = $j;
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
  <main class="content">

    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
      <div>
        <h2 style="margin:0;">Kelola Draft KRS</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif sistem: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
          · Status: <?= badge_status($draftStatus) ?>
          · Total: <b><?= (int)$totalSks ?>/<?= (int)MAX_SKS ?></b>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('mahasiswa/krs/index.php')) ?>">Summary</a>
        <a class="btn" href="<?= e(url('mahasiswa/krs/detail.php')) ?>">Detail</a>
      </div>
    </div>

    <!-- Filter semester (tanpa mengosongkan draft) -->
    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="color:#666;font-size:12px;margin-bottom:8px;">
        Periode aktif <b><?= e($periode) ?></b> → pilih filter semester: <?= e(implode(', ', $allowedSem)) ?> (boleh campur)
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a class="btn" href="<?= e(url('mahasiswa/krs/add.php')) ?>">Semua</a>
        <?php foreach ($allowedSem as $s): ?>
          <a class="btn" href="<?= e(url('mahasiswa/krs/add.php?sem='.$s)) ?>">Sem <?= (int)$s ?></a>
        <?php endforeach; ?>
        <?php if (!$canEdit): ?>
          <span style="color:#666;font-size:12px;">Draft terkunci (tidak bisa tambah/hapus/ganti).</span>
        <?php endif; ?>
      </div>
    </section>

    <!-- List matkul -->
    <section style="margin-top:12px;">
      <?php if (!$grouped): ?>
        <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;color:#666;">
          Tidak ada jadwal untuk filter ini.
        </div>
      <?php else: ?>
        <?php foreach ($grouped as $mk => $classes): ?>
          <?php $sel = $selectedByMk[$mk] ?? null; ?>

          <div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
              <div>
                <h3 style="margin:0;"><?= e($mk) ?></h3>
                <?php if ($sel): ?>
                  <div style="margin-top:6px;color:#666;">
                    Terpilih: <b><?= e((string)($sel['kode_kelas'] ?? '')) ?></b>
                    · <?= e((string)($sel['hari'] ?? '')) ?> <?= e((string)($sel['jam_mulai'] ?? '')) ?>-<?= e((string)($sel['jam_selesai'] ?? '')) ?>
                    · Ruang <?= e((string)($sel['ruangan'] ?? '')) ?>
                  </div>
                <?php else: ?>
                  <div style="margin-top:6px;color:#666;">Belum dipilih.</div>
                <?php endif; ?>
              </div>

              <?php if ($sel && $canEdit): ?>
                <form method="post" action="<?= e(url('mahasiswa/krs/edit.php')) ?>" style="margin:0;">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="kode_kelas" value="<?= e((string)$sel['kode_kelas']) ?>">
                  <button class="btn" type="submit" onclick="return confirm('Hapus dari draft?')">Hapus</button>
                </form>
              <?php endif; ?>
            </div>

            <div style="overflow:auto;margin-top:10px;">
              <table style="width:100%;border-collapse:collapse;min-width:980px;">
                <thead>
                  <tr>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Sem</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kode</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">SKS</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Hari</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Jam</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Ruangan</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($classes as $c): ?>
                    <?php
                      $isSel = $sel && ((string)$sel['kode_kelas'] === (string)$c['kode_kelas']);
                      $disabled = !$canEdit;
                      $reason = $disabled ? 'Terkunci' : '';

                      // UI-only: batas sks (server-side tetap cek)
                      if (!$disabled && ($totalSks + (int)$c['sks']) > MAX_SKS) { $disabled = true; $reason = 'Melebihi 24 SKS'; }

                      // UI-only: kalau MK sudah dipilih, disable kelas lain
                      if (!$disabled && $sel && !$isSel) { $disabled = true; $reason = 'MK sudah dipilih'; }
                    ?>
                    <tr>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$c['semester_ke'] ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= e((string)$c['kode_kelas']) ?></b></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$c['sks'] ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$c['dosen']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$c['hari']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$c['jam_mulai']) ?>-<?= e((string)$c['jam_selesai']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)$c['ruangan']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                        <?php if ($isSel): ?>
                          <span style="color:green;font-weight:700;">Dipilih</span>
                        <?php elseif ($disabled): ?>
                          <span style="color:#666;font-size:12px;"><?= e($reason) ?></span>
                        <?php else: ?>
                          <form method="post" style="margin:0;">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="jadwal_id" value="<?= (int)$c['id'] ?>">
                            <button class="btn" type="submit">Tambah</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
