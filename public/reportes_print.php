<?php
// public/reportes_print.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

header('Content-Type: text/html; charset=utf-8');

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }

function is_admin(): bool {
  return !empty($_SESSION['user']) && (($_SESSION['user']['role'] ?? '')==='admin');
}

$pdo = db();

// --- Lectura de parámetros ---
$start   = $_GET['start']   ?? null; // YYYY-MM-DD
$end     = $_GET['end']     ?? null; // YYYY-MM-DD
$user_id = $_GET['user_id'] ?? null; // solo admin
$auto    = isset($_GET['auto']) && $_GET['auto'] == '1';

// Normalizar fechas
$filters = [];
$params  = [];
$subtitle_parts = [];

// Validar y aplicar fechas
if ($start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
  $filters[] = "i.created_at >= ?";
  $params[]  = $start . " 00:00:00";
  $subtitle_parts[] = "desde $start";
} else { $start = null; }

if ($end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
  $filters[] = "i.created_at <= ?";
  $params[]  = $end . " 23:59:59";
  $subtitle_parts[] = "hasta $end";
} else { $end = null; }

// Determinar alcance por usuario
$selected_user_label = null;

if (is_admin()) {
  // Admin: puede ver todos o filtrar por usuario específico
  if ($user_id !== null && is_numeric($user_id) && (int)$user_id > 0) {
    $filters[] = "i.usuario_id = ?";
    $params[]  = (int)$user_id;

    // Cargar nombre del usuario filtrado (para subtítulo)
    $stU = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stU->execute([(int)$user_id]);
    if ($rowU = $stU->fetch()) {
      $selected_user_label = $rowU['nombre'].' <'.$rowU['email'].'>';
      $subtitle_parts[] = "usuario: ".$selected_user_label;
    } else {
      $selected_user_label = "ID $user_id";
      $subtitle_parts[] = "usuario: $selected_user_label";
    }
  } else {
    $subtitle_parts[] = "todos los usuarios";
  }
} else {
  // No admin: obligado a su propio usuario
  $filters[] = "i.usuario_id = ?";
  $params[]  = (int)$user['id'];
  $selected_user_label = $user['nombre'].' <'.($user['email'] ?? '').'>';
  $subtitle_parts[] = "usuario: ".$user['nombre'];
}

// WHERE dinámico
$where = $filters ? ("WHERE " . implode(" AND ", $filters)) : "";

// --- Datos para selector (solo admin) ---
$allUsers = [];
if (is_admin()) {
  $allUsers = $pdo->query("SELECT id, nombre, email FROM usuarios ORDER BY nombre ASC, email ASC")->fetchAll();
}

// --- Consulta principal ---
if (is_admin()) {
  $title = "Reporte general de evaluaciones";
  $sql = "SELECT i.puntaje, i.total_preguntas, i.created_at,
                 u.nombre AS usuario, u.email, e.titulo AS evaluacion
          FROM intentos i
          JOIN usuarios u ON u.id = i.usuario_id
          JOIN evaluaciones e ON e.id = i.evaluacion_id
          $where
          ORDER BY i.created_at DESC";
} else {
  $title = "Reporte de resultados – ".$user['nombre'];
  $sql = "SELECT i.puntaje, i.total_preguntas, i.created_at,
                 e.titulo AS evaluacion
          FROM intentos i
          JOIN evaluaciones e ON e.id = i.evaluacion_id
          $where
          ORDER BY i.created_at DESC";
}
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Subtítulo render
$subtitle = $subtitle_parts ? implode(' · ', $subtitle_parts) : 'Sin filtros';

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>ElectroDomus – <?= htmlspecialchars($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --primary:#0ea5e9; --text:#0f172a; --muted:#334155; --border:#e5e7eb; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:var(--text); margin:24px; }
  header { display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:16px; gap:12px; }
  header .brand { display:flex; align-items:center; gap:12px; min-width:260px; }
  header img { width:56px; height:56px; object-fit:contain; border-radius:12px; }
  h1 { font-size:20px; color:var(--primary); margin:0; }
  .subtitle { color:var(--muted); margin:2px 0 0; }
  .meta { text-align:right; font-size:12px; color:#475569; white-space:nowrap; }
  #printBtn { display:inline-block; padding:8px 12px; border-radius:8px; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; margin:10px 0; }
  .filters { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin: 8px 0 4px; }
  .filters label { font-size:12px; color:#334155; display:flex; flex-direction:column; gap:4px; }
  .filters input, .filters select { padding:8px; border:1px solid var(--border); border-radius:8px; font-size:13px; min-width:160px; }
  .filters button, .filters a { padding:8px 12px; border-radius:8px; text-decoration:none; border:1px solid var(--border); font-weight:600; }
  .filters .apply { background:var(--primary); color:#fff; border:none; }
  table { width:100%; border-collapse:collapse; margin-top:12px; }
  th, td { border:1px solid var(--border); padding:8px; text-align:left; font-size:13px; }
  th { background:#e0f2fe; color:#075985; }
  tfoot td { border:none; font-size:12px; color:#64748b; padding-top:12px; }
  .pct { font-weight:700; color:var(--primary); }
  @media print {
    @page { margin:12mm; }
    #printBtn, .filters { display:none; }
    body { margin:0; }
  }
</style>
</head>
<body>
  <header>
    <div class="brand">
      <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>" alt="ElectroDomus">
      <div>
        <h1>ElectroDomus</h1>
        <div class="subtitle"><?= htmlspecialchars($title) ?><br><small><?= htmlspecialchars($subtitle) ?></small></div>
      </div>
    </div>
    <div class="meta">Fecha de generación: <?= date('d/m/Y H:i') ?></div>
  </header>

  <!-- Filtros (solo visual; submit GET) -->
  <form method="get" class="filters">
    <?php if (is_admin()): ?>
      <label>Usuario
        <select name="user_id">
          <option value="">Todos</option>
          <?php foreach ($allUsers as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (isset($user_id) && $user_id!==''
                && (int)$user_id===(int)$u['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['nombre'].' <'.$u['email'].'>') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <label>Desde
      <input type="date" name="start" value="<?= htmlspecialchars($start ?? '') ?>">
    </label>
    <label>Hasta
      <input type="date" name="end" value="<?= htmlspecialchars($end ?? '') ?>">
    </label>

    <button type="submit" class="apply">Aplicar</button>
    <?php
      // Construimos URL de impresión conservando filtros
      $qs = $_GET;
      $qs['auto'] = '1';
      $printUrl = basename(__FILE__) . '?' . http_build_query($qs);
    ?>
    <a id="printBtn" href="<?= htmlspecialchars($printUrl) ?>">🖨️ Imprimir / Guardar PDF</a>
  </form>

  <table>
    <thead>
      <tr>
        <?php if (is_admin()): ?><th>Usuario</th><?php endif; ?>
        <th>Evaluación</th>
        <th>Aciertos / Total</th>
        <th>%</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= is_admin()?5:4 ?>" style="text-align:center;">No hay intentos registrados con los filtros seleccionados.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $punt  = (int)($r['puntaje'] ?? 0);
          $total = (int)($r['total_preguntas'] ?? 0);
          $pct   = $total>0 ? round($punt*100.0/$total, 1) : 0;
        ?>
          <tr>
            <?php if (is_admin()): ?>
              <td><?= htmlspecialchars(($r['usuario'] ?? '').' <'.($r['email'] ?? '').'>') ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($r['evaluacion']) ?></td>
            <td><?= $punt ?>/<?= $total ?></td>
            <td class="pct"><?= $pct ?>%</td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="<?= is_admin()?5:4 ?>">© <?= date('Y') ?> ElectroDomus – Sistema web para el aprendizaje de electricidad</td>
      </tr>
    </tfoot>
  </table>

  <?php if ($auto): ?><script>window.addEventListener('load',()=>window.print());</script><?php endif; ?>
</body>
</html>
