<?php
// public/index.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/db.php';

// Verificar conexión con la base de datos
try { 
  db(); 
  $db_ok = true; 
} catch (Throwable $e) { 
  $db_ok = false; 
  $db_err = $e->getMessage(); 
}

include __DIR__.'/../views/header.php';
?>
<div class="container" style="display:flex; justify-content:center; align-items:center; min-height:80vh;">
  <div class="card" style="text-align:center; max-width:600px; width:100%; padding:30px;">
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>" 
         alt="ElectroDomus" 
         style="width:110px; height:110px; object-fit:contain; margin-bottom:12px; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.15);">

    <h1 style="color:#0ea5e9; margin:0;">ElectroDomus</h1>
    <p style="margin-top:6px; color:#334155; font-size:15px; font-weight:500;">
      Sistema web para el aprendizaje de electricidad con simulaciones interactivas ⚡
    </p>

    <div style="margin-top:20px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
      <a class="btn" href="<?= htmlspecialchars(base_url('register.php')) ?>" style="padding:10px 20px; font-weight:600;">Crear cuenta</a>
      <a class="btn" href="<?= htmlspecialchars(base_url('login.php')) ?>" style="padding:10px 20px; font-weight:600;">Ingresar</a>
    </div>

    <p style="margin-top:20px; color:#6b7280; font-size:13px;">
      <small>Conexión a base de datos: 
        <?= $db_ok ? '<span style="color:#16a34a;">OK</span>' : '<span style="color:#b91c1c;">Error: '.htmlspecialchars($db_err).'</span>' ?>
      </small>
    </p>
  </div>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>

