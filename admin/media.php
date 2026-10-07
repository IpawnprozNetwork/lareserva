<?php
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
$db = getDB();
$aviso = null;
$rol = usuarioActual()['rol'];

// ── Handle uploads ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfOk($_POST['csrf'] ?? null)) {
    $aviso = ['error','Sesión expirada.'];
  } else {
    $accion = $_POST['accion'] ?? '';

    // Upload gallery image
    if ($accion === 'upload_galeria') {
      $f = $_FILES['imagen'] ?? null;
      $titulo = limpiar($_POST['titulo'] ?? '');
      $orden  = (int)($_POST['orden'] ?? 50);
      $allowed = ['image/jpeg','image/png','image/webp'];
      if (!$f || $f['error'] !== 0) {
        $aviso = ['error','Error al subir. Verificá que sea JPG/PNG/WEBP.'];
      } elseif (!in_array(lrMime($f['tmp_name']), $allowed)) {
        $aviso = ['error','Solo se aceptan JPG, PNG o WEBP.'];
      } else {
        $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
        $name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $titulo ?: pathinfo($f['name'], PATHINFO_FILENAME))));
        $name = $name ?: 'imagen-' . time();
        $dest_dir = __DIR__.'/../assets/img/galeria/';
        if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
        $dest = $dest_dir . $name . '.' . $ext;
        // avoid overwrite
        $i = 1;
        while (file_exists($dest)) { $dest = $dest_dir . $name . '-' . $i++ . '.' . $ext; }
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          $ruta = 'assets/img/galeria/' . basename($dest);
          if ($db) {
            try {
              $db->prepare("INSERT INTO galeria (archivo,titulo,alt,orden,activo) VALUES (:a,:t,:t2,:o,1)")
                 ->execute([':a'=>$ruta,':t'=>$titulo,':t2'=>$titulo,':o'=>$orden]);
            } catch(Exception $e){ error_log($e->getMessage()); }
          }
          $aviso = ['ok','Imagen subida: '.$ruta];
        } else {
          $aviso = ['error','No se pudo guardar. Verificá permisos de assets/img/galeria/'];
        }
      }
    }

    // Upload welcome/hero video
    elseif ($accion === 'upload_video') {
      $pagina = $_POST['pagina'] ?? '';
      $map = [
        'welcome' => ['../assets/video/hero.mp4',          '../assets/video/hero-poster.jpg'],
        'historia'=> ['../assets/video/pages/historia.mp4','../assets/video/pages/historia-poster.jpg'],
        'espacios'=> ['../assets/video/pages/espacios.mp4','../assets/video/pages/espacios-poster.jpg'],
        'retiros' => ['../assets/video/pages/retiros.mp4', '../assets/video/pages/retiros-poster.jpg'],
        'galeria' => ['../assets/video/pages/galeria.mp4', '../assets/video/pages/galeria-poster.jpg'],
      ];
      $f = $_FILES['video'] ?? null;
      if (!isset($map[$pagina])) {
        $aviso = ['error','Página inválida.'];
      } elseif (!$f || $f['error'] !== 0) {
        $errs=[1=>'Demasiado grande (comprimí con HandBrake)',2=>'Demasiado grande',3=>'Incompleto',4=>'Sin archivo'];
        $aviso = ['error', $errs[$f['error']??4] ?? 'Error '.$f['error']];
      } else {
        $dest = __DIR__.'/'.$map[$pagina][0];
        $dir  = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          if (is_executable('/usr/bin/ffmpeg')) {
            $poster = __DIR__.'/'.$map[$pagina][1];
            shell_exec("/usr/bin/ffmpeg -nostdin -v error -ss 5 -i ".escapeshellarg($dest)." -frames:v 1 -q:v 4 ".escapeshellarg($poster)." -y 2>/dev/null");
          }
          $aviso = ['ok','Video subido para: '.$pagina.' ('.round($f['size']/1024/1024,1).'MB)'];
        } else {
          $aviso = ['error','No se pudo guardar el video.'];
        }
      }
    }

    // Toggle/delete gallery image
    elseif ($accion === 'toggle_galeria' && $db) {
      $id = (int)$_POST['id'];
      $db->prepare("UPDATE galeria SET activo=1-activo WHERE id=:i")->execute([':i'=>$id]);
      $aviso = ['ok','Visibilidad actualizada.'];
    }
    elseif ($accion === 'delete_galeria' && $db) {
      $id = (int)$_POST['id'];
      $row = $db->prepare("SELECT archivo FROM galeria WHERE id=:i");
      $row->execute([':i'=>$id]); $row = $row->fetch();
      if ($row) {
        $path = __DIR__.'/../'.$row['archivo'];
        if (file_exists($path)) unlink($path);
        $db->prepare("DELETE FROM galeria WHERE id=:i")->execute([':i'=>$id]);
      }
      $aviso = ['ok','Imagen eliminada.'];
    }
    elseif ($accion === 'reorder_galeria' && $db) {
      $id = (int)$_POST['id']; $orden = (int)$_POST['orden'];
      $db->prepare("UPDATE galeria SET orden=:o WHERE id=:i")->execute([':o'=>$orden,':i'=>$id]);
      $aviso = ['ok','Orden actualizado.'];
    }
  }
}

// ── Load data ───────────────────────────────────────────────────
$galeria = [];
if ($db) {
  try { $galeria = $db->query("SELECT * FROM galeria ORDER BY orden ASC,id ASC")->fetchAll(); }
  catch(Exception $e){}
}

// ── Video status ─────────────────────────────────────────────────
function vidSize(string $rel): string {
  $path = __DIR__.'/'.$rel;
  return file_exists($path) ? round(filesize($path)/1024/1024,1).'MB' : '';
}
$videos = [
  'welcome'  => ['Welcome + Inicio (hero.mp4)',    '../assets/video/hero.mp4'],
  'historia' => ['Historia',                        '../assets/video/pages/historia.mp4'],
  'espacios' => ['Espacios',                        '../assets/video/pages/espacios.mp4'],
  'retiros'  => ['Agenda de retiros',               '../assets/video/pages/retiros.mp4'],
  'galeria'  => ['Galería',                         '../assets/video/pages/galeria.mp4'],
];
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Media · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.1);--teal-b:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252;--green:#00e676;--amber:#ffab40}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.tabs{display:flex;gap:6px;flex-wrap:wrap}
.tab{padding:8px 18px;border-radius:20px;border:1.5px solid var(--border);background:transparent;color:var(--text2);font:inherit;font-size:.8rem;font-weight:600;cursor:pointer;transition:.2s}
.tab:hover{border-color:var(--teal-b);color:var(--text)}
.tab.active{border-color:var(--teal-b);background:var(--teal-dim);color:var(--teal)}
.content{padding:22px}
.tab-panel{display:none}.tab-panel.show{display:block}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
/* Upload zone */
.upload-zone{border:2px dashed var(--border);border-radius:10px;padding:32px;text-align:center;cursor:pointer;transition:.2s;background:var(--card);margin-bottom:12px}
.upload-zone:hover{border-color:var(--teal-b);background:var(--hover)}
.upload-zone input{display:none}
.upload-zone .ico{font-size:2rem;margin-bottom:10px;display:block}
.upload-zone p{color:var(--text2);font-size:.88rem;margin-bottom:6px}
.upload-zone .hint{color:var(--text3);font-size:.76rem}
.fname{color:var(--teal);font-size:.82rem;margin-top:8px;display:block}
/* Gallery grid */
.gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-top:20px}
.gal-card{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;position:relative}
.gal-card img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.gal-card.hidden img{opacity:.3}
.gal-meta{padding:10px 12px}
.gal-meta .t{font-size:.82rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gal-meta .p{font-size:.7rem;color:var(--text3);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gal-actions{display:flex;gap:6px;padding:8px 12px;border-top:1px solid var(--border);align-items:center}
.gal-order{width:48px;font:inherit;font-size:.8rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:3px;padding:4px 7px;text-align:center}
.hidden-badge{position:absolute;top:6px;left:6px;background:rgba(255,82,82,.85);color:#fff;font-size:.62rem;padding:2px 7px;border-radius:10px;font-weight:700}
/* Video cards */
.vid-row{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:18px;margin-bottom:12px;display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start}
.vid-label{font-weight:600;color:var(--text);margin-bottom:4px}
.vid-path{font-size:.74rem;color:var(--text3);margin-bottom:10px}
.badge-ok{font-size:.68rem;padding:3px 10px;border-radius:20px;background:rgba(0,230,118,.12);color:var(--green);font-weight:600;white-space:nowrap}
.badge-miss{font-size:.68px;padding:3px 10px;border-radius:20px;background:rgba(255,82,82,.12);color:var(--red);font-weight:600;white-space:nowrap;font-size:.68rem}
/* Form */
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}.field label{font-size:.76rem;color:var(--text3)}.field input{font:inherit;font-size:.9rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 12px;width:100%}.field input:focus{outline:none;border-color:var(--teal)}
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 18px;border-radius:4px;font:inherit;font-size:.8rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-teal{background:var(--teal);color:#0a1628}.btn-teal:hover{background:#00b898}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2)}.btn-outline:hover{border-color:var(--teal-b);color:var(--teal)}
.btn-sm{padding:5px 10px;font-size:.74rem}
.btn-danger{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-danger:hover{background:rgba(255,82,82,.22)}
.grid2{display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start}
@media(max-width:900px){.grid2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>Imágenes y videos</h1>
    <div class="tabs">
      <button class="tab active" onclick="showTab('galeria',this)">Galería de imágenes</button>
      <button class="tab" onclick="showTab('videos',this)">Videos de páginas</button>
    </div>
  </div>

  <div class="content">
    <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>

    <!-- ══ GALLERY TAB ══════════════════════════════════════════ -->
    <div class="tab-panel show" id="tab-galeria">
      <div class="grid2">

        <!-- Upload form -->
        <div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="upload_galeria">

            <div class="upload-zone" id="dropzone" onclick="document.getElementById('imgFile').click()">
              <span class="ico">🖼</span>
              <p>Arrastrá una imagen o hacé clic</p>
              <span class="hint">JPG · PNG · WEBP · Máx 10MB</span>
              <span class="fname" id="imgFname">Ningún archivo seleccionado</span>
              <input type="file" id="imgFile" name="imagen" accept="image/jpeg,image/png,image/webp"
                     onchange="document.getElementById('imgFname').textContent=this.files[0]?.name||''">
            </div>

            <!-- Preview -->
            <div id="imgPreview" style="display:none;margin-bottom:12px">
              <img id="imgPreviewEl" style="width:100%;border-radius:8px;aspect-ratio:16/10;object-fit:cover;border:1px solid var(--border)">
            </div>

            <div class="field"><label>Título / caption</label><input type="text" name="titulo" placeholder="El salón al amanecer"></div>
            <div class="field"><label>Orden (menor = primero)</label><input type="number" name="orden" value="50" min="1" max="999"></div>
            <button type="submit" class="btn btn-teal" style="width:100%">Subir imagen</button>
          </form>
        </div>

        <!-- Gallery grid -->
        <div>
          <p style="font-size:.84rem;color:var(--text2);margin-bottom:14px"><?= count($galeria) ?> imágenes en la galería</p>
          <?php if($galeria): ?>
          <div class="gal-grid">
            <?php foreach($galeria as $img): ?>
            <div class="gal-card <?= !$img['activo']?'hidden':'' ?>">
              <?php if(!$img['activo']): ?><span class="hidden-badge">OCULTA</span><?php endif; ?>
              <img src="../<?= e($img['archivo']) ?>" alt="<?= e($img['titulo']) ?>" loading="lazy"
                   onerror="this.style.background='#1a2a3a';this.style.height='80px'">
              <div class="gal-meta">
                <div class="t"><?= e($img['titulo']?:'Sin título') ?></div>
                <div class="p"><?= e($img['archivo']) ?></div>
              </div>
              <div class="gal-actions">
                <form method="post" style="display:contents">
                  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="accion" value="reorder_galeria">
                  <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                  <input type="number" name="orden" value="<?= (int)$img['orden'] ?>" class="gal-order" min="1" max="999">
                  <button type="submit" class="btn btn-outline btn-sm">OK</button>
                </form>
                <form method="post" style="display:contents">
                  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="accion" value="toggle_galeria">
                  <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= $img['activo']?'Ocultar':'Mostrar' ?></button>
                </form>
                <form method="post" style="display:contents" onsubmit="return confirm('¿Eliminar esta imagen?')">
                  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="accion" value="delete_galeria">
                  <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">✕</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color:var(--text3);padding:20px 0">No hay imágenes en la galería todavía.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ══ VIDEOS TAB ═══════════════════════════════════════════ -->
    <div class="tab-panel" id="tab-videos">
      <p style="color:var(--text2);font-size:.86rem;margin-bottom:20px">
        Subí el video de fondo para cada página. Solo MP4. Si da error 500, el archivo supera el límite - comprimí con <strong style="color:var(--teal)">HandBrake</strong> (RF 28, Fast 720p, menos de 30MB).
        <br><strong style="color:var(--amber)">Welcome e Inicio comparten hero.mp4.</strong>
      </p>

      <?php foreach($videos as $key => [$label, $path]): 
        $full = __DIR__.'/'.$path;
        $exists = file_exists($full);
        $size = $exists ? round(filesize($full)/1024/1024,1).'MB' : '';
        $url = str_replace('../', '../', $path);
      ?>
      <div class="vid-row">
        <div>
          <div class="vid-label"><?= e($label) ?></div>
          <div class="vid-path"><?= e(ltrim($path,'../')) ?></div>
          <?php if($exists): ?>
            <video style="width:100%;max-height:140px;object-fit:cover;border-radius:6px;background:#000;display:block;margin-bottom:10px" muted playsinline preload="metadata">
              <source src="<?= e($url) ?>?t=<?= time() ?>" type="video/mp4">
            </video>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="upload_video">
            <input type="hidden" name="pagina" value="<?= e($key) ?>">
            <label style="cursor:pointer;background:var(--teal-dim);border:1px solid var(--teal-b);border-radius:4px;padding:7px 14px;font-size:.8rem;color:var(--teal);white-space:nowrap">
              Elegir MP4
              <input type="file" name="video" accept="video/mp4" style="display:none"
                     onchange="this.closest('form').querySelector('.vfname').textContent=this.files[0]?.name||''">
            </label>
            <span class="vfname" style="font-size:.76rem;color:var(--text3)"></span>
            <button type="submit" class="btn btn-teal btn-sm">Subir</button>
          </form>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <?php if($exists): ?>
            <span class="badge-ok">Activo · <?= $size ?></span>
          <?php else: ?>
            <span class="badge-miss">Sin video</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>
</div>

<script>
// Tab switching
function showTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('show')});
  document.querySelectorAll('.tab').forEach(function(b){b.classList.remove('active')});
  document.getElementById('tab-'+id).classList.add('show');
  btn.classList.add('active');
}

// Image preview on select
document.getElementById('imgFile').addEventListener('change', function(){
  var f = this.files[0];
  if (!f) return;
  var reader = new FileReader();
  reader.onload = function(e){
    document.getElementById('imgPreviewEl').src = e.target.result;
    document.getElementById('imgPreview').style.display = 'block';
  };
  reader.readAsDataURL(f);
});

// Drag and drop
var dz = document.getElementById('dropzone');
dz.addEventListener('dragover', function(e){e.preventDefault();dz.style.borderColor='var(--teal)';});
dz.addEventListener('dragleave', function(){dz.style.borderColor='';});
dz.addEventListener('drop', function(e){
  e.preventDefault();dz.style.borderColor='';
  var f = e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) {
    var inp = document.getElementById('imgFile');
    var dt = new DataTransfer();
    dt.items.add(f);
    inp.files = dt.files;
    document.getElementById('imgFname').textContent = f.name;
    inp.dispatchEvent(new Event('change'));
  }
});
</script>
</body>
</html>
