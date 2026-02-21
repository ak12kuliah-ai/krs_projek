<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

/**
 * Overlap jika:
 * existing_mulai < new_selesai AND existing_selesai > new_mulai
 */
function find_conflict(PDO $pdo, int $semesterId, string $hari, string $field, string $value, string $newMulai, string $newSelesai): ?array
{
  $sql = "
    SELECT id, semester_ke, kode_kelas, mata_kuliah, dosen, ruangan, hari, jam_mulai, jam_selesai
    FROM jadwal_kelas
    WHERE semester_id = ?
      AND hari = ?
      AND {$field} = ?
      AND jam_mulai < ?
      AND jam_selesai > ?
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$semesterId, $hari, $value, $newSelesai, $newMulai]);
  $row = $st->fetch();
  return $row ?: null;
}

// Semester aktif = penentu sistem
$active = $pdo->query("SELECT id, nama, periode FROM semester WHERE is_active=1 LIMIT 1")->fetch();
if (!$active) {
  flash_set('global', 'Belum ada semester aktif. Set dulu di Kelola Semester.', 'warning');
  redirect(url('admin/semester/index.php'));
}

$semesterId = (int)$active['id'];
$periode = (string)$active['periode'];
$allowed = ($periode === 'ganjil') ? [1, 3, 5, 7] : [2, 4, 6, 8];

$semesterKe = (int)($_GET['semester_ke'] ?? $_POST['semester_ke'] ?? 0);
if ($semesterKe < 1 || $semesterKe > 8) {
  flash_set('global', 'semester_ke harus 1 sampai 8.', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}
if (!in_array($semesterKe, $allowed, true)) {
  flash_set('global', 'Semester aktif saat ini ' . $periode . ', jadi hanya boleh: ' . implode(',', $allowed), 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
  }

  // Ambil input
  $mk     = trim($_POST['mata_kuliah'] ?? '');
  $kode   = trim($_POST['kode_kelas'] ?? '');
  $sks    = (int)($_POST['sks'] ?? 2);
  $dosen  = trim($_POST['dosen'] ?? '');
  $hari   = trim($_POST['hari'] ?? '');
  $mulai  = trim($_POST['jam_mulai'] ?? '');
  $seles  = trim($_POST['jam_selesai'] ?? '');
  $ruang  = trim($_POST['ruangan'] ?? '');
  $kuota  = (int)($_POST['kuota'] ?? 30);

  // Validasi minimal
  if ($mk === '' || $kode === '' || $hari === '' || $mulai === '' || $seles === '') {
    flash_set('global', 'Wajib: mata kuliah, kode kelas, hari, jam mulai, jam selesai.', 'warning');
    redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
  }

  // Validasi jam
  if (strtotime('1970-01-01 ' . $mulai) >= strtotime('1970-01-01 ' . $seles)) {
    flash_set('global', 'Jam mulai harus lebih kecil dari jam selesai.', 'warning');
    redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
  }

  if ($sks <= 0) $sks = 2;
  if ($kuota <= 0) $kuota = 30;

  // Normalisasi kosong -> NULL
  $dosenDb = ($dosen === '') ? null : $dosen;
  $ruangDb = ($ruang === '') ? null : $ruang;

  try {
    // 1) Unique kode_kelas dalam semester aktif + semester_ke
    $st = $pdo->prepare("
      SELECT COUNT(*) c
      FROM jadwal_kelas
      WHERE semester_id=? AND semester_ke=? AND kode_kelas=?
    ");
    $st->execute([$semesterId, $semesterKe, $kode]);
    if ((int)($st->fetch()['c'] ?? 0) > 0) {
      flash_set('global', 'Kode kelas sudah dipakai untuk semester_ke ini.', 'danger');
      redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
    }

    // 2) Bentrok dosen (kalau diisi)
    if ($dosen !== '') {
      $conf = find_conflict($pdo, $semesterId, $hari, 'dosen', $dosen, $mulai, $seles);
      if ($conf) {
        flash_set(
          'global',
          'Bentrok DOSEN: ' . $dosen . ' sudah ada jadwal '
          . e($conf['mata_kuliah']) . ' (' . e($conf['kode_kelas']) . ') '
          . 'semester ' . e((string)$conf['semester_ke']) . ' '
          . e($conf['hari']) . ' ' . e($conf['jam_mulai']) . '-' . e($conf['jam_selesai']) . '.',
          'danger'
        );
        redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
      }
    }

    // 3) Bentrok ruangan (kalau diisi)
    if ($ruang !== '') {
      $conf = find_conflict($pdo, $semesterId, $hari, 'ruangan', $ruang, $mulai, $seles);
      if ($conf) {
        flash_set(
          'global',
          'Bentrok RUANGAN: ' . $ruang . ' sudah dipakai untuk '
          . e($conf['mata_kuliah']) . ' (' . e($conf['kode_kelas']) . ') '
          . 'semester ' . e((string)$conf['semester_ke']) . ' '
          . e($conf['hari']) . ' ' . e($conf['jam_mulai']) . '-' . e($conf['jam_selesai']) . '.',
          'danger'
        );
        redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
      }
    }

    // 4) Insert data
    $st = $pdo->prepare("
      INSERT INTO jadwal_kelas
      (semester_id, semester_ke, kode_kelas, mata_kuliah, sks, dosen, hari, jam_mulai, jam_selesai, ruangan, kuota)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([$semesterId, $semesterKe, $kode, $mk, $sks, $dosenDb, $hari, $mulai, $seles, $ruangDb, $kuota]);

    flash_set('global', 'Jadwal berhasil ditambahkan.', 'success');
    redirect(url('admin/penjadwalan/detail.php?semester_ke=' . $semesterKe));
  } catch (Throwable $e) {
    flash_set('global', APP_DEBUG ? ('DB error: ' . $e->getMessage()) : 'Gagal menambah jadwal.', 'danger');
    redirect(url('admin/penjadwalan/add.php?semester_ke=' . $semesterKe));
  }
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Tambah Jadwal</h2>
    <p style="color:#666;margin-top:6px;">
      Semester aktif: <b><?= e($active['nama'] . ' - ' . $active['periode']) ?></b>
      · Semester mahasiswa: <b><?= (int)$semesterKe ?></b>
    </p>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;max-width:820px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="semester_ke" value="<?= (int)$semesterKe ?>">

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div style="grid-column:1 / -1;">
            <label>Mata Kuliah*</label>
            <input name="mata_kuliah" required placeholder="contoh: Basis Data"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Kode Kelas*</label>
            <input name="kode_kelas" required placeholder="contoh: TIF23A / TIF23B"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>SKS</label>
            <input name="sks" type="number" min="1" value="2"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Dosen</label>
            <input name="dosen" placeholder="contoh: Dr. Budi"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Ruangan</label>
            <input name="ruangan" placeholder="contoh: Lab 2"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Hari*</label>
            <select name="hari" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
              <option value="">- pilih -</option>
              <option>Senin</option><option>Selasa</option><option>Rabu</option>
              <option>Kamis</option><option>Jumat</option><option>Sabtu</option>
            </select>
          </div>

          <div>
            <label>Kuota</label>
            <input name="kuota" type="number" min="1" value="30"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Jam Mulai*</label>
            <input name="jam_mulai" type="time" required
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Jam Selesai*</label>
            <input name="jam_selesai" type="time" required
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn" type="submit">Simpan</button>
          <a class="btn" href="<?= e(url('admin/penjadwalan/detail.php?semester_ke=' . $semesterKe)) ?>">Batal</a>
        </div>
      </form>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
