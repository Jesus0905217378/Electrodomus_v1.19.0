<?php
// app/auth.php
require_once __DIR__.'/db.php';

function auth_register(string $nombre, string $email, string $password): bool {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $sql = 'INSERT INTO usuarios (nombre,email,password_hash) VALUES (?,?,?)';
  $stmt = db()->prepare($sql);
  try {
    return $stmt->execute([$nombre, $email, $hash]);
  } catch (Throwable $e) {
    // email duplicado u otro error
    return false;
  }
}

function auth_login(string $email, string $password): bool {
  $stmt = db()->prepare('SELECT * FROM usuarios WHERE email=? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if ($u && password_verify($password, $u['password_hash'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = [
      'id'     => $u['id'],
      'nombre' => $u['nombre'],
      'email'  => $u['email'],
      'role'   => $u['role'],
    ];
    return true;
  }
  return false;
}

function auth_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function auth_logout(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}
