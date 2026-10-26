<?php
/* admin/retiros.php - VERSION 3.9
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


/* ── helpers ─────────────────────────────────────────────── */
function ve($r, $k) {
    return isset($r[$k]) ? htmlspecialchars((string)$r[$k], ENT_QUOTES, 'UTF-8') : '';
}

/* ── POST handler ─────────────────────────────────────────── */
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

        /* ── Save retiro info ── */
        if ($accion === 'guardar') {
            $titulo        = limpiar($_POST['titulo']        ?? '');
            $bajada        = limpiar($_POST['bajada']        ?? '') ?: null;
            $facilitador   = limpiar($_POST['facilitador']   ?? '') ?: null;
            $fac_ig        = limpiar($_POST['facilitador_ig']?? '') ?: null;
            $f_inicio      = limpiar($_POST['fecha_inicio']  ?? '') ?: null;
            $f_fin         = limpiar($_POST['fecha_fin']     ?? '') ?: null;
            $modalidad     = limpiar($_POST['modalidad']     ?? 'retiro');
            $estado        = limpiar($_POST['estado']        ?? 'borrador');
            $precio        = ($_POST['precio_desde'] ?? '') !== '' ? (float)$_POST['precio_desde'] : null;
            $moneda        = limpiar($_POST['moneda']        ?? 'ARS') ?: 'ARS';
            $cupos         = ($_POST['cupos'] ?? '') !== '' ? (int)$_POST['cupos'] : null;
            $wa            = limpiar($_POST['wa_contacto']   ?? '') ?: null;
            $url_ins       = limpiar($_POST['url_inscripcion']?? '') ?: null;
            $imagen        = limpiar($_POST['imagen']        ?? '') ?: null;
            $descripcion   = limpiar($_POST['descripcion']   ?? '') ?: null;
            $incluye       = limpiar($_POST['incluye']       ?? '') ?: null;
            $es_propio     = !empty($_POST['es_propio']) ? 1 : 0;
            $retiro_id     = (int)($_POST['id'] ?? 0);

            if (!$titulo) {
                $aviso = ['error', 'El titulo es obligatorio.'];
            } else {
                $params = [
                    ':titulo'          => $titulo,
                    ':bajada'          => $bajada,
                    ':facilitador'     => $facilitador,
                    ':facilitador_ig'  => $fac_ig,
                    ':fecha_inicio'    => $f_inicio,
                    ':fecha_fin'       => $f_fin,
                    ':modalidad'       => $modalidad,
                    ':estado'          => $estado,
                    ':precio_desde'    => $precio,
                    ':moneda'          => $moneda,
                    ':cupos'           => $cupos,
                    ':wa_contacto'     => $wa,
                    ':url_inscripcion' => $url_ins,
                    ':imagen'          => $imagen,
                    ':descripcion'     => $descripcion,
                    ':incluye'         => $incluye,
                    ':es_propio'       => $es_propio,
                ];

                if ($retiro_id) {
                    $db->prepare("UPDATE retiros SET
                        titulo=:titulo, bajada=:bajada, facilitador=:facilitador,
                        facilitador_ig=:facilitador_ig, fecha_inicio=:fecha_inicio,
                        fecha_fin=:fecha_fin, modalidad=:modalidad, estado=:estado,
                        precio_desde=:precio_desde, moneda=:moneda, cupos=:cupos,
                        wa_contacto=:wa_contacto, url_inscripcion=:url_inscripcion,
                        imagen=:imagen, descripcion=:descripcion, incluye=:incluye,
                        es_propio=:es_propio
                        WHERE id=:id")->execute($params + [':id' => $retiro_id]);
                    $aviso = ['ok', 'Retiro actualizado.'];
                } else {
                    $slug_base = preg_replace('/[^a-z0-9]+/', '-', strtolower($titulo));
                    $slug_base = trim($slug_base, '-');
                    $slug = $slug_base . '-' . date('Y');
                    // ensure unique slug
                    $n = 1;
                    while (true) {
                        $chk = $db->prepare("SELECT id FROM retiros WHERE slug=:s LIMIT 1");
                        $chk->execute([':s' => $slug]);
                        if (!$chk->fetch()) break;
                        $slug = $slug_base . '-' . date('Y') . '-' . $n++;
                    }
                    $db->prepare("INSERT INTO retiros
                        (slug, titulo, bajada, facilitador, facilitador_ig,
                         fecha_inicio, fecha_fin, modalidad, estado,
                         precio_desde, moneda, cupos, wa_contacto, url_inscripcion,
                         imagen, descripcion, incluye, es_propio)
                        VALUES
                        (:slug, :titulo, :bajada, :facilitador, :facilitador_ig,
                         :fecha_inicio, :fecha_fin, :modalidad, :estado,
                         :precio_desde, :moneda, :cupos, :wa_contacto, :url_inscripcion,
                         :imagen, :descripcion, :incluye, :es_propio)")
                        ->execute($params + [':slug' => $slug]);
                    $retiro_id = (int)$db->lastInsertId();
                    $aviso = ['ok', 'Retiro creado.'];
                }

                $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
                $st->execute([':i' => $retiro_id]);
                $editar = $st->fetch() ?: null;
            }

        /* ── Upload media (image or video) ──
           v3.5 FIX: the guardar block was never closed in v3.4, so this elseif
           landed inside it. That is a hard parse error and it is why every hit
           on admin/retiros.php returned 500, upload or not. */
        } elseif ($accion === 'upload_media') {
      $retiro_id = (int)($_POST['retiro_id'] ?? 0);
      $tipo      = ($_POST['tipo'] ?? '') === 'video' ? 'video' : 'imagen';
      $titulo_m  = limpiar($_POST['titulo'] ?? '') ?: '';

      if ($tipo === 'imagen') {
        $f = $_FILES['imagen'] ?? null;
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!$f || $f['error'] !== 0) {
          $aviso = ['error', 'Error al subir (cod:'.$f['error'].')'];
        } elseif (!in_array(lrMime($f['tmp_name']), $allowed)) {
          $aviso = ['error', 'Solo JPG, PNG o WEBP.'];
        } else {
          $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
          $name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $titulo_m ?: pathinfo($f['name'], PATHINFO_FILENAME))));
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
              $db->prepare("INSERT INTO retiro_media (retiro_id,tipo,archivo,titulo,orden) VALUES (:r,:t,:a,:ti,10)")
                 ->execute([':r'=>$retiro_id,':t'=>'imagen',':a'=>$ruta,':ti'=>$titulo_m]);
              $aviso = ['ok', 'Imagen subida: '.basename($dest)];
            } catch (Exception $ex) {
              $aviso = ['error', 'Error BD: '.$ex->getMessage()];
            }
          } else {
            $aviso = ['error', 'No se pudo guardar en assets/img/galeria/'];
          }
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
          $name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $titulo_m ?: pathinfo($f['name'], PATHINFO_FILENAME))));
          $name = $name ?: 'vid-'.time();
          $dest = $dest_dir . $name . '.' . $ext;
          $i = 1;
          while (file_exists($dest)) { $dest = $dest_dir . $name . '-' . $i++ . '.' . $ext; }
          if (move_uploaded_file($f['tmp_name'], $dest)) {
            $ruta = 'assets/video/pages/' . basename($dest);
            try {
              $db->prepare("INSERT INTO retiro_media (retiro_id,tipo,archivo,titulo,orden) VALUES (:r,:t,:a,:ti,10)")
                 ->execute([':r'=>$retiro_id,':t'=>'video',':a'=>$ruta,':ti'=>$titulo_m]);
              $aviso = ['ok', 'Video subido: '.basename($dest).' ('.round($f['size']/1024/1024,1).'MB)'];
            } catch (Exception $ex) {
              $aviso = ['error', 'Error BD: '.$ex->getMessage()];
            }
          } else {
            $aviso = ['error', 'No se pudo guardar en assets/video/pages/'];
          }
          }
        }
      }

      $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
      $st->execute([':i' => $retiro_id]);
      $editar = $st->fetch() ?: null;

        } elseif ($accion === 'delete_media') {
            $mid = (int)$_POST['mid'];
            $retiro_id = (int)$_POST['retiro_id'];
            $row = $db->prepare("SELECT archivo FROM retiro_media WHERE id=:i");
            $row->execute([':i' => $mid]);
            $row = $row->fetch();
            if ($row) {
                $path = __DIR__ . '/../' . $row['archivo'];
                if (file_exists($path)) unlink($path);
                $db->prepare("DELETE FROM retiro_media WHERE id=:i")->execute([':i' => $mid]);
            }
            $aviso = ['ok', 'Eliminado.'];
            $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
            $st->execute([':i' => $retiro_id]);
            $editar = $st->fetch() ?: null;

        /* ── Approve / delete review ── */
        } elseif ($accion === 'approve_resena') {
            $rid = (int)$_POST['rid'];
            $retiro_id = (int)$_POST['retiro_id'];
            $db->prepare("UPDATE resenas SET aprobada = 1 - aprobada WHERE id=:i")->execute([':i' => $rid]);
            $aviso = ['ok', 'Resena actualizada.'];
            $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
            $st->execute([':i' => $retiro_id]);
            $editar = $st->fetch() ?: null;

        } elseif ($accion === 'delete_resena') {
            $rid = (int)$_POST['rid'];
            $retiro_id = (int)$_POST['retiro_id'];
            $db->prepare("DELETE FROM resenas WHERE id=:i")->execute([':i' => $rid]);
            $aviso = ['ok', 'Resena eliminada.'];
            $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
            $st->execute([':i' => $retiro_id]);
            $editar = $st->fetch() ?: null;

        /* ── Delete retiro ── */
        } elseif ($accion === 'eliminar') {
            $db->prepare("DELETE FROM retiros WHERE id=:i")->execute([':i' => (int)$_POST['id']]);
            $aviso = ['ok', 'Retiro eliminado.'];
        }
    }
}

/* ── Load edit from GET ─────────────────────────────────── */
if (!$editar && isset($_GET['id']) && $db) {
    $st = $db->prepare("SELECT * FROM retiros WHERE id=:i");
    $st->execute([':i' => (int)$_GET['id']]);
    $editar = $st->fetch() ?: null;
}

/* ── Lists ──────────────────────────────────────────────── */
$lista = [];
if ($db) {
    try { $lista = $db->query("SELECT * FROM retiros ORDER BY fecha_inicio DESC")->fetchAll(); }
    catch (Exception $e) {}
}

$media_list = [];
$resenas_list = [];
if ($editar && $db) {
    try {
        $m = $db->prepare("SELECT * FROM retiro_media WHERE retiro_id=:i ORDER BY tipo DESC, orden ASC");
        $m->execute([':i' => $editar['id']]);
        $media_list = $m->fetchAll();

        $rv = $db->prepare("SELECT * FROM resenas WHERE retiro_id=:i ORDER BY creado DESC");
        $rv->execute([':i' => $editar['id']]);
        $resenas_list = $rv->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- admin/retiros.php VERSION 3.9 -->
<title>Retiros · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--td:rgba(0,201,167,.12);--tb:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252;--amber:#ffab40;--green:#00e676}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}
.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}
.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-sect{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}
.sb-nav li a{display:flex;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}
.sb-nav li a:hover{background:var(--td);color:var(--text)}
.sb-nav li a.active{background:var(--td);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}
.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}
.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:22px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}
.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}
.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px}
.tab{padding:7px 16px;border-radius:20px;border:1.5px solid var(--border);background:transparent;color:var(--text2);font:inherit;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s}
.tab:hover{border-color:var(--tb);color:var(--text)}
.tab.active{border-color:var(--tb);background:var(--td);color:var(--teal)}
.tp{display:none}.tp.show{display:block}
fieldset{border:1px solid var(--border);padding:20px;background:var(--card);margin-bottom:18px;border-radius:6px}
legend{font-family:'Fraunces',serif;font-size:1.05rem;padding:0 10px;color:var(--text);font-weight:300}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.campo{display:flex;flex-direction:column;gap:5px}
.campo.w{grid-column:1/-1}
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
.badge{font-size:.67rem;padding:2px 9px;border-radius:20px;font-weight:600;text-transform:uppercase}
.badge.publicado{background:rgba(0,201,167,.15);color:var(--teal)}
.badge.borrador{background:rgba(74,106,133,.15);color:var(--text3)}
.badge.agotado{background:rgba(255,82,82,.15);color:var(--red)}
.badge.finalizado{background:rgba(74,106,133,.12);color:var(--text3)}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:14px}
.mc{background:var(--bg2);border:1px solid var(--border);border-radius:6px;overflow:hidden}
.mc img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.mc video{width:100%;max-height:90px;object-fit:cover;display:block}
.mc-meta{padding:7px 10px;font-size:.72rem;color:var(--text3);display:flex;align-items:center;justify-content:space-between}
.dz{border:2px dashed var(--border);border-radius:8px;padding:22px;text-align:center;cursor:pointer;transition:.2s;background:var(--bg2);margin-bottom:10px}
.dz:hover{border-color:var(--tb)}.dz input{display:none}
.stars{color:var(--amber)}
</style>
</head>
<body>
<div class="layout">

<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1><?= $editar ? 'Editar: ' . e($editar['titulo']) : 'Retiros' ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($editar): ?>
        <a href="../retiro.php?r=<?= urlencode($editar['slug']) ?>" target="_blank" class="btn btn-o sm">Ver en sitio</a>
        <a href="retiros.php" class="btn btn-o sm">Ver todos</a>
      <?php else: ?>
        <button onclick="showTab('nuevo',this)" class="btn btn-t sm">+ Nuevo retiro</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($aviso): ?>
      <div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div>
    <?php endif; ?>

    <?php if ($editar): ?>
    <!-- ═══ EDIT MODE ════════════════════════════════════════ -->
    <div class="tabs">
      <button class="tab active" onclick="showTab('info',this)">Informacion</button>
      <button class="tab" onclick="showTab('media',this)">Fotos y videos (<?= count($media_list) ?>)</button>
      <button class="tab" onclick="showTab('resenas',this)">Resenas (<?= count($resenas_list) ?>)</button>
    </div>

    <!-- INFO -->
    <div class="tp show" id="tab-info">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <fieldset>
          <legend>Informacion del retiro</legend>
          <div class="grid2">
            <div class="campo w"><label>Titulo *</label><input name="titulo" required value="<?= ve($editar,'titulo') ?>"></div>
            <div class="campo w"><label>Bajada</label><input name="bajada" value="<?= ve($editar,'bajada') ?>"></div>
            <div class="campo"><label>Facilitador</label><input name="facilitador" value="<?= ve($editar,'facilitador') ?>
            <small style="display:block;color:#4a6a85;font-size:.8rem;margin-top:6px;line-height:1.6">Nombre, o nombre y contacto entre parentesis:<br><code>Johnny (Jonathanr@fasedos.com.ar)</code><br><code>Johnny (+54 9 11 6939-0659)</code><br>El contacto queda clickeable en la pagina.</small>"></div>
            <div class="campo"><label>Instagram facilitador</label><input name="facilitador_ig" value="<?= ve($editar,'facilitador_ig') ?>" placeholder="sin @"></div>
            <div class="campo"><label>Fecha inicio</label><input type="date" name="fecha_inicio" value="<?= ve($editar,'fecha_inicio') ?>"></div>
            <div class="campo"><label>Fecha fin</label><input type="date" name="fecha_fin" value="<?= ve($editar,'fecha_fin') ?>"></div>
            <div class="campo"><label>Modalidad</label>
              <select name="modalidad">
                <?php foreach (['retiro','taller','jornada','encuentro','campamento'] as $m): ?>
                  <option value="<?= $m ?>"<?= ve($editar,'modalidad')===$m?' selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo"><label>Estado</label>
              <select name="estado">
                <?php foreach (['borrador','publicado','agotado','finalizado'] as $e): ?>
                  <option value="<?= $e ?>"<?= ve($editar,'estado')===$e?' selected':'' ?>><?= $e ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo"><label>Precio desde</label><input type="number" step="0.01" name="precio_desde" value="<?= ve($editar,'precio_desde') ?>"></div>
            <div class="campo"><label>Moneda</label><input name="moneda" value="<?= ve($editar,'moneda') ?: 'ARS' ?>"></div>
            <div class="campo"><label>Cupos</label><input type="number" name="cupos" value="<?= ve($editar,'cupos') ?>"></div>
            <div class="campo"><label>WhatsApp</label><input name="wa_contacto" value="<?= ve($editar,'wa_contacto') ?>" placeholder="5491133629514"></div>
            <div class="campo"><label>URL inscripcion</label><input name="url_inscripcion" value="<?= ve($editar,'url_inscripcion') ?>
            <small style="display:block;color:#4a6a85;font-size:.8rem;margin-top:6px;line-height:1.6">Acepta cuatro cosas y arma el boton solo:<br>un mail, y abre el programa de correo<br>un telefono, y abre WhatsApp<br>una direccion web completa<br>vacio, y queda solo el boton de WhatsApp</small>"></div>
            <div class="campo"><label>Imagen cover (ruta)</label><input name="imagen" value="<?= ve($editar,'imagen') ?>" placeholder="uploads/retiros/foto.jpg"></div>
            <div class="campo w" style="flex-direction:row;align-items:center;gap:10px;padding:8px 0">
              <input type="checkbox" id="esp" name="es_propio" value="1"<?= ($editar['es_propio']??0)?' checked':'' ?>>
              <label for="esp" style="font-size:.88rem;color:var(--text)">Organizado por La Reserva</label>
            </div>
            <div class="campo w"><label>Descripcion</label><textarea name="descripcion" rows="5"><?= ve($editar,'descripcion') ?></textarea></div>
            <div class="campo w"><label>Incluye</label><textarea name="incluye" rows="4"><?= ve($editar,'incluye') ?></textarea>
            <small style="display:block;color:#4a6a85;font-size:.8rem;margin-top:6px;line-height:1.6">Tres formas de escribir cada renglon:<br><code>Alojamiento (3 noches)</code> es un item suelto.<br><code>Adulto | $442.000</code> pone el valor alineado a la derecha.<br><code># 3 dias y 2 noches</code> es un subtitulo que separa grupos.</small></div>
          </div>
        </fieldset>
        <button type="submit" class="btn btn-t">Guardar cambios</button>
        <a href="retiros.php" class="btn btn-o" style="margin-left:8px">Cancelar</a>
      </form>

      <form method="post" style="margin-top:12px" onsubmit="return confirm('Eliminar este retiro permanentemente?')">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <button type="submit" class="btn btn-d sm">Eliminar retiro</button>
      </form>
    </div>

    <!-- MEDIA -->
    <div class="tp" id="tab-media">
      <form method="post" enctype="multipart/form-data" style="margin-bottom:22px">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="upload_media">
        <input type="hidden" name="retiro_id" value="<?= (int)$editar['id'] ?>">
        <fieldset>
          <legend>Subir foto o video</legend>
          <div class="grid2">
            <div class="campo">
              <label>Tipo</label>
              <select name="tipo" id="tipoSel">
                <option value="imagen">Imagen (JPG / PNG / WEBP)</option>
                <option value="video">Video (MP4)</option>
              </select>
            </div>
            <div class="campo"><label>Titulo / caption</label><input name="titulo" placeholder="Ej: Vista del salon"></div>
            <div class="campo w">
              <div id="dz-img">
                <div class="dz" onclick="document.getElementById('imgFile').click()">
                  <p style="color:var(--text2);font-size:.86rem;margin-bottom:6px">Clic para elegir imagen</p>
                  <span id="imgFname" style="color:var(--teal);font-size:.76rem"></span>
                  <input type="file" id="imgFile" name="imagen"
                         accept="image/jpeg,image/png,image/webp"
                         onchange="document.getElementById('imgFname').textContent=this.files[0]?.name||''">
                </div>
              </div>
              <div id="dz-vid" style="display:none">
                <div class="dz" onclick="document.getElementById('vidFile').click()">
                  <p style="color:var(--text2);font-size:.86rem;margin-bottom:6px">Clic para elegir video MP4</p>
                  <span id="vidFname" style="color:var(--teal);font-size:.76rem"></span>
                  <input type="file" id="vidFile" name="video"
                         accept="video/mp4"
                         onchange="document.getElementById('vidFname').textContent=this.files[0]?.name||''">
                </div>
              </div>
              <p style="font-size:.74rem;color:var(--text3)">
                Imagenes: max 10 MB &nbsp;|&nbsp; Videos: max 50 MB (comprime con HandBrake si da error)
              </p>
            </div>
          </div>
          <button type="submit" class="btn btn-t">Subir archivo</button>
        </fieldset>
      </form>

      <?php if ($media_list): ?>
        <p style="color:var(--text2);font-size:.82rem;margin-bottom:10px"><?= count($media_list) ?> archivos</p>
        <div class="media-grid">
          <?php foreach ($media_list as $m): ?>
          <div class="mc">
            <?php if ($m['tipo'] === 'imagen'): ?>
              <img src="../<?= e($m['archivo']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <video muted preload="metadata">
                <source src="../<?= e($m['archivo']) ?>" type="video/mp4">
              </video>
            <?php endif; ?>
            <div class="mc-meta">
              <span><?= e($m['titulo'] ?: $m['tipo']) ?></span>
              <form method="post" onsubmit="return confirm('Eliminar?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="delete_media">
                <input type="hidden" name="mid" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="retiro_id" value="<?= (int)$editar['id'] ?>">
                <button type="submit" class="btn btn-d sm" style="padding:3px 8px">X</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:var(--text3)">Sin archivos subidos. Subi fotos o videos del retiro.</p>
      <?php endif; ?>
    </div>

    <!-- RESENAS -->
    <div class="tp" id="tab-resenas">
      <?php if ($resenas_list): ?>
        <table>
          <tr><th>Nombre</th><th>Puntuacion</th><th>Comentario</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
          <?php foreach ($resenas_list as $rv): ?>
          <tr>
            <td><strong><?= e($rv['nombre']) ?></strong><?php if($rv['email']): ?><br><small style="color:var(--teal)"><?= e($rv['email']) ?></small><?php endif; ?></td>
            <td><span class="stars"><?= str_repeat('*', (int)$rv['puntuacion']) ?></span> <?= (int)$rv['puntuacion'] ?>/5</td>
            <td style="max-width:260px;font-size:.82rem"><?= nl2br(e($rv['comentario'])) ?></td>
            <td style="font-size:.76rem;color:var(--text3)"><?= date('d/m/Y', strtotime($rv['creado'])) ?></td>
            <td><?= $rv['aprobada'] ? '<span style="color:var(--teal)">Aprobada</span>' : '<span style="color:var(--amber)">Pendiente</span>' ?></td>
            <td style="display:flex;gap:6px">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="approve_resena">
                <input type="hidden" name="rid" value="<?= (int)$rv['id'] ?>">
                <input type="hidden" name="retiro_id" value="<?= (int)$editar['id'] ?>">
                <button type="submit" class="btn btn-o sm"><?= $rv['aprobada'] ? 'Ocultar' : 'Aprobar' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Eliminar resena?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="delete_resena">
                <input type="hidden" name="rid" value="<?= (int)$rv['id'] ?>">
                <input type="hidden" name="retiro_id" value="<?= (int)$editar['id'] ?>">
                <button type="submit" class="btn btn-d sm">X</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="color:var(--text3)">No hay resenas todavia.</p>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══ LIST MODE ════════════════════════════════════════ -->
    <div class="tabs">
      <button class="tab active" onclick="showTab('lista',this)">Todos los retiros</button>
      <button class="tab" onclick="showTab('nuevo',this)">+ Nuevo retiro</button>
    </div>

    <!-- LIST -->
    <div class="tp show" id="tab-lista">
      <?php if ($lista): ?>
      <table>
        <tr><th>Titulo</th><th>Fecha inicio</th><th>Estado</th><th>Acciones</th></tr>
        <?php foreach ($lista as $row): ?>
        <tr>
          <td>
            <strong><?= e($row['titulo']) ?></strong>
            <?php if ($row['facilitador'] ?? ''): ?>
              <br><small style="color:var(--text3)">Coordina: <?= e($row['facilitador']) ?></small>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--text2)"><?= $row['fecha_inicio'] ? e(date('d/m/Y', strtotime($row['fecha_inicio']))) : '' ?></td>
          <td><span class="badge <?= e($row['estado']) ?>"><?= e($row['estado']) ?></span></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <a href="retiros.php?id=<?= (int)$row['id'] ?>" class="btn btn-t sm">Editar</a>
            <a href="../retiro.php?r=<?= urlencode($row['slug']) ?>" target="_blank" class="btn btn-o sm">Ver</a>
            <form method="post" onsubmit="return confirm('Eliminar este retiro?')">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button type="submit" class="btn btn-d sm">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <p style="color:var(--text3);padding:20px 0">No hay retiros. Crea el primero.</p>
      <?php endif; ?>
    </div>

    <!-- NEW -->
    <div class="tp" id="tab-nuevo">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <fieldset>
          <legend>Nuevo retiro</legend>
          <div class="grid2">
            <div class="campo w"><label>Titulo *</label><input name="titulo" required></div>
            <div class="campo w"><label>Bajada</label><input name="bajada"></div>
            <div class="campo"><label>Facilitador</label><input name="facilitador"></div>
            <div class="campo"><label>Instagram facilitador</label><input name="facilitador_ig" placeholder="sin @"></div>
            <div class="campo"><label>Fecha inicio</label><input type="date" name="fecha_inicio"></div>
            <div class="campo"><label>Fecha fin</label><input type="date" name="fecha_fin"></div>
            <div class="campo"><label>Modalidad</label>
              <select name="modalidad">
                <?php foreach (['retiro','taller','jornada','encuentro','campamento'] as $m): ?>
                  <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo"><label>Estado</label>
              <select name="estado">
                <option value="borrador">borrador</option>
                <option value="publicado">publicado</option>
              </select>
            </div>
            <div class="campo"><label>Precio desde</label><input type="number" step="0.01" name="precio_desde"></div>
            <div class="campo"><label>Moneda</label><input name="moneda" value="ARS"></div>
            <div class="campo"><label>Cupos</label><input type="number" name="cupos"></div>
            <div class="campo"><label>WhatsApp</label><input name="wa_contacto" placeholder="5491133629514"></div>
            <div class="campo w"><label>Descripcion</label><textarea name="descripcion" rows="4"></textarea></div>
            <div class="campo w"><label>Incluye (una linea por item)</label><textarea name="incluye" rows="3"></textarea></div>
          </div>
        </fieldset>
        <button type="submit" class="btn btn-t">Crear retiro</button>
        <p style="font-size:.76rem;color:var(--text3);margin-top:10px">Despues podes subir fotos y videos desde la pestana "Fotos y videos".</p>
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
document.getElementById('tipoSel') && document.getElementById('tipoSel').addEventListener('change', function(){
  var isVid = this.value === 'video';
  document.getElementById('dz-img').style.display = isVid ? 'none' : 'block';
  document.getElementById('dz-vid').style.display = isVid ? 'block' : 'none';
});

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
