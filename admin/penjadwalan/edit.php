<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$active = $pdo->query("SELECT id, nama, periode FROM semester WHERE is_active=1 LIMIT 1")->fetch();
if (!$active) {
  flash_set('global', 'Belum ada semester aktif. Set dulu di Kelola Semester.', 'warning');
  redirect(url('admin/semester/index.php'));
}

$semesterId = (int)$active['id'];
$periode = (string)$active['periode'];
$allowed = ($periode === 'ganjil') ? [1,3,5,7] : [2,4,6,8];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$semesterKe = (int)($_GET['semester_ke'] ?? $_POST['semester_ke'] ?? 0);

if ($id <= 0) {
  flash_set('global', 'ID jadwal tidak valid.', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}
if ($semesterKe < 1 || $semesterKe > 8) {
  flash_set('global', 'semester_ke harus 1 sampai 8.', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}
if (!in_array($semesterKe, $allowed, true)) {
  flash_set('global', 'Semester aktif saat ini '.$periode.', jadi hanya boleh: '.implode(',', $allowed), 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}

// ambil row (harus milik semester aktif + semester_ke)
try {
  $st = $pdo->prepare("
    SELECT *
    FROM jadwal_kelas
    WHERE id=? AND semester_id=? AND semester_ke=?
    LIMIT 1
  ");
  $st->execute([$id, $semesterId, $semesterKe]);
  $row = $st->fetch();

  if (!$row) {
    flash_set('global', 'Data jadwal tidak ditemukan (bukan milik semester aktif).', 'danger');
    redirect(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe));
  }
} catch (Throwable $e) {
  flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Terjadi kesalahan.', 'danger');
  redirect(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
  }

  $mk     = trim($_POST['mata_kuliah'] ?? '');
  $kode   = trim($_POST['kode_kelas'] ?? '');
  $sks    = (int)($_POST['sks'] ?? 2);
  $dosen  = trim($_POST['dosen'] ?? '');
  $hari   = trim($_POST['hari'] ?? '');
  $mulai  = trim($_POST['jam_mulai'] ?? '');
  $seles  = trim($_POST['jam_selesai'] ?? '');
  $ruang  = trim($_POST['ruangan'] ?? '');
  $kuota  = (int)($_POST['kuota'] ?? 30);

  if ($mk === '' || $kode === '' || $hari === '' || $mulai === '' || $seles === '') {
    flash_set('global', 'Wajib: mata kuliah, kode kelas, hari, jam mulai, jam selesai.', 'warning');
    redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
  }
  if ($sks <= 0) $sks = 2;
  if ($kuota <= 0) $kuota = 30;

  // ... setelah ambil input POST:
if (strtotime('1970-01-01 '.$mulai) >= strtotime('1970-01-01 '.$seles)) {
  flash_set('global', 'Jam mulai harus lebih kecil dari jam selesai.', 'warning');
  redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
}

$dosenDb = ($dosen === '') ? null : $dosen;
$ruangDb = ($ruang === '') ? null : $ruang;

function find_conflict_exclude(PDO $pdo, int $semesterId, int $excludeId, string $hari, string $field, string $value, string $newMulai, string $newSelesai): ?array {
  $sql = "
    SELECT id, semester_ke, kode_kelas, mata_kuliah, dosen, ruangan, hari, jam_mulai, jam_selesai
    FROM jadwal_kelas
    WHERE semester_id = ?
      AND id <> ?
      AND hari = ?
      AND {$field} = ?
      AND jam_mulai < ?
      AND jam_selesai > ?
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$semesterId, $excludeId, $hari, $value, $newSelesai, $newMulai]);
  $row = $st->fetch();
  return $row ?: null;
}

// Bentrok dosen
if ($dosen !== '') {
  $conf = find_conflict_exclude($pdo, $semesterId, $id, $hari, 'dosen', $dosen, $mulai, $seles);
  if ($conf) {
    flash_set(
      'global',
      'Bentrok DOSEN: '.$dosen.' sudah ada jadwal '
      .e($conf['mata_kuliah']).' ('.$conf['kode_kelas'].') '
      .'semester '.$conf['semester_ke'].' '
      .$conf['hari'].' '.$conf['jam_mulai'].'-'.$conf['jam_selesai'].'.',
      'danger'
    );
    redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
  }
}

// Bentrok ruangan
if ($ruang !== '') {
  $conf = find_conflict_exclude($pdo, $semesterId, $id, $hari, 'ruangan', $ruang, $mulai, $seles);
  if ($conf) {
    flash_set(
      'global',
      'Bentrok RUANGAN: '.$ruang.' sudah dipakai untuk '
      .e($conf['mata_kuliah']).' ('.$conf['kode_kelas'].') '
      .'semester '.$conf['semester_ke'].' '
      .$conf['hari'].' '.$conf['jam_mulai'].'-'.$conf['jam_selesai'].'.',
      'danger'
    );
    redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
  }
}

// Unique kode_kelas (kecuali dirinya)
$st = $pdo->prepare("
  SELECT COUNT(*) c
  FROM jadwal_kelas
  WHERE semester_id=? AND semester_ke=? AND kode_kelas=? AND id<>?
");
$st->execute([$semesterId, $semesterKe, $kode, $id]);
if ((int)($st->fetch()['c'] ?? 0) > 0) {
  flash_set('global', 'Kode kelas sudah dipakai untuk semester_ke ini.', 'danger');
  redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
}

// UPDATE (pakai $dosenDb dan $ruangDb)
$st = $pdo->prepare("
  UPDATE jadwal_kelas
  SET mata_kuliah=?, kode_kelas=?, sks=?, dosen=?, hari=?, jam_mulai=?, jam_selesai=?, ruangan=?, kuota=?
  WHERE id=? AND semester_id=? AND semester_ke=?
");
$st->execute([$mk, $kode, $sks, $dosenDb, $hari, $mulai, $seles, $ruangDb, $kuota, $id, $semesterId, $semesterKe]);

  try {
    // cek unique kode_kelas dalam semester aktif + semester_ke (kecuali dirinya)
    $st = $pdo->prepare("
      SELECT COUNT(*) c
      FROM jadwal_kelas
      WHERE semester_id=? AND semester_ke=? AND kode_kelas=? AND id<>?
    ");
    $st->execute([$semesterId, $semesterKe, $kode, $id]);
    if ((int)($st->fetch()['c'] ?? 0) > 0) {
      flash_set('global', 'Kode kelas sudah dipakai untuk semester_ke ini.', 'danger');
      redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
    }

    $st = $pdo->prepare("
      UPDATE jadwal_kelas
      SET mata_kuliah=?, kode_kelas=?, sks=?, dosen=?, hari=?, jam_mulai=?, jam_selesai=?, ruangan=?, kuota=?
      WHERE id=? AND semester_id=? AND semester_ke=?
    ");
    $st->execute([$mk, $kode, $sks, $dosen, $hari, $mulai, $seles, $ruang, $kuota, $id, $semesterId, $semesterKe]);

    flash_set('global', 'Jadwal berhasil diupdate.', 'success');
    redirect(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe));
  } catch (Throwable $e) {
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal update jadwal.', 'danger');
    redirect(url('admin/penjadwalan/edit.php?id='.$id.'&semester_ke='.$semesterKe));
  }
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Edit Jadwal</h2>
    <p style="color:#666;margin-top:6px;">
      Semester aktif: <b><?= e($active['nama'].' - '.$active['periode']) ?></b>
      · Semester mahasiswa: <b><?= (int)$semesterKe ?></b>
    </p>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;max-width:820px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="semester_ke" value="<?= (int)$semesterKe ?>">

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div style="grid-column:1 / -1;">
            <label>Mata Kuliah*</label>
            <input name="mata_kuliah" required value="<?= e($row['mata_kuliah']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Kode Kelas*</label>
            <input name="kode_kelas" required value="<?= e($row['kode_kelas']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>SKS</label>
            <input name="sks" type="number" min="1" value="<?= (int)$row['sks'] ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Dosen</label>
            <input name="dosen" value="<?= e($row['dosen']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Ruangan</label>
            <input name="ruangan" value="<?= e($row['ruangan']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Hari*</label>
            <?php $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; ?>
            <select name="hari" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
              <option value="">- pilih -</option>
              <?php foreach ($days as $d): ?>
                <option <?= ((string)$row['hari'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Kuota</label>
            <input name="kuota" type="number" min="1" value="<?= (int)$row['kuota'] ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Jam Mulai*</label>
            <input name="jam_mulai" type="time" required value="<?= e($row['jam_mulai']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Jam Selesai*</label>
            <input name="jam_selesai" type="time" required value="<?= e($row['jam_selesai']) ?>"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn" type="submit">Update</button>
          <a class="btn" href="<?= e(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe)) ?>">Batal</a>
        </div>
      </form>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
