<?php
// public/contenidos.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }

function is_admin(): bool { return !empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '')==='admin'; }

$err = ''; $ok = '';
$pdo = db();
$uid = (int)$user['id'];

/* ============================
   Helpers internos
   ============================ */
function get_max_orden(PDO $pdo): int {
  $max = (int)$pdo->query("SELECT COALESCE(MAX(orden),0) AS m FROM contenidos")->fetch()['m'];
  return $max;
}
function fetch_row(PDO $pdo, int $id) {
  $st = $pdo->prepare("SELECT * FROM contenidos WHERE id=?");
  $st->execute([$id]);
  return $st->fetch();
}

/* ============================
   Acciones de visto / no visto
   ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='mark_seen') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) { $err='CSRF inválido.'; }
  else {
    $cid = (int)($_POST['id'] ?? 0);
    if ($cid>0) {
      // INSERT IGNORE estilo MySQL
      $st = $pdo->prepare("INSERT IGNORE INTO contenidos_vistos (usuario_id, contenido_id) VALUES (?,?)");
      $st->execute([$uid, $cid]);
      $ok = 'Marcado como visto.';
    }
  }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='mark_unseen') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) { $err='CSRF inválido.'; }
  else {
    $cid = (int)($_POST['id'] ?? 0);
    if ($cid>0) {
      $st = $pdo->prepare("DELETE FROM contenidos_vistos WHERE usuario_id=? AND contenido_id=?");
      $st->execute([$uid, $cid]);
      $ok = 'Marcado como pendiente.';
    }
  }
}

/* ============================
   Crear contenido (ADMIN)
   ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create') {
  if (!is_admin()) { $err = 'No autorizado.'; }
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) { $err = 'CSRF inválido.'; }
  else {
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cuerpo      = $_POST['cuerpo'] ?? '';
    $orden       = (isset($_POST['orden']) && $_POST['orden']!=='') ? (int)$_POST['orden'] : null;

    if (!$titulo) $err = 'El título es obligatorio.';
    else {
      $stmt = $pdo->prepare('INSERT INTO contenidos (orden, titulo, descripcion, cuerpo, autor_id) VALUES (?,?,?,?,?)');
      $stmt->execute([$orden, $titulo, $descripcion, $cuerpo, $uid]);
      $ok = 'Contenido creado correctamente.';
    }
  }
}

/* ============================
   Editar contenido (ADMIN)
   ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='update') {
  if (!is_admin()) { $err = 'No autorizado.'; }
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) { $err = 'CSRF inválido.'; }
  else {
    $id          = (int)($_POST['id'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cuerpo      = $_POST['cuerpo'] ?? '';
    $orden       = (isset($_POST['orden']) && $_POST['orden']!=='') ? (int)$_POST['orden'] : null;

    if ($id<=0 || !$titulo) $err = 'Datos incompletos.';
    else {
      $stmt = $pdo->prepare('UPDATE contenidos SET orden=?, titulo=?, descripcion=?, cuerpo=?, updated_at=NOW() WHERE id=?');
      $stmt->execute([$orden, $titulo, $descripcion, $cuerpo, $id]);
      $ok = 'Contenido actualizado.';
    }
  }
}

/* ============================
   Eliminar contenido (ADMIN)
   ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='delete') {
  if (!is_admin()) { $err = 'No autorizado.'; }
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) { $err = 'CSRF inválido.'; }
  else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $pdo->beginTransaction();
      try {
        $pdo->prepare('DELETE FROM contenidos_vistos WHERE contenido_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM contenidos WHERE id=?')->execute([$id]);
        $pdo->commit();
        $ok = 'Contenido eliminado.';
      } catch (Throwable $e) {
        $pdo->rollBack();
        $err = 'No se pudo eliminar: '.$e->getMessage();
      }
    } else { $err = 'ID inválido.'; }
  }
}

/* ============================
   Reordenar (ADMIN) – mover arriba/abajo
   ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array(($_POST['action'] ?? ''), ['move_up','move_down'], true)) {
  if (!is_admin()) { $err = 'No autorizado.'; }
  elseif (!csrf_verify($_POST['_csrf'] ?? '')) { $err = 'CSRF inválido.'; }
  else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $pdo->beginTransaction();
      try {
        $row = fetch_row($pdo, $id);
        if ($row) {
          if ($row['orden'] === null) {
            $newOrden = get_max_orden($pdo) + 1;
            $pdo->prepare("UPDATE contenidos SET orden=? WHERE id=?")->execute([$newOrden, $id]);
            $row['orden'] = $newOrden;
          }

          if ($_POST['action']==='move_up') {
            $st = $pdo->prepare("SELECT id, orden FROM contenidos WHERE orden IS NOT NULL AND orden < ? ORDER BY orden DESC LIMIT 1");
            $st->execute([$row['orden']]);
            $adj = $st->fetch();
          } else {
            $st = $pdo->prepare("SELECT id, orden FROM contenidos WHERE orden IS NOT NULL AND orden > ? ORDER BY orden ASC LIMIT 1");
            $st->execute([$row['orden']]);
            $adj = $st->fetch();
            if (!$adj) {
              $newOrden = get_max_orden($pdo) + 1;
              $pdo->prepare("UPDATE contenidos SET orden=? WHERE id=?")->execute([$newOrden, $id]);
              $ok = 'Movido al final.';
              $pdo->commit();
              goto after_move;
            }
          }

          if ($adj) {
            $tmp = -1 * (time() % 1000000);
            $pdo->prepare("UPDATE contenidos SET orden=? WHERE id=?")->execute([$tmp, $row['id']]);
            $pdo->prepare("UPDATE contenidos SET orden=? WHERE id=?")->execute([$row['orden'], $adj['id']]);
            $pdo->prepare("UPDATE contenidos SET orden=? WHERE id=?")->execute([$adj['orden'], $row['id']]);
            $ok = ($_POST['action']==='move_up') ? 'Movido arriba.' : 'Movido abajo.';
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

/* ============================
   Listar contenidos + estado visto
   ============================ */
$sql = "SELECT c.*, u.nombre AS autor, (cv.id IS NOT NULL) AS visto
        FROM contenidos c
        LEFT JOIN usuarios u ON u.id=c.autor_id
        LEFT JOIN contenidos_vistos cv
          ON cv.contenido_id = c.id AND cv.usuario_id = ?
        ORDER BY COALESCE(c.orden, 999999) ASC, c.created_at ASC, c.id ASC";
$st = $pdo->prepare($sql);
$st->execute([$uid]);
$rows = $st->fetchAll();

$total = count($rows);
$vistos = 0;
foreach ($rows as $r) { if (!empty($r['visto'])) $vistos++; }
$porc = $total>0 ? round(($vistos/$total)*100) : 0;

include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container">

  <!-- Progreso de lectura -->
  <div class="card" style="display:flex; align-items:center; gap:16px; justify-content:space-between;">
    <div>
      <h2 style="margin:0;">Contenidos</h2>
      <p class="muted" style="margin:4px 0 0;">
        Progreso: <strong><?= $vistos ?>/<?= $total ?></strong> (<?= $porc ?>%)
      </p>
    </div>
    <div style="min-width:220px; width:30%;">
      <div style="height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
        <div style="height:100%; width:<?= $porc ?>%; background:#0ea5e9;"></div>
      </div>
    </div>
  </div>

  <?php if ($ok): ?><div class="card"><p style="color:#15803d; margin:0;"><?= htmlspecialchars($ok) ?></p></div><?php endif; ?>
  <?php if ($err): ?><div class="card"><p style="color:#b91c1c; margin:0;"><?= htmlspecialchars($err) ?></p></div><?php endif; ?>

  <?php if (is_admin()): ?>
    <div class="card">
      <details>
        <summary>➕ Nuevo contenido</summary>
        <form method="post" style="margin-top:8px;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <label class="label">Orden (número)</label>
          <input class="input" type="number" name="orden" min="1" placeholder="1">
          <small class="muted">Si lo dejas vacío, irá al final de la lista.</small>

          <label class="label">Título</label>
          <input class="input" name="titulo" required>

          <label class="label">Descripción</label>
          <input class="input" name="descripcion">

          <label class="label">Cuerpo (HTML permitido)</label>
          <textarea class="input" name="cuerpo" rows="6" placeholder="<p>Texto con <strong>formato</strong></p>"></textarea>

          <br><button class="btn">Guardar</button>
        </form>
      </details>
    </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="card"><p>No hay contenidos aún.</p></div>
  <?php endif; ?>

  <?php foreach($rows as $r): ?>
    <div class="card" style="position:relative;">
      <!-- Cabecera del contenido -->
      <div style="display:flex; align-items:center; gap:8px; justify-content:space-between;">
        <div>
          <h3 style="margin:0;"><?= htmlspecialchars($r['titulo']) ?></h3>
          <small class="muted">
            <?php if (!is_null($r['orden'])): ?>Orden: <?= (int)$r['orden'] ?> · <?php endif; ?>
            Autor: <?= htmlspecialchars($r['autor'] ?? 'N/A') ?>
            · Creado: <?= htmlspecialchars($r['created_at']) ?>
            <?php if (!empty($r['updated_at'])): ?> · Actualizado: <?= htmlspecialchars($r['updated_at']) ?><?php endif; ?>
          </small>
        </div>

        <!-- Badge de estado + acciones rápidas -->
        <div style="display:flex; align-items:center; gap:6px;">
          <?php if (!empty($r['visto'])): ?>
            <span style="background:#dcfce7; color:#166534; border:1px solid #86efac; padding:4px 8px; border-radius:999px; font-size:12px;">✔ Visto</span>
          <?php else: ?>
            <span style="background:#e0f2fe; color:#075985; border:1px solid #7dd3fc; padding:4px 8px; border-radius:999px; font-size:12px;">● Pendiente</span>
          <?php endif; ?>

          <?php if (is_admin()): ?>
            <!-- Reordenar -->
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="move_up">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" title="Subir" aria-label="Subir">⬆️</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="move_down">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" title="Bajar" aria-label="Bajar">⬇️</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($r['descripcion'])): ?>
        <p style="margin-top:6px;"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></p>
      <?php endif; ?>

      <!-- Cuerpo HTML -->
      <div style="margin-top:8px;"><?= $r['cuerpo'] ?></div>

      <!-- Acciones de marcado por el usuario -->
      <div style="margin-top:10px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <?php if (empty($r['visto'])): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="mark_seen">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn" title="Marcar como visto">Marcar como visto</button>
          </form>
        <?php else: ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="mark_unseen">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn" style="background:#eab308" title="Quitar visto">Quitar visto</button>
          </form>
        <?php endif; ?>

        <?php if (is_admin()): ?>
          <details style="margin-top:8px;">
            <summary>✏️ Editar / 🗑️ Eliminar</summary>

            <!-- Editar -->
            <form method="post" style="margin-top:8px;">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

              <label class="label">Orden (número)</label>
              <input class="input" type="number" name="orden" min="1" value="<?= htmlspecialchars((string)($r['orden'] ?? '')) ?>">
              <small class="muted">Deja vacío para enviar al final.</small>

              <label class="label">Título</label>
              <input class="input" name="titulo" value="<?= htmlspecialchars($r['titulo']) ?>" required>

              <label class="label">Descripción</label>
              <input class="input" name="descripcion" value="<?= htmlspecialchars($r['descripcion']) ?>">

              <label class="label">Cuerpo (HTML permitido)</label>
              <textarea class="input" name="cuerpo" rows="6"><?= htmlspecialchars($r['cuerpo']) ?></textarea>

              <br><button class="btn">Actualizar</button>
            </form>

            <!-- Eliminar -->
            <form method="post" style="margin-top:8px;" onsubmit="return confirm('¿Eliminar definitivamente este contenido?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" style="background:#ef4444">Eliminar</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__.'/../views/footer.php'; ?>
