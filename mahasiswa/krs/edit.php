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
function join_codes(array $codes): string {
  $codes = array_values(array_unique(array_filter(array_map('trim', $codes), fn($v)=>$v!=='')));
  return implode(',', $codes);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die('Method Not Allowed');
}

$token = $_POST['_csrf'] ?? '';
if (!csrf_verify($token)) {
  flash_set('global', 'CSRF tidak valid.', 'danger');
  redirect(url('mahasiswa/krs/add.php'));
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
if ($action !== 'remove') {
  flash_set('global', 'Aksi tidak valid.', 'warning');
  redirect(url('mahasiswa/krs/add.php'));
}

$kode = trim((string)($_POST['kode_kelas'] ?? ''));
if ($kode === '') {
  flash_set('global', 'Kode kelas tidak valid.', 'warning');
  redirect(url('mahasiswa/krs/add.php'));
}

$active = get_active_semester($pdo);
if (!$active) {
  flash_set('global', 'Belum ada semester aktif sistem.', 'warning');
  redirect(url('mahasiswa/index.php'));
}
$semesterId = (int)$active['id'];

$st = $pdo->prepare("SELECT * FROM krs_draft WHERE user_id=? AND semester_id=? LIMIT 1");
$st->execute([$userId, $semesterId]);
$draft = $st->fetch();

if (!$draft) {
  flash_set('global', 'Draft tidak ditemukan.', 'warning');
  redirect(url('mahasiswa/krs/add.php'));
}

$status = strtolower((string)$draft['status']);
if (!in_array($status, ['draft','rejected'], true)) {
  flash_set('global', 'Draft terkunci. Status: '.$status, 'warning');
  redirect(url('mahasiswa/krs/detail.php'));
}

$codes = parse_codes((string)$draft['kode_kelas_text']);
$codes2 = array_values(array_filter($codes, fn($c) => $c !== $kode));

$newTotal = 0;
if ($codes2) {
  $ph = implode(',', array_fill(0, count($codes2), '?'));
  $params = array_merge([$semesterId], $codes2);
  $st = $pdo->prepare("SELECT COALESCE(SUM(sks),0) total FROM jadwal_kelas WHERE semester_id=? AND kode_kelas IN ($ph)");
  $st->execute($params);
  $newTotal = (int)($st->fetch()['total'] ?? 0);
}

$st = $pdo->prepare("UPDATE krs_draft SET kode_kelas_text=?, total_sks=? WHERE id=?");
$st->execute([join_codes($codes2), $newTotal, (int)$draft['id']]);

flash_set('global', 'Berhasil dihapus.', 'success');
redirect(url('mahasiswa/krs/add.php'));
