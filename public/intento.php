<?php
// public/intento.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }

$pdo = db();
$uid = (int)$user['id'];
$eval_id = (int)($_GET['id'] ?? 0);

// Carga evaluación
$eval = null;
if ($eval_id > 0) {
  $st = $pdo->prepare("SELECT * FROM evaluaciones WHERE id=?");
  $st->execute([$eval_id]);
  $eval = $st->fetch();
}
if (!$eval) {
  include __DIR__.'/../views/header.php';
  echo '<div class="container"><div class="card"><p>Evaluación no encontrada.</p></div></div>';
  include __DIR__.'/../views/footer.php';
  exit;
}

// Preguntas de la evaluación
$pregs = $pdo->prepare("SELECT * FROM preguntas WHERE evaluacion_id=? ORDER BY id ASC");
$pregs->execute([$eval_id]);
$pregs = $pregs->fetchAll();

$resultado = null; // se llena al enviar
$ok=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='submit') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) {
    $err='CSRF inválido.';
  } else {
    // Calificar
    $aciertos = 0;
    $detalles = []; // por pregunta: correcto, respuesta_usuario, respuesta_correcta

    foreach ($pregs as $p) {
      $qid = (int)$p['id'];
      $tipo = $p['tipo'];
      $resp_correcta = json_decode($p['respuesta_correcta'] ?? '[]', true);
      $resp_user = $_POST['q_'.$qid] ?? null;

      $correct = false;
      $rc_text = '';
      $ru_text = '';

      if ($tipo==='opcion_multiple') {
        $opcs = json_decode($p['opciones'] ?? '[]', true);
        $correct_idx = is_array($resp_correcta) ? ($resp_correcta[0] ?? -1) : -1;

        // Texto correcto
        $rc_text = ($correct_idx>=0 && isset($opcs[$correct_idx])) ? $opcs[$correct_idx] : '(sin definir)';
        // Texto usuario
        $user_idx = is_numeric($resp_user) ? (int)$resp_user : -1;
        $ru_text = ($user_idx>=0 && isset($opcs[$user_idx])) ? $opcs[$user_idx] : '(sin respuesta)';

        $correct = ($user_idx === $correct_idx);
      } else { // verdadero_falso
        $rc = is_array($resp_correcta) ? ($resp_correcta[0] ?? false) : false; // bool
        $rc_text = $rc ? 'Verdadero' : 'Falso';
        $ru_text = ($resp_user==='true') ? 'Verdadero' : (($resp_user==='false') ? 'Falso' : '(sin respuesta)');
        $correct = (($resp_user==='true') === (bool)$rc);
      }

      if ($correct) $aciertos++;

      $detalles[] = [
        'id' => $qid,
        'enunciado' => $p['enunciado'],
        'tipo' => $tipo,
        'correcto' => $correct,
        'respuesta_usuario' => $ru_text,
        'respuesta_correcta' => $rc_text,
      ];
    }

    $total = count($pregs);
    $porc = $total>0 ? round(($aciertos/$total)*100) : 0;
    $resultado = compact('aciertos','total','porc','detalles');

    // (Opcional) Guardar intento si existen tablas
    try {
      $hasIntentos = $pdo->query("SHOW TABLES LIKE 'intentos'")->rowCount() > 0;
      if ($hasIntentos) {
        // Crear intento
        $st = $pdo->prepare("INSERT INTO intentos (usuario_id, evaluacion_id, puntaje, total_preguntas) VALUES (?,?,?,?)");
        $st->execute([$uid, $eval_id, $aciertos, $total]);
        $intento_id = (int)$pdo->lastInsertId();

        // Guardar respuestas
        $st2 = $pdo->prepare("INSERT INTO intentos_respuestas (intento_id, pregunta_id, correcta, respuesta_usuario, respuesta_correcta) VALUES (?,?,?,?,?)");
        foreach ($resultado['detalles'] as $d) {
          $st2->execute([
            $intento_id,
            (int)$d['id'],
            $d['correcto'] ? 1 : 0,
            $d['respuesta_usuario'],
            $d['respuesta_correcta'],
          ]);
        }
      }
    } catch (Throwable $e) {
      // Silencioso: si la estructura no existe, no rompemos el flujo
    }
  }
}

include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container">
  <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
    <div>
      <h2 style="margin:0;"><?= htmlspecialchars($eval['titulo']) ?></h2>
      <p class="muted" style="margin:4px 0 0;"><?= nl2br(htmlspecialchars($eval['descripcion'] ?? '')) ?></p>
    </div>
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>" alt="ElectroDomus" width="56" height="56" style="border-radius:12px;object-fit:contain;">
  </div>

  <?php if ($err): ?><div class="card"><p style="color:#b91c1c; margin:0;"><?= htmlspecialchars($err) ?></p></div><?php endif; ?>

  <?php if (!$resultado): ?>
    <!-- Formulario de evaluación -->
    <form method="post" class="card">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="submit">

      <?php if (!$pregs): ?>
        <p>No hay preguntas cargadas.</p>
      <?php endif; ?>

      <?php foreach ($pregs as $idx => $p): 
        $n = $idx + 1; ?>
        <div style="padding:12px; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:10px;">
          <p style="margin:0 0 8px;"><strong><?= $n ?>.</strong> <?= htmlspecialchars($p['enunciado']) ?></p>

          <?php if ($p['tipo']==='opcion_multiple'):
            $opcs = json_decode($p['opciones'] ?? '[]', true) ?: []; ?>
            <?php foreach ($opcs as $i => $txt): ?>
              <label style="display:flex; align-items:center; gap:8px; margin:6px 0;">
                <input type="radio" name="q_<?= (int)$p['id'] ?>" value="<?= $i ?>">
                <span><?= htmlspecialchars($txt) ?></span>
              </label>
            <?php endforeach; ?>
          <?php else: ?>
            <label style="display:flex; align-items:center; gap:8px; margin:6px 0;">
              <input type="radio" name="q_<?= (int)$p['id'] ?>" value="true"> <span>Verdadero</span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; margin:6px 0;">
              <input type="radio" name="q_<?= (int)$p['id'] ?>" value="false"> <span>Falso</span>
            </label>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <button class="btn">Enviar y ver resultados</button>
    </form>

  <?php else: ?>
    <!-- Resultados -->
    <div class="card">
      <h3 style="margin-top:0;">Resultados</h3>
      <p style="margin:6px 0;">
        Aciertos: <strong><?= (int)$resultado['aciertos'] ?></strong> / <?= (int)$resultado['total'] ?>  
        · Puntaje: <strong><?= (int)$resultado['porc'] ?>%</strong>
      </p>
      <div style="height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin:8px 0 12px;">
        <div style="height:100%; width:<?= (int)$resultado['porc'] ?>%; background:#0ea5e9;"></div>
      </div>

      <?php foreach ($resultado['detalles'] as $k => $d): ?>
        <div style="padding:12px; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:10px;">
          <p style="margin:0 0 6px;">
            <strong><?= $k+1 ?>.</strong> <?= htmlspecialchars($d['enunciado']) ?>
          </p>
          <?php if ($d['correcto']): ?>
            <p style="margin:0; color:#166534; background:#dcfce7; border:1px solid #86efac; padding:6px 8px; border-radius:8px;">
              ✔ Correcto. Tu respuesta: <strong><?= htmlspecialchars($d['respuesta_usuario']) ?></strong>
            </p>
          <?php else: ?>
            <p style="margin:0; color:#991b1b; background:#fee2e2; border:1px solid #fca5a5; padding:6px 8px; border-radius:8px;">
              ✖ Incorrecto. Tu respuesta: <strong><?= htmlspecialchars($d['respuesta_usuario']) ?></strong>
            </p>
            <p style="margin:6px 0 0; color:#1f2937;">
              Respuesta correcta: <strong><?= htmlspecialchars($d['respuesta_correcta']) ?></strong>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a class="btn" href="<?= htmlspecialchars(base_url('evaluaciones.php')) ?>">Volver a evaluaciones</a>
        <a class="btn" href="<?= htmlspecialchars(base_url('intento.php?id='.$eval_id)) ?>">Reintentar</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
