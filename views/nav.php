<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../app/helpers.php';
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav" role="navigation" aria-label="Principal">
  <!-- Botón hamburguesa (solo visible en móvil) -->
  <button class="nav-toggle" id="navToggle"
          aria-label="Abrir menú" aria-controls="navLinks" aria-expanded="false">
    ☰
  </button>

  <!-- Contenedor de enlaces -->
  <div class="nav-links" id="navLinks">
    <a href="<?= htmlspecialchars(base_url('dashboard.php')) ?>" class="<?= $current==='dashboard.php'?'active':'' ?>">Inicio</a>
    <a href<?= '="'.htmlspecialchars(base_url('contenidos.php')).'"' ?> class="<?= $current==='contenidos.php'?'active':'' ?>">Contenidos</a>
    <a href="<?= htmlspecialchars(base_url('simulaciones.php')) ?>" class="<?= $current==='simulaciones.php'?'active':'' ?>">Simulaciones</a>
    <a href="<?= htmlspecialchars(base_url('evaluaciones.php')) ?>" class="<?= $current==='evaluaciones.php'?'active':'' ?>">Evaluaciones</a>
    <a href="<?= htmlspecialchars(base_url('reportes.php')) ?>" class="<?= $current==='reportes.php'?'active':'' ?>">Reportes</a>
    <span class="spacer"></span>
    <?php if (!empty($_SESSION['user'])): ?>
      <span class="user-pill">👤 <?= htmlspecialchars($_SESSION['user']['nombre']) ?></span>
      <a href="<?= htmlspecialchars(base_url('logout.php')) ?>">Salir</a>
    <?php else: ?>
      <a href="<?= htmlspecialchars(base_url('login.php')) ?>">Ingresar</a>
      <a href="<?= htmlspecialchars(base_url('register.php')) ?>">Registro</a>
    <?php endif; ?>
  </div>
</nav>

<script>
(function(){
  const btn  = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');
  if (!btn || !links) return;
  btn.addEventListener('click', () => {
    const isOpen = links.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
})();
</script>
