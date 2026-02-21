<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('mahasiswa');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Method Not Allowed'); }

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$u = auth_user();
$userId = (int)($u['id'] ?? 0);

const MAX_SKS = 24;

$token = $_POST['_csrf'] ?? '';
if (!csrf_verify($token)) {
  flash_set('global', 'CSRF tidak valid.', 'danger');
  redirect(url('mahasiswa/krs/index.php'));
}

$active = get_active_semester($pdo);
if (!$active) {
  flash_set('global', 'Belum ada semester aktif sistem.', 'warning');
  redirect(url('mahasiswa/index.php'));
}
$semesterId = (int)$active['id'];

try {
  // krs header
  $st = $pdo->prepare("SELECT id, status FROM krs WHERE user_id=? AND semester_id=? LIMIT 1");
  $st->execute([$userId, $semesterId]);
  $krs = $st->fetch();

  if (!$krs) {
    flash_set('global', 'Draft belum dibuat. Pilih semester dulu.', 'warning');
    redirect(url('mahasiswa/krs/index.php'));
  }

  $krsId = (int)$krs['id'];
  $status = strtolower((string)$krs['status']);

  if (!in_array($status, ['draft','rejected'], true)) {
    flash_set('global', 'Tidak bisa submit. Status: '.$status, 'warning');
    redirect(url('mahasiswa/krs/index.php'));
  }

  // dosen wali
  $st = $pdo->prepare("SELECT dosen_wali_id FROM mahasiswa WHERE user_id=? LIMIT 1");
  $st->execute([$userId]);
  $m = $st->fetch() ?: [];

  $dosenWaliId = (int)($m['dosen_wali_id'] ?? 0);
  if ($dosenWaliId <= 0) {
    flash_set('global', 'Dosen wali belum diset admin. Hubungi admin.', 'warning');
    redirect(url('mahasiswa/krs/index.php'));
  }

  // item count + total sks
  $st = $pdo->prepare("
    SELECT COUNT(*) c, COALESCE(SUM(jk.sks),0) total
    FROM krs_item ki
    JOIN jadwal_kelas jk ON jk.id = ki.jadwal_id
    WHERE ki.krs_id=?
  ");
  $st->execute([$krsId]);
  $row = $st->fetch() ?: ['c'=>0,'total'=>0];

  $cnt = (int)($row['c'] ?? 0);
  $total = (int)($row['total'] ?? 0);

  if ($cnt <= 0) {
    flash_set('global', 'Draft masih kosong. Pilih matkul dulu.', 'warning');
    redirect(url('mahasiswa/krs/index.php'));
  }
  if ($total > MAX_SKS) {
    flash_set('global', 'Total SKS melebihi '.MAX_SKS.'. Kurangi dulu.', 'warning');
    redirect(url('mahasiswa/krs/index.php'));
  }

  // submit
  $st = $pdo->prepare("UPDATE krs SET status='submitted', dosen_wali_id=?, submitted_at=NOW() WHERE id=? AND user_id=? AND semester_id=?");
  $st->execute([$dosenWaliId, $krsId, $userId, $semesterId]);

  flash_set('global', 'KRS berhasil diajukan (submitted).', 'success');
  redirect(url('mahasiswa/krs/index.php'));
} catch (Throwable $e) {
  flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal submit KRS.', 'danger');
  redirect(url('mahasiswa/krs/index.php'));
}
