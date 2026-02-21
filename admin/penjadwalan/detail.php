<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

// semester aktif penentu sistem
$active = $pdo->query("SELECT id, nama, periode, is_active FROM semester WHERE is_active=1 LIMIT 1")->fetch();
if (!$active) {
  flash_set('global', 'Belum ada semester aktif. Set dulu di Kelola Semester.', 'warning');
  redirect(url('admin/semester/index.php'));
}

$semesterId = (int)$active['id'];
$periode = (string)$active['periode'];

$allowed = ($periode === 'ganjil') ? [1,3,5,7] : [2,4,6,8];

$semesterKe = (int)($_GET['semester_ke'] ?? 0);
if ($semesterKe < 1 || $semesterKe > 8) {
  flash_set('global', 'semester_ke harus 1 sampai 8.', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}
if (!in_array($semesterKe, $allowed, true)) {
  flash_set('global', 'Semester aktif saat ini '.$periode.', jadi hanya boleh: '.implode(',', $allowed), 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}

// ambil data jadwal untuk semester aktif + semester_ke terpilih
$rows = [];
try {
  $st = $pdo->prepare("
    SELECT id, kode_kelas, mata_kuliah, sks, dosen, hari, jam_mulai, jam_selesai, ruangan, kuota
    FROM jadwal_kelas
    WHERE semester_id = ? AND semester_ke = ?
    ORDER BY mata_kuliah, kode_kelas, hari, jam_mulai
  ");
  $st->execute([$semesterId, $semesterKe]);
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
}

// group by mata_kuliah (percabangan kode kelas)
$grouped = [];
foreach ($rows as $r) {
  $mk = (string)$r['mata_kuliah'];
  $grouped[$mk][] = $r;
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

  <main class="content">
    <div style="display:flex;gap:10px;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0;">Detail Jadwal</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif: <b><?= e($active['nama'].' - '.$active['periode']) ?></b><br>
          Semester mahasiswa: <b><?= (int)$semesterKe ?></b>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('admin/penjadwalan/index.php')) ?>">Kembali</a>
        <a class="btn" href="<?= e(url('admin/penjadwalan/add.php?semester_ke='.$semesterKe)) ?>">+ Tambah Jadwal</a>
      </div>
    </div>

    <!-- tombol cepat pindah semester_ke (sesuai periode aktif) -->
    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="font-size:12px;color:#666;">Pindah semester mahasiswa (mengikuti periode aktif)</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
        <?php foreach ($allowed as $k): ?>
          <a class="btn" href="<?= e(url('admin/penjadwalan/detail.php?semester_ke='.$k)) ?>"
             style="<?= ($k===$semesterKe) ? 'font-weight:700;' : '' ?>">
            Semester <?= $k ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <?php if (!$rows): ?>
        <div style="color:#666;">Belum ada jadwal untuk mahasiswa semester <b><?= (int)$semesterKe ?></b>.</div>
      <?php else: ?>
        <?php foreach ($grouped as $mk => $items): ?>
          <div style="border:1px solid #eee;border-radius:12px;padding:12px;margin-bottom:12px;">
            <h3 style="margin:0 0 10px;"><?= e($mk) ?></h3>

            <div style="overflow:auto;">
              <table style="width:100%;border-collapse:collapse;min-width:900px;">
                <thead>
                  <tr>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kode Kelas</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">SKS</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Hari</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Jam</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Ruangan</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kuota</th>
                    <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $r): ?>
                    <tr>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= e($r['kode_kelas']) ?></b></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$r['sks'] ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['dosen']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['hari']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['jam_mulai']) ?> - <?= e($r['jam_selesai']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e($r['ruangan']) ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)$r['kuota'] ?></td>
                      <td style="padding:10px;border-bottom:1px solid #f3f3f3;display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="btn" href="<?= e(url('admin/penjadwalan/edit.php?id='.(int)$r['id'].'&semester_ke='.(int)$semesterKe)) ?>">Edit</a>

                        <form method="post" action="<?= e(url('admin/penjadwalan/delete.php')) ?>" style="margin:0;">
                          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="semester_ke" value="<?= (int)$semesterKe ?>">
                          <button class="btn" type="submit" onclick="return confirm('Hapus jadwal ini?')">Hapus</button>
                        </form>
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
