<?php
// app/helpers.php
function base_url(string $path = ''): string {
  static $base = null;
  if ($base === null) {
    $config = require __DIR__ . '/config.php';
    $base = rtrim($config['app']['base_url'], '/');
  }
  $path = ltrim($path, '/');
  return $path ? ($base . '/' . $path) : $base . '/';
}
// --- CSRF helpers ---
function csrf_token(): string {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_verify(string $token): bool {
  if (session_status() === PHP_SESSION_NONE) session_start();
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
