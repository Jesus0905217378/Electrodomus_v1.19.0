<?php
// public/evaluaciones.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }
function is_admin(): bool { return !empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '')==='admin'; }

$pdo = db();
$ok=''; $err='';

/* Helpers de orden */
function eval_get(PDO $pdo, int $id) {
  $st = $pdo->prepare("SELECT * FROM evaluaciones WHERE id=?");
  $st->execute([$id]);
  return $st->fetch();
}
function eval_max_orden(PDO $pdo): int {
  $st = $pdo->query("SELECT COALESCE(MAX(orden),0) AS m FROM evaluaciones");
  return (int)($st->fetch()['m'] ?? 0);
}

/* Crear evaluación (ADMIN) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create_eval') {
  if (!is_admin()) $err='No autorizado.';
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) $err='CSRF inválido.';
  else {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = (isset($_POST['orden']) && $_POST['orden']!=='') ? (int)$_POST['orden'] : null;
    if (!$titulo) $err='El título es obligatorio.';
    else {
      $stmt=$pdo->prepare('INSERT INTO evaluaciones (orden, titulo, descripcion) VALUES (?,?,?)');
      $stmt->execute([$orden, $titulo, $descripcion]);
      $ok='Evaluación creada.';
    }
  }
}

/* Reordenar evaluación (ADMIN) */
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array(($_POST['action'] ?? ''), ['move_up','move_down'], true)) {
  if (!is_admin()) $err='No autorizado.';
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) $err='CSRF inválido.';
  else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $pdo->beginTransaction();
      try {
        $row = eval_get($pdo, $id);
        if ($row) {
          if ($row['orden'] === null) {
            $newOrd = eval_max_orden($pdo) + 1;
            $pdo->prepare("UPDATE evaluaciones SET orden=? WHERE id=?")->execute([$newOrd, $id]);
            $row['orden'] = $newOrd;
          }
          if (($_POST['action'] ?? '')==='move_up') {
            $st = $pdo->prepare("SELECT id, orden FROM evaluaciones WHERE orden IS NOT NULL AND orden < ? ORDER BY orden DESC LIMIT 1");
            $st->execute([$row['orden']]);
            $adj = $st->fetch();
          } else {
            $st = $pdo->prepare("SELECT id, orden FROM evaluaciones WHERE orden IS NOT NULL AND orden > ? ORDER BY orden ASC LIMIT 1");
            $st->execute([$row['orden']]);
            $adj = $st->fetch();
            if (!$adj) {
              $newOrd = eval_max_orden($pdo) + 1;
              $pdo->prepare("UPDATE evaluaciones SET orden=? WHERE id=?")->execute([$newOrd, $id]);
              $ok = 'Movida al final.';
              $pdo->commit();
              goto after_move;
            }
          }
          if ($adj) {
            $tmp = -1 * (time() % 1000000);
            $pdo->prepare("UPDATE evaluaciones SET orden=? WHERE id=?")->execute([$tmp, $row['id']]);
            $pdo->prepare("UPDATE evaluaciones SET orden=? WHERE id=?")->execute([$row['orden'], $adj['id']]);
            $pdo->prepare("UPDATE evaluaciones SET orden=? WHERE id=?")->execute([$adj['orden'], $row['id']]);
            $ok = (($_POST['action'] ?? '')==='move_up') ? 'Movida arriba.' : 'Movida abajo.';
          }
        }
        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        $err = 'No se pudo reordenar: '.$e->getMessage();
      }
    }
  }
}
after_move:

/* Agregar pregunta (ADMIN) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add_q') {
  if (!is_admin()) $err='No autorizado.';
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) $err='CSRF inválido.';
  else {
    $evaluacion_id=(int)($_POST['evaluacion_id'] ?? 0);
    $enunciado=trim($_POST['enunciado'] ?? '');
    $tipo=$_POST['tipo'] ?? 'opcion_multiple';
    $opciones=null; $respuesta=null;

    if ($tipo==='opcion_multiple') {
      $raw = trim($_POST['opciones'] ?? '');
      $ops = array_values(array_filter(array_map('trim', preg_split("/\\r?\\n/",$raw))));
      $opciones = json_encode($ops, JSON_UNESCAPED_UNICODE);
      $correcta = (int)($_POST['indice_correcto'] ?? -1);
      $respuesta = json_encode([$correcta], JSON_UNESCAPED_UNICODE);
      if ($correcta<0 || $correcta>=count($ops)) $err='Índice correcto fuera de rango.';
    } else {
      $valor = ($_POST['vf_valor'] ?? 'true')==='true';
      $respuesta = json_encode([$valor], JSON_UNESCAPED_UNICODE);
      $opciones = null;
    }

    if (!$err) {
      if (!$evaluacion_id || !$enunciado) $err='Datos incompletos.';
      else {
        $stmt=$pdo->prepare('INSERT INTO preguntas (evaluacion_id,enunciado,tipo,opciones,respuesta_correcta) VALUES (?,?,?,?,?)');
        $stmt->execute([$evaluacion_id,$enunciado,$tipo,$opciones,$respuesta]);
        $ok='Pregunta agregada.';
      }
    }
  }
}

/* Eliminar evaluación (ADMIN) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='delete_eval') {
  if (!is_admin()) $err='No autorizado.';
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) $err='CSRF inválido.';
  else {
    $id=(int)($_POST['id'] ?? 0);
    if ($id>0) {
      $pdo->prepare('DELETE FROM evaluaciones WHERE id=?')->execute([$id]);
      $ok='Evaluación eliminada.';
    }
  }
}

/* Listado con conteo de preguntas */
$sql = "SELECT e.*,
        (SELECT COUNT(*) FROM preguntas p WHERE p.evaluacion_id=e.id) AS n_pregs
        FROM evaluaciones e
        ORDER BY COALESCE(e.orden,999999) ASC, e.created_at ASC, e.id ASC";
$evals = $pdo->query($sql)->fetchAll();

include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container">
  <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
    <div>
      <h2 style="margin:0;">Evaluaciones</h2>
      <p class="muted" style="margin:4px 0 0;">Pon a prueba tu aprendizaje.</p>
    </div>
    <img src="<?= htmlspecialchars(base_url('assets/img/logo.png')) ?>" alt="ElectroDomus" width="56" height="56" style="border-radius:12px;object-fit:contain;">
  </div>

  <?php if($ok): ?><div class="card"><p style="color:#15803d; margin:0;"><?= htmlspecialchars($ok) ?></p></div><?php endif; ?>
  <?php if($err): ?><div class="card"><p style="color:#b91c1c; margin:0;"><?= htmlspecialchars($err) ?></p></div><?php endif; ?>

  <?php if (is_admin()): ?>
    <div class="card">
      <details>
        <summary>➕ Nueva evaluación</summary>
        <form method="post" style="margin-top:8px;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_eval">
          <label class="label">Orden (número)</label>
          <input class="input" type="number" name="orden" min="1" placeholder="1">
          <small class="muted">Si lo dejas vacío, se enviará al final.</small>
          <label class="label">Título</label>
          <input class="input" name="titulo" required>
          <label class="label">Descripción</label>
          <input class="input" name="descripcion">
          <br><button class="btn">Crear</button>
        </form>
      </details>
    </div>
  <?php endif; ?>

  <div class="grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px;">
    <?php foreach($evals as $e): ?>
      <div class="card" style="display:flex; flex-direction:column; gap:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
          <div>
            <h3 style="margin:0;"><?= htmlspecialchars($e['titulo']) ?></h3>
            <small class="muted">
              <?php if (!is_null($e['orden'])): ?>Orden: <?= (int)$e['orden'] ?> · <?php endif; ?>
              <?= (int)$e['n_pregs'] ?> preguntas
            </small>
          </div>
          <?php if (is_admin()): ?>
            <div style="display:flex; gap:6px;">
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="move_up">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="btn" title="Subir">⬆️</button>
              </form>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="move_down">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="btn" title="Bajar">⬇️</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <p style="margin:0;"><?= nl2br(htmlspecialchars($e['descripcion'] ?? '')) ?></p>

        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
          <a class="btn" href="<?= htmlspecialchars(base_url('intento.php?id='.$e['id'])) ?>">Iniciar</a>

          <?php if (is_admin()): ?>
            <details>
              <summary>✏️ Agregar pregunta</summary>
              <form method="post" style="margin-top:8px;">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_q">
                <input type="hidden" name="evaluacion_id" value="<?= (int)$e['id'] ?>">

                <label class="label">Enunciado</label>
                <textarea class="input" name="enunciado" rows="3" required></textarea>

                <label class="label">Tipo</label>
                <select class="input" name="tipo" onchange="
                  const f=this.closest('form');
                  f.querySelector('.pm').style.display=(this.value==='opcion_multiple')?'block':'none';
                  f.querySelector('.vf').style.display=(this.value==='verdadero_falso')?'block':'none';
                ">
                  <option value="opcion_multiple">Opción múltiple</option>
                  <option value="verdadero_falso">Verdadero/Falso</option>
                </select>

                <div class="pm" style="margin-top:8px;">
                  <label class="label">Opciones (una por línea)</label>
                  <textarea class="input" name="opciones" rows="4" placeholder="Opción A&#10;Opción B&#10;Opción C"></textarea>
                  <label class="label">Índice correcto (0 = la primera)</label>
                  <input class="input" type="number" name="indice_correcto" min="0" value="0">
                </div>

                <div class="vf" style="display:none; margin-top:8px;">
                  <label class="label">Respuesta correcta</label>
                  <select class="input" name="vf_valor">
                    <option value="true">Verdadero</option>
                    <option value="false">Falso</option>
                  </select>
                </div>

                <br><button class="btn">Agregar pregunta</button>
              </form>
            </details>

            <form method="post" style="margin-top:8px;" onsubmit="return confirm('¿Eliminar esta evaluación?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_eval">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <button class="btn" style="background:#ef4444">Eliminar</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
