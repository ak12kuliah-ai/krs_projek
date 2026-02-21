<?php
// includes/config.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

date_default_timezone_set('Asia/Jakarta');

function env_load(string $path): void {
  if (!file_exists($path)) return;

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    $pos = strpos($line, '=');
    if ($pos === false) continue;

    $key = trim(substr($line, 0, $pos));
    $val = trim(substr($line, $pos + 1));

    // hapus quote jika ada
    $val = trim($val, "\"'");

    $_ENV[$key] = $val;
  }
}

env_load(__DIR__ . '/../.env');

define('APP_NAME', $_ENV['APP_NAME'] ?? 'KRS Project');
define('APP_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost/krs_project', '/'));
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? '0') === '1');

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'krs_project');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
