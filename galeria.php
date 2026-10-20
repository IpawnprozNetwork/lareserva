<?php
/* galeria.php - VERSION 1.4
   1.1: the photo grid is a real CSS grid. It used to be a multi-column layout,
   which reads top to bottom per column and leaves a ragged bottom row.
   1.2: hero padding so the rotulo stops colliding with the fixed nav. */
require_once __DIR__.'/includes/config.php';
$pagina='galeria'; $raiz='';

$db = getDB();
$imagenes = [];
if ($db) {
  try {
    $imagenes = $db->query("SELECT * FROM galeria WHERE activo=1 ORDER BY orden ASC, id ASC")->fetchAll();
  } catch(Exception $e){ error_log($e->getMessage()); }
}
// Static fallback
if (!$imagenes) {
  $imagenes = [
    ['id'=>1,'archivo'=>'assets/img/galeria/predio.jpg','titulo'=>'El casco principal','alt'=>'Vista aérea del casco'],
    ['id'=>2,'archivo'=>'assets/img/galeria/pasarela-atardecer.jpg','titulo'=>'La pasarela sobre el lago','alt'=>'Pasarela al atardecer'],
    ['id'=>3,'archivo'=>'assets/img/salon.jpg','titulo'=>'El salón','alt'=>'Main Hall interior'],
    ['id'=>4,'archivo'=>'assets/img/galeria/atardecer-lago.jpg','titulo'=>'Atardecer sobre el lago','alt'=>'Lago al atardecer'],
    ['id'=>5,'archivo'=>'assets/img/galeria/practica-yoga.jpg','titulo'=>'Práctica en el salón','alt'=>'Sesión de yoga'],
    ['id'=>6,'archivo'=>'assets/img/galeria/grupo-parque.jpg','titulo'=>'Encuentro en el parque','alt'=>'Grupo en el parque'],
    ['id'=>7,'archivo'=>'assets/img/comedor.jpg','titulo'=>'La mesa larga','alt'=>'Comedor compartido'],
    ['id'=>8,'archivo'=>'assets/img/galeria/mesa-servida.jpg','titulo'=>'Cocina del retiro','alt'=>'Mesa servida'],
    ['id'=>9,'archivo'=>'assets/img/galeria/hogar.jpg','titulo'=>'El hogar a leña','alt'=>'Hogar encendido'],
    ['id'=>10,'archivo'=>'assets/img/galeria/fogon.jpg','titulo'=>'Fogón','alt'=>'Fogón nocturno'],
  ];
}
$ig_handle = SITE_IG; // lareservanaturalezaarte
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Galería · La Reserva Naturaleza Arte</title>
<meta name="description" content="Fotografías del predio, los espacios y los retiros en La Reserva Naturaleza Arte, Capilla del Señor.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<!-- galeria.php VERSION 1.4 -->
<style>
/* ── Gallery page extras ── */
/* v1.2: padding-top clears the fixed nav. Without it the rotulo collided
   with the brand on any viewport under ~900px tall. */
.gal-hero{min-height:62vh;padding-top:110px;position:relative;overflow:hidden;background:var(--bg);display:flex;align-items:flex-end}
.gal-hero video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
.gal-hero .velo{position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(10,22,40,.4) 0%,rgba(10,22,40,.8) 100%)}
.gal-hero .texto{position:relative;z-index:2;padding:0 24px 6vh;width:100%}
.gal-hero .interior{max-width:1180px;margin:0 auto}

/* Masonry grid */
.gal-section{padding:64px 0}
/* v1.1: grid, not columns. A multi-column layout flows items DOWN column 1
   and then down column 2, so the orden you set in the panel read vertically
   instead of left to right, and the browser balanced the columns by height
   which left a ragged bottom. Every photo is 1200x1600, so masonry bought
   nothing anyway. */
.gal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:40px}
@media(max-width:900px){.gal-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.gal-grid{grid-template-columns:1fr}}
.gal-item{position:relative;cursor:pointer;overflow:hidden;border-radius:6px;background:var(--card)}
.gal-item img{width:100%;height:100%;aspect-ratio:3/4;object-fit:cover;display:block;transition:transform .4s ease,filter .4s ease}
.gal-item:hover img{transform:scale(1.03);filter:brightness(.85)}
.gal-item video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  opacity:0;transition:opacity .35s;z-index:1}
.gal-item:hover video{opacity:1}
.gal-play{position:absolute;top:10px;right:10px;z-index:3;width:26px;height:26px;
  border-radius:50%;background:rgba(6,14,24,.6);color:#fff;font-size:.6rem;
  display:flex;align-items:center;justify-content:center;transition:opacity .25s}
.gal-item:hover .gal-play{opacity:0}
@media(hover:none){.gal-item video{opacity:1}.gal-play{display:none}}
.gal-caption{position:absolute;bottom:0;left:0;right:0;padding:24px 16px 14px;background:linear-gradient(transparent,rgba(10,22,40,.85));color:#e8f0f7;font-size:.82rem;opacity:0;transition:opacity .3s;pointer-events:none}
.gal-item:hover .gal-caption{opacity:1}

/* Lightbox */
#lightbox{position:fixed;inset:0;z-index:9000;background:rgba(5,12,25,.96);display:none;align-items:center;justify-content:center;padding:20px}
#lightbox.open{display:flex}
.lb-inner{display:grid;grid-template-columns:1fr 340px;gap:0;max-width:1100px;width:100%;max-height:90vh;background:var(--card);border-radius:10px;overflow:hidden;border:1px solid var(--border)}
@media(max-width:760px){.lb-inner{grid-template-columns:1fr;max-height:95vh;overflow-y:auto}}
.lb-img-wrap{position:relative;background:#000;display:flex;align-items:center;justify-content:center;min-height:300px}
.lb-img-wrap img{width:100%;height:100%;object-fit:contain;max-height:70vh;display:block}
.lb-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(10,22,40,.7);border:1px solid var(--border);color:#e8f0f7;border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:.18s;z-index:1}
.lb-nav:hover{background:var(--teal);color:#0a1628;border-color:var(--teal)}
.lb-prev{left:12px}.lb-next{right:12px}
.lb-close{position:absolute;top:12px;right:12px;background:rgba(10,22,40,.7);border:1px solid var(--border);color:#e8f0f7;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:.18s;z-index:2}
.lb-close:hover{background:var(--red);border-color:var(--red)}
.lb-info{padding:28px 24px;display:flex;flex-direction:column;border-left:1px solid var(--border);overflow-y:auto}
.lb-title{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:300;color:var(--text);margin-bottom:8px}
.lb-counter{font-size:.74rem;color:var(--text3);letter-spacing:.1em;margin-bottom:20px}
.lb-desc{font-size:.88rem;color:var(--text2);line-height:1.7;margin-bottom:auto}
.lb-ig-link{display:flex;align-items:center;gap:8px;margin-top:20px;padding:12px 14px;background:var(--teal-dim);border:1px solid var(--teal-b);border-radius:6px;color:var(--teal);font-size:.84rem;font-weight:500;text-decoration:none;transition:.18s}
.lb-ig-link:hover{background:rgba(0,201,167,.18)}
.lb-ig-link svg{flex-shrink:0}

/* Instagram widget */
.ig-section{padding:0 0 80px}
.ig-widget-wrap{margin-top:32px;border-radius:10px;overflow:hidden;border:1px solid var(--border);background:var(--card)}
.ig-header{display:flex;align-items:center;gap:14px;padding:18px 20px;border-bottom:1px solid var(--border)}
.ig-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;font-weight:700;flex-shrink:0}
.ig-handle{font-weight:600;color:var(--text);font-size:.95rem}
.ig-sub{font-size:.76rem;color:var(--text3);margin-top:2px}
.ig-embed-area{padding:0}
.ig-cta{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-top:1px solid var(--border)}
.ig-cta p{font-size:.84rem;color:var(--text2)}
.ig-follow-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);color:#fff;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;transition:opacity .2s}
.ig-follow-btn:hover{opacity:.88}

/* WhatsApp float + IG float */
.floats{position:fixed;right:18px;bottom:18px;z-index:70;display:flex;flex-direction:column;gap:10px;align-items:flex-end}
.float-btn{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 4px 16px rgba(0,0,0,.3);transition:.2s}
.float-btn:hover{transform:scale(1.08)}
.float-wa{background:#25d366}
.float-ig{background:linear-gradient(135deg,#f09433,#dc2743,#bc1888)}
.float-btn svg{width:24px;height:24px;fill:#fff}

.rotulo{font-size:.74rem;letter-spacing:.2em;color:var(--teal);margin-bottom:14px}
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
<?php $portada_transparente=true; include __DIR__.'/includes/nav.php'; ?>

<!-- Hero -->
<section class="gal-hero">
  <video autoplay muted loop playsinline preload="metadata" poster="assets/video/pages/galeria-poster.jpg">
    <source src="assets/video/pages/galeria.mp4" type="video/mp4">
  </video>
  <div class="velo"></div>
  <div class="texto">
    <div class="interior">
      <p class="rotulo">GALERIA · LA RESERVA EN IMÁGENES</p>
      <h1 style="font-size:clamp(2.4rem,5vw,4rem);color:#fff;max-width:14ch">El predio en imágenes</h1>
      <p style="color:rgba(232,240,247,.7);max-width:44ch;margin-top:12px">Momentos de retiros que ya sucedieron y los espacios donde suceden.</p>
    </div>
  </div>
</section>

<!-- Masonry gallery -->
<section class="gal-section">
  <div class="envoltura">
    <p class="rotulo">FOTOGRAFÍAS</p>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <h2 class="titulo-bloque" style="margin:0">El lugar y sus momentos</h2>
      <a href="https://www.instagram.com/<?= e($ig_handle) ?>/" target="_blank" rel="noopener"
         style="font-size:.82rem;color:var(--teal);display:flex;align-items:center;gap:6px">
        Ver más en Instagram ↗
      </a>
    </div>

    <div class="gal-grid" id="galGrid">
      <?php foreach($imagenes as $i => $img): ?>
      <div class="gal-item" data-index="<?= $i ?>" onclick="openLb(<?= $i ?>)">
        <img src="<?= e($img['archivo']) ?>"
             alt="<?= e($img['alt'] ?? $img['titulo']) ?>"
             loading="<?= $i < 4 ? 'eager' : 'lazy' ?>">
        <?php /* v1.4: si la foto tiene un clip asociado, se reproduce al pasar
                 el mouse y al entrar en pantalla en celular. La columna `video`
                 es opcional: sin ella la tarjeta se comporta como siempre. */ ?>
        <?php if (!empty($img['video'])): ?>
        <video muted loop playsinline webkit-playsinline preload="none"
               disablePictureInPicture aria-hidden="true"
               poster="<?= e($img['archivo']) ?>">
          <source src="<?= e($img['video']) ?>" type="video/mp4">
        </video>
        <span class="gal-play" aria-hidden="true">&#9654;</span>
        <?php endif; ?>
        <?php if($img['titulo']): ?>
        <div class="gal-caption"><?= e($img['titulo']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Vení a conocerlo CTA -->
    <div style="margin-top:56px;padding:40px;background:var(--card);border:1px solid var(--border);border-radius:10px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center">
      <div>
        <p style="font-size:.76rem;letter-spacing:.16em;color:var(--teal);margin-bottom:8px">VISITAS AL PREDIO</p>
        <h3 style="font-family:'Fraunces',serif;font-size:1.5rem;font-weight:300;color:var(--text);margin-bottom:8px">Vení a conocerlo</h3>
        <p style="font-size:.92rem;color:rgba(232,240,247,.72);max-width:44ch">Coordinamos visitas para facilitadores que están armando su próxima fecha.</p>
      </div>
      <a href="contacto.php" class="boton" style="white-space:nowrap">Consultar disponibilidad</a>
    </div>
  </div>
</section>

<!-- Instagram section -->
<section class="ig-section">
  <div class="envoltura">
    <p class="rotulo">INSTAGRAM</p>
    <h2 class="titulo-bloque">Seguinos en Instagram</h2>
    <p style="color:var(--text2);font-size:.92rem;margin-top:8px;max-width:50ch">Compartimos el día a día del predio, los retiros que suceden y los espacios en cada estación.</p>

    <div class="ig-widget-wrap">
      <div class="ig-header">
        <div class="ig-avatar">LR</div>
        <div>
          <div class="ig-handle">@<?= e($ig_handle) ?></div>
          <div class="ig-sub">La Reserva Naturaleza Arte · Capilla del Señor</div>
        </div>
      </div>

      <!-- Instagram embed -->
      <div class="ig-embed-area" style="padding:16px">
        <blockquote class="instagram-media"
          data-instgrm-permalink="https://www.instagram.com/<?= e($ig_handle) ?>/"
          data-instgrm-version="14"
          style="background:#0d1e2c;border:0;border-radius:6px;margin:0;padding:0;width:100%;min-width:100%">
        </blockquote>
      </div>

      <div class="ig-cta">
        <p>Más fotos y videos en nuestro perfil</p>
        <a href="https://www.instagram.com/<?= e($ig_handle) ?>/" target="_blank" rel="noopener" class="ig-follow-btn" onclick="lrBoton('click_ig','instagram','galeria boton')">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          Seguir en Instagram
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>

<!-- Floating buttons (WA + IG) -->
<div class="floats">
  <a href="https://www.instagram.com/<?= e($ig_handle) ?>/" target="_blank" rel="noopener" class="float-btn float-ig" onclick="lrBoton('click_ig','instagram','galeria flotante')" title="Instagram">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
  </a>
  <a href="https://wa.me/<?= e(SITE_WA) ?>?text=<?= rawurlencode('Hola, quiero consultar sobre La Reserva.') ?>"
     target="_blank" rel="noopener" class="float-btn float-wa" title="WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
  </a>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="if(event.target===this)closeLb()">
  <div class="lb-inner">
    <div class="lb-img-wrap">
      <button class="lb-close" onclick="closeLb()">✕</button>
      <button class="lb-nav lb-prev" onclick="navLb(-1)">&#8592;</button>
      <img id="lbImg" src="" alt="">
      <button class="lb-nav lb-next" onclick="navLb(1)">&#8594;</button>
    </div>
    <div class="lb-info">
      <div class="lb-title" id="lbTitle"></div>
      <div class="lb-counter" id="lbCounter"></div>
      <div class="lb-desc" id="lbDesc">Fotografía del predio de La Reserva Naturaleza Arte, Capilla del Señor, Buenos Aires.</div>
      <a href="https://www.instagram.com/<?= e($ig_handle) ?>/" target="_blank" rel="noopener" class="lb-ig-link" onclick="lrBoton('click_ig','instagram','galeria lightbox')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#00c9a7"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        Ver más en Instagram
      </a>
    </div>
  </div>
</div>

<script async src="//www.instagram.com/embed.js"></script>

<script>
var imgs = <?= json_encode(array_values(array_map(fn($img) => [
  'src'   => $img['archivo'],
  'title' => $img['titulo'] ?? '',
  'alt'   => $img['alt'] ?? $img['titulo'] ?? '',
], $imagenes))) ?>;

var cur = 0;

/* v1.4: los clips de la galeria. Mismo criterio que index y espacios:
   con mouse, al pasar por encima; sin mouse, cuando la tarjeta entra en
   pantalla. preload="none" para no bajar diez videos al abrir la pagina. */
(function(){
  var touch = window.matchMedia('(hover: none)').matches;
  document.querySelectorAll('.gal-item').forEach(function(it){
    var v = it.querySelector('video'); if(!v) return;
    function play(){ v.muted = true; var p = v.play(); if(p&&p.catch) p.catch(function(){}); }
    if (touch) {
      v.preload = 'metadata';
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function(es){
          es.forEach(function(e){ e.isIntersecting ? play() : v.pause(); });
        }, {threshold:.5}).observe(it);
      }
      return;
    }
    it.addEventListener('mouseenter', function(){ v.preload='auto'; play(); });
    it.addEventListener('mouseleave', function(){ v.pause(); try{ v.currentTime=0; }catch(e){} });
  });
})();

function openLb(i) {
  cur = i;
  renderLb();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLb() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function navLb(dir) {
  cur = (cur + dir + imgs.length) % imgs.length;
  renderLb();
}
function renderLb() {
  var img = imgs[cur];
  document.getElementById('lbImg').src = img.src;
  document.getElementById('lbImg').alt = img.alt;
  document.getElementById('lbTitle').textContent = img.title || 'La Reserva';
  document.getElementById('lbCounter').textContent = (cur+1) + ' de ' + imgs.length;
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
  var lb = document.getElementById('lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') closeLb();
  if (e.key === 'ArrowLeft') navLb(-1);
  if (e.key === 'ArrowRight') navLb(1);
});

// Touch swipe
var tx = 0;
document.getElementById('lightbox').addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; }, {passive:true});
document.getElementById('lightbox').addEventListener('touchend', function(e){
  var dx = e.changedTouches[0].clientX - tx;
  if (Math.abs(dx) > 50) navLb(dx < 0 ? 1 : -1);
}, {passive:true});
</script>
</body>
</html>
