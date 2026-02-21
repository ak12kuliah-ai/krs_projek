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
  redirect(url('admin/users/index.php'));
}

$id = (int)($_POST['id'] ?? 0);
$back = (string)($_POST['back'] ?? 'index.php');

if ($id <= 0) {
  flash_set('global', 'ID user tidak valid.', 'warning');
  redirect(url('admin/users/index.php'));
}

$pdo = db();

try {
  $st = $pdo->prepare("DELETE FROM users WHERE id=?");
  $st->execute([$id]);

  flash_set('global', 'User berhasil dihapus.', 'success');
  redirect(url('admin/users/'.$back));
} catch (Throwable $e) {
  flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal menghapus user.', 'danger');
  redirect(url('admin/users/'.$back));
}
