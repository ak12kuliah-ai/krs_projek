<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die('Method Not Allowed');
}

$token = $_POST['_csrf'] ?? '';
if (!csrf_verify($token)) {
  flash_set('global', 'CSRF tidak valid.', 'danger');
  redirect(url('admin/penjadwalan/index.php'));
}

$pdo = db();

$active = $pdo->query("SELECT id, periode FROM semester WHERE is_active=1 LIMIT 1")->fetch();
if (!$active) {
  flash_set('global', 'Belum ada semester aktif. Set dulu di Kelola Semester.', 'warning');
  redirect(url('admin/semester/index.php'));
}

$semesterId = (int)$active['id'];
$periode = (string)$active['periode'];
$allowed = ($periode === 'ganjil') ? [1,3,5,7] : [2,4,6,8];

$id = (int)($_POST['id'] ?? 0);
$semesterKe = (int)($_POST['semester_ke'] ?? 0);

if ($id <= 0) {
  flash_set('global', 'ID jadwal tidak valid.', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}
if ($semesterKe < 1 || $semesterKe > 8 || !in_array($semesterKe, $allowed, true)) {
  flash_set('global', 'semester_ke tidak sesuai periode aktif ('.$periode.').', 'warning');
  redirect(url('admin/penjadwalan/index.php'));
}

try {
  // Hapus hanya jika milik semester aktif + semester_ke
  $st = $pdo->prepare("DELETE FROM jadwal_kelas WHERE id=? AND semester_id=? AND semester_ke=?");
  $st->execute([$id, $semesterId, $semesterKe]);

  flash_set('global', 'Jadwal berhasil dihapus.', 'success');
  redirect(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe));
} catch (Throwable $e) {
  flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal hapus jadwal.', 'danger');
  redirect(url('admin/penjadwalan/detail.php?semester_ke='.$semesterKe));
}
