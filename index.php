<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/ajustes.php';
$pagina = 'inicio';
$raiz   = '';
$portada_transparente = true;

/* ── Próximos retiros ────────────────────────────────────────
   Si no hay base de datos todavía, la agenda simplemente no
   aparece y el resto del sitio funciona igual. */
$retiros = [];
$espacios = [];
$media_esp = [];
if ($db = getDB()) {
    try {
        $q = $db->prepare(
            "SELECT slug,titulo,facilitador,fecha_inicio,fecha_fin,modalidad,estado,es_propio,url_inscripcion
             FROM retiros
             WHERE estado IN ('publicado','agotado') AND fecha_inicio >= CURDATE()
             ORDER BY fecha_inicio ASC LIMIT 6"
        );
        $q->execute();
        $retiros = $q->fetchAll();
    } catch (Exception $e) { error_log('[LR agenda] '.$e->getMessage()); }

    try {
        /* v1.4: todos los espacios, no cinco, y con su material.
           Antes el LIMIT 5 dejaba tres espacios afuera de la portada. */
        $espacios = $db->query(
            "SELECT id,slug,nombre,tipo,resumen,imagen,capacidad FROM espacios
             WHERE activo=1 ORDER BY orden ASC"
        )->fetchAll();

        if ($espacios) {
            $ids = implode(',', array_map(function($x){ return (int)$x['id']; }, $espacios));
            /* v1.8: mismo orden que el panel. El primero de la lista es la
               portada de la tarjeta. */
            foreach ($db->query("SELECT espacio_id,tipo,archivo FROM espacio_media
                                 WHERE espacio_id IN($ids) AND activo=1
                                 ORDER BY orden ASC, id ASC")->fetchAll() as $m) {
                $media_esp[$m['espacio_id']][] = $m;
            }
        }
    } catch (Exception $e) { error_log('[LR espacios] '.$e->getMessage()); }
}

/* Respaldo estático: lo que se muestra mientras la base esté vacía. */
if (!$espacios) {
    $espacios = [
        ['slug'=>'salon-principal','nombre'=>'Salón principal','imagen'=>'assets/img/salon.jpg','capacidad'=>40,
         'resumen'=>'Piso de madera y ventanales al parque. Se arma para yoga, círculos, constelaciones o formaciones.'],
        ['slug'=>'alojamiento','nombre'=>'Alojamiento','imagen'=>'assets/img/galeria-ext.jpg','capacidad'=>null,
         'resumen'=>'Habitaciones sobre la galería, a pasos del salón y del comedor. El grupo no se mueve en auto durante la estadía.'],
        ['slug'=>'comedor','nombre'=>'Comedor','imagen'=>'assets/img/comedor.jpg','capacidad'=>null,
         'resumen'=>'Menús caseros con opciones vegetarianas, veganas y sin TACC, coordinados según el ritmo de cada retiro.'],
        ['slug'=>'lago-senderos','nombre'=>'Lago y senderos','imagen'=>'assets/img/sendero.jpg','capacidad'=>null,
         'resumen'=>'Pasarela de madera sobre el agua y circuitos de caminata para trabajos en silencio.'],
    ];
}
?>
<!DOCTYPE html>
<!-- index.php VERSION 1.8 -->
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>La Reserva · Retiros y encuentros en Capilla del Señor</title>
<meta name="description" content="Predio de <?= SITE_HECTAREAS ?> hectáreas en Capilla del Señor, a una hora de Buenos Aires. Salones, alojamiento y gastronomía para retiros, talleres y formaciones.">
<link rel="canonical" href="<?= SITE_URL ?>/">
<meta property="og:type" content="website">
<meta property="og:title" content="La Reserva Naturaleza Arte">
<meta property="og:description" content="Un lugar para que tu retiro suceda. Capilla del Señor, a una hora de Buenos Aires.">
<meta property="og:url" content="<?= SITE_URL ?>/">
<meta property="og:image" content="<?= SITE_URL ?>/assets/video/hero-poster.jpg">
<meta property="og:locale" content="es_AR">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<!-- index.php VERSION 1.8 -->

<script type="application/ld+json">
{"@context":"https://schema.org","@type":"LodgingBusiness",
 "name":"<?= SITE_LEGAL ?>","url":"<?= SITE_URL ?>/",
 "description":"Predio de <?= SITE_HECTAREAS ?> hectáreas para retiros, talleres y encuentros.",
 "address":{"@type":"PostalAddress","addressLocality":"Capilla del Señor","addressRegion":"Buenos Aires","addressCountry":"AR"},
 "geo":{"@type":"GeoCoordinates","latitude":<?= SITE_LAT ?>,"longitude":<?= SITE_LNG ?>}}
</script>
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
/* v1.2: el hero de la portada, centrado de verdad.
   El bloque ya estaba centrado, pero el texto adentro seguia alineado a la
   izquierda, y el rotulo se partia en dos lineas porque el ancho del bloque
   lo fijaba el h1, que es mas angosto. text-align:center alinea las lineas y
   el nowrap del rotulo evita el corte feo en "A UNA HORA DE / BUENOS AIRES". */
.portada .texto .interior{text-align:center}
.portada .texto .interior h1,
.portada .texto .interior p{margin-left:auto;margin-right:auto}
.portada .texto .interior .lugar{white-space:nowrap}
.portada .texto .interior .acciones{justify-content:center}
@media(max-width:620px){
  .portada .texto .interior .lugar{white-space:normal}
}
/* v1.3: el bloque del hero, mas abajo.
   .portada usa align-items:flex-end, o sea que el contenido esta anclado
   ABAJO con padding-bottom:8vh. Para bajarlo hay que achicar ese padding,
   no agregar arriba. 8vh -> 3vh lo mueve unos 60px hacia abajo en una
   pantalla de 1200, que es lo que hacia falta para que el rotulo dejara de
   quedar tapado por el nav fijo.
   El padding-top es un piso de seguridad: en una ventana baja el bloque
   nunca puede meterse debajo del header. */
.portada .texto{padding-top:120px;padding-bottom:3vh}
/* v1.4: los modulos de espacios son links, con video al pasar el mouse.
   El <a> hereda color para que los textos de adentro no se pinten de
   celeste de link. */
.modulo{color:inherit;text-decoration:none;position:relative;transition:background .25s}
.modulo .foto{position:relative}
.modulo .foto video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  opacity:0;transition:opacity .35s}
.modulo:hover .foto video{opacity:1}
@media(hover:none){
  .modulo .foto video{opacity:1}
  .modulo:hover .foto img{transform:none}
}
.modulo .foto img{transition:transform .5s}
.modulo:hover .foto img{transform:scale(1.03)}
.mod-tipo{display:inline-block;font-size:.64rem;letter-spacing:.16em;text-transform:uppercase;
  font-weight:600;color:#00c9a7;border:1px solid rgba(0,201,167,.3);border-radius:12px;
  padding:3px 10px;margin-bottom:14px}
.mod-ver{display:block;margin-top:6px;color:#00c9a7;font-size:.88rem;font-weight:500}
/* v1.5: la linea de capacidad tambien se ve clickeable. El modulo entero ya
   es un <a>, asi que no hace falta (ni se puede) anidar otro link adentro:
   un <a> dentro de un <a> es HTML invalido y el navegador lo desarma. */
/* v1.7: cada uno en su renglon. Los dos eran inline-block y en pantalla
   angosta quedaban pegados: "Hasta 40 personas Ver detalles". */
.modulo .dato{color:#00c9a7;font-weight:500;padding-bottom:1px;margin-bottom:16px;
  display:table;border-bottom:1px solid rgba(0,201,167,.3)}
.modulo:hover .dato{border-bottom-color:#00c9a7}
.modulo:hover .mod-ver{text-decoration:underline}
.modulo:focus-visible{outline:2px solid #00c9a7;outline-offset:-4px}
@media(max-height:720px){
  .portada .texto{padding-top:96px;padding-bottom:2vh}
  .portada h1{margin-bottom:14px}
  .portada .lugar{margin-bottom:14px}
}
</style>
</head>
<body>

<?php include __DIR__.'/includes/nav.php'; ?>

<!-- ═══ PORTADA ═══════════════════════════════════════════════ -->
<section class="portada">
  <video autoplay muted loop playsinline preload="metadata"
         poster="assets/video/hero-poster.jpg" aria-hidden="true">
    <source src="assets/video/hero.mp4" type="video/mp4">
  </video>
  <div class="velo"></div>

  <div class="texto">
    <div class="interior">
      <?php /* v1.6: editable desde admin/portada.php. El segundo argumento de
               lrAjuste es el texto original: si la clave esta vacia o la tabla
               no existe, la portada se ve igual que siempre. */ ?>
      <p class="lugar"><?= e(lrAjuste('hero_rotulo','CAPILLA DEL SEÑOR · A UNA HORA DE BUENOS AIRES')) ?></p>
      <h1><?= e(lrAjuste('hero_titulo','Un lugar para que tu retiro suceda')) ?></h1>
      <p><?= e(lrAjuste('hero_bajada', SITE_HECTAREAS.' hectáreas con salones, alojamiento, gastronomía y naturaleza en un mismo predio. Vos traés la propuesta; nosotros preparamos el lugar.')) ?></p>
      <div class="acciones">
        <a href="contacto.php" class="boton solido-claro"
           onclick="lrBoton('click_rsvp','contacto','Organiza tu retiro')"><?= e(lrAjuste('hero_boton1','Organizá tu retiro')) ?></a>
        <a href="retiros.php" class="boton claro"><?= e(lrAjuste('hero_boton2','Ver la agenda')) ?></a>
      </div>
    </div>
  </div>

  <a href="#el-lugar" class="bajar">SEGUIR</a>
</section>

<!-- ═══ EL LUGAR ══════════════════════════════════════════════ -->
<section class="bloque papel" id="el-lugar">
  <div class="envoltura dos-columnas">
    <h2 class="titulo-bloque">El entorno hace la mitad del trabajo</h2>
    <div class="medida">
      <p>
        <?= e(lrAjuste('ubic_texto','La Reserva es un predio de '.SITE_HECTAREAS.' hectáreas en Capilla del Señor.')) ?>
        Lago, senderos, arboleda y aire limpio, a una hora de la ciudad y lo
        suficientemente lejos como para que el ruido cotidiano deje de sonar.
      </p>
      <p>
        Facilitadores de yoga, biodanza, gestalt, constelaciones familiares y
        meditación eligen este lugar para sostener sus procesos grupales.
        El silencio y el paisaje acompañan la práctica sin interferir.
      </p>
      <p style="margin-top:26px">
        <a href="espacios.php" class="boton hueco"
           onclick="lrBoton('click_url','lr','Conocer los espacios')">Conocer los espacios</a>
      </p>
    </div>
  </div>
</section>

<!-- ═══ AGENDA ════════════════════════════════════════════════ -->
<section class="bloque niebla" id="agenda">
  <div class="envoltura">
    <div class="dos-columnas">
      <h2 class="titulo-bloque">Próximos encuentros</h2>
      <p class="medida" style="color:var(--gris)">
        Retiros y talleres abiertos al público que se desarrollan en el predio,
        organizados por La Reserva y por facilitadores invitados. La inscripción
        de cada propuesta va directo a quien la coordina.
      </p>
    </div>

    <div class="agenda">
      <?php if ($retiros): ?>
        <?php foreach ($retiros as $r): ?>
        <a class="fila" href="retiro.php?r=<?= urlencode($r['slug']) ?>">
          <span class="fila-fecha"><?= e(rangoFecha($r['fecha_inicio'], $r['fecha_fin'])) ?></span>
          <span>
            <span class="fila-titulo"><?= e($r['titulo']) ?></span>
            <?php if ($r['facilitador']): ?>
              <span class="fila-quien">Coordina <?= e($r['facilitador']) ?></span>
            <?php endif; ?>
          </span>
          <?php if ($r['estado'] === 'agotado'): ?>
            <span class="fila-estado agotado">SIN CUPOS</span>
          <?php elseif ((int)$r['es_propio'] === 1): ?>
            <span class="fila-estado propio">DE LA RESERVA</span>
          <?php else: ?>
            <span class="fila-estado"><?= e(strtoupper($r['modalidad'])) ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="agenda-vacia">
          Estamos cerrando las próximas fechas.
          <a href="<?= e(waLink('Hola, quiero saber qué retiros hay disponibles.')) ?>" target="_blank" rel="noopener">Escribinos</a>
          y te contamos qué hay disponible.
        </div>
      <?php endif; ?>
    </div>

    <p style="margin-top:32px">
      <a href="retiros.php" class="boton hueco">Ver toda la agenda</a>
    </p>
  </div>
</section>

<!-- ═══ ESPACIOS (módulos alternados) ═════════════════════════ -->
<section id="espacios">
  <?php foreach ($espacios as $sp): ?>
  <?php
    /* Imagen fija primero, video despues. Igual criterio que espacios.php:
       la tarjeta muestra una foto quieta y el video solo corre al pasar
       el mouse. Si el espacio no tiene material cargado, cae en la
       columna imagen de la propia fila. */
    $mm   = $media_esp[$sp['id'] ?? 0] ?? [];
    $foto = null; $vid = null;
    foreach ($mm as $m) {
      if ($m['tipo'] === 'imagen' && !$foto) $foto = $m['archivo'];
      if ($m['tipo'] === 'video'  && !$vid)  $vid  = $m['archivo'];
    }
    $foto = $foto ?: ($sp['imagen'] ?: 'assets/img/parque.jpg');
  ?>
  <a class="modulo" href="espacios.php#<?= e($sp['slug']) ?>"
     onclick="lrBoton('click_url','lr',<?= json_encode($sp['nombre']) ?>)">
    <div class="foto">
      <img src="<?= e($foto) ?>" alt="<?= e($sp['nombre']) ?> en La Reserva" loading="lazy" width="1600" height="900">
      <?php if ($vid): ?>
        <video muted loop playsinline preload="none" poster="<?= e($foto) ?>">
          <source src="<?= e($vid) ?>" type="video/mp4">
        </video>
      <?php endif; ?>
    </div>
    <div class="cuerpo">
      <?php if (!empty($sp['tipo'])): ?>
        <span class="mod-tipo"><?= e(str_replace('_',' ',$sp['tipo'])) ?></span>
      <?php endif; ?>
      <h3><?= e($sp['nombre']) ?></h3>
      <p><?= e($sp['resumen']) ?></p>
      <?php if (!empty($sp['capacidad'])): ?>
        <p class="dato">Hasta <?= (int)$sp['capacidad'] ?> personas</p>
      <?php endif; ?>
      <span class="mod-ver">Ver detalles &rarr;</span>
    </div>
  </a>
  <?php endforeach; ?>
</section>

<!-- ═══ CIFRAS ════════════════════════════════════════════════ -->
<section class="bloque ajustado oscuro">
  <div class="envoltura cifras">
    <div><div class="cifra-n"><?= e(lrAjuste('cifra1_n', (string)SITE_HECTAREAS)) ?></div><div class="cifra-t"><?= e(lrAjuste('cifra1_t','hectáreas de parque, lago y arboleda')) ?></div></div>
    <div><div class="cifra-n"><?= e(lrAjuste('cifra2_n','1 h')) ?></div><div class="cifra-t"><?= e(lrAjuste('cifra2_t','desde CABA por Ruta 8')) ?></div></div>
    <div><div class="cifra-n"><?= e(lrAjuste('cifra3_n','Todo')) ?></div><div class="cifra-t"><?= e(lrAjuste('cifra3_t','salón, alojamiento y comidas en el mismo predio')) ?></div></div>
    <div><div class="cifra-n"><?= e(lrAjuste('cifra4_n','2027')) ?></div><div class="cifra-t"><?= e(lrAjuste('cifra4_t','agenda abierta para reservar fechas')) ?></div></div>
  </div>
</section>

<!-- ═══ ORGANIZÁ TU RETIRO ════════════════════════════════════ -->
<section class="bloque" id="organizar" style="position:relative;overflow:hidden;background:var(--bg)">
  <video autoplay muted loop playsinline preload="metadata" aria-hidden="true"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.25">
    <source src="assets/video/pages/espacios.mp4" type="video/mp4">
  </video>
  <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,22,40,.9),rgba(10,22,40,.75));z-index:1"></div>
  <div class="envoltura dos-columnas" style="position:relative;z-index:2">
    <div>
      <h2 class="titulo-bloque">Vos diseñás la propuesta</h2>
      <p class="medida" style="color:var(--gris)">
        Si sos profe, guía o facilitador y estás armando un retiro, una
        formación o un taller, coordinamos una visita para que conozcas el
        predio y veamos juntos el formato que mejor se adapta a tu grupo.
      </p>
      <p style="margin-top:24px">
        <a href="contacto.php" class="boton"
           onclick="lrBoton('click_rsvp','johnny','Consultar disponibilidad')">Consultar disponibilidad</a>
      </p>
    </div>

    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:18px">
      <li style="border-top:1px solid var(--linea);padding-top:16px">
        <strong>Espacios que cambian de ritmo.</strong>
        <span style="color:var(--gris)">Salón cubierto para la práctica, parque para el aire libre, sectores comunes para las pausas.</span>
      </li>
      <li style="border-top:1px solid var(--linea);padding-top:16px">
        <strong>Cocina coordinada con tu grupo.</strong>
        <span style="color:var(--gris)">Menús caseros ajustados a horarios, restricciones y al ritmo de las actividades.</span>
      </li>
      <li style="border-top:1px solid var(--linea);padding-top:16px">
        <strong>Alojamiento en el predio.</strong>
        <span style="color:var(--gris)">Nadie se traslada entre actividades: dormir, comer y practicar en el mismo lugar.</span>
      </li>
      <li style="border-top:1px solid var(--linea);padding-top:16px">
        <strong>Acompañamiento durante la estadía.</strong>
        <span style="color:var(--gris)">Vos te ocupás del contenido; el equipo del lugar resuelve la logística.</span>
      </li>
    </ul>
  </div>
</section>

<!-- ═══ UBICACIÓN ═════════════════════════════════════════════ -->
<section class="bloque ajustado oscuro" id="ubicacion" style="position:relative;overflow:hidden;background:#0a1628">
  <video autoplay muted loop playsinline preload="metadata"
         poster="assets/video/hero-poster.jpg"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.35">
    <source src="assets/video/pages/retiros.mp4" type="video/mp4">
  </video>
  <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,22,40,.88),rgba(10,22,40,.72));z-index:1"></div>
  <div class="envoltura" style="position:relative;z-index:2">
    <p class="rotulo" style="color:#00c9a7">CÓMO LLEGAR</p>
    <h2 class="titulo-bloque" style="color:#fff">Capilla del Señor,<br>Exaltación de la Cruz</h2>
    <p class="medida" style="color:rgba(232,240,247,.75);margin-top:14px">
      A 80 km del centro de Buenos Aires por Ruta 8, Km 77.
      Coordinamos la llegada del grupo y el estacionamiento dentro del predio.
    </p>
    <p style="margin-top:24px">
      <a href="https://maps.app.goo.gl/n3sNRpNpCmE8EKYh6"
         class="boton claro" target="_blank" rel="noopener">Abrir en Google Maps</a>
    </p>
  </div>
</section>

<!-- ═══ CONTACTO ══════════════════════════════════════════════ -->
<section class="bloque papel" id="contacto">
  <div class="envoltura dos-columnas">
    <div>
      <h2 class="titulo-bloque">Contanos qué estás armando</h2>
      <p class="medida" style="color:var(--gris)">
        Escribinos con la fecha tentativa y la cantidad de personas y te
        respondemos con disponibilidad y una propuesta.
      </p>
      <p style="margin-top:22px">
        <a href="<?= e(waLink('Hola, quiero consultar por fechas en La Reserva.')) ?>" target="_blank" rel="noopener">WhatsApp <?= e(SITE_WA_LABEL) ?></a><br>
        <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>
      </p>
    </div>

    <form class="formulario" action="contacto.php" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="origen" value="inicio">
      <div class="honeypot"><label>No completar<input type="text" name="apodo" tabindex="-1" autocomplete="off"></label></div>

      <div class="campo"><label for="n">Nombre</label><input id="n" name="nombre" required></div>
      <div class="campo"><label for="em">E-mail</label><input id="em" type="email" name="email" required></div>
      <div class="campo"><label for="tel">Teléfono</label><input id="tel" type="tel" name="telefono"></div>
      <div class="campo">
        <label for="tp">Motivo</label>
        <select id="tp" name="tipo">
          <option value="organizar">Quiero organizar un retiro</option>
          <option value="participar">Quiero participar de un retiro</option>
          <option value="visita">Quiero coordinar una visita</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div class="campo"><label for="ft">Fecha tentativa</label><input id="ft" type="date" name="fecha_tentativa"></div>
      <div class="campo"><label for="pe">Cantidad de personas</label><input id="pe" type="number" min="1" max="500" name="personas"></div>
      <div class="campo ancho"><label for="ms">Mensaje</label><textarea id="ms" name="mensaje" rows="4"></textarea></div>
      <div class="campo ancho"><button type="submit" class="boton">Enviar consulta</button></div>
    </form>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
<script>
/* El video de cada modulo arranca al entrar el mouse y vuelve al principio
   al salir. preload="none" para que ocho videos no se bajen todos al abrir
   la portada: recien se pide el archivo cuando alguien apunta a la tarjeta. */
var lrTouch = window.matchMedia('(hover: none)').matches;

document.querySelectorAll('.modulo').forEach(function(m){
  var v = m.querySelector('video');
  if(!v) return;

  if (lrTouch) {
    /* v1.7: en celular no existe el hover, asi que el video nunca arrancaba
       y se veia la foto quieta. Aca arranca cuando la tarjeta entra en
       pantalla y se pausa cuando sale, que es lo unico que se puede hacer
       sin un gesto del usuario. Sigue en silencio: el audio sin gesto lo
       bloquean todos los navegadores. */
    v.preload = 'metadata';
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function(entradas){
        entradas.forEach(function(en){
          if (en.isIntersecting) {
            var p = v.play(); if(p && p.catch) p.catch(function(){});
          } else {
            v.pause();
          }
        });
      }, {threshold: 0.45}).observe(m);
    } else {
      var p = v.play(); if(p && p.catch) p.catch(function(){});
    }
    return;
  }

  m.addEventListener('mouseenter', function(){
    var p = v.play(); if(p && p.catch) p.catch(function(){});
  });
  m.addEventListener('mouseleave', function(){
    v.pause(); try { v.currentTime = 0; } catch(e){}
  });
});
</script>
</body>
</html>
