<?php
// public/dashboard.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }

$is_admin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

// Datos rápidos (opcionales: si alguna tabla no existe, no rompe)
$stats = ['contenidos'=>0,'evaluaciones'=>0,'intentos'=>0];
$last_attempt = null;
try {
  $pdo = db();
  $stats['contenidos']   = (int)$pdo->query("SELECT COUNT(*) FROM contenidos")->fetchColumn();
  $stats['evaluaciones'] = (int)$pdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();

  // intentos del usuario (si existe tabla)
  $hasIntentos = $pdo->query("SHOW TABLES LIKE 'intentos'")->rowCount() > 0;
  if ($hasIntentos) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM intentos WHERE usuario_id=?");
    $st->execute([$user['id']]);
    $stats['intentos'] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT created_at FROM intentos WHERE usuario_id=? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$user['id']]);
    $last_attempt = $st->fetchColumn() ?: null;
  }
} catch (Throwable $e) {
  // Si la BD no responde, solo mostramos el panel sin métricas
}
include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container" style="display:flex; flex-direction:column; gap:16px;">
  <!-- Encabezado -->
  <div class="card" style="display:flex; align-items:center; gap:16px;">
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>"
         alt="ElectroDomus" width="56" height="56"
         style="border-radius:12px; object-fit:contain;">
    <div>
      <h2 style="margin:0;">Bienvenido, <?= htmlspecialchars($user['nombre']) ?> 👋</h2>
      <p class="muted" style="margin:4px 0 0;">
        ElectroDomus · Electricidad para todos
        <?php if ($last_attempt): ?>
          · Última actividad: <strong><?= htmlspecialchars($last_attempt) ?></strong>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <!-- Acciones rápidas -->
  <div class="grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
    <a class="card" href="<?= htmlspecialchars(base_url('contenidos.php')) ?>" style="text-decoration:none;">
      <h3 style="margin:0 0 6px;">📚 Contenidos</h3>
      <p class="muted" style="margin:0;">Teoría paso a paso de instalaciones.</p>
      <p style="margin:10px 0 0; font-weight:700; color:#0ea5e9;"><?= (int)$stats['contenidos'] ?> módulos</p>
    </a>

    <a class="card" href="<?= htmlspecialchars(base_url('simulaciones.php')) ?>" style="text-decoration:none;">
      <h3 style="margin:0 0 6px;">🧪 Simulaciones</h3>
      <p class="muted" style="margin:0;">Foco, tomas, bomba con flotador, 3-way.</p>
      <p style="margin:10px 0 0; font-weight:700; color:#0ea5e9;">Interactivo</p>
    </a>

    <a class="card" href<?= "=\"".htmlspecialchars(base_url('evaluaciones.php'))."\"" ?> style="text-decoration:none;">
      <h3 style="margin:0 0 6px;">📝 Evaluaciones</h3>
      <p class="muted" style="margin:0;">Comprueba tu aprendizaje.</p>
      <p style="margin:10px 0 0; font-weight:700; color:#0ea5e9;"><?= (int)$stats['evaluaciones'] ?> pruebas · <?= (int)$stats['intentos'] ?> intentos</p>
    </a>

    <a class="card" href="<?= htmlspecialchars(base_url('reportes.php')) ?>" style="text-decoration:none;">
      <h3 style="margin:0 0 6px;">📈 Reportes</h3>
      <p class="muted" style="margin:0;">Progreso y resultados.</p>
      <p style="margin:10px 0 0; font-weight:700; color:#0ea5e9;">Vista resumida</p>
    </a>
  </div>

  <?php if ($is_admin): ?>
    <!-- Solo Admin -->
    <div class="card">
      <h3 style="margin-top:0;">🔧 Zona administrativa</h3>
      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <a class="btn" href="<?= htmlspecialchars(base_url('contenidos.php')) ?>">➕ Nuevo contenido</a>
        <a class="btn" href="<?= htmlspecialchars(base_url('evaluaciones.php')) ?>">➕ Nueva evaluación</a>
        <a class="btn" href="<?= htmlspecialchars(base_url('simulaciones.php')) ?>">⚙️ Ajustar simulaciones</a>
        <a class="btn" href="<?= htmlspecialchars(base_url('reportes.php')) ?>">📊 Revisar reportes</a>
      </div>
      <p class="muted" style="margin-top:8px;">Consejo: usa las flechas ⬆️⬇️ para ordenar módulos y evaluaciones.</p>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
