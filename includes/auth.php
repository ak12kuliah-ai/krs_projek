<?php
// includes/auth.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function auth_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function auth_check(): bool {
  return isset($_SESSION['user']);
}

function auth_login(string $email, string $password): bool {
  $pdo = db();

  $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user) return false;
  if (!password_verify($password, $user['password_hash'])) return false;

  // simpan session (jangan simpan hash)
  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
  ];

  return true;
}

function auth_logout(): void {
  unset($_SESSION['user']);
}

function require_login(): void {
  if (!auth_check()) {
    flash_set('global', 'Silakan login dulu.', 'warning');
    redirect(url('login.php'));
  }
}

function require_role(string $role): void {
  require_login();
  $u = auth_user();
  if (!$u || ($u['role'] ?? '') !== $role) {
    http_response_code(403);
    die('403 Forbidden');
  }
}
