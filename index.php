<?php
require_once __DIR__ . '/includes/auth.php';

if (!auth_check()) {
  redirect(url('login.php'));
}

$u = auth_user();
$role = $u['role'] ?? 'mahasiswa';

if ($role === 'admin') redirect(url('admin/dashboard/index.php'));
if ($role === 'dosen') redirect(url('dosen/index.php'));
redirect(url('mahasiswa/index.php'));
