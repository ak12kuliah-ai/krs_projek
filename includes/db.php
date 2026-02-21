<?php
// includes/db.php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    if (APP_DEBUG) {
      die("DB Connection Error: " . $e->getMessage());
    }
    die("DB Connection Error.");
  }

  return $pdo;
}
