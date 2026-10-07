<?php
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
$db = getDB();
$aviso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
  if (!csrfOk($_POST['csrf'] ?? null)) {
    $aviso = ['error','Sesion expirada.'];
  } else {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'toggle') {
      $id = (int)$_POST['id'];
      $db->prepare("UPDATE galeria SET activo = 1 - activo WHERE id=:i")->execute([':i'=>$id]);
      $aviso = ['ok','Visibilidad actualizada.'];
    } elseif ($accion === 'reorder') {
      $id = (int)$_POST['id'];
      $orden = (int)$_POST['orden'];
      $db->prepare("UPDATE galeria SET orden=:o WHERE id=:i")->execute([':o'=>$orden,':i'=>$id]);
      $aviso = ['ok','Orden actualizado.'];
    } elseif ($accion === 'delete') {
      $id = (int)$_POST['id'];
      $db->prepare("DELETE FROM galeria WHERE id=:i")->execute([':i'=>$id]);
      $aviso = ['ok','Imagen eliminada.'];
    } elseif ($accion === 'add') {
      $archivo = limpiar($_POST['archivo'] ?? '');
      $titulo  = limpiar($_POST['titulo'] ?? '');
      $alt     = limpiar($_POST['alt'] ?? '') ?: $titulo;
      $orden   = (int)($_POST['orden'] ?? 50);
      if ($archivo) {
        $db->prepare("INSERT INTO galeria (archivo,titulo,alt,orden,activo) VALUES (:a,:t,:al,:o,1)")
           ->execute([':a'=>$archivo,':t'=>$titulo,':al'=>$alt,':o'=>$orden]);
        $aviso = ['ok','Imagen agregada a la galeria.'];
      } else { $aviso = ['error','Ingresa la ruta del archivo.']; }
    }
  }
}

$items = [];
if ($db) {
  try { $items = $db->query("SELECT * FROM galeria ORDER BY orden ASC, id ASC")->fetchAll(); }
  catch(Exception $e){}
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Editor de galeria · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.12);--teal-b:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252;--green:#00e676}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}
.main{flex:1}.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between}.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:24px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:20px;border-left:3px solid}.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.grid2{display:grid;grid-template-columns:320px 1fr;gap:22px;align-items:start}@media(max-width:960px){.grid2{grid-template-columns:1fr}}
.add-card{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:20px}
.add-card h2{font-size:.95rem;font-weight:600;color:var(--text);margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:10px}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:13px}.field label{font-size:.76rem;color:var(--text3)}.field input{font:inherit;font-size:.9rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 12px;width:100%}.field input:focus{outline:none;border-color:var(--teal)}.field small{font-size:.72rem;color:var(--text3)}
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:4px;font:inherit;font-size:.8rem;cursor:pointer;border:0;transition:.18s;font-weight:500}.btn-teal{background:var(--teal);color:#0a1628}.btn-teal:hover{background:#00b898}.btn-sm{padding:5px 11px;font-size:.74rem}.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2)}.btn-outline:hover{border-color:var(--teal-b);color:var(--teal)}.btn-danger{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-danger:hover{background:rgba(255,82,82,.22)}
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.gal-item{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;position:relative}
.gal-item img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.gal-item.hidden img{opacity:.35}
.gal-meta{padding:10px 12px}
.gal-meta .title{font-size:.82rem;font-weight:500;color:var(--text);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gal-meta .path{font-size:.7rem;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gal-actions{padding:8px 12px;border-top:1px solid var(--border);display:flex;gap:6px;align-items:center}
.gal-order{width:50px;font:inherit;font-size:.8rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:3px;padding:4px 7px;text-align:center}
.badge-hidden{position:absolute;top:8px;right:8px;background:rgba(255,82,82,.8);color:#fff;font-size:.64rem;padding:2px 7px;border-radius:10px;font-weight:600}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>Editor de galeria</h1>
    <a href="../galeria.php" target="_blank" class="btn btn-outline btn-sm">Ver galeria</a>
  </div>
  <div class="content">
    <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <?php if(!$db): ?><div class="aviso error">Sin base de datos - la galeria usa las imagenes estaticas de respaldo.</div><?php endif; ?>

    <div class="grid2">
      <div class="add-card">
        <h2>Agregar imagen</h2>
        <p style="font-size:.82rem;color:var(--text3);margin-bottom:16px">Subi la imagen a <code>assets/img/galeria/</code> via File Manager primero, luego registrala aqui.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="accion" value="add">
          <div class="field">
            <label>Ruta del archivo</label>
            <input type="text" name="archivo" required placeholder="assets/img/galeria/nueva.jpg">
            <small>Relativa a public_html/</small>
          </div>
          <div class="field"><label>Titulo (caption)</label><input type="text" name="titulo" placeholder="El salon al amanecer"></div>
          <div class="field"><label>Texto alternativo</label><input type="text" name="alt" placeholder="Descripcion para accesibilidad"></div>
          <div class="field"><label>Orden (menor = primero)</label><input type="number" name="orden" value="50"></div>
          <button type="submit" class="btn btn-teal">Agregar a la galeria</button>
        </form>
      </div>

      <div>
        <p style="font-size:.84rem;color:var(--text2);margin-bottom:14px"><?= count($items) ?> imagenes · Orden menor aparece primero</p>
        <?php if($items): ?>
        <div class="gallery-grid">
          <?php foreach($items as $img): ?>
          <div class="gal-item <?= !$img['activo'] ? 'hidden' : '' ?>">
            <?php if(!$img['activo']): ?><span class="badge-hidden">OCULTA</span><?php endif; ?>
            <img src="../<?= e($img['archivo']) ?>" alt="<?= e($img['alt'] ?: $img['titulo']) ?>" loading="lazy" onerror="this.style.background='#1a2a3a';this.style.height='80px'">
            <div class="gal-meta">
              <div class="title"><?= e($img['titulo'] ?: 'Sin titulo') ?></div>
              <div class="path"><?= e($img['archivo']) ?></div>
            </div>
            <div class="gal-actions">
              <form method="post" style="display:contents">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="reorder">
                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                <input type="number" name="orden" value="<?= (int)$img['orden'] ?>" class="gal-order" title="Orden">
                <button type="submit" class="btn btn-outline btn-sm">OK</button>
              </form>
              <form method="post" style="display:contents">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" title="<?= $img['activo']?'Ocultar':'Mostrar' ?>"><?= $img['activo']?'Ocultar':'Mostrar' ?></button>
              </form>
              <form method="post" style="display:contents" onsubmit="return confirm('Eliminar esta imagen de la galeria?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="delete">
                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">X</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <p style="color:var(--text3);padding:20px 0">La galeria usa las imagenes estaticas de respaldo (<code>assets/img/galeria/</code>). Agrega una imagen arriba para empezar a gestionar la galeria desde la base de datos.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
</body>
</html>
