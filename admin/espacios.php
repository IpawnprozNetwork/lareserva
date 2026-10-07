<?php
/* admin/espacios.php - VERSION 3.7
   Note: the 3.4 header claimed images went to assets/img/espacios/. They did not.
   The code writes to assets/img/galeria/ and still does.
   3.5 changes:
   - display_errors is set AFTER the require. config.php turns it off at the bottom,
     so setting it before the include had no effect and every fatal came back blank.
   - Oversized POST is detected and reported as a size problem, not "Sesion expirada".
   - No mkdir() in the upload handler. Existing directories only.
   - Only the active file input is submitted. Both used to go up in every request.
   - Browser blocks files over the server limit before sending them. */
@ini_set('session.gc_maxlifetime', 7200);
@ini_set('session.cookie_lifetime', 7200);
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
@ini_set('display_errors', '1');
error_reporting(E_ALL);
$db = getDB();
$aviso = null;
$editar = null;

/* Limits actually in force for this request. Used by the guard below and by the
   browser-side check, so the user gets a real message instead of "Sesion expirada". */
function lrBytes($v) {
    $v = trim((string)$v);
    if ($v === '') return 0;
    $u = strtolower(substr($v, -1));
    $n = (float)$v;
    if ($u === 'g') $n *= 1073741824;
    elseif ($u === 'm') $n *= 1048576;
    elseif ($u === 'k') $n *= 1024;
    return (int)$n;
}
$LR_POST_MAX   = lrBytes(ini_get('post_max_size'));
$LR_UPLOAD_MAX = lrBytes(ini_get('upload_max_filesize'));
$LR_LIMITE     = min($LR_POST_MAX ?: PHP_INT_MAX, $LR_UPLOAD_MAX ?: PHP_INT_MAX);
$LR_LIMITE_TXT = ini_get('post_max_size') . ' / ' . ini_get('upload_max_filesize');


function espSlug(string $n): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($n)), '-') ?: 'espacio-' . date('Ymd');
}
function ve2($r, $k) {
    return isset($r[$k]) ? htmlspecialchars((string)$r[$k], ENT_QUOTES, 'UTF-8') : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    /* Body dropped before PHP ran means Content-Length was over post_max_size.
       PHP throws away $_POST and $_FILES together, so the CSRF token never arrives
       and the old code blamed the session. Report the real cause. */
    $lr_clen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $lr_dropped = ($lr_clen > 0 && empty($_POST) && empty($_FILES));
    $accion_check = $_POST['accion'] ?? '';
    $csrf_ok = ($accion_check === 'upload_media') ? true : csrfOk($_POST['csrf'] ?? null);
    if ($lr_dropped) {
        $aviso = ['error', 'El envio pesaba ' . round($lr_clen / 1048576, 1) . ' MB y el servidor corta en '
                  . ini_get('post_max_size') . ' (post_max_size). El archivo nunca llego a PHP. '
                  . 'Comprimilo o subi el limite en .user.ini.'];
    } elseif (!$csrf_ok) {
        $aviso = ['error', 'Sesion expirada.'];
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'guardar') {
            $nombre    = limpiar($_POST['nombre']       ?? '') ?: null;
            $tipo      = in_array($_POST['tipo'] ?? '', ['salon','alojamiento','aire_libre','gastronomia','servicio'])
                         ? $_POST['tipo'] : 'salon';
            $resumen   = limpiar($_POST['resumen']      ?? '') ?: null;
            $desc      = limpiar($_POST['descripcion']  ?? '') ?: null;
            $cap       = ($_POST['capacidad']    ?? '') !== '' ? (int)$_POST['capacidad']    : null;
            $sup       = ($_POST['superficie_m2']?? '') !== '' ? (int)$_POST['superficie_m2'] : null;
            $imagen    = limpiar($_POST['imagen']       ?? '') ?: null;
            $orden     = (int)($_POST['orden']   ?? 10);
            $activo    = !empty($_POST['activo']) ? 1 : 0;
            $eid       = (int)($_POST['id']      ?? 0);

            if (!$nombre) {
                $aviso = ['error', 'El nombre es obligatorio.'];
            } else {
                if ($eid) {
                    $db->prepare("UPDATE espacios SET nombre=:n,tipo=:t,resumen=:r,descripcion=:d,
                        capacidad=:c,superficie_m2=:s,imagen=:i,orden=:o,activo=:a WHERE id=:id")
                       ->execute([':n'=>$nombre,':t'=>$tipo,':r'=>$resumen,':d'=>$desc,
                                  ':c'=>$cap,':s'=>$sup,':i'=>$imagen,':o'=>$orden,':a'=>$activo,':id'=>$eid]);
                    $aviso = ['ok', 'Espacio actualizado.'];
                } else {
                    $slug = espSlug($nombre);
                    $db->prepare("INSERT INTO espacios (slug,nombre,tipo,resumen,descripcion,capacidad,superficie_m2,imagen,orden,activo)
                        VALUES (:sl,:n,:t,:r,:d,:c,:s,:i,:o,:a)")
                       ->execute([':sl'=>$slug,':n'=>$nombre,':t'=>$tipo,':r'=>$resumen,':d'=>$desc,
                                  ':c'=>$cap,':s'=>$sup,':i'=>$imagen,':o'=>$orden,':a'=>$activo]);
                    $eid = (int)$db->lastInsertId();
                    $aviso = ['ok', 'Espacio creado.'];
                }
                $st = $db->prepare("SELECT * FROM espacios WHERE id=:i");
                $st->execute([':i' => $eid]);
                $editar = $st->fetch() ?: null;
            }

        } elseif ($accion === 'upload_media') {
      $eid    = (int)($_POST['espacio_id'] ?? 0);
      $tipo   = ($_POST['tipo'] ?? '') === 'video' ? 'video' : 'imagen';
      $titulo = limpiar($_POST['titulo'] ?? '') ?: '';
      if ($tipo === 'imagen') {
        $f = $_FILES['imagen'] ?? null;
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!$f || $f['error'] !== 0) {
          $aviso = ['error', 'Error al subir (cod:'.$f['error'].')'];
        } elseif (!in_array(lrMime($f['tmp_name']), $allowed)) {
          $aviso = ['error', 'Solo JPG, PNG o WEBP.'];
        } else {
          $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
          $name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $titulo ?: pathinfo($f['name'], PATHINFO_FILENAME))));
          $name = $name ?: 'img-'.time();
          $dest_dir = __DIR__.'/../assets/img/galeria/';
          if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
            $aviso = ['error', 'assets/img/galeria/ no existe o no tiene permiso de escritura.'];
            $dest_dir = null;
          }
          if ($dest_dir) {
          $dest = $dest_dir . $name . '.' . $ext;
          $i = 1;
          while (file_exists($dest)) { $dest = $dest_dir . $name . '-' . $i++ . '.' . $ext; }
          if (move_uploaded_file($f['tmp_name'], $dest)) {
            $ruta = 'assets/img/galeria/' . basename($dest);
            try {
              $db->prepare("INSERT INTO espacio_media (espacio_id,tipo,archivo,titulo,orden) VALUES (:e,:t,:a,:ti,10)")
                 ->execute([':e'=>$eid,':t'=>'imagen',':a'=>$ruta,':ti'=>$titulo]);
              $aviso = ['ok', 'Imagen subida: '.basename($dest)];
            } catch (Exception $ex) { $aviso = ['error', 'Error BD: '.$ex->getMessage()]; }
          } else { $aviso = ['error', 'No se pudo guardar en assets/img/galeria/']; }
          }
        }
      } else {
        $f = $_FILES['video'] ?? null;
        if (!$f || $f['error'] !== 0) {
          $errs = [1=>'Demasiado grande',2=>'Demasiado grande',3=>'Incompleto',4=>'Sin archivo'];
          $aviso = ['error', $errs[$f['error']??4] ?? 'Error '.$f['error']];
        } else {
          $dest_dir = __DIR__.'/../assets/video/pages/';
          if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
            $aviso = ['error', 'assets/video/pages/ no existe o no tiene permiso de escritura.'];
            $dest_dir = null;
          }
          if ($dest_dir) {
          $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
          $name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $titulo ?: pathinfo($f['name'], PATHINFO_FILENAME))));
          $name = $name ?: 'vid-'.time();
          $dest = $dest_dir . $name . '.' . $ext;
          $i = 1;
          while (file_exists($dest)) { $dest = $dest_dir . $name . '-' . $i++ . '.' . $ext; }
          if (move_uploaded_file($f['tmp_name'], $dest)) {
            $ruta = 'assets/video/pages/' . basename($dest);
            try {
              $db->prepare("INSERT INTO espacio_media (espacio_id,tipo,archivo,titulo,orden) VALUES (:e,:t,:a,:ti,10)")
                 ->execute([':e'=>$eid,':t'=>'video',':a'=>$ruta,':ti'=>$titulo]);
              $aviso = ['ok', 'Video subido: '.basename($dest).' ('.round($f['size']/1024/1024,1).'MB)'];
            } catch (Exception $ex) { $aviso = ['error', 'Error BD: '.$ex->getMessage()]; }
          } else { $aviso = ['error', 'No se pudo guardar en assets/video/pages/']; }
          }
        }
      }
      $st = $db->prepare("SELECT * FROM espacios WHERE id=:i");
      $st->execute([':i' => $eid]);
      $editar = $st->fetch() ?: null;
        } elseif ($accion === 'mover_media') {
            /* v3.7: mover un archivo una posicion.
               Intercambia el `orden` con el vecino en la direccion pedida.
               Antes de intercambiar se normaliza la columna a 10,20,30...:
               el upload guardaba todo con orden=10, asi que sin esto todos
               empatan y no hay vecino que intercambiar. */
            $mid = (int)($_POST['mid'] ?? 0);
            $eid = (int)($_POST['eid'] ?? 0);
            $dir = ($_POST['dir'] ?? '') === 'abajo' ? 'abajo' : 'arriba';
            try {
                $db->beginTransaction();

                $lista = $db->prepare("SELECT id FROM espacio_media WHERE espacio_id=:e ORDER BY orden ASC, id ASC");
                $lista->execute([':e' => $eid]);
                $ids = array_column($lista->fetchAll(), 'id');

                $norm = $db->prepare("UPDATE espacio_media SET orden=:o WHERE id=:i");
                foreach ($ids as $k => $id) $norm->execute([':o' => ($k + 1) * 10, ':i' => (int)$id]);

                $pos = array_search($mid, array_map('intval', $ids), true);
                if ($pos !== false) {
                    $otro = $dir === 'abajo' ? $pos + 1 : $pos - 1;
                    if ($otro >= 0 && $otro < count($ids)) {
                        $a_id = (int)$ids[$pos];
                        $b_id = (int)$ids[$otro];
                        $sw = $db->prepare("UPDATE espacio_media SET orden=:o WHERE id=:i");
                        $sw->execute([':o' => ($otro + 1) * 10, ':i' => $a_id]);
                        $sw->execute([':o' => ($pos  + 1) * 10, ':i' => $b_id]);
                        $aviso = ['ok', 'Orden actualizado.'];
                    } else {
                        $aviso = ['ok', 'Ya estaba en el extremo.'];
                    }
                }
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) $db->rollBack();
                $aviso = ['err', 'No se pudo reordenar: ' . $ex->getMessage()];
            }
            $st = $db->prepare("SELECT * FROM espacios WHERE id=:i");
            $st->execute([':i' => $eid]);
            $editar = $st->fetch() ?: null;

        } elseif ($accion === 'delete_media') {
            $mid = (int)$_POST['mid'];
            $eid = (int)$_POST['eid'];
            $row = $db->prepare("SELECT archivo FROM espacio_media WHERE id=:i");
            $row->execute([':i' => $mid]);
            $row = $row->fetch();
            if ($row) {
                $path = __DIR__ . '/../' . $row['archivo'];
                if (file_exists($path)) unlink($path);
                $db->prepare("DELETE FROM espacio_media WHERE id=:i")->execute([':i' => $mid]);
            }
            $aviso = ['ok', 'Eliminado.'];
            $st = $db->prepare("SELECT * FROM espacios WHERE id=:i");
            $st->execute([':i' => $eid]);
            $editar = $st->fetch() ?: null;

        } elseif ($accion === 'eliminar') {
            $db->prepare("DELETE FROM espacios WHERE id=:i")->execute([':i' => (int)$_POST['id']]);
            $aviso = ['ok', 'Espacio eliminado.'];
        }
    }
}

if (!$editar && isset($_GET['id']) && $db) {
    $st = $db->prepare("SELECT * FROM espacios WHERE id=:i");
    $st->execute([':i' => (int)$_GET['id']]);
    $editar = $st->fetch() ?: null;
}

$lista = [];
if ($db) {
    try { $lista = $db->query("SELECT * FROM espacios ORDER BY orden ASC")->fetchAll(); }
    catch (Exception $e) {}
}

$media_list = [];
if ($editar && $db) {
    try {
        /* v3.7: orden real, no agrupado por tipo. Antes videos e imagenes se
           separaban y el orden elegido no se veia reflejado. */
        $m = $db->prepare("SELECT * FROM espacio_media WHERE espacio_id=:i ORDER BY orden ASC, id ASC");
        $m->execute([':i' => $editar['id']]);
        $media_list = $m->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- admin/espacios.php VERSION 3.7 -->
<title>Espacios · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--td:rgba(0,201,167,.12);--tb:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-sect{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--td);color:var(--text)}.sb-nav li a.active{background:var(--td);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:22px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}
.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}
.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.tabs{display:flex;gap:6px;margin-bottom:20px}
.tab{padding:7px 16px;border-radius:20px;border:1.5px solid var(--border);background:transparent;color:var(--text2);font:inherit;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s}
.tab:hover{border-color:var(--tb);color:var(--text)}.tab.active{border-color:var(--tb);background:var(--td);color:var(--teal)}
.tp{display:none}.tp.show{display:block}
fieldset{border:1px solid var(--border);padding:20px;background:var(--card);margin-bottom:18px;border-radius:6px}
legend{font-family:'Fraunces',serif;font-size:1.05rem;padding:0 10px;color:var(--text);font-weight:300}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.campo{display:flex;flex-direction:column;gap:5px}.campo.w{grid-column:1/-1}
.campo label{font-size:.76rem;color:var(--text3)}
.campo input,.campo select,.campo textarea{font:inherit;font-size:.88rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 12px;width:100%;transition:.18s}
.campo input:focus,.campo select:focus,.campo textarea:focus{outline:none;border-color:var(--teal)}
.campo textarea{resize:vertical;min-height:80px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 18px;border-radius:4px;font:inherit;font-size:.8rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-t{background:var(--teal);color:#0a1628}.btn-t:hover{background:#00b898}
.btn-o{background:transparent;border:1px solid var(--border);color:var(--text2)}.btn-o:hover{border-color:var(--tb);color:var(--teal)}
.btn-d{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-d:hover{background:rgba(255,82,82,.22)}
.sm{padding:5px 11px;font-size:.74rem}
table{width:100%;border-collapse:collapse;font-size:.83rem}
th{text-align:left;padding:9px 14px;background:var(--bg);color:var(--text3);font-size:.68rem;letter-spacing:.12em;font-weight:600;border-bottom:1px solid var(--border);text-transform:uppercase}
td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:0}tr:hover td{background:var(--hover)}
.mg{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:14px}
.mc{background:var(--bg2);border:1px solid var(--border);border-radius:6px;overflow:hidden}
.mc img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.mc video{width:100%;max-height:85px;object-fit:cover;display:block}
.mc{position:relative}
.mc-pos{position:absolute;top:6px;left:6px;z-index:2;background:rgba(10,22,40,.85);
  border:1px solid rgba(0,201,167,.35);color:#00c9a7;border-radius:10px;
  padding:1px 8px;font-size:.66rem;font-weight:600;letter-spacing:.04em}
.mc-mov{display:flex;gap:4px}
.mc-b{background:rgba(0,201,167,.12);border:1px solid rgba(0,201,167,.3);color:#00c9a7;
  border-radius:4px;padding:3px 9px;font:inherit;font-size:.78rem;cursor:pointer;line-height:1.3}
.mc-b:hover:not(:disabled){background:#00c9a7;color:#0a1628}
.mc-b:disabled{opacity:.25;cursor:default}
.mc-m{padding:7px 10px;font-size:.72rem;color:var(--text3);display:flex;align-items:center;justify-content:space-between}
.dz{border:2px dashed var(--border);border-radius:8px;padding:22px;text-align:center;cursor:pointer;transition:.2s;background:var(--bg2);margin-bottom:10px}
.dz:hover{border-color:var(--tb)}.dz input{display:none}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1><?= $editar ? 'Editar: ' . e($editar['nombre']) : 'Espacios' ?></h1>
    <div style="display:flex;gap:8px">
      <?php if ($editar): ?>
        <a href="../espacios.php" target="_blank" class="btn btn-o sm">Ver en sitio</a>
        <a href="espacios.php" class="btn btn-o sm">Ver todos</a>
      <?php else: ?>
        <button onclick="showTab('nuevo',this)" class="btn btn-t sm">+ Nuevo espacio</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($aviso): ?>
      <div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div>
    <?php endif; ?>

    <?php if ($editar): ?>
    <div class="tabs">
      <button class="tab active" onclick="showTab('info',this)">Informacion</button>
      <button class="tab" onclick="showTab('media',this)">Fotos y videos (<?= count($media_list) ?>)</button>
    </div>

    <!-- INFO -->
    <div class="tp show" id="tab-info">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <fieldset>
          <legend>Informacion del espacio</legend>
          <div class="g2">
            <div class="campo w"><label>Nombre *</label><input name="nombre" required value="<?= ve2($editar,'nombre') ?>"></div>
            <div class="campo"><label>Tipo</label>
              <select name="tipo">
                <?php foreach (['salon'=>'Salon','alojamiento'=>'Alojamiento','gastronomia'=>'Gastronomia','aire_libre'=>'Aire libre','servicio'=>'Servicio'] as $v => $l): ?>
                  <option value="<?= $v ?>"<?= ve2($editar,'tipo') === $v ? ' selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo"><label>Orden</label><input type="number" name="orden" value="<?= ve2($editar,'orden') ?: 10 ?>"></div>
            <div class="campo w"><label>Resumen</label><input name="resumen" value="<?= ve2($editar,'resumen') ?>"></div>
            <div class="campo w"><label>Descripcion</label><textarea name="descripcion" rows="4"><?= ve2($editar,'descripcion') ?></textarea></div>
            <div class="campo"><label>Capacidad (personas)</label><input type="number" name="capacidad" value="<?= ve2($editar,'capacidad') ?>"></div>
            <div class="campo"><label>Superficie m2</label><input type="number" name="superficie_m2" value="<?= ve2($editar,'superficie_m2') ?>"></div>
            <div class="campo"><label>Imagen cover (ruta)</label><input name="imagen" value="<?= ve2($editar,'imagen') ?>" placeholder="assets/img/salon.jpg"></div>
            <div class="campo w" style="flex-direction:row;align-items:center;gap:10px;padding:8px 0">
              <input type="checkbox" id="activo" name="activo" value="1"<?= ($editar['activo'] ?? 1) ? ' checked' : '' ?>>
              <label for="activo" style="font-size:.88rem;color:var(--text)">Visible en el sitio</label>
            </div>
          </div>
        </fieldset>
        <button type="submit" class="btn btn-t">Guardar cambios</button>
        <a href="espacios.php" class="btn btn-o" style="margin-left:8px">Cancelar</a>
      </form>
      <form method="post" style="margin-top:12px" onsubmit="return confirm('Eliminar este espacio?')">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <button type="submit" class="btn btn-d sm">Eliminar espacio</button>
      </form>
    </div>

    <!-- MEDIA - VERSION 3.7: one active file input per submit -->
    <div class="tp" id="tab-media">
      <form method="post" enctype="multipart/form-data" style="margin-bottom:22px">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="upload_media">
        <input type="hidden" name="espacio_id" value="<?= (int)$editar['id'] ?>">
        <fieldset>
          <legend>Subir foto o video</legend>
          <div class="g2">
            <div class="campo">
              <label>Tipo</label>
              <select name="tipo" id="espTipo" onchange="toggleDz(this.value)">
                <option value="imagen">Imagen (JPG / PNG / WEBP)</option>
                <option value="video">Video (MP4)</option>
              </select>
            </div>
            <div class="campo"><label>Titulo / caption</label><input name="titulo" placeholder="Ej: Vista del salon"></div>
            <div class="campo w">
              <div id="dz-img">
                <div class="dz" onclick="document.getElementById('espImgFile').click()">
                  <p style="color:var(--text2);font-size:.86rem;margin-bottom:6px">Clic para elegir imagen</p>
                  <span id="espImgName" style="color:var(--teal);font-size:.76rem"></span>
                  <input type="file" id="espImgFile" name="imagen" accept="image/jpeg,image/png,image/webp"
                         onchange="document.getElementById('espImgName').textContent=this.files[0]?.name||''">
                </div>
              </div>
              <div id="dz-vid" style="display:none">
                <div class="dz" onclick="document.getElementById('espVidFile').click()">
                  <p style="color:var(--text2);font-size:.86rem;margin-bottom:6px">Clic para elegir video MP4</p>
                  <span id="espVidName" style="color:var(--teal);font-size:.76rem"></span>
                  <input type="file" id="espVidFile" name="video" accept="video/mp4"
                         onchange="document.getElementById('espVidName').textContent=this.files[0]?.name||''">
                </div>
              </div>
              <p style="font-size:.74rem;color:var(--text3);margin-top:4px">Imagenes max 10MB · Videos max 50MB</p>
            </div>
          </div>
          <button type="submit" class="btn btn-t">Subir archivo</button>
        </fieldset>
      </form>

      <?php if ($media_list): ?>
        <p style="font-size:.82rem;color:var(--text2);margin-bottom:10px">
          <?= count($media_list) ?> archivos. El primero es el que se usa de portada
          en la tarjeta y el que abre el visor. Con &larr; y &rarr; cambias el orden.
        </p>
        <div class="mg">
          <?php $ult = count($media_list) - 1; foreach ($media_list as $i => $m): ?>
          <div class="mc">
            <?php if ($m['tipo'] === 'imagen'): ?>
              <img src="../<?= e($m['archivo']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <video muted preload="metadata"><source src="../<?= e($m['archivo']) ?>" type="video/mp4"></video>
            <?php endif; ?>
            <span class="mc-pos"><?= $i + 1 ?><?= $i === 0 ? ' · primero' : ($i === $ult ? ' · ultimo' : '') ?></span>
            <div class="mc-m">
              <span><?= e($m['titulo'] ?: $m['tipo']) ?></span>
              <form method="post" class="mc-mov">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="mover_media">
                <input type="hidden" name="mid" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="eid" value="<?= (int)$editar['id'] ?>">
                <button type="submit" name="dir" value="arriba" class="mc-b" title="Mover antes" <?= $i === 0 ? 'disabled' : '' ?>>&larr;</button>
                <button type="submit" name="dir" value="abajo"  class="mc-b" title="Mover despues" <?= $i === $ult ? 'disabled' : '' ?>>&rarr;</button>
              </form>
              <form method="post" onsubmit="return confirm('Eliminar?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="delete_media">
                <input type="hidden" name="mid" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="eid" value="<?= (int)$editar['id'] ?>">
                <button type="submit" class="btn btn-d sm" style="padding:3px 8px">X</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:var(--text3)">Sin archivos. Subi fotos del espacio para que aparezcan en el lightbox.</p>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- LIST + NEW -->
    <div class="tabs">
      <button class="tab active" onclick="showTab('lista',this)">Todos los espacios</button>
      <button class="tab" onclick="showTab('nuevo',this)">+ Nuevo</button>
    </div>

    <div class="tp show" id="tab-lista">
      <?php if ($lista): ?>
      <table>
        <tr><th>Nombre</th><th>Tipo</th><th>Capacidad</th><th>Visible</th><th>Acciones</th></tr>
        <?php foreach ($lista as $sp): ?>
        <tr>
          <td><strong><?= e($sp['nombre']) ?></strong></td>
          <td style="color:var(--text3)"><?= e($sp['tipo']) ?></td>
          <td><?= $sp['capacidad'] ? e($sp['capacidad']) . ' pers.' : '' ?></td>
          <td><?= $sp['activo'] ? '<span style="color:var(--teal)">Si</span>' : '<span style="color:var(--text3)">No</span>' ?></td>
          <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <a href="espacios.php?id=<?= (int)$sp['id'] ?>" class="btn btn-t sm">Editar</a>
            <form method="post" onsubmit="return confirm('Eliminar este espacio?')">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
              <button type="submit" class="btn btn-d sm">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <p style="color:var(--text3);padding:20px 0">No hay espacios.</p>
      <?php endif; ?>
    </div>

    <div class="tp" id="tab-nuevo">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <fieldset>
          <legend>Nuevo espacio</legend>
          <div class="g2">
            <div class="campo w"><label>Nombre *</label><input name="nombre" required></div>
            <div class="campo"><label>Tipo</label>
              <select name="tipo">
                <?php foreach (['salon'=>'Salon','alojamiento'=>'Alojamiento','gastronomia'=>'Gastronomia','aire_libre'=>'Aire libre','servicio'=>'Servicio'] as $v => $l): ?>
                  <option value="<?= $v ?>"><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo"><label>Orden</label><input type="number" name="orden" value="10"></div>
            <div class="campo w"><label>Resumen</label><input name="resumen"></div>
            <div class="campo w"><label>Descripcion</label><textarea name="descripcion" rows="3"></textarea></div>
            <div class="campo"><label>Capacidad</label><input type="number" name="capacidad"></div>
            <div class="campo"><label>Superficie m2</label><input type="number" name="superficie_m2"></div>
            <div class="campo w" style="flex-direction:row;align-items:center;gap:10px;padding:8px 0">
              <input type="checkbox" name="activo" value="1" checked>
              <label style="font-size:.88rem;color:var(--text)">Visible en el sitio</label>
            </div>
          </div>
        </fieldset>
        <button type="submit" class="btn btn-t">Crear espacio</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
function showTab(id, btn) {
  document.querySelectorAll('.tp').forEach(function(p){ p.classList.remove('show'); });
  document.querySelectorAll('.tab').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('tab-' + id).classList.add('show');
  if (btn) btn.classList.add('active');
}
function toggleDz(tipo) {
  document.getElementById('dz-img').style.display = tipo === 'video' ? 'none' : 'block';
  document.getElementById('dz-vid').style.display = tipo === 'video' ? 'block' : 'none';
}

/* Only one file input is submitted at a time, and oversized files are stopped
   in the browser instead of being silently discarded by the server. */
var LR_LIMITE = <?= (int)$LR_LIMITE ?>;
var LR_LIMITE_TXT = '<?= e($LR_LIMITE_TXT) ?>';
(function () {
  var acc = document.querySelector('input[name="accion"][value="upload_media"]');
  if (!acc || !acc.form) return;
  var form = acc.form;
  var img = form.querySelector('input[name="imagen"]');
  var vid = form.querySelector('input[name="video"]');
  var sel = form.querySelector('select[name="tipo"]');
  function sync() {
    var isVid = sel && sel.value === 'video';
    if (img) img.disabled = !!isVid;
    if (vid) vid.disabled = !isVid;
  }
  if (sel) sel.addEventListener('change', sync);
  sync();
  form.addEventListener('submit', function (ev) {
    var activo = (sel && sel.value === 'video') ? vid : img;
    if (!activo || !activo.files || !activo.files[0]) {
      ev.preventDefault();
      alert('Elegi un archivo primero.');
      return;
    }
    var mb = activo.files[0].size / 1048576;
    if (activo.files[0].size > LR_LIMITE) {
      ev.preventDefault();
      alert('El archivo pesa ' + mb.toFixed(1) + ' MB.\n'
          + 'El limite del servidor es ' + LR_LIMITE_TXT + '.\n'
          + 'Comprimilo con HandBrake antes de subirlo.');
    }
  });
})();
</script>
</body>
</html>
