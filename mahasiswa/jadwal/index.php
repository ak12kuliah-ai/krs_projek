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

function parse_codes(string $csv): array {
  $parts = array_map('trim', explode(',', $csv));
  $parts = array_filter($parts, fn($v) => $v !== '');
  return array_values(array_unique($parts));
}

$active = null;
$rows = [];
$byDay = [];

try {
  // 1) semester aktif sistem
  $active = get_active_semester($pdo);
  if (!$active) {
    flash_set('global', 'Belum ada semester aktif sistem. Minta admin set semester aktif dulu.', 'warning');
  } else {
    $semesterId = (int)$active['id'];

    // 2) ambil draft KRS mahasiswa (tabel krs_draft)
    $st = $pdo->prepare("SELECT kode_kelas_text FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
    $st->execute([$userId, $semesterId]);
    $draft = $st->fetch() ?: [];

    $csv = (string)($draft['kode_kelas_text'] ?? '');
    $codes = parse_codes($csv);

    // 3) kalau ada kode kelas, tampilkan jadwal berdasarkan kode tersebut
    if ($codes) {
      $ph = implode(',', array_fill(0, count($codes), '?'));
      $params = array_merge([$semesterId], $codes);

      $st = $pdo->prepare("
        SELECT kode_kelas, mata_kuliah, sks, dosen, hari, jam_mulai, jam_selesai, ruangan, kuota
        FROM jadwal_kelas
        WHERE semester_id = ?
          AND kode_kelas IN ($ph)
        ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai, mata_kuliah, kode_kelas
      ");
      $st->execute($params);
      $rows = $st->fetchAll();
    }
  }
} catch (Throwable $e) {
  if (APP_DEBUG) flash_set('global', 'DB error: '.$e->getMessage(), 'warning');
}

// group by hari
foreach ($rows as $r) {
  $day = (string)($r['hari'] ?? 'Lainnya');
  $byDay[$day][] = $r;
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
        <h2 style="margin:0;">Jadwal Kuliah (Dari Draft KRS)</h2>
        <div style="color:#666;margin-top:6px;">
          Semester aktif sistem: <b><?= $active ? e($active['nama'].' - '.$active['periode']) : '-' ?></b>
        </div>
      </div>
      <a class="btn" href="<?= e(url('mahasiswa/index.php')) ?>">Kembali</a>
    </div>

    <?php if (!$active): ?>
      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
        <div style="color:#666;">Belum ada semester aktif sistem. Minta admin set semester aktif dulu.</div>
      </section>

    <?php elseif (!$rows): ?>
      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
        <div style="color:#666;">
          Belum ada kelas yang dipilih di Draft KRS. Silakan pilih dulu di menu KRS.
        </div>
        <div style="margin-top:10px;">
          <a class="btn" href="<?= e(url('mahasiswa/krs/add.php')) ?>">Kelola Draft KRS</a>
          <a class="btn" href="<?= e(url('mahasiswa/krs/detail.php')) ?>">Lihat Detail KRS</a>
        </div>
      </section>

    <?php else: ?>
      <?php foreach ($byDay as $day => $items): ?>
        <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
          <h3 style="margin:0 0 10px;"><?= e($day) ?></h3>

          <div style="overflow:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:950px;">
              <thead>
                <tr>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Jam</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Mata Kuliah</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kode Kelas</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">SKS</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Dosen</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Ruangan</th>
                  <th style="text-align:left;border-bottom:1px solid #eee;padding:10px;">Kuota</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $r): ?>
                  <tr>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;">
                      <?= e((string)($r['jam_mulai'] ?? '')) ?> - <?= e((string)($r['jam_selesai'] ?? '')) ?>
                    </td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['mata_kuliah'] ?? '')) ?></td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><b><?= e((string)($r['kode_kelas'] ?? '')) ?></b></td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)($r['sks'] ?? 0) ?></td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['dosen'] ?? '')) ?></td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= e((string)($r['ruangan'] ?? '')) ?></td>
                    <td style="padding:10px;border-bottom:1px solid #f3f3f3;"><?= (int)($r['kuota'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>