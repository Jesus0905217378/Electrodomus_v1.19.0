<?php
// public/login.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';

// Si ya hay sesión iniciada, al panel
if (auth_user()) { header('Location: '.base_url('dashboard.php')); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) {
    $err = 'Token CSRF inválido. Recarga la página.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (auth_login($email, $pass)) {
      header('Location: '.base_url('dashboard.php'));
      exit;
    } else {
      $err = 'Credenciales inválidas.';
    }
  }
}

include __DIR__.'/../views/header.php';
?>
<div class="container" style="display:flex; justify-content:center;">
  <div class="card" style="text-align:center; max-width:420px; width:100%;">
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>"
         alt="ElectroDomus"
         style="width:100px; height:100px; object-fit:contain; margin:8px auto 10px; border-radius:16px;">
    <h2 style="color:#0ea5e9; margin:0;">ElectroDomus</h2>
    <p class="muted" style="margin:4px 0 16px;">Adentrate al Mundo de la Electricidad</p>

    <?php if (isset($_GET['reg'])): ?>
      <p style="color:#15803d; margin:0 0 10px;">Registro exitoso. Inicia sesión.</p>
    <?php endif; ?>
    <?php if ($err): ?>
      <p style="color:#b91c1c; margin:0 0 10px;"><?= htmlspecialchars($err) ?></p>
    <?php endif; ?>

    <form method="post" action="" style="display:flex; flex-direction:column; align-items:center; gap:10px;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <label class="label" for="email" style="width:260px; text-align:left;">Email</label>
      <input class="input" id="email" name="email" type="email"
             required autocomplete="username" autofocus
             style="width:260px;">

      <label class="label" for="password" style="width:260px; text-align:left; margin-top:4px;">Contraseña</label>
      <input class="input" id="password" name="password" type="password"
             required autocomplete="current-password"
             style="width:260px;">

      <label style="display:flex; align-items:center; gap:6px; width:260px; text-align:left; font-size:14px; color:#334155;">
        <input type="checkbox" id="showpass" onclick="document.getElementById('password').type=this.checked?'text':'password'">
        Mostrar contraseña
      </label>

      <button class="btn" style="width:140px; margin-top:6px;">Entrar</button>
    </form>
  </div>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
