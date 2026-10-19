<?php
/* admin/inbox.php - VERSION 1.3
   Reads the mensajes table filled by /home/lareserv/pipe_correo.php.
   Read only. Replying opens your own mail client, because mail() on this
   host is unreliable and a reply that silently vanishes is worse than none. */

@ini_set('session.gc_maxlifetime', 7200);
require_once __DIR__.'/../includes/auth.php';

/* v1.2: telefonos y mails del cuerpo, clickeables.
   Un mail no trae el telefono en un campo aparte como el formulario: viene
   suelto en el texto. Esto busca secuencias que parezcan un numero argentino
   (con o sin +54, con o sin 9, con espacios, puntos o guiones) y las
   convierte en links a wa.me. Los mails del cuerpo pasan a mailto.
   Se escapa primero y se enlaza despues, asi nada del mail entra como HTML. */
function lrEnlazar(string $texto): string {
    $h = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

    /* mails */
    $h = preg_replace(
        '~([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})~i',
        '<a class="lnk" href="mailto:$1">$1</a>', $h);

    /* telefonos: 8 a 15 digitos con separadores, opcionalmente +54 */
    $h = preg_replace_callback(
        '~(?<![\w@.])(\+?54\s?)?(?:9\s?)?(\(?0?\d{2,4}\)?[\s.-]?)?\d{3,4}[\s.-]?\d{4}(?![\w@])~',
        function ($m) {
            $crudo = $m[0];
            $d = preg_replace('/\D+/', '', $crudo);
            if (strlen($d) < 8 || strlen($d) > 15) return $crudo;
            /* normalizar a 549 + area + numero */
            $d = ltrim($d, '0');
            if (str_starts_with($d, '54')) {
                $d = substr($d, 2);
                if (str_starts_with($d, '9')) $d = substr($d, 1);
            }
            if (strlen($d) < 8) return $crudo;
            $wa = '549' . $d;
            return '<a class="lnk lnk-wa" href="https://wa.me/' . $wa . '" target="_blank" rel="noopener" title="Abrir en WhatsApp">' . $crudo . '</a>';
        }, $h);

    return $h;
}

exigirSesion();
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = getDB();
$aviso = null;
$falta = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    if (!csrfOk($_POST['csrf'] ?? null)) {
        $aviso = ['error', 'Sesion expirada.'];
    } else {
        $accion = $_POST['accion'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        try {
            if ($accion === 'leido') {
                $db->prepare("UPDATE mensajes SET leido = 1 - leido WHERE id=:i")->execute([':i'=>$id]);
            } elseif ($accion === 'borrar') {
                /* v1.3: borrado blando. No se borra la fila: se le pone fecha en
                   `borrado` y deja de aparecer en la bandeja. Queda 30 dias en la
                   papelera y despues se va sola. Antes un clic equivocado perdia
                   el mensaje para siempre. */
                $db->prepare("UPDATE mensajes SET borrado = NOW() WHERE id=:i AND borrado IS NULL")->execute([':i'=>$id]);
                $aviso = ['ok', 'Mensaje movido a la papelera. Se puede recuperar durante 30 dias.'];
            } elseif ($accion === 'restaurar') {
                $db->prepare("UPDATE mensajes SET borrado = NULL WHERE id=:i")->execute([':i'=>$id]);
                $aviso = ['ok', 'Mensaje restaurado a la bandeja.'];
            } elseif ($accion === 'borrar_definitivo') {
                $db->prepare("DELETE FROM mensajes WHERE id=:i AND borrado IS NOT NULL")->execute([':i'=>$id]);
                $aviso = ['ok', 'Mensaje eliminado definitivamente.'];
            } elseif ($accion === 'vaciar_papelera') {
                $n = $db->exec("DELETE FROM mensajes WHERE borrado IS NOT NULL");
                $aviso = ['ok', "Papelera vaciada. $n mensajes eliminados."];
            } elseif ($accion === 'todos_leidos') {
                $db->exec("UPDATE mensajes SET leido = 1 WHERE leido = 0");
                $aviso = ['ok', 'Todos marcados como leidos.'];
            }
        } catch (Exception $ex) { $aviso = ['error', 'Error BD: '.$ex->getMessage()]; }
    }
}

/* Purga: lo que lleva mas de 30 dias en la papelera se borra. Corre al abrir
   el inbox porque este hosting no tiene tareas programadas. Falla en silencio:
   si la columna no existe todavia, el inbox igual abre. */
try { if ($db) $db->exec("DELETE FROM mensajes WHERE borrado IS NOT NULL AND borrado < (NOW() - INTERVAL 30 DAY)"); }
catch (Throwable $e) { error_log('[LR papelera] '.$e->getMessage()); }

$filtro = $_GET['f'] ?? '';
$abierto = (int)($_GET['id'] ?? 0);
$lista = []; $sinLeer = 0; $enPapelera = 0; $total = 0;

if ($db) {
    try {
        $sql = "SELECT * FROM mensajes";
        if ($filtro === 'papelera')   $sql .= " WHERE borrado IS NOT NULL";
        elseif ($filtro === 'nuevos') $sql .= " WHERE borrado IS NULL AND leido = 0";
        else                          $sql .= " WHERE borrado IS NULL";
        $sql .= " ORDER BY creado DESC LIMIT 200";
        $lista   = $db->query($sql)->fetchAll();
        $sinLeer    = (int)$db->query("SELECT COUNT(*) FROM mensajes WHERE leido = 0 AND borrado IS NULL")->fetchColumn();
        $enPapelera = (int)$db->query("SELECT COUNT(*) FROM mensajes WHERE borrado IS NOT NULL")->fetchColumn();
        $total   = (int)$db->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();
    } catch (Exception $e) {
        $falta = true;
        error_log('[LR inbox] '.$e->getMessage());
    }
}

$log = '/home/lareserv/pipe_correo.log';
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- admin/inbox.php VERSION 1.3 -->
<title>Inbox · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--td:rgba(0,201,167,.12);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}
.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem}
.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-sect{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}
.sb-nav li a{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent}
.sb-nav li a:hover{background:var(--td);color:var(--text)}
.sb-nav li a.active{background:var(--td);color:var(--teal);border-left-color:var(--teal)}
.sb-badge{background:var(--teal);color:#0a1628;border-radius:10px;padding:0 7px;font-size:.66rem;font-weight:700}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}
.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300}
.content{padding:22px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}
.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}
.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.aviso.info{background:rgba(122,154,184,.08);border-color:var(--text3);color:var(--text2)}
.filtros a{display:inline-block;padding:5px 13px;border-radius:16px;border:1px solid var(--border);color:var(--text2);font-size:.76rem;margin-right:6px}
.filtros a.on{border-color:rgba(0,201,167,.35);background:var(--td);color:var(--teal)}
.msg{background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:8px;overflow:hidden}
.msg.nuevo{border-left:3px solid var(--teal)}
.msg-h{display:flex;gap:14px;align-items:baseline;padding:12px 16px;cursor:pointer}
.msg-h:hover{background:var(--hover)}
.msg-de{font-weight:600;min-width:170px;color:var(--text)}
.msg-de small{display:block;font-weight:400;color:var(--text3);font-size:.74rem}
.msg-as{flex:1;color:var(--text2)}
.msg.nuevo .msg-as{color:var(--text)}
.msg-fe{color:var(--text3);font-size:.76rem;white-space:nowrap}
.msg-b{border-top:1px solid var(--border);padding:16px}
.msg-b pre{white-space:pre-wrap;word-break:break-word;font-family:inherit;font-size:.9rem;line-height:1.65;color:var(--text2)}
.acciones{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}
.btn{display:inline-flex;align-items:center;padding:6px 14px;border-radius:4px;font:inherit;font-size:.76rem;cursor:pointer;border:0;font-weight:500}
.btn-t{background:var(--teal);color:#0a1628}
.btn-o{background:transparent;border:1px solid var(--border);color:var(--text2)}
.btn-d{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}
.tag{font-size:.66rem;padding:2px 8px;border-radius:12px;background:var(--td);color:var(--teal)}
.nota{color:var(--text3);font-size:.78rem;margin-top:10px;line-height:1.6}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem}
.lnk{color:#00c9a7;text-decoration:none;border-bottom:1px dotted rgba(0,201,167,.5)}
.lnk:hover{border-bottom-style:solid}
.lnk-wa{color:#25d366;border-bottom-color:rgba(37,211,102,.5)}
.lnk-wa::before{content:"\1F4AC\00A0";font-size:.85em}
</style>
</head>
<body>
<div class="layout">

<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>Inbox <?php if($total): ?><span class="tag"><?= $sinLeer ?> sin leer de <?= $total ?></span><?php endif; ?></h1>
    <div style="display:flex;gap:8px;align-items:center">
      <div class="filtros">
        <a href="inbox.php" class="<?= $filtro===''?'on':'' ?>">Todos</a>
        <a href="inbox.php?f=nuevos" class="<?= $filtro==='nuevos'?'on':'' ?>">Sin leer</a>
        <a href="inbox.php?f=papelera" class="<?= $filtro==='papelera'?'on':'' ?>">Papelera<?= $enPapelera ? ' ('.$enPapelera.')' : '' ?></a>
      </div>
      <?php if($sinLeer): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="todos_leidos">
        <button class="btn btn-o">Marcar todos leidos</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($filtro === 'papelera'): ?>
    <div class="av ok" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
      <span>Los mensajes de la papelera se borran solos a los 30 dias. Hasta entonces se pueden restaurar.</span>
      <?php if ($enPapelera): ?>
      <form method="post" onsubmit="return confirm('Vaciar la papelera entera? Esto no se puede deshacer.')" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="vaciar_papelera">
        <button class="btn btn-d">Vaciar papelera</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>

    <?php if ($falta): ?>
      <div class="aviso error">Falta la tabla <span class="mono">mensajes</span>. Corre mensajes_table.sql en phpMyAdmin.</div>
    <?php elseif (!$lista): ?>
      <div class="aviso info">
        Todavia no llego ningun mensaje.<br>
        Esta bandeja no revisa la casilla por su cuenta: la llena el script
        <span class="mono">/home/lareserv/pipe_correo.php</span>, que cPanel ejecuta con cada correo
        entrante. Si no configuraste el forwarder todavia, aca no va a aparecer nada nunca.
        <?php if (is_readable($log)): ?>
          <br><br>Ultimas lineas de <span class="mono">pipe_correo.log</span>:
          <pre class="mono" style="margin-top:6px;color:var(--text3);white-space:pre-wrap"><?php
            $l = @file($log, FILE_IGNORE_NEW_LINES) ?: [];
            echo e(implode("\n", array_slice($l, -8)));
          ?></pre>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($lista as $m): $ab = ($abierto === (int)$m['id']); ?>
    <div class="msg<?= $m['leido'] ? '' : ' nuevo' ?>">
      <a class="msg-h" href="inbox.php?<?= $filtro?'f='.e($filtro).'&':'' ?>id=<?= $ab ? 0 : (int)$m['id'] ?>#m<?= (int)$m['id'] ?>" id="m<?= (int)$m['id'] ?>">
        <span class="msg-de"><?= e($m['de_nombre'] ?: $m['de_email']) ?><small><a class="lnk" href="mailto:<?= e($m['de_email']) ?>"><?= e($m['de_email']) ?></a></small></span>
        <span class="msg-as"><?= e($m['asunto'] ?: '(sin asunto)') ?>
          <?php if((int)$m['adjuntos']): ?><span class="tag"><?= (int)$m['adjuntos'] ?> adj</span><?php endif; ?>
        </span>
        <span class="msg-fe"><?= e(date('d/m/Y H:i', strtotime($m['creado']))) ?></span>
      </a>
      <?php if ($ab): ?>
      <div class="msg-b">
        <pre><?= $m['cuerpo'] ? lrEnlazar($m['cuerpo']) : '(sin texto)' ?></pre>
        <?php if((int)$m['adjuntos']): ?>
          <p class="nota">Este mensaje traia <?= (int)$m['adjuntos'] ?> archivo(s) adjunto(s). No se guardan
          en el servidor. Para verlos abri la casilla en webmail.</p>
        <?php endif; ?>
        <div class="acciones">
          <?php if($m['de_email']): ?>
          <a class="btn btn-t" href="mailto:<?= e($m['de_email']) ?>?subject=<?= rawurlencode('Re: '.($m['asunto'] ?: '')) ?>">Responder</a>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="leido">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-o"><?= $m['leido'] ? 'Marcar sin leer' : 'Marcar leido' ?></button>
          </form>
          <?php if ($filtro === 'papelera'): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="accion" value="restaurar">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-t">Restaurar</button>
            </form>
            <form method="post" onsubmit="return confirm('Eliminar definitivamente? Esto no se puede deshacer.')">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="accion" value="borrar_definitivo">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-d">Eliminar para siempre</button>
            </form>
          <?php else: ?>
            <form method="post" onsubmit="return confirm('Mover a la papelera?')">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="accion" value="borrar">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-d">Eliminar</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($lista): ?>
    <p class="nota">Esta bandeja es solo lectura. Responder abre tu cliente de correo, porque
    <span class="mono">mail()</span> en este hosting no es confiable y una respuesta que se pierde en
    silencio es peor que no tener el boton. La copia original queda siempre en webmail.</p>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
