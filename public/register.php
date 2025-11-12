<?php 
// public/register.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) {
    $err = 'Token CSRF inválido. Recarga la página.';
  } else {
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    if ($nombre && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pass) >= 6) {
      if (auth_register($nombre, $email, $pass)) {
        header('Location: '.base_url('login.php').'?reg=1');
        exit;
      } else {
        $err = 'No se pudo registrar (¿email ya existe?).';
      }
    } else {
      $err = 'Completa todos los campos (contraseña mínimo 6 caracteres).';
    }
  }
}

include __DIR__.'/../views/header.php';
?>
<div class="container" style="display:flex; justify-content:center; align-items:center; min-height:80vh;">
  <div class="card" style="text-align:center; max-width:460px; width:100%;">
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>"
         alt="ElectroDomus"
         style="width:100px; height:100px; object-fit:contain; margin-bottom:10px; border-radius:16px;">
    <h2 style="color:#0ea5e9; margin:0;">ElectroDomus</h2>
    <p class="muted" style="margin-top:4px;">Registro de nuevo usuario</p>

    <?php if ($err): ?>
      <p style="color:#b91c1c; margin-top:10px;"><?= htmlspecialchars($err) ?></p>
    <?php endif; ?>

    <form method="post" action="" style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:16px;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <label class="label" for="nombre" style="width:260px; text-align:left;">Nombre</label>
      <input class="input" id="nombre" name="nombre" required style="width:260px;">

      <label class="label" for="email" style="width:260px; text-align:left;">Email</label>
      <input class="input" id="email" type="email" name="email" required style="width:260px;">

      <label class="label" for="password" style="width:260px; text-align:left;">Contraseña</label>
      <input class="input" id="password" type="password" name="password" minlength="6" required style="width:260px;">

      <label style="display:flex; align-items:center; gap:6px; width:260px; text-align:left; font-size:14px; color:#334155;">
        <input type="checkbox" onclick="document.getElementById('password').type=this.checked?'text':'password'">
        Mostrar contraseña
      </label>

      <button class="btn" style="width:180px; margin-top:10px;">Crear cuenta</button>
    </form>

    <p style="margin-top:18px; font-size:14px; color:#334155;">
      ¿Ya tienes cuenta?
      <a href="<?= htmlspecialchars(base_url('login.php')) ?>" style="color:#0ea5e9; font-weight:600;">Inicia sesión</a>
    </p>
  </div>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
