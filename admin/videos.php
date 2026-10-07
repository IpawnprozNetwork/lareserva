<?php
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
if (usuarioActual()['rol'] !== 'admin') { http_response_code(403); die('Acceso denegado.'); }

$aviso = null;
$pages = [
  'welcome'  => ['label'=>'Welcome / Portada principal',  'file'=>'hero.mp4',      'dir'=>'../assets/video/'],
  'index'    => ['label'=>'Inicio (index.php)',            'file'=>'hero.mp4',      'dir'=>'../assets/video/'],
  'historia' => ['label'=>'Historia del predio',           'file'=>'historia.mp4',  'dir'=>'../assets/video/pages/'],
  'espacios' => ['label'=>'Espacios',                      'file'=>'espacios.mp4',  'dir'=>'../assets/video/pages/'],
  'retiros'  => ['label'=>'Agenda de retiros',             'file'=>'retiros.mp4',   'dir'=>'../assets/video/pages/'],
  'galeria'  => ['label'=>'Galeria',                       'file'=>'galeria.mp4',   'dir'=>'../assets/video/pages/'],
];

foreach ($pages as $k => $pg) {
  $dir = __DIR__.'/'.$pg['dir'];
  if (!is_dir($dir)) mkdir($dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfOk($_POST['csrf'] ?? null)) {
    $aviso = ['error','Sesion expirada.'];
  } else {
    $key    = $_POST['pagina'] ?? '';
    $accion = $_POST['accion'] ?? '';

    if (!array_key_exists($key, $pages)) {
      $aviso = ['error','Pagina invalida.'];
    } elseif ($accion === 'upload_retiro_video') {
      $f = $_FILES['video'] ?? null;
      if (!$f || $f['error'] !== 0) {
        $aviso = ['error','Error al subir. Verificá que el archivo sea MP4 y menos de 30MB.'];
      } else {
        $slug_clean = preg_replace('/[^a-z0-9-]/', '', $key); // key = retiro-slug
        $dest = __DIR__.'/../assets/video/pages/'.$slug_clean.'.mp4';
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          $aviso = ['ok','Video subido para este retiro.'];
        } else {
          $aviso = ['error','No se pudo guardar el archivo.'];
        }
      }
    } elseif ($accion === 'delete_video') {
      $dest = __DIR__.'/'.$pages[$key]['dir'].$pages[$key]['file'];
      if (file_exists($dest)) { unlink($dest); }
      $poster = str_replace('.mp4','-poster.jpg',$dest);
      if (file_exists($poster)) { unlink($poster); }
      $aviso = ['ok','Video eliminado.'];
    } elseif ($accion === 'instagram_url') {
      $url = trim($_POST['instagram_url'] ?? '');
      $aviso = ['error','Pega el link en sssinstagram.com para descargar el MP4, luego subilo con Subir MP4.'];
    } elseif ($accion === 'upload_video') {
      $f = $_FILES['video'] ?? null;
      $upload_err = [0=>'OK',1=>'Archivo demasiado grande para el servidor (comprimilo primero)',2=>'Archivo demasiado grande (limite del formulario)',3=>'Subida incompleta - intentalo de nuevo',4=>'No se selecciono ningun archivo',6=>'Sin carpeta temporal',7=>'No se puede escribir en disco'];
      if (!$f || $f['error'] !== 0) {
        $msg = $upload_err[$f['error']??4] ?? 'Error de subida ('.$f['error'].')';
        if (in_array($f['error']??0,[1,2])) $msg .= '. Comprimi el video con HandBrake (RF 28, Fast 720p) antes de subir.';
        $aviso = ['error', $msg];
      } elseif (!in_array(lrMime($f['tmp_name']),['video/mp4','video/x-m4v','video/quicktime'])) {
        $aviso = ['error','Solo se aceptan archivos MP4.'];
      } else {
        $dir  = __DIR__.'/'.$pages[$key]['dir'];
        $dest = $dir.$pages[$key]['file'];
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          if (is_executable('/usr/bin/ffmpeg')) {
            $poster = str_replace('.mp4','-poster.jpg',$dest);
            shell_exec("/usr/bin/ffmpeg -nostdin -v error -ss 5 -i ".escapeshellarg($dest)." -frames:v 1 -q:v 4 ".escapeshellarg($poster)." -y 2>/dev/null");
          }
          $aviso = ['ok','Video subido para: '.$pages[$key]['label'].' ('.round($f['size']/1024/1024,1).'MB)'];
        } else {
          $aviso = ['error','No se pudo mover el archivo. Comprobá permisos de la carpeta assets/video/'];
        }
      }
    }
  }
}

function vidInfo(string $key, array $pg): array {
  $path = __DIR__.'/'.$pg['dir'].$pg['file'];
  $exists = file_exists($path);
  return ['exists'=>$exists,'size'=>$exists?round(filesize($path)/1024/1024,1).'MB':null,'url'=>$pg['dir'].$pg['file']];
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Videos · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.12);--teal-b:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252;--green:#00e676;--amber:#ffab40}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:24px;max-width:860px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:20px;border-left:3px solid}.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.vid-card{background:var(--card);border:1px solid var(--border);border-radius:8px;margin-bottom:16px;overflow:hidden}
.vid-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.vid-head h2{font-size:.95rem;font-weight:600;color:var(--text)}
.vid-head .pg{font-size:.72rem;color:var(--text3);margin-top:2px}
.badge-ok{font-size:.68rem;padding:3px 10px;border-radius:20px;background:rgba(0,230,118,.12);color:var(--green);font-weight:600}
.badge-miss{font-size:.68rem;padding:3px 10px;border-radius:20px;background:rgba(255,82,82,.12);color:var(--red);font-weight:600}
.vid-body{padding:16px 18px}
video.prev{width:100%;max-height:180px;object-fit:cover;border-radius:4px;margin-bottom:12px;display:block;background:#000}
.tabs{display:flex;gap:0;margin-bottom:14px;border:1px solid var(--border);border-radius:5px;overflow:hidden}
.tab-btn{flex:1;padding:8px;background:transparent;border:0;color:var(--text2);font:inherit;font-size:.8rem;cursor:pointer;transition:.15s;border-right:1px solid var(--border)}
.tab-btn:last-child{border-right:0}.tab-btn:hover{background:var(--teal-dim);color:var(--text)}.tab-btn.active{background:var(--teal-dim);color:var(--teal);font-weight:600}
.tab-panel{display:none}.tab-panel.show{display:block}
.upload-zone{border:1px dashed var(--border);border-radius:6px;padding:20px;text-align:center;color:var(--text3);font-size:.84rem;cursor:pointer;transition:.2s}.upload-zone:hover{border-color:var(--teal-b);color:var(--text2)}
.upload-zone input{display:none}
.fname{display:block;margin-top:6px;color:var(--teal);font-size:.82rem}
.ig-note{background:rgba(255,171,64,.08);border:1px solid rgba(255,171,64,.2);border-radius:4px;padding:12px 14px;font-size:.82rem;color:var(--text2);line-height:1.6;margin-bottom:12px}
.ig-note strong{color:var(--amber)}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:12px}.field label{font-size:.76rem;color:var(--text3)}.field input{font:inherit;font-size:.9rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 12px;width:100%}.field input:focus{outline:none;border-color:var(--teal)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:4px;font:inherit;font-size:.8rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-teal{background:var(--teal);color:#0a1628}.btn-teal:hover{background:#00b898}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2)}.btn-outline:hover{border-color:var(--teal-b);color:var(--teal)}
.btn-danger{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-danger:hover{background:rgba(255,82,82,.22)}
.btn-sm{padding:5px 12px;font-size:.75rem}
.actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.note{font-size:.76rem;color:var(--text3);margin-top:8px;line-height:1.5}
.sb-toggle{display:none;position:fixed;top:11px;left:11px;z-index:100;width:38px;height:38px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:18px;cursor:pointer;align-items:center;justify-content:center}
@media(max-width:768px){.sb-toggle{display:flex}.sb{position:fixed;top:0;left:0;height:100vh;z-index:90;transform:translateX(-100%);transition:.25s ease}.sb.open{transform:none}.content{padding:16px}.topbar{padding:12px 16px 12px 54px}}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>Gestor de videos</h1><a href="index.php" class="btn btn-outline btn-sm">← Dashboard</a></div>
  <div class="content">
    <p style="color:var(--text2);font-size:.86rem;margin-bottom:20px">
      Subí el video de fondo para cada pagina. Solo MP4. Si da error 500, el archivo supera el limite del servidor - comprimi con <strong style="color:var(--teal)">HandBrake</strong> (gratis): RF 28, Fast 720p, menos de 30MB.
    </p>

    <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>

    <?php foreach ($pages as $key => $pg):
      $info = vidInfo($key, $pg);
    ?>
    <div class="vid-card">
      <div class="vid-head">
        <div>
          <h2><?= e($pg['label']) ?></h2>
          <div class="pg"><?= e($pg['file']) ?> &rarr; <?= e($pg['dir']) ?></div>
        </div>
        <?php if($info['exists']): ?><span class="badge-ok">Activo · <?= $info['size'] ?></span>
        <?php else: ?><span class="badge-miss">Sin video</span><?php endif; ?>
      </div>

      <div class="vid-body">
        <?php if($info['exists']): ?>
          <video class="prev" muted playsinline preload="metadata">
            <source src="<?= e($info['url']) ?>?t=<?= time() ?>" type="video/mp4">
          </video>
        <?php endif; ?>

        <div class="tabs">
          <button class="tab-btn active" onclick="showTab('<?= $key ?>','upload',this)">Subir MP4</button>
          <button class="tab-btn" onclick="showTab('<?= $key ?>','instagram',this)">Instagram / URL</button>
        </div>

        <!-- Upload tab -->
        <div class="tab-panel show" id="tab-<?= $key ?>-upload">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="pagina" value="<?= e($key) ?>">
            <input type="hidden" name="accion" value="upload_video">
            <div class="upload-zone" onclick="this.querySelector('input[type=file]').click()">
              <input type="file" name="video" accept="video/mp4,video/quicktime"
                onchange="this.closest('.upload-zone').querySelector('.fname').textContent=this.files[0]?.name||''">
              Arrastrar MP4 aqui o <strong style="color:var(--teal)">hacer clic para buscar</strong>
              <span class="fname"></span>
            </div>
            <div class="actions-row">
              <button type="submit" class="btn btn-teal">Subir video</button>
              <?php if($info['exists']): ?>
                <a href="<?= e($info['url']) ?>" target="_blank" class="btn btn-outline btn-sm">Ver</a>
              <?php endif; ?>
            </div>
          </form>
          <?php if($info['exists']): ?>
          <form method="post" style="margin-top:10px" onsubmit="return confirm('Eliminar este video?')">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="pagina" value="<?= e($key) ?>">
            <input type="hidden" name="accion" value="delete_video">
            <button type="submit" class="btn btn-danger btn-sm">Eliminar video</button>
          </form>
          <?php endif; ?>
        </div>

        <!-- Instagram tab -->
        <div class="tab-panel" id="tab-<?= $key ?>-instagram">
          <div class="ig-note">
            Pega el link de Instagram abajo y hacé clic en <strong style="color:var(--teal)">Abrir descargador</strong>.
            Se abre <strong>sssinstagram.com</strong> con el reel ya cargado - descargá el MP4
            y luego subilo con la pestaña <em>Subir MP4</em>.
          </div>
          <div class="field">
            <label>Link del reel de Instagram</label>
            <input type="text" id="ig-url-<?= $key ?>" placeholder="https://www.instagram.com/reel/xxxxx/">
          </div>
          <div style="display:flex;gap:8px;margin-top:4px">
            <button type="button" class="btn btn-teal"
              onclick="var u=document.getElementById('ig-url-<?= $key ?>').value.trim();if(u){window.open('https://sssinstagram.com/en1?url='+encodeURIComponent(u),'_blank');}else{alert('Pegá el link de Instagram primero.');}">
              Abrir descargador
            </button>
            <a href="https://sssinstagram.com/en1" target="_blank" class="btn btn-outline">sssinstagram.com</a>
          </div>
          <p class="note" style="margin-top:12px">Descargá el MP4, luego volvé y usá la pestaña <em>Subir MP4</em> para subirlo a esta pagina.</p>
        </div>

        <p class="note">
          <?php if($key==='welcome'||$key==='index'): ?>
            Welcome e Inicio comparten el mismo archivo <code>hero.mp4</code>.
          <?php else: ?>
            Este video va en <code>assets/video/pages/<?= e($pg['file']) ?></code>.
          <?php endif; ?>
        </p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<script>
function showTab(page, tab, btn) {
  document.getElementById('tab-'+page+'-upload').classList.remove('show');
  document.getElementById('tab-'+page+'-instagram').classList.remove('show');
  document.getElementById('tab-'+page+'-'+tab).classList.add('show');
  btn.closest('.tabs').querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active')});
  btn.classList.add('active');
}
document.getElementById('sb').addEventListener('click',function(e){if(e.target.tagName==='A'&&window.innerWidth<=768)this.classList.remove('open');});
</script>
</body>
</html>
