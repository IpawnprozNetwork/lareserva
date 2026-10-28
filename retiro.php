<?php
/* retiro.php - VERSION 1.9
   1.2: detail copy moved into the right-hand column. The bajada and the
   descripcion used to render in a second grid row below the gallery because
   an unclosed wrapper div broke the two-column layout. */
require_once __DIR__.'/includes/config.php';
$pagina='retiros'; $raiz='';

$slug = $_GET['r'] ?? '';
$r = null; $media = []; $resenas = [];
if ($slug !== '' && ($db = getDB())) {
  try {
    $st = $db->prepare("SELECT * FROM retiros WHERE slug=:s AND estado IN('publicado','agotado','finalizado') LIMIT 1");
    $st->execute([':s'=>$slug]); $r = $st->fetch() ?: null;
    if ($r) {
      $media   = $db->prepare("SELECT * FROM retiro_media WHERE retiro_id=:i AND activo=1 ORDER BY tipo DESC,orden ASC");
      $media->execute([':i'=>$r['id']]); $media = $media->fetchAll();
      $resenas = $db->prepare("SELECT * FROM resenas WHERE retiro_id=:i AND aprobada=1 ORDER BY creado DESC");
      $resenas->execute([':i'=>$r['id']]); $resenas = $resenas->fetchAll();
    }
  } catch(Exception $e){ error_log('[LR retiro] '.$e->getMessage()); }
}

// Submit review
$rv_ok = false; $rv_err = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($db=getDB()) && $r) {
  if (!csrfOk($_POST['csrf']??null)) {
    $rv_err = 'Sesión expirada, intentá de nuevo.';
  } else {
    $nombre    = limpiar($_POST['nombre']??'');
    $email     = filter_var(trim($_POST['email']??''), FILTER_VALIDATE_EMAIL);
    $punt      = max(1,min(5,(int)($_POST['puntuacion']??5)));
    $coment    = limpiar($_POST['comentario']??'');
    if (!$nombre || !$coment) {
      $rv_err = 'Completá nombre y comentario.';
    } else {
      $db->prepare("INSERT INTO resenas (retiro_id,nombre,email,puntuacion,comentario) VALUES (:r,:n,:e,:p,:c)")
         ->execute([':r'=>$r['id'],':n'=>$nombre,':e'=>$email?:null,':p'=>$punt,':c'=>$coment]);
      $rv_ok = true;
    }
  }
}

if (!$r) { http_response_code(404); }

// Separate media by type
$imgs = array_filter($media, function($m){ return $m['tipo']==='imagen'; });
$vids = array_filter($media, function($m){ return $m['tipo']==='video'; });

// Hero video: retiro-specific or fallback
$vid_file = __DIR__.'/assets/video/pages/retiro-'.$slug.'.mp4';
$hero_vid = file_exists($vid_file) ? 'assets/video/pages/retiro-'.$slug.'.mp4' : 'assets/video/pages/retiros.mp4';

// Average rating
$avg = count($resenas) ? round(array_sum(array_column($resenas,'puntuacion'))/count($resenas),1) : null;

/* v1.7: calculadora del retiro.

   Los precios no tienen columnas propias: viven dentro del campo Incluye,
   escritos como "Adulto | $442.000" debajo de un subtitulo "# 3 dias y 2
   noches". En vez de inventar tablas nuevas se leen de ahi, asi cualquier
   retiro que use ese formato tiene calculadora sin tocar nada mas.

   Solo cuentan las filas cuyo valor tiene numeros: "Alojamiento" o
   "No se aceptan mascotas" quedan afuera solas. */
$rtPlanes = [];
$rtIdx = -1;
foreach (preg_split('/\r?\n/', (string)($r['incluye'] ?? '')) as $rtLi) {
    $rtLi = trim($rtLi);
    if ($rtLi === '') continue;
    if ($rtLi[0] === '#') {
        $rtPlanes[] = ['nombre' => trim(ltrim($rtLi, '# ')), 'filas' => []];
        $rtIdx++;
        continue;
    }
    if ($rtIdx < 0 || strpos($rtLi, '|') === false) continue;
    $par = array_map('trim', explode('|', $rtLi, 2));
    $rtN = (int)preg_replace('/[^0-9]/', '', $par[1]);
    if ($rtN > 0) $rtPlanes[$rtIdx]['filas'][] = ['et' => $par[0], 'n' => $rtN];
}
$rtPlanes = array_values(array_filter($rtPlanes, function ($g) { return count($g['filas']) > 0; }));
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $r ? e($r['titulo']).' · La Reserva' : 'Retiro no encontrado · La Reserva' ?></title>
<?php if($r):?><meta name="description" content="<?= e($r['bajada']??$r['titulo']) ?>"><?php else:?><meta name="robots" content="noindex"><?php endif;?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<!-- retiro.php VERSION 1.9 -->
<style>
@media(max-width:900px){.retiro-cols{grid-template-columns:1fr !important}}

/* v1.7: la calculadora */
.rt-calc{margin-top:18px;padding:20px 18px;border:1px solid rgba(0,201,167,.25);
  border-radius:14px;background:rgba(17,34,51,.6)}
.rt-calc-tit{font:300 1.25rem 'Fraunces',Georgia,serif;color:#e8f0f7;margin-bottom:14px}
.rt-calc-planes{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px}
.rt-calc-plan{background:none;border:1px solid #26405c;color:#7a9ab8;border-radius:18px;
  padding:7px 14px;font:inherit;font-size:.82rem;cursor:pointer}
.rt-calc-plan:hover{border-color:#00c9a7;color:#00c9a7}
.rt-calc-plan.on{background:#00c9a7;border-color:#00c9a7;color:#0a1628;font-weight:600}
.rt-calc-fila{display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:8px 0;border-bottom:1px solid rgba(122,154,184,.12)}
.rt-calc-fila label{color:#7a9ab8;font-size:.88rem;flex:1;min-width:0}
.rt-calc-num{display:flex;align-items:center;gap:5px;flex-shrink:0}
.rt-calc-num button{width:30px;height:30px;border-radius:50%;border:1px solid #26405c;
  background:none;color:#e8f0f7;font-size:1rem;cursor:pointer;line-height:1}
.rt-calc-num button:hover{border-color:#00c9a7;color:#00c9a7}
.rt-calc-num input{width:48px;text-align:center;background:#0a1420;border:1px solid #1c3247;
  border-radius:7px;color:#e8f0f7;font:inherit;font-size:.92rem;padding:6px 3px;-moz-appearance:textfield}
.rt-calc-num input::-webkit-outer-spin-button,
.rt-calc-num input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.rt-calc-det{margin-top:12px;font-size:.84rem;color:#7a9ab8;line-height:1.8}
.rt-calc-det div{display:flex;justify-content:space-between;gap:12px}
.rt-calc-det em{font-style:normal;color:#e8f0f7}
.rt-calc-total{display:flex;justify-content:space-between;align-items:baseline;gap:12px;
  margin-top:12px;padding-top:12px;border-top:1px solid rgba(0,201,167,.3)}
.rt-calc-total span{color:#7a9ab8;font-size:.9rem}
.rt-calc-total strong{font:300 1.6rem 'Fraunces',Georgia,serif;color:#00c9a7}
.rt-calc-nota{margin:10px 0 14px;font-size:.78rem;color:#4a6a85;line-height:1.6}
.rt-contacto{margin-top:16px;padding:14px 16px;border-radius:10px;background:rgba(17,34,51,.5);
  border:1px solid #1c3247;text-align:center}
.rt-ct-rot{font-size:.66rem;letter-spacing:.18em;text-transform:uppercase;color:#00c9a7;margin-bottom:5px}
.rt-ct-nom{color:#e8f0f7;font-size:.98rem;font-weight:600;margin-bottom:6px}
.rt-contacto a{display:inline-block;margin:0 7px;color:#7a9ab8;font-size:.84rem;
  text-decoration:none;border-bottom:1px dotted rgba(122,154,184,.5)}
.rt-contacto a:hover{color:#00c9a7;border-bottom-color:#00c9a7}
</style>
<style>
/* Slideshow */
.slides{position:relative;background:#000;overflow:hidden;border-radius:0}
.slide{display:none;width:100%;aspect-ratio:16/9;object-fit:cover}
.slide.active{display:block}
.slide-vid{display:none;width:100%;max-height:480px;object-fit:cover}
.slide-vid.active{display:block}
.slides-nav{position:absolute;top:50%;transform:translateY(-50%);width:100%;display:flex;justify-content:space-between;padding:0 12px;z-index:2;pointer-events:none}
.snav{pointer-events:auto;background:rgba(10,22,40,.7);border:1px solid rgba(0,201,167,.25);color:#e8f0f7;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:.18s}
.snav:hover{background:var(--teal,#00c9a7);color:#0a1628}
.slides-dots{display:flex;justify-content:center;gap:6px;padding:10px 0;background:var(--card,#112233)}
.sdot{width:8px;height:8px;border-radius:50%;background:rgba(232,240,247,.2);cursor:pointer;transition:.18s}
.sdot.active{background:#00c9a7}
.slides-counter{position:absolute;bottom:10px;right:14px;font-size:.72rem;color:rgba(232,240,247,.6);background:rgba(10,22,40,.6);padding:3px 8px;border-radius:10px}

/* Stars */
.stars{display:flex;gap:3px}
.star{font-size:1.2rem;color:rgba(232,240,247,.2);cursor:pointer;transition:.15s;line-height:1}
.star.on,.star:hover~.star.on{color:#ffab40}
.star:hover,.star:hover~*{color:#ffab40}
.stars:hover .star{color:#ffab40}
.stars:not(:hover) .star.on{color:#ffab40}
.star-input{display:flex;flex-direction:row-reverse;gap:4px}
.star-input input{display:none}
.star-input label{font-size:1.5rem;color:rgba(232,240,247,.25);cursor:pointer;transition:.15s}
.star-input input:checked~label,.star-input label:hover,.star-input label:hover~label{color:#ffab40}

/* Reviews */
.review-card{background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:8px;padding:20px 22px;margin-bottom:12px}
.rv-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px}
.rv-name{font-weight:600;color:#e8f0f7;font-size:.95rem}
.rv-date{font-size:.74rem;color:#4a6a85}
.rv-text{color:#7a9ab8;font-size:.91rem;line-height:1.7}

/* Rating summary */
.rating-summary{display:flex;align-items:center;gap:20px;background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:8px;padding:20px 24px;margin-bottom:24px;flex-wrap:wrap}
.rating-big{font-family:'Fraunces',Georgia,serif;font-size:3rem;font-weight:300;color:#00c9a7;line-height:1}
.rating-label{font-size:.78rem;color:#4a6a85;margin-top:4px}
.rating-bars{flex:1;min-width:160px}
.rbar-row{display:flex;align-items:center;gap:8px;margin-bottom:5px;font-size:.76rem;color:#7a9ab8}
.rbar-track{flex:1;height:5px;background:rgba(232,240,247,.1);border-radius:3px;overflow:hidden}
.rbar-fill{height:100%;background:#00c9a7;border-radius:3px}

/* Review form */
.review-form{background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:8px;padding:24px;margin-top:32px}
.review-form h3{font-family:'Fraunces',Georgia,serif;font-size:1.2rem;font-weight:300;color:#e8f0f7;margin-bottom:18px}
.campo{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.campo label{font-size:.78rem;color:#4a6a85;letter-spacing:.04em}
.campo input,.campo textarea{font:inherit;font-size:.92rem;color:#e8f0f7;background:#0d1e2c;border:1px solid rgba(0,180,150,.18);border-radius:4px;padding:11px 13px;width:100%;transition:.18s}
.campo input:focus,.campo textarea:focus{outline:none;border-color:#00c9a7}
.campo textarea{min-height:100px;resize:vertical}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
</style>
<style>
/* Hero centrado, igual que historia.php.
   historia centra porque su contenedor es un flex item con margin:0 auto, asi
   que se encoge al ancho del contenido y despues se centra. En estas paginas
   .interior es un bloque dentro de un .texto de ancho completo, asi que
   margin:0 auto no tiene nada que centrar: el bloque ya ocupa todo y el texto
   arranca pegado a la izquierda. width:fit-content es la pieza que faltaba. */
.portada .texto .interior,
.gal-hero .texto .interior{
  width:fit-content;
  max-width:min(var(--ancho,1180px), 100%);
  margin-inline:auto;
}
@media(max-width:760px){
  .portada .texto .interior,
  .gal-hero .texto .interior{width:auto}
}
</style>
</head>
<body>
<?php $portada_transparente=(bool)$r; include __DIR__.'/includes/nav.php'; ?>

<?php if(!$r): ?>
<section class="bloque" style="padding-top:150px">
  <div class="envoltura">
    <h1 class="titulo-bloque">No encontramos ese retiro</h1>
    <p style="color:var(--text2,#7a9ab8);margin-top:12px">Puede que la fecha ya pasó o el enlace esté mal escrito.</p>
    <p style="margin-top:24px"><a href="retiros.php" class="boton">Ver la agenda</a></p>
  </div>
</section>
<?php else: ?>

<!-- ══ HERO ══════════════════════════════════════════════════ -->
<!-- v1.3: clearance for the fixed nav. -->
<section class="portada" style="min-height:64vh;padding-top:110px">
  <video autoplay muted loop playsinline preload="metadata" poster="assets/video/pages/retiros-poster.jpg" aria-hidden="true">
    <source src="<?= e($hero_vid) ?>" type="video/mp4">
  </video>
  <div class="velo"></div>
  <div class="texto" style="padding-bottom:7vh">
    <div class="interior">
      <p class="lugar"><?= e(rangoFecha($r['fecha_inicio'],$r['fecha_fin'])) ?> &middot; <?= e(strtoupper($r['modalidad'])) ?></p>
      <h1 style="font-size:clamp(2.2rem,5vw,4rem);max-width:18ch"><?= e($r['titulo']) ?></h1>
      <?php if($r['bajada']): ?><p style="max-width:46ch;color:rgba(232,240,247,.75);margin-top:12px"><?= e($r['bajada']) ?></p><?php endif; ?>
      <?php if($r['estado']==='agotado'): ?>
        <span style="display:inline-block;margin-top:16px;background:rgba(255,82,82,.2);border:1px solid rgba(255,82,82,.4);color:#ff5252;font-size:.76rem;letter-spacing:.14em;padding:5px 14px;border-radius:3px">SIN CUPOS</span>
      <?php elseif($r['es_propio']??false): ?>
        <span style="display:inline-block;margin-top:16px;background:rgba(0,201,167,.15);border:1px solid rgba(0,201,167,.3);color:#00c9a7;font-size:.76rem;letter-spacing:.14em;padding:5px 14px;border-radius:3px">DE LA RESERVA</span>
      <?php endif; ?>
      <?php if($avg): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-top:16px">
          <span style="color:#ffab40;font-size:1rem"><?= str_repeat('★',round($avg)) ?><?= str_repeat('☆',5-round($avg)) ?></span>
          <span style="color:rgba(232,240,247,.6);font-size:.84rem"><?= $avg ?> · <?= count($resenas) ?> <?= count($resenas)===1?'reseña':'reseñas' ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- MEDIA VIEWER + DETAILS -->
<section class="bloque" style="padding-top:48px;background:var(--bg2,#0d1e2c)">
  <div class="envoltura">
    <div class="retiro-cols" style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start">
      <?php if(count($media) > 0): ?>
      <!-- Media viewer -->
      <div>
        <div style="position:relative;background:#000;border-radius:8px;overflow:hidden" id="mediaBox">
          <?php foreach($media as $mi => $m): ?>
            <?php if($m['tipo']==='video'): ?>
              <video id="slide-<?= $mi ?>" class="retiro-slide<?= $mi===0?' active':'' ?>"
                     style="width:100%;max-height:480px;object-fit:contain;display:<?= $mi===0?'block':'none' ?>"
                     preload="metadata" playsinline>
                <source src="<?= e($m['archivo']) ?>" type="video/mp4">
              </video>
            <?php else: ?>
              <img id="slide-<?= $mi ?>" class="retiro-slide<?= $mi===0?' active':'' ?>"
                   style="width:100%;max-height:480px;object-fit:cover;display:<?= $mi===0?'block':'none' ?>"
                   src="<?= e($m['archivo']) ?>" alt="<?= e($m['titulo']??'') ?>" loading="<?= $mi===0?'eager':'lazy' ?>">
            <?php endif; ?>
          <?php endforeach; ?>

          <?php if(count($media) > 1): ?>
          <!-- Arrows -->
          <button onclick="slideGo(-1)" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:#e8f0f7;border-radius:50%;width:42px;height:42px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.18s" onmouseover="this.style.background='#00c9a7';this.style.color='#0a1628'" onmouseout="this.style.background='rgba(10,22,40,.75)';this.style.color='#e8f0f7'">&#8592;</button>
          <button onclick="slideGo(1)"  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:#e8f0f7;border-radius:50%;width:42px;height:42px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.18s" onmouseover="this.style.background='#00c9a7';this.style.color='#0a1628'" onmouseout="this.style.background='rgba(10,22,40,.75)';this.style.color='#e8f0f7'">&#8594;</button>
          <?php endif; ?>

          <!-- Play/pause for video -->
          <button id="playBtn" onclick="togglePlay()" style="display:none;position:absolute;bottom:14px;right:14px;background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:#e8f0f7;border-radius:20px;padding:6px 14px;font-size:.78rem;cursor:pointer">Pause</button>

          <!-- Counter -->
          <?php if(count($media) > 1): ?>
          <div id="slideCtr" style="position:absolute;bottom:12px;left:14px;background:rgba(10,22,40,.6);color:rgba(232,240,247,.6);font-size:.72rem;padding:3px 9px;border-radius:10px">1 / <?= count($media) ?></div>
          <?php endif; ?>
        </div>

        <!-- Dots -->
        <?php if(count($media) > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;padding:10px 0;background:var(--card,#112233);border-radius:0 0 8px 8px" id="slideDots">
          <?php foreach($media as $mi => $m): ?>
            <div onclick="slideTo(<?= $mi ?>)" style="width:8px;height:8px;border-radius:50%;background:<?= $mi===0?'#00c9a7':'rgba(232,240,247,.2)' ?>;cursor:pointer;transition:.18s" class="rdot"></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div></div>
      <?php endif; ?>

      <!-- Details panel. v1.2: the old sticky wrapper here was never closed,
           which is why the description fell into a second grid row underneath
           the gallery instead of sitting in this column. -->
      <div class="retiro-side">
        <?php if($r['facilitador']): ?>
        <div style="margin-bottom:24px">
          <p style="font-size:.72rem;letter-spacing:.14em;color:#4a6a85;margin-bottom:6px">COORDINA</p>
          <?php
          /* v1.6: el campo Coordina es texto libre, y a veces se escribe un
             mail o un telefono en vez de un nombre. Antes quedaba muerto en
             pantalla. Ahora, si parece un mail o un telefono, se enlaza; si
             es un nombre, se muestra como siempre.

             Tambien acepta "Nombre <mail o telefono>", que es lo mas util:
             se ve el nombre y el contacto queda clickeable debajo. */
          $fac = trim((string)$r['facilitador']);
          $nom = $fac; $cont = '';
          if (preg_match('/^(.*?)\s*[<(]\s*([^>)]+)\s*[>)]\s*$/', $fac, $mm)) {
              $nom = trim($mm[1]); $cont = trim($mm[2]);
          } elseif (filter_var($fac, FILTER_VALIDATE_EMAIL)
                 || (preg_match('/^[\d\s()+.-]{8,20}$/', $fac))) {
              $nom = ''; $cont = $fac;
          }
          $chref = '';
          if ($cont !== '') {
              $dg = preg_replace('/\D/', '', $cont);
              if (filter_var($cont, FILTER_VALIDATE_EMAIL)) {
                  $chref = 'mailto:' . $cont . '?subject='
                         . rawurlencode('Consulta: ' . $r['titulo']);
              } elseif (strlen($dg) >= 8 && strlen($dg) <= 15) {
                  $chref = 'https://wa.me/' . $dg . '?text='
                         . rawurlencode('Hola, quiero info sobre ' . $r['titulo']);
              }
          }
          ?>
          <?php if ($nom !== ''): ?>
            <p style="font-size:1.05rem;font-weight:600;color:#e8f0f7"><?= e($nom) ?></p>
          <?php endif; ?>
          <?php if ($cont !== ''): ?>
            <?php if ($chref): ?>
              <a href="<?= e($chref) ?>" <?= strpos($chref,'mailto:')===0 ? '' : 'target="_blank" rel="noopener"' ?>
                 style="font-size:<?= $nom === '' ? '1.02rem' : '.9rem' ?>;color:#00c9a7;
                        display:inline-block;margin-top:<?= $nom === '' ? '0' : '4px' ?>;
                        text-decoration:none;border-bottom:1px dotted rgba(0,201,167,.5)"><?= e($cont) ?></a>
            <?php else: ?>
              <p style="font-size:.9rem;color:#7a9ab8;margin-top:4px"><?= e($cont) ?></p>
            <?php endif; ?>
          <?php endif; ?>
          <?php if($r['facilitador_ig']??''): ?>
            <a href="https://instagram.com/<?= e(ltrim($r['facilitador_ig'],'@')) ?>" target="_blank" rel="noopener"
               style="font-size:.86rem;color:#00c9a7;display:inline-block;margin-top:4px">@<?= e(ltrim($r['facilitador_ig'],'@')) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($r['bajada']??''): ?>
          <p style="color:#e8f0f7;font-size:1.02rem;line-height:1.6;margin-bottom:18px"><?= e($r['bajada']) ?></p>
        <?php endif; ?>

        <?php if($r['descripcion']??''): ?>
        <div style="margin-bottom:22px">
          <?php foreach(preg_split('/\r?\n\r?\n/',trim($r['descripcion'])) as $p): ?>
            <p style="color:#7a9ab8;line-height:1.75;font-size:.94rem;margin-bottom:14px"><?= nl2br(e($p)) ?></p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if($r['incluye']??''): ?>
        <div style="background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:6px;padding:18px;margin-bottom:18px">
          <p style="font-size:.72rem;letter-spacing:.14em;color:#00c9a7;margin-bottom:12px;font-weight:600">INCLUYE</p>
          <ul style="list-style:none;padding:0;margin:0">
            <?php
            /* v1.4: tres formas de escribir una linea en Incluye.

                 Alojamiento (3 noches)        item simple
                 Adulto | $442.000             etiqueta a la izquierda, valor a la derecha
                 # 3 dias y 2 noches           subtitulo, separa grupos

               Antes cada renglon era un item suelto, asi que pegar una tabla
               de precios daba veinte bolitas en fila: "3 dias y 2 noches",
               "$442.000", "Menores", "$221.000". Ilegible. */
            foreach (preg_split('/\r?\n/', trim($r['incluye'])) as $li):
              $li = trim($li);
              if ($li === '') continue;

              if ($li[0] === '#'):  /* subtitulo */ ?>
                <li style="list-style:none;padding:16px 0 6px;color:#00c9a7;font-size:.78rem;
                           letter-spacing:.12em;text-transform:uppercase;font-weight:600;
                           border-bottom:1px solid rgba(0,180,150,.18)"><?= e(ltrim($li, '# ')) ?></li>
            <?php elseif (strpos($li, '|') !== false):  /* etiqueta y valor */
                [$et, $val] = array_map('trim', explode('|', $li, 2)); ?>
                <li style="display:flex;justify-content:space-between;align-items:baseline;gap:16px;
                           border-bottom:1px solid rgba(0,180,150,.1);padding:9px 0;font-size:.92rem">
                  <span style="color:#7a9ab8"><?= e($et) ?></span>
                  <strong style="color:#e8f0f7;font-weight:600;white-space:nowrap"><?= e($val) ?></strong>
                </li>
            <?php else: /* item simple */ ?>
                <li style="border-bottom:1px solid rgba(0,180,150,.1);padding:9px 0;color:#7a9ab8;
                           font-size:.92rem;padding-left:18px;position:relative">
                  <span style="position:absolute;left:0;top:15px;width:6px;height:6px;border-radius:50%;
                               background:#00c9a7;opacity:.5"></span><?= e($li) ?></li>
            <?php endif; endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if($r['precio_desde']??''): ?>
          <p style="font-size:1.05rem;color:#e8f0f7;margin-bottom:18px">Desde <strong style="color:#00c9a7"><?= e($r['moneda']??'ARS') ?> <?= number_format((float)$r['precio_desde'],0,',','.') ?></strong></p>
        <?php endif; ?>

        <?php if($r['estado']==='agotado'): ?>
          <p style="color:#ff5252;font-size:.9rem;margin-bottom:12px">Sin cupos disponibles.</p>
          <a href="retiros.php" class="boton hueco" style="display:block;text-align:center">Ver próximas fechas</a>
        <?php elseif($r['estado']==='finalizado'): ?>
          <p style="color:#4a6a85;font-size:.9rem;margin-bottom:12px">Este encuentro ya sucedió.</p>
          <a href="retiros.php" class="boton hueco" style="display:block;text-align:center">Ver próximas fechas</a>
        <?php else: ?>
          <?php
          /* v1.5: el campo "URL inscripcion" recibe lo que se le ponga. Si
             alguien escribe un mail, antes salia como <a href="mail@x.com">,
             que el navegador lee como una pagina del sitio y da 404. Ahora se
             mira que es y se arma el link que corresponde.

             Ademas el boton de WhatsApp salia SOLO cuando el campo estaba
             vacio, asi que llenarlo lo hacia desaparecer. Ahora van los dos:
             el de inscripcion arriba y WhatsApp abajo. */
          $insc = trim((string)($r['url_inscripcion'] ?? ''));
          $href = $texto = '';
          if ($insc !== '') {
              $soloDigitos = preg_replace('/\D/', '', $insc);
              if (filter_var($insc, FILTER_VALIDATE_EMAIL)) {
                  $href  = 'mailto:' . $insc . '?subject=' . rawurlencode('Inscripcion: ' . $r['titulo']);
                  $texto = 'Inscribirme por mail';
              } elseif (preg_match('~^https?://~i', $insc)) {
                  $href = $insc; $texto = 'Inscribirme';
              } elseif (strlen($soloDigitos) >= 8 && strlen($soloDigitos) <= 15
                        && !preg_match('/[a-z]/i', $insc)) {
                  $href  = 'https://wa.me/' . $soloDigitos . '?text='
                         . rawurlencode('Hola, quiero inscribirme en ' . $r['titulo']);
                  $texto = 'Inscribirme por WhatsApp';
              } else {
                  /* Le falta el esquema: "miweb.com/form" */
                  $href = 'https://' . ltrim($insc, '/'); $texto = 'Inscribirme';
              }
          }
          $wa = preg_replace('/\D/', '', $r['wa_contacto'] ?? '') ?: preg_replace('/\D/', '', SITE_WA);
          ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>" class="boton" <?= strpos($href,'mailto:')===0 ? '' : 'target="_blank" rel="noopener"' ?>
               style="display:block;text-align:center"><?= e($texto) ?></a>
          <?php endif; ?>
          <?php if ($wa): ?>
            <a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hola, quiero información sobre '.$r['titulo']) ?>"
               class="boton <?= $href ? 'hueco' : '' ?>" target="_blank" rel="noopener"
               style="display:block;text-align:center;margin-top:<?= $href ? '10px' : '0' ?>">Consultar por WhatsApp</a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($rtPlanes): ?>
        <div class="rt-calc" id="rtCalc">
          <p class="rt-calc-tit">Calculá tu estadía</p>
          <?php if (count($rtPlanes) > 1): ?>
          <div class="rt-calc-planes">
            <?php foreach ($rtPlanes as $k => $pl): ?>
            <button type="button" class="rt-calc-plan<?= $k === 0 ? ' on' : '' ?>" data-plan="<?= $k ?>"><?= e($pl['nombre']) ?></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="rt-calc-filas" id="rtCalcFilas"></div>
          <div class="rt-calc-det" id="rtCalcDet"></div>
          <div class="rt-calc-total"><span>Total estimado</span><strong id="rtCalcTotal">$0</strong></div>
          <p class="rt-calc-nota">Es una estimación. Jonathan Ramírez te confirma el valor
            final, la disponibilidad y la forma de pago.</p>
          <a class="boton" id="rtCalcWa" href="#" target="_blank" rel="noopener"
             style="display:block;text-align:center">Pedir este presupuesto</a>
        </div>
        <?php endif; ?>

        <?php /* v1.8: a quien escribirle. Antes el boton decia WhatsApp a
                 secas y no quedaba claro con quien hablabas. */ ?>
        <div class="rt-contacto">
          <p class="rt-ct-rot">Consultas</p>
          <p class="rt-ct-nom"><?= e(function_exists('lrAjuste') ? lrAjuste('v27_contacto_nombre','Jonathan Ramírez') : 'Jonathan Ramírez') ?></p>
          <?php $rtMail = function_exists('lrAjuste') ? lrAjuste('v27_contacto_mail','Jonathanr@fasedos.com.ar') : 'Jonathanr@fasedos.com.ar'; ?>
          <a href="mailto:<?= e($rtMail) ?>?subject=<?= rawurlencode('Consulta: '.$r['titulo']) ?>"><?= e($rtMail) ?></a>
          <?php if ($wa): ?>
            <a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hola, quiero información sobre '.$r['titulo']) ?>"
               target="_blank" rel="noopener">WhatsApp</a>
          <?php endif; ?>
        </div>

        <a href="retiros.php" style="display:block;text-align:center;margin-top:14px;font-size:.82rem;color:#4a6a85">← Ver toda la agenda</a>
      </div>
    </div>

  </div>
</section>


<script>
var slides = document.querySelectorAll('.retiro-slide');
var dots   = document.querySelectorAll('.rdot');
var cur    = 0;
var total  = slides.length;

function showSlide(n) {
  if (!total) return;
  slides[cur].style.display = 'none';
  slides[cur].classList.remove('active');
  if (dots[cur]) dots[cur].style.background = 'rgba(232,240,247,.2)';
  // pause video if leaving it
  if (slides[cur].tagName === 'VIDEO') slides[cur].pause();
  
  cur = (n + total) % total;
  
  slides[cur].style.display = 'block';
  slides[cur].classList.add('active');
  if (dots[cur]) dots[cur].style.background = '#00c9a7';
  
  var ctr = document.getElementById('slideCtr');
  if (ctr) ctr.textContent = (cur+1) + ' / ' + total;
  
  // show play/pause for video
  var btn = document.getElementById('playBtn');
  if (btn) {
    if (slides[cur].tagName === 'VIDEO') {
      btn.style.display = 'block';
      slides[cur].play();
      btn.textContent = 'Pause';
    } else {
      btn.style.display = 'none';
    }
  }
}

function slideGo(d) { showSlide(cur + d); }
function slideTo(n) { showSlide(n); }

function togglePlay() {
  var v = slides[cur];
  if (v.tagName !== 'VIDEO') return;
  var btn = document.getElementById('playBtn');
  if (v.paused) { v.play(); btn.textContent = 'Pause'; }
  else { v.pause(); btn.textContent = 'Play'; }
}

// Auto-play first video if present
if (total > 0 && slides[0].tagName === 'VIDEO') {
  var btn = document.getElementById('playBtn');
  if (btn) btn.style.display = 'block';
  slides[0].play().catch(function(){});
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
  if (e.key === 'ArrowLeft')  slideGo(-1);
  if (e.key === 'ArrowRight') slideGo(1);
  if (e.key === ' ') { e.preventDefault(); togglePlay(); }
});

// Touch swipe
var tx = 0;
document.getElementById('mediaBox') && document.getElementById('mediaBox').addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; }, {passive:true});
document.getElementById('mediaBox') && document.getElementById('mediaBox').addEventListener('touchend', function(e){ var dx = e.changedTouches[0].clientX - tx; if(Math.abs(dx)>50) slideGo(dx<0?1:-1); }, {passive:true});
</script>

<!-- ══ REVIEWS ═══════════════════════════════════════════════ -->
<section class="bloque" style="padding-top:48px;background:var(--bg,#0a1628)">
  <div class="envoltura">
    <p style="font-size:.72rem;letter-spacing:.18em;color:#00c9a7;margin-bottom:10px">RESEÑAS</p>
    <h2 class="titulo-bloque">Lo que dicen quienes participaron</h2>

    <?php if($resenas): ?>
    <!-- Rating summary -->
    <div class="rating-summary">
      <div style="text-align:center">
        <div class="rating-big"><?= $avg ?></div>
        <div style="color:#ffab40;font-size:1.1rem;margin:4px 0"><?= str_repeat('★',round($avg)) ?><?= str_repeat('☆',5-round($avg)) ?></div>
        <div class="rating-label"><?= count($resenas) ?> reseña<?= count($resenas)!==1?'s':'' ?></div>
      </div>
      <div class="rating-bars">
        <?php for($s=5;$s>=1;$s--):
          $cnt = count(array_filter($resenas, function($rv) use ($s){ return (int)$rv['puntuacion'] === (int)$s; }));
          $pct = count($resenas) ? round($cnt/count($resenas)*100) : 0;
        ?>
        <div class="rbar-row">
          <span style="width:6px"><?= $s ?></span>
          <span style="color:#ffab40;font-size:.8rem">★</span>
          <div class="rbar-track"><div class="rbar-fill" style="width:<?= $pct ?>%"></div></div>
          <span style="width:20px;text-align:right"><?= $cnt ?></span>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Review cards -->
    <?php foreach($resenas as $rv): ?>
    <div class="review-card">
      <div class="rv-header">
        <div>
          <div class="rv-name"><?= e($rv['nombre']) ?></div>
          <div style="color:#ffab40;font-size:.9rem;margin-top:3px"><?= str_repeat('★',(int)$rv['puntuacion']) ?><?= str_repeat('☆',5-(int)$rv['puntuacion']) ?></div>
        </div>
        <div class="rv-date"><?= date('d/m/Y',strtotime($rv['creado'])) ?></div>
      </div>
      <p class="rv-text"><?= nl2br(e($rv['comentario'])) ?></p>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p style="color:#4a6a85;margin-top:16px">Todavía no hay reseñas para este retiro. ¡Sé el primero!</p>
    <?php endif; ?>

    <!-- Review form -->
    <div class="review-form">
      <h3>Dejá tu reseña</h3>
      <?php if($rv_ok): ?>
        <div style="background:rgba(0,201,167,.1);border:1px solid rgba(0,201,167,.3);border-radius:4px;padding:16px;color:#00c9a7;font-size:.9rem">
          ✓ Gracias por tu reseña. Aparecerá en cuanto sea aprobada por el equipo.
        </div>
      <?php else: ?>
        <?php if($rv_err): ?><div style="background:rgba(255,82,82,.1);border:1px solid rgba(255,82,82,.3);border-radius:4px;padding:12px;color:#ff5252;font-size:.86rem;margin-bottom:14px"><?= e($rv_err) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <div style="margin-bottom:18px">
            <p style="font-size:.78rem;color:#4a6a85;margin-bottom:10px">Tu puntuación</p>
            <div class="star-input" id="starInput">
              <?php for($s=5;$s>=1;$s--): ?>
                <input type="radio" name="puntuacion" id="s<?= $s ?>" value="<?= $s ?>"<?= $s===5?' checked':'' ?>>
                <label for="s<?= $s ?>" title="<?= $s ?> estrellas">★</label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="form-grid">
            <div class="campo"><label>Nombre *</label><input type="text" name="nombre" required placeholder="Tu nombre"></div>
            <div class="campo"><label>E-mail (no se publica)</label><input type="email" name="email" placeholder="tu@email.com"></div>
          </div>
          <div class="campo"><label>Comentario *</label><textarea name="comentario" required placeholder="Contá cómo fue tu experiencia..."></textarea></div>
          <button type="submit" class="boton" style="margin-top:4px">Enviar reseña</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Admin quick edit -->
<?php
if (function_exists('sesionIniciada') === false) @include_once __DIR__.'/includes/auth.php';
if (function_exists('sesionIniciada') && sesionIniciada()): ?>
<div style="position:fixed;bottom:80px;right:18px;z-index:60">
  <a href="admin/retiros.php?id=<?= (int)$r['id'] ?>"
     style="display:flex;align-items:center;gap:6px;padding:9px 16px;background:rgba(10,22,40,.9);backdrop-filter:blur(8px);border:1px solid rgba(0,201,167,.3);border-radius:20px;color:#00c9a7;font-size:.78rem;font-weight:500;text-decoration:none">
    ✎ Editar este retiro
  </a>
</div>
<?php endif; ?>

<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>


<?php if ($rtPlanes): ?>
<script>
/* v1.7: la calculadora. Los planes y los precios los escribe PHP desde el
   campo Incluye, asi que editar ese campo en el panel cambia la cuenta. */
(function(){
  var PLANES = <?= json_encode($rtPlanes, JSON_UNESCAPED_UNICODE) ?>;
  var WA = <?= json_encode(preg_replace('/\D/', '', $r['wa_contacto'] ?? '') ?: preg_replace('/\D/', '', SITE_WA)) ?>;
  var TITULO = <?= json_encode($r['titulo']) ?>;
  var plan = 0, cant = {};
  var $ = function(i){ return document.getElementById(i); };
  function plata(n){ return '$' + n.toLocaleString('es-AR'); }

  function dibujarFilas(){
    var cont = $('rtCalcFilas'); cont.innerHTML = '';
    PLANES[plan].filas.forEach(function(f, i){
      var id = 'rtq' + i;
      if (cant[plan] === undefined) cant[plan] = {};
      if (cant[plan][i] === undefined) cant[plan][i] = (i === 0 ? 2 : 0);
      var d = document.createElement('div');
      d.className = 'rt-calc-fila';
      d.innerHTML = '<label for="' + id + '">' + f.et + '</label>'
        + '<div class="rt-calc-num">'
        + '<button type="button" data-i="' + i + '" data-p="-1">&minus;</button>'
        + '<input type="number" id="' + id + '" min="0" max="40" inputmode="numeric" value="' + cant[plan][i] + '">'
        + '<button type="button" data-i="' + i + '" data-p="1">+</button></div>';
      cont.appendChild(d);
    });
    cont.querySelectorAll('button').forEach(function(b){
      b.addEventListener('click', function(){
        var i = +b.dataset.i;
        cant[plan][i] = Math.max(0, Math.min(40, (cant[plan][i] || 0) + (+b.dataset.p)));
        dibujarFilas(); recalcular();
      });
    });
    cont.querySelectorAll('input').forEach(function(inp, i){
      inp.addEventListener('input', function(){
        cant[plan][i] = Math.max(0, Math.min(40, parseInt(inp.value, 10) || 0));
        recalcular();
      });
    });
  }

  function recalcular(){
    var p = PLANES[plan], total = 0, filas = [];
    p.filas.forEach(function(f, i){
      var q = cant[plan][i] || 0;
      if (!q) return;
      var sub = q * f.n; total += sub;
      filas.push('<div><span>' + q + ' x ' + f.et + '</span><em>' + plata(sub) + '</em></div>');
    });
    $('rtCalcDet').innerHTML = filas.join('')
      || '<div style="color:#4a6a85">Elegí cuántos son para ver el total.</div>';
    $('rtCalcTotal').textContent = plata(total);
    var txt = 'Hola, quiero consultar por ' + TITULO + '.\n' + p.nombre + '\n';
    p.filas.forEach(function(f, i){
      var q = cant[plan][i] || 0;
      if (q) txt += q + ' x ' + f.et + '\n';
    });
    txt += 'Total estimado: ' + plata(total);
    $('rtCalcWa').href = 'https://wa.me/' + WA + '?text=' + encodeURIComponent(txt);
  }

  document.querySelectorAll('.rt-calc-plan').forEach(function(b){
    b.addEventListener('click', function(){
      document.querySelectorAll('.rt-calc-plan').forEach(function(x){ x.classList.remove('on'); });
      b.classList.add('on'); plan = +b.dataset.plan; dibujarFilas(); recalcular();
    });
  });
  dibujarFilas(); recalcular();
})();
</script>
<?php endif; ?>
</body>
</html>
