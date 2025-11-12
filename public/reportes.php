<?php
// public/reportes.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }
function is_admin(): bool { return !empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '')==='admin'; }

$pdo = db();
$uid = (int)$user['id'];

function table_exists(PDO $pdo, string $name): bool {
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return $st->rowCount() > 0;
  } catch (Throwable $e) { return false; }
}

$has_intentos   = table_exists($pdo, 'intentos');
$has_respuestas = table_exists($pdo, 'intentos_respuestas');

$rows = [];
$err = '';

if ($has_intentos) {
  if (is_admin()) {
    // Admin: todos los intentos con aciertos/total/%
    $sql = "SELECT i.id, i.puntaje, i.total_preguntas, i.created_at,
                   u.nombre AS usuario, u.email,
                   e.titulo AS evaluacion
            FROM intentos i
            JOIN usuarios u ON u.id = i.usuario_id
            JOIN evaluaciones e ON e.id = i.evaluacion_id
            ORDER BY i.created_at DESC";
    $rows = $pdo->query($sql)->fetchAll();
  } else {
    // Usuario: sus intentos
    $st = $pdo->prepare("SELECT i.id, i.puntaje, i.total_preguntas, i.created_at,
                                e.titulo AS evaluacion
                         FROM intentos i
                         JOIN evaluaciones e ON e.id = i.evaluacion_id
                         WHERE i.usuario_id = ?
                         ORDER BY i.created_at DESC");
    $st->execute([$uid]);
    $rows = $st->fetchAll();
  }
} else {
  $err = 'Aún no existen registros de intentos. Termina una evaluación para ver resultados, o crea las tablas de intentos (abajo).';
}

include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container" style="display:flex; flex-direction:column; gap:16px;">
  <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
    <div>
      <h2 style="margin:0;">Reportes de progreso</h2>
      <p class="muted" style="margin:4px 0 0;">Resultados por intento y tendencia de puntajes.</p>
    </div>
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>" alt="ElectroDomus" width="56" height="56" style="border-radius:12px;object-fit:contain;">
  </div>

  <?php if ($err): ?>
    <div class="card" style="background:#fffbeb; border:1px solid #fbbf24;">
      <p style="margin:0;"><strong>Nota:</strong> <?= htmlspecialchars($err) ?></p>
    </div>
  <?php endif; ?>

<div class="card">
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <?php if (is_admin()): ?>
      <a class="btn" href="<?= htmlspecialchars(base_url('reportes_csv.php?scope=all')) ?>">⬇️ Exportar CSV (todos)</a>
    <?php else: ?>
      <a class="btn" href="<?= htmlspecialchars(base_url('reportes_csv.php?scope=mine')) ?>">⬇️ Exportar CSV (mis intentos)</a>
    <?php endif; ?>
    <a class="btn" target="_blank" href="<?= htmlspecialchars(base_url('reportes_print.php?auto=1')) ?>">📄 Exportar PDF</a>
  </div>
</div>


  <?php if (!$has_intentos): ?>
    <div class="card">
      <h3 style="margin-top:0;">Crear estructura de intentos (si falta)</h3>
      <pre style="white-space:pre-wrap; background:#fff; border:1px solid #e5e7eb; padding:10px; border-radius:8px;">
CREATE TABLE IF NOT EXISTS intentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  evaluacion_id INT NOT NULL,
  puntaje INT NOT NULL,
  total_preguntas INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (usuario_id),
  KEY idx_eval (evaluacion_id)
);

CREATE TABLE IF NOT EXISTS intentos_respuestas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  intento_id INT NOT NULL,
  pregunta_id INT NOT NULL,
  correcta TINYINT(1) NOT NULL,
  respuesta_usuario TEXT,
  respuesta_correcta TEXT,
  KEY idx_intento (intento_id),
  KEY idx_preg (pregunta_id)
);</pre>
    </div>
  <?php endif; ?>

  <div class="card" style="overflow-x:auto;">
    <h3 style="margin-top:0;">📋 Intentos</h3>
    <?php if (!$rows): ?>
      <p>No hay intentos registrados aún.</p>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <?php if (is_admin()): ?><th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Usuario</th><?php endif; ?>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Evaluación</th>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Aciertos</th>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Total</th>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">%</th>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $punt  = (int)($r['puntaje'] ?? 0);
            $total = (int)($r['total_preguntas'] ?? 0);
            $pct   = $total>0 ? round($punt*100.0/$total, 1) : 0;
          ?>
            <tr>
              <?php if (is_admin()): ?>
                <td style="padding:8px; border-bottom:1px solid #e5e7eb;">
                  <?= htmlspecialchars(($r['usuario'] ?? '').' <'.($r['email'] ?? '').'>') ?>
                </td>
              <?php endif; ?>
              <td style="padding:8px; border-bottom:1px solid #e5e7eb;"><?= htmlspecialchars($r['evaluacion']) ?></td>
              <td style="padding:8px; border-bottom:1px solid #e5e7eb;"><?= $punt ?></td>
              <td style="padding:8px; border-bottom:1px solid #e5e7eb;"><?= $total ?></td>
              <td style="padding:8px; border-bottom:1px solid #e5e7eb; font-weight:700; color:#0ea5e9;"><?= $pct ?>%</td>
              <td style="padding:8px; border-bottom:1px solid #e5e7eb; white-space:nowrap;"><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if ($rows && !is_admin()): ?>
    <div class="card">
      <h3 style="margin-top:0;">📈 Tendencia de mis resultados (%)</h3>
      <canvas id="chartPuntajes" width="680" height="280"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      (function(){
        const data = <?= json_encode(array_map(function($r){
          $punt  = (int)($r['puntaje'] ?? 0);
          $total = (int)($r['total_preguntas'] ?? 0);
          $pct   = $total>0 ? round($punt*100.0/$total,1) : 0;
          return ['fecha'=>$r['created_at'], 'pct'=>$pct];
        }, array_reverse($rows)), JSON_UNESCAPED_UNICODE); ?>;

        const ctx = document.getElementById('chartPuntajes').getContext('2d');
        new Chart(ctx, {
          type: 'line',
          data: {
            labels: data.map(d => d.fecha),
            datasets: [{
              label: 'Puntaje (%)',
              data: data.map(d => d.pct),
              fill: false
            }]
          },
          options: {
            responsive: true,
            scales: { y: { suggestedMin: 0, suggestedMax: 100 } }
          }
        });
      })();
    </script>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
