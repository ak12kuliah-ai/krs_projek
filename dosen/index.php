<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('dosen');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$u = auth_user();
$userId = (int)($u['id'] ?? 0);

// Ambil dosen.id dari tabel dosen
$st = $pdo->prepare("SELECT id, nidn, prodi FROM dosen WHERE user_id=? LIMIT 1");
$st->execute([$userId]);
$dosen = $st->fetch();

if (!$dosen) {
  flash_set('global', 'Profil dosen tidak ditemukan di tabel dosen.', 'danger');
  redirect(url('logout.php'));
}

$dosenId = (int)$dosen['id'];

// Semester aktif sistem
$active = get_active_semester($pdo);

// Hitung jumlah pengajuan (submitted) yang harus divalidasi dosen ini
$pendingCount = 0;
if ($active) {
  $st = $pdo->prepare("
    SELECT COUNT(*) c
    FROM krs_draft
    WHERE semester_id = ?
      AND dosen_wali_id = ?
      AND status = 'submitted'
  ");
  $st->execute([(int)$active['id'], $dosenId]);
  $pendingCount = (int)($st->fetch()['c'] ?? 0);
}
?>

<?php include __DIR__ . '/../partials/header.php'; ?>
<?php include __DIR__ . '/../partials/navbar.php'; ?>
<?php include __DIR__ . '/../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="content">
    <h2 style="margin-top:0;">Dashboard Dosen</h2>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin-top:12px;">
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div>
          <div style="font-size:12px;color:#666;">Nama</div>
          <div style="font-weight:700;"><?= e((string)($u['name'] ?? '-')) ?></div>
        </div>
        <div>
          <div style="font-size:12px;color:#666;">NIDN</div>
          <div><?= e((string)($dosen['nidn'] ?? '-')) ?></div>
        </div>

        <div>
          <div style="font-size:12px;color:#666;">Prodi</div>
          <div><?= e((string)($dosen['prodi'] ?? '-')) ?></div>
        </div>
        <div>
          <div style="font-size:12px;color:#666;">Semester aktif sistem</div>
          <div style="font-weight:700;">
            <?= $active ? e((string)$active['nama'].' - '.(string)$active['periode']) : '-' ?>
          </div>
        </div>
      </div>
    </section>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px;">
      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Pengajuan KRS (Submitted)</div>
        <div style="font-size:26px;font-weight:800;margin-top:6px;"><?= (int)$pendingCount ?></div>
        <div style="color:#666;font-size:12px;margin-top:6px;">
          Jumlah draft KRS yang menunggu validasi kamu sebagai dosen wali.
        </div>
        <div style="margin-top:10px;">
          <a class="btn" href="<?= e(url('dosen/validasi/index.php')) ?>">Buka Validasi KRS</a>
        </div>
      </section>

      <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;">
        <div style="font-size:12px;color:#666;">Panduan singkat</div>
        <div style="margin-top:8px;color:#444;">
          1) Masuk menu <b>Validasi KRS</b><br>
          2) Buka detail pengajuan<br>
          3) Klik <b>Approve</b> atau <b>Reject</b>
        </div>
      </section>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
