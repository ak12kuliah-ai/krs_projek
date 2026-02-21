<?php
// includes/functions.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $str): string {
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string {
  $path = ltrim($path, '/');
  return APP_URL . ($path ? '/' . $path : '');
}

function redirect(string $to): never {
  header('Location: ' . $to);
  exit;
}

function flash_set(string $key, string $message, string $type = 'info'): void {
  $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
}

function flash_get(string $key): ?array {
  if (!isset($_SESSION['_flash'][$key])) return null;
  $data = $_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return $data;
}

function csrf_token(): string {
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool {
  return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
}

/**
 * ====== SEMESTER AUTO-CALC (OPS B) ======
 * semester aktif sistem: semester.nama = "2025/2026", semester.periode = "ganjil|genap"
 * angkatan mahasiswa: 2023
 *
 * rumus:
 * yearDiff = startYear - angkatan
 * ganjil: sem = yearDiff*2 + 1
 * genap : sem = yearDiff*2 + 2
 * clamp 1..8
 */

function parse_start_year(string $namaSemester): ?int {
  // contoh "2025/2026" -> 2025
  if (preg_match('/(\d{4})\s*\/\s*(\d{4})/', $namaSemester, $m)) {
    return (int)$m[1];
  }
  // fallback: ambil 4 digit pertama
  if (preg_match('/(\d{4})/', $namaSemester, $m2)) {
    return (int)$m2[1];
  }
  return null;
}

function hitung_semester_ke(int $angkatan, string $namaSemesterAktif, string $periode): int {
  $startYear = parse_start_year($namaSemesterAktif);
  if (!$startYear) return 1;

  $periode = strtolower(trim($periode));
  $yearDiff = $startYear - $angkatan;

  if ($yearDiff < 0) return 1;

  $sem = ($periode === 'genap')
    ? ($yearDiff * 2 + 2)
    : ($yearDiff * 2 + 1);

  if ($sem < 1) $sem = 1;
  if ($sem > 8) $sem = 8;

  return $sem;
}

/**
 * Ambil semester aktif dari DB.
 * return: ['id'=>..,'nama'=>..,'periode'=>..] atau null
 */
function get_active_semester(PDO $pdo): ?array {
  $st = $pdo->query("SELECT id, nama, periode FROM semester WHERE is_active=1 LIMIT 1");
  $row = $st->fetch();
  return $row ?: null;
}

/**
 * Sync semester_aktif untuk SEMUA mahasiswa berdasarkan semester aktif sistem.
 * Dipanggil saat admin set semester aktif.
 */
function sync_semester_aktif_semua_mahasiswa(PDO $pdo, string $namaSemesterAktif, string $periode): void {
  $startYear = parse_start_year($namaSemesterAktif);
  if (!$startYear) {
    // kalau format nama semester kacau, jangan update massal
    return;
  }

  $periode = strtolower(trim($periode));
  $offset = ($periode === 'genap') ? 2 : 1;

  // UPDATE massal menggunakan rumus di SQL (clamp 1..8)
  // semester = LEAST(8, GREATEST(1, ((startYear - angkatan)*2 + offset)))
  $sql = "
    UPDATE mahasiswa
    SET semester_aktif = LEAST(8, GREATEST(1, ((? - CAST(angkatan AS SIGNED)) * 2 + ?)))
    WHERE angkatan IS NOT NULL
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$startYear, $offset]);

  // jika angkatan NULL (data lama), amanin jadi 1
  $pdo->exec("UPDATE mahasiswa SET semester_aktif = 1 WHERE angkatan IS NULL");
}
