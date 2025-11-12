<?php
// public/reportes_csv.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }
function is_admin(): bool { return !empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '')==='admin'; }

$pdo = db();

// Parámetros
$scope = $_GET['scope'] ?? 'mine'; // 'mine' | 'all' (solo admin)
$start = $_GET['start'] ?? null;   // YYYY-MM-DD
$end   = $_GET['end'] ?? null;     // YYYY-MM-DD

// Normalizar fechas (si llegan mal, se ignoran)
$filters = [];
$params  = [];

if ($start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
  $filters[] = "i.created_at >= ?";
  $params[]  = $start . " 00:00:00";
}
if ($end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
  $filters[] = "i.created_at <= ?";
  $params[]  = $end . " 23:59:59";
}

// Scope
if ($scope === 'all') {
  if (!is_admin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 - No autorizado para exportar todos los usuarios.";
    exit;
  }
} else {
  $filters[] = "i.usuario_id = ?";
  $params[]  = (int)$user['id'];
}

// WHERE dinámico
$where = $filters ? ("WHERE " . implode(" AND ", $filters)) : "";

// Encabezados CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reportes.csv"');

// BOM para Excel (¡importante!)
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

if ($scope === 'all') {
  fputcsv($output, ['Usuario','Email','Evaluación','Aciertos','Total','%','Fecha']);

  $sql = "SELECT u.nombre AS usuario, u.email, e.titulo AS evaluacion,
                 i.puntaje, i.total_preguntas, i.created_at
          FROM intentos i
          JOIN usuarios u ON u.id = i.usuario_id
          JOIN evaluaciones e ON e.id = i.evaluacion_id
          $where
          ORDER BY i.created_at DESC";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $total = (int)$row['total_preguntas'];
    $punt  = (int)$row['puntaje'];
    $pct   = $total > 0 ? round($punt * 100.0 / $total, 1) : 0;
    fputcsv($output, [
      $row['usuario'],
      $row['email'],
      $row['evaluacion'],
      $punt,
      $total,
      $pct.'%',
      $row['created_at']
    ]);
  }

} else {
  fputcsv($output, ['Evaluación','Aciertos','Total','%','Fecha']);

  $sql = "SELECT e.titulo AS evaluacion, i.puntaje, i.total_preguntas, i.created_at
          FROM intentos i
          JOIN evaluaciones e ON e.id = i.evaluacion_id
          $where
          ORDER BY i.created_at DESC";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $total = (int)$row['total_preguntas'];
    $punt  = (int)$row['puntaje'];
    $pct   = $total > 0 ? round($punt * 100.0 / $total, 1) : 0;
    fputcsv($output, [
      $row['evaluacion'],
      $punt,
      $total,
      $pct.'%',
      $row['created_at']
    ]);
  }
}

fclose($output);
