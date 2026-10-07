<?php
/* espacios.php - VERSION 3.1
   1.1: card thumbnails show a photo by default. The espacio video only plays
   while the pointer is over the card, then rewinds.
   1.2: lightbox close button moves to the left on mobile, where the nav
   hamburger was covering it.
   2.0: the lightbox now carries an occupancy calendar, an enquiry form that
   writes to consultas, a WhatsApp button, and click tracking. The calendar
   marks every day covered by an upcoming retiro ON THE PROPERTY, not in this
   specific space: the retiros table has no espacio_id column to filter on.
   2.1: min-height:0 on the info panel. Necessary but not sufficient.
   2.2: grid-template-rows:minmax(0,1fr) plus a fixed 88vh box height. Without
   a capped ROW the panel still sized to its content and got clipped by
   overflow:hidden instead of scrolling, which is why 2.1 changed nothing.
   2.3: the calendar picks a RANGE. First tap sets the start, second sets the
   end, and a range cannot be drawn across a day that already has a retiro.
   The range is written to fecha_tentativa + fecha_fin and spelled out above
   the form. Bigger day cells under 760px, because 5px of padding is a coin
   toss with a thumb. Needs consultas_fecha_fin.sql.
   2.4: every button carries its own tag (lr, whatsapp, johnny) in the new
   boton column, separate from ref. Needs boton_columns.sql.
   2.5: the static fallback array is in Spanish. It only renders if the DB
   query fails, so it is a safety net, not what the site normally shows. The
   real copy lives in the espacios table. */
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/antispam.php';
$pagina='espacios'; $raiz='';

$db  = getDB();
$ref = lrCapturarRef();

/* ── Consulta de espacio. Responde JSON al formulario del lightbox. ─────── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '')==='consulta_espacio') {
  header('Content-Type: application/json; charset=utf-8');
  $out = ['ok'=>false, 'msg'=>'No se pudo enviar la consulta.'];

  if (!empty($_POST['apodo'])) {
    $out = ['ok'=>true, 'msg'=>'Recibimos tu consulta.'];
  } elseif (!csrfOk($_POST['csrf'] ?? null)) {
    $out['msg'] = 'La sesion expiro. Recargá la página e intentá de nuevo.';
  } elseif (!$db) {
    $out['msg'] = 'La base de datos no responde en este momento.';
  } elseif (lrIpBloqueada($db, lrIp())) {
    $out['msg'] = 'No pudimos procesar la consulta. Escribinos por WhatsApp.';
  } elseif (!lrRitmoOk($db, lrIp(), 'consulta_esp_ok')) {
    $out['msg'] = 'Ya enviaste varias consultas. Esperá un rato o escribinos por WhatsApp.';
  } else {
    $nombre = limpiar($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $eid    = (int)($_POST['espacio_id'] ?? 0);
    $motivo = null;

    if ($nombre === '') {
      $out['msg'] = 'Falta el nombre.';
    } elseif (!lrEmailAceptable($email, $motivo)) {
      $out['msg'] = lrMotivoTexto($motivo);
      lrEvento($db, 'consulta_esp_rechazada', ['detalle'=>'email:'.$motivo]);
    } else {
      $nombre_esp = ''; $slug_esp = '';
      $q = $db->prepare("SELECT nombre,slug FROM espacios WHERE id=:i LIMIT 1");
      $q->execute([':i'=>$eid]);
      if ($ee = $q->fetch()) { $nombre_esp = $ee['nombre']; $slug_esp = $ee['slug']; }

      $fp  = $_POST['fecha_tentativa'] ?? '';
      $fp2 = $_POST['fecha_fin'] ?? '';
      $fecha  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fp)  ? $fp  : null;
      $fecha2 = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fp2) ? $fp2 : null;
      if ($fecha && $fecha2 && $fecha2 < $fecha) { $tmp = $fecha; $fecha = $fecha2; $fecha2 = $tmp; }
      if ($fecha2 === $fecha) $fecha2 = null;

      $mensaje = limpiar($_POST['mensaje'] ?? '');
      $rango = '';
      if ($fecha)  $rango = 'Fechas: '.$fecha.($fecha2 ? ' al '.$fecha2 : '');
      $mensaje = 'Consulta por el espacio: '.($nombre_esp ?: 'sin identificar')
               . ($rango ? "\n".$rango : '')
               . ($mensaje ? "\n".$mensaje : '');

      try {
        $db->prepare(
          "INSERT INTO consultas
           (nombre,email,telefono,tipo,fecha_tentativa,fecha_fin,personas,mensaje,origen,ref,boton,ip,ip_txt,user_agent)
           VALUES (:nombre,:email,:telefono,:tipo,:fecha_tentativa,:fecha_fin,:personas,:mensaje,:origen,:ref,:boton,:ip,:ip_txt,:ua)"
        )->execute([
          'boton'           => substr(preg_replace('/[^a-z0-9_-]/','',strtolower($_POST['boton'] ?? 'lr')), 0, 24) ?: 'lr',
          'fecha_fin'       => $fecha2,
          'nombre'          => $nombre,
          'email'           => $email,
          'telefono'        => limpiar($_POST['telefono'] ?? '') ?: null,
          'tipo'            => 'organizar',
          'fecha_tentativa' => $fecha,
          'personas'        => !empty($_POST['personas']) ? max(1, min(500, (int)$_POST['personas'])) : null,
          'mensaje'         => $mensaje,
          'origen'          => substr('espacio:'.($slug_esp ?: $eid), 0, 60),
          'ref'             => $_SESSION['lr_ref'] ?? null,
          'ip'              => @inet_pton(lrIp()) ?: null,
          'ip_txt'          => lrIp() ?: null,
          'ua'              => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        lrEvento($db, 'consulta_esp_ok', ['detalle'=>$nombre_esp, 'boton'=>'lr']);
        $out = ['ok'=>true, 'msg'=>'Listo. Te escribimos para coordinar la fecha.'];
      } catch (Exception $ex) {
        error_log('[LR consulta esp] '.$ex->getMessage());
        $out['msg'] = 'No pudimos guardar la consulta. Escribinos por WhatsApp.';
      }
    }
  }
  echo json_encode($out);
  exit;
}
$espacios = [];
if ($db) {
  try {
    $espacios = $db->query("SELECT * FROM espacios WHERE activo=1 ORDER BY orden ASC")->fetchAll();
  } catch(Exception $e){ error_log($e->getMessage()); }
}

// Static fallback
if (!$espacios) {
  $espacios = [
    ['id'=>1,'slug'=>'salon-principal','nombre'=>'Salón Principal','tipo'=>'salon','resumen'=>'Piso de madera y ventanales en tres lados que dan al parque.','descripcion'=>'El espacio cubierto más grande de La Reserva. Hay colchonetas de yoga, almohadones de meditación y pantalla con proyector. Entra luz natural por tres lados y el parque se ve durante toda la sesión.','capacidad'=>40,'superficie_m2'=>null,'imagen'=>'assets/img/salon.jpg','activo'=>1,'orden'=>10],
    ['id'=>2,'slug'=>'alojamiento','nombre'=>'Alojamiento','tipo'=>'alojamiento','resumen'=>'Habitaciones sobre la galería principal, a pasos del salón y del comedor.','descripcion'=>'El grupo duerme, come y practica en el mismo lugar. Las habitaciones están sobre la galería que recorre todo el edificio principal.','capacidad'=>null,'superficie_m2'=>null,'imagen'=>null,'activo'=>1,'orden'=>20],
    ['id'=>3,'slug'=>'comedor','nombre'=>'Comedor','tipo'=>'gastronomia','resumen'=>'Mesas largas compartidas. Menú adaptado a cada grupo.','descripcion'=>'Comida casera con opciones vegetarianas, veganas y sin gluten, coordinadas de antemano según los horarios del grupo.','capacidad'=>null,'superficie_m2'=>null,'imagen'=>'assets/img/comedor.jpg','activo'=>1,'orden'=>30],
    ['id'=>4,'slug'=>'parque-y-arboleda','nombre'=>'Parque y arboleda','tipo'=>'aire_libre','resumen'=>'Pasto abierto y sectores con sombra para practicar al aire libre.','descripcion'=>'Pasto, árboles y sectores con sombra para clases al aire libre, ceremonias, juegos y descanso. Hay ovejas pastando en el predio.','capacidad'=>null,'superficie_m2'=>null,'imagen'=>null,'activo'=>1,'orden'=>40],
    ['id'=>5,'slug'=>'lago-y-senderos','nombre'=>'Lago y senderos','tipo'=>'aire_libre','resumen'=>'Pasarela de madera sobre el agua y circuitos para caminar.','descripcion'=>'Una pasarela cruza el lago y los senderos se meten entre los árboles. Se usan para caminatas conscientes, trabajo individual en silencio y sesiones de amanecer o atardecer.','capacidad'=>null,'superficie_m2'=>null,'imagen'=>null,'activo'=>1,'orden'=>50],
  ];
}

// Load media per espacio
$media_map = [];
if ($db && $espacios) {
  try {
    $ids = implode(',', array_column($espacios, 'id'));
    /* v3.1: el mismo orden que muestra el panel. Antes agrupaba por tipo,
       asi que reordenar en el panel no cambiaba nada aca y parecia roto. */
    $media = $db->query("SELECT * FROM espacio_media WHERE espacio_id IN($ids) AND activo=1 ORDER BY orden ASC, id ASC")->fetchAll();
    foreach ($media as $m) {
      $media_map[$m['espacio_id']][] = $m;
    }
  } catch(Exception $e){}
}

// Load upcoming retiros per espacio (optional link)
$retiros = [];
if ($db) {
  try {
    $retiros = $db->query("SELECT id,titulo,slug,fecha_inicio,fecha_fin,estado FROM retiros WHERE estado IN('publicado','agotado') AND fecha_inicio>=CURDATE() ORDER BY fecha_inicio ASC LIMIT 10")->fetchAll();
  } catch(Exception $e){}
}

$tipo_labels = ['salon'=>'Salón','alojamiento'=>'Alojamiento','gastronomia'=>'Gastronomía','aire_libre'=>'Aire libre','servicio'=>'Servicio'];
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Espacios · La Reserva Naturaleza Arte</title>
<meta name="description" content="Los espacios de La Reserva: salón, alojamiento, comedor y parque en el mismo predio en Capilla del Señor.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<!-- espacios.php VERSION 3.1 -->
<style>
/* ── Espacio cards ── */
/* v2.8: encabezado de seccion centrado. max-width + margin-inline:auto centra
   el bloque, y text-align:center centra las lineas adentro. Sin el max-width
   el parrafo se estira a todo el ancho y deja de leerse. */
.esp-encabezado{max-width:62ch;margin:0 auto 8px;text-align:center}
.esp-encabezado .esp-rotulo{font-size:.74rem;letter-spacing:.2em;color:#00c9a7;margin-bottom:10px}
.esp-encabezado .titulo-bloque{margin-left:auto;margin-right:auto}
.esp-encabezado .esp-sub{color:#7a9ab8;max-width:52ch;margin:8px auto 0}
.esp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-top:40px}
@media(max-width:700px){.esp-grid{grid-template-columns:1fr}}
.esp-card{background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:10px;overflow:hidden;cursor:pointer;transition:transform .2s,box-shadow .2s}
.esp-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.35)}
.esp-thumb{position:relative;aspect-ratio:16/10;background:#0d1e2c;overflow:hidden}
.esp-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.esp-card:hover .esp-thumb img{transform:scale(1.04)}
.esp-thumb-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.esp-slides{position:relative;width:100%;height:100%}
.esp-slides img,.esp-slides video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .4s}
.esp-slides img.s-active,.esp-slides video.s-active{opacity:1}
.esp-slide-dots{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);display:flex;gap:4px;z-index:2}
.esp-dot{width:6px;height:6px;border-radius:50%;background:rgba(232,240,247,.4);cursor:pointer;transition:.18s}
.esp-dot.active{background:#00c9a7}
.esp-type-badge{position:absolute;top:10px;left:10px;background:rgba(10,22,40,.75);backdrop-filter:blur(6px);color:#00c9a7;font-size:.66rem;letter-spacing:.14em;padding:3px 10px;border-radius:10px;text-transform:uppercase;font-weight:600;z-index:2}
.esp-body{padding:20px 22px 22px}
.esp-name{font-family:'Fraunces',Georgia,serif;font-size:1.25rem;font-weight:300;color:#e8f0f7;margin-bottom:6px}
.esp-summary{font-size:.88rem;color:#7a9ab8;line-height:1.6;margin-bottom:12px}
.esp-meta{display:flex;align-items:center;gap:12px;font-size:.76rem;color:#4a6a85;flex-wrap:wrap}
.esp-meta span{display:flex;align-items:center;gap:4px}
.esp-cta{margin-top:14px;display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#00c9a7;font-weight:500}
.esp-cta:hover{text-decoration:underline}

/* Lightbox */
#esp-lightbox{position:fixed;inset:0;z-index:9000;background:rgba(5,12,25,.97);display:none;align-items:center;justify-content:center;padding:16px}
#esp-lightbox.open{display:flex}
.esp-lb{display:grid;grid-template-columns:1fr 360px;
  /* v2.2: minmax(0,1fr) is the piece that was missing. max-height caps the
     BOX, but a grid row still sizes to its content, so the row grew past the
     box and overflow:hidden simply cut the panel off. Capping the row is what
     forces .lb-info to overflow itself, which is what produces a scrollbar. */
  grid-template-rows:minmax(0,1fr);
  max-width:1080px;width:100%;background:#112233;border-radius:12px;overflow:hidden;
  border:1px solid rgba(0,180,150,.18);height:88vh;max-height:88vh}
@media(max-width:760px){
  .esp-lb{grid-template-columns:1fr;grid-template-rows:auto auto;height:auto;
          overflow-y:auto;max-height:94vh}
  .lb-info{overflow-y:visible;min-height:auto;max-height:none}
  .lb-media{height:52vw;min-height:220px}
}
.lb-media{position:relative;background:#000;display:flex;flex-direction:column;min-height:0}
.lb-slideshow{flex:1;position:relative;min-height:260px}
.lb-slideshow img,.lb-slideshow video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;display:none}
.lb-slideshow img.active,.lb-slideshow video.active{display:block}
.lb-nav-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(10,22,40,.7);border:1px solid rgba(0,201,167,.25);color:#e8f0f7;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;transition:.18s}
.lb-nav-btn:hover{background:#00c9a7;color:#0a1628;border-color:#00c9a7}
.lb-prev{left:10px}.lb-next{right:10px}
.lb-dots{position:absolute;bottom:56px;left:50%;transform:translateX(-50%);display:flex;gap:5px;z-index:2}
.lb-dot{width:7px;height:7px;border-radius:50%;background:rgba(232,240,247,.25);cursor:pointer;transition:.18s}
.lb-dot.active{background:#00c9a7}
.lb-counter{position:absolute;bottom:56px;right:14px;font-size:.7rem;color:rgba(232,240,247,.5);background:rgba(10,22,40,.6);padding:2px 8px;border-radius:10px}
/* v2.6: reproductor con barra de progreso y sonido. */
.vp{position:absolute;left:0;right:0;bottom:0;z-index:4;display:none;
    padding:10px 12px 9px;background:linear-gradient(transparent,rgba(6,14,24,.88))}
.vp.on{display:block}
.vp-barra{height:4px;border-radius:2px;background:rgba(232,240,247,.22);cursor:pointer;position:relative}
.vp-barra:hover{height:6px}
.vp-llena{height:100%;width:0;border-radius:2px;background:#00c9a7;pointer-events:none}
.vp-fila{display:flex;align-items:center;gap:10px;margin-top:7px}
.vp-fila button{background:rgba(10,22,40,.7);border:1px solid rgba(0,201,167,.3);color:#e8f0f7;
  border-radius:14px;padding:3px 11px;font:inherit;font-size:.72rem;cursor:pointer;line-height:1.5}
.vp-fila button:hover{background:#00c9a7;color:#0a1628}
.vp-t{color:rgba(232,240,247,.65);font-size:.7rem;font-variant-numeric:tabular-nums;margin-left:auto}

.lb-info{padding:28px 24px;border-left:1px solid rgba(0,180,150,.15);display:flex;flex-direction:column;
  /* v2.1: min-height:0 is what makes this scroll. A grid item defaults to
     min-height:auto, so the panel grew past the 92vh box and overflow-y
     never engaged. The panel was simply cut off at the bottom. */
  /* Belt and braces: its own cap too, so it scrolls even if the grid row
     sizing misbehaves on an older browser. */
  min-height:0;max-height:88vh;overflow-y:auto;overscroll-behavior:contain}
/* The default scrollbar is invisible on this palette. Make it visible. */
.lb-info::-webkit-scrollbar{width:10px}
.lb-info::-webkit-scrollbar-track{background:rgba(10,22,40,.6);border-radius:5px}
.lb-info::-webkit-scrollbar-thumb{background:rgba(0,201,167,.35);border-radius:5px}
.lb-info::-webkit-scrollbar-thumb:hover{background:rgba(0,201,167,.6)}
.lb-info{scrollbar-width:thin;scrollbar-color:rgba(0,201,167,.35) rgba(10,22,40,.6)}
/* v2.0 lightbox: calendar, enquiry form, WhatsApp */
.ep-rot{font-size:.68rem;letter-spacing:.14em;color:#4a6a85;text-transform:uppercase;margin:22px 0 8px;font-weight:600}
.ep-cal{background:rgba(10,22,40,.5);border:1px solid rgba(0,180,150,.14);border-radius:8px;padding:14px}
.ep-cal-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.ep-cal-h b{color:#e8f0f7;font-size:.86rem;font-weight:600;text-transform:capitalize}
.ep-cal-h button{background:none;border:1px solid rgba(0,180,150,.2);color:#7a9ab8;border-radius:4px;width:26px;height:24px;cursor:pointer;font:inherit;line-height:1}
.ep-cal-h button:hover{border-color:#00c9a7;color:#00c9a7}
.ep-cal-g{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.ep-cal-g span{text-align:center;font-size:.72rem;padding:5px 0;color:#7a9ab8;border-radius:4px}
.ep-cal-g span.wd{color:#4a6a85;font-size:.62rem;letter-spacing:.06em}
.ep-cal-g span.libre{cursor:pointer}
.ep-cal-g span.libre:hover{background:rgba(0,201,167,.14);color:#00c9a7}
.ep-cal-g span.ocupado{background:rgba(255,82,82,.16);color:#ff8a8a}
.ep-cal-g span.pasado{color:#2c4157}
.ep-cal-g span.elegido{background:#00c9a7;color:#0a1628;font-weight:700}
.ep-cal-g span.borde{background:#00c9a7;color:#0a1628;font-weight:700}
.ep-cal-g span.dentro{background:rgba(0,201,167,.22);color:#00c9a7}
.ep-cal-g span.bloqueado{color:#2c4157;cursor:not-allowed;text-decoration:line-through}
.ep-rango{margin-top:8px;padding:8px 11px;border-radius:5px;background:rgba(0,201,167,.08);
  border:1px solid rgba(0,201,167,.2);color:#00c9a7;font-size:.82rem}
.ep-rango.vacio{background:rgba(10,22,40,.5);border-color:rgba(0,180,150,.14);color:#4a6a85}
.ep-rango button{background:none;border:0;color:#7a9ab8;cursor:pointer;font:inherit;font-size:.76rem;
  text-decoration:underline;padding:0;margin-left:8px}
/* Touch targets. 5px of padding is a coin toss with a thumb. */
@media(max-width:760px){
  .ep-cal-g span{padding:10px 0;font-size:.85rem}
  .ep-cal-g span.wd{padding:4px 0;font-size:.66rem}
}
.ep-nota{color:#4a6a85;font-size:.74rem;line-height:1.5;margin-top:8px}
.ep-form label{display:block;font-size:.68rem;letter-spacing:.08em;color:#4a6a85;margin:0 0 4px;text-transform:uppercase}
.ep-form input,.ep-form textarea{width:100%;background:rgba(10,22,40,.6);border:1px solid rgba(0,180,150,.2);border-radius:5px;padding:9px 11px;color:#e8f0f7;font:inherit;font-size:.88rem;margin-bottom:10px}
.ep-form input:focus,.ep-form textarea:focus{outline:none;border-color:#00c9a7}
.ep-form textarea{resize:vertical;min-height:60px}
.ep-g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:520px){.ep-g2{grid-template-columns:1fr}}
.ep-hp{position:absolute;left:-9999px}
.ep-btn{width:100%;background:#00c9a7;color:#0a1628;border:0;border-radius:5px;padding:11px;font:inherit;font-weight:600;cursor:pointer}
.ep-btn[disabled]{opacity:.55;cursor:default}
.ep-wa{display:block;text-align:center;margin-top:10px;padding:11px;border-radius:5px;border:1px solid rgba(0,201,167,.35);color:#00c9a7;text-decoration:none;font-size:.9rem}
.ep-wa:hover{background:rgba(0,201,167,.1)}
.ep-wa small{display:block;color:#4a6a85;font-size:.72rem;margin-top:2px}
.ep-msg{border-radius:5px;padding:11px;font-size:.86rem;margin-bottom:10px;display:none}
.ep-msg.ok{display:block;background:rgba(0,201,167,.1);border:1px solid rgba(0,201,167,.3);color:#00c9a7}
.ep-msg.err{display:block;background:rgba(255,82,82,.1);border:1px solid rgba(255,82,82,.3);color:#ff5252}
.lb-close{position:absolute;top:12px;right:12px;background:rgba(10,22,40,.8);border:1px solid rgba(0,180,150,.2);color:#e8f0f7;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;font-size:.9rem;transition:.18s}
.lb-close:hover{background:#ff5252;border-color:#ff5252}
/* v1.2: under the nav breakpoint the hamburger sits in the top right corner and
   swallows the tap. Move the close button to the left side there. */
@media(max-width:899px){.lb-close{right:auto;left:12px}}
.lb-tag{font-size:.68rem;letter-spacing:.16em;color:#00c9a7;margin-bottom:8px;text-transform:uppercase;font-weight:600}
.lb-name{font-family:'Fraunces',Georgia,serif;font-size:1.5rem;font-weight:300;color:#e8f0f7;margin-bottom:10px}
.lb-desc{font-size:.89rem;color:#7a9ab8;line-height:1.72;margin-bottom:18px}
.lb-cap{font-size:.8rem;color:#4a6a85;margin-bottom:20px}
.lb-retiros{margin-top:auto;border-top:1px solid rgba(0,180,150,.12);padding-top:16px}
.lb-retiros-title{font-size:.68rem;letter-spacing:.14em;color:#4a6a85;margin-bottom:10px;text-transform:uppercase}
.lb-retiro-link{display:block;font-size:.82rem;color:#7a9ab8;padding:7px 0;border-bottom:1px solid rgba(0,180,150,.08);transition:.15s}
.lb-retiro-link:hover{color:#00c9a7}
.lb-ig{display:flex;align-items:center;gap:7px;margin-top:14px;padding:10px 14px;background:linear-gradient(135deg,rgba(240,148,51,.15),rgba(188,24,136,.15));border:1px solid rgba(240,148,51,.2);border-radius:6px;color:#f09433;font-size:.82rem;font-weight:500;text-decoration:none;transition:.18s}
.lb-ig:hover{background:linear-gradient(135deg,rgba(240,148,51,.25),rgba(188,24,136,.25))}
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
<!-- v2.6: the nav is position:fixed, so a flex-end hero on a short viewport
     puts its first line straight under it. padding-top is the clearance. -->
<section class="portada" style="min-height:62vh;padding-top:110px">
  <video autoplay muted loop playsinline preload="metadata" poster="assets/video/pages/espacios-poster.jpg" aria-hidden="true">
    <source src="assets/video/pages/espacios.mp4" type="video/mp4">
  </video>
  <div class="velo"></div>
  <div class="texto" style="padding-bottom:6vh">
    <div class="interior">
      <p class="lugar">LOS ESPACIOS DE LA RESERVA</p>
      <h1 style="font-size:clamp(2.4rem,5vw,4rem)">Los espacios</h1>
      <p style="max-width:46ch;color:rgba(232,240,247,.72);margin-top:12px">Todo sucede dentro del mismo predio. Una misma propuesta puede cambiar de ritmo a lo largo del día.</p>
    </div>
  </div>
</section>

<!-- Grid -->
<section class="bloque" style="padding-top:56px;background:var(--bg2,#0d1e2c)">
  <div class="envoltura">
    <!-- v2.8: encabezado centrado, igual que el hero. El contador sale de la
         base: decia "Cinco" fijo y ahora hay ocho espacios cargados. -->
    <div class="esp-encabezado">
      <p class="esp-rotulo">INSTALACIONES</p>
      <?php
        /* "1 espacios" no es castellano. Numero en palabras hasta doce, con
           singular y plural. Arriba de doce vuelve a la cifra, que a esa
           altura se lee mejor que "veintitres". */
        $n = count($espacios);
        $palabras = [1=>'Un',2=>'Dos',3=>'Tres',4=>'Cuatro',5=>'Cinco',6=>'Seis',
                     7=>'Siete',8=>'Ocho',9=>'Nueve',10=>'Diez',11=>'Once',12=>'Doce'];
        $cuantos = $palabras[$n] ?? $n;
        $sustantivo = $n === 1 ? 'espacio' : 'espacios';
      ?>
      <h2 class="titulo-bloque"><?= e($cuantos) ?> <?= $sustantivo ?>, un mismo predio</h2>
      <p class="esp-sub">No hay traslados entre actividades. El grupo duerme, come y practica en el mismo lugar.</p>
    </div>

    <div class="esp-grid">
      <?php foreach($espacios as $esp):
        $media = $media_map[$esp['id']] ?? [];
        $imgs  = array_filter($media, function($m){ return $m['tipo']==='imagen'; });
        $vids  = array_filter($media, function($m){ return $m['tipo']==='video'; });
        // v1.1: images first so the card shows a still by default. Video is
        // revealed and played only while the pointer is over the card.
        $all_media = array_merge(array_values($imgs), array_values($vids));
        $poster_card = $esp['imagen'] ?: '';
        if (!$poster_card) { $first_img = reset($imgs); $poster_card = $first_img ? $first_img['archivo'] : ''; }
        $icon  = ['salon'=>'🏛','alojamiento'=>'🛏','gastronomia'=>'🍽','aire_libre'=>'🌿','servicio'=>'⚙'][$esp['tipo']] ?? '📍';
        $tipo_label = $tipo_labels[$esp['tipo']] ?? $esp['tipo'];
      ?>
      <div class="esp-card" id="<?= e($esp['slug']) ?>" onclick="openEsp(<?= (int)$esp['id'] ?>)">
        <div class="esp-thumb">
          <span class="esp-type-badge"><?= e($tipo_label) ?></span>

          <?php if($all_media): ?>
            <div class="esp-slides" id="thumb-<?= $esp['id'] ?>">
              <?php foreach($all_media as $mi => $m): ?>
                <?php if($m['tipo']==='video'): ?>
                  <video class="<?= $mi===0?'s-active':'' ?>" muted loop playsinline preload="metadata"
                         <?php if($poster_card): ?>poster="<?= e($poster_card) ?>"<?php endif; ?>>
                    <source src="<?= e($m['archivo']) ?>" type="video/mp4">
                  </video>
                <?php else: ?>
                  <img class="<?= $mi===0?'s-active':'' ?>" src="<?= e($m['archivo']) ?>" alt="<?= e($m['titulo']??$esp['nombre']) ?>" loading="lazy">
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if(count($all_media)>1): ?>
              <div class="esp-slide-dots">
                <?php for($d=0;$d<count($all_media);$d++): ?>
                  <div class="esp-dot<?= $d===0?' active':'' ?>" onclick="event.stopPropagation();thumbSlide(<?= $esp['id'] ?>,<?= $d ?>)"></div>
                <?php endfor; ?>
              </div>
              <?php endif; ?>
            </div>
          <?php elseif($esp['imagen']): ?>
            <img src="<?= e($esp['imagen']) ?>" alt="<?= e($esp['nombre']) ?>" loading="lazy">
          <?php else: ?>
            <div class="esp-thumb-placeholder"><?= $icon ?></div>
          <?php endif; ?>
        </div>

        <div class="esp-body">
          <h3 class="esp-name"><?= e($esp['nombre']) ?></h3>
          <p class="esp-summary"><?= e($esp['resumen']??'') ?></p>
          <div class="esp-meta">
            <?php if($esp['capacidad']): ?><span>👥 <?= (int)$esp['capacidad'] ?> personas</span><?php endif; ?>
            <?php if($esp['superficie_m2']): ?><span>📐 <?= (int)$esp['superficie_m2'] ?> m²</span><?php endif; ?>
          </div>
          <div class="esp-cta">Ver detalles →</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- CTA -->
    <div style="margin-top:56px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;background:var(--card,#112233);border:1px solid rgba(0,180,150,.15);border-radius:10px;padding:32px;flex-wrap:wrap">
      <div>
        <p style="font-size:.72rem;letter-spacing:.16em;color:#00c9a7;margin-bottom:8px">VISITAS</p>
        <h3 style="font-family:'Fraunces',serif;font-size:1.4rem;font-weight:300;color:#e8f0f7;margin-bottom:6px">Coordinemos una visita</h3>
        <p style="color:rgba(232,240,247,.72);font-size:.9rem;max-width:44ch">Conocer el lugar antes de definir la fecha ayuda a armar mejor el programa del retiro.</p>
      </div>
      <a href="contacto.php" class="boton" style="white-space:nowrap;flex-shrink:0">Consultar disponibilidad</a>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>

<!-- Lightbox -->
<div id="esp-lightbox" onclick="if(event.target===this)closeEsp()">
  <div style="position:relative;width:100%;max-width:1080px">
    <button class="lb-close" onclick="closeEsp()">✕</button>
    <div class="esp-lb">
      <div class="lb-media">
        <div class="lb-slideshow" id="lbSlides"></div>
        <div class="lb-dots" id="lbDots"></div>
        <div class="lb-counter" id="lbCtr"></div>
        <button class="lb-nav-btn lb-prev" id="lbPrev" onclick="lbNav(-1)" style="display:none">&#8592;</button>
        <button class="lb-nav-btn lb-next" id="lbNext" onclick="lbNav(1)" style="display:none">&#8594;</button>
      </div>
      <div class="lb-info">
        <p class="lb-tag" id="lbTag"></p>
        <h2 class="lb-name" id="lbName"></h2>
        <p class="lb-desc" id="lbDesc"></p>
        <p class="lb-cap" id="lbCap"></p>
        <div class="lb-retiros" id="lbRetiros" style="display:none">
          <p class="lb-retiros-title">Próximos retiros en este espacio</p>
          <div id="lbRetirosList"></div>
        </div>
        <div class="ep-rot">Fechas ocupadas en el predio</div>
        <div id="lbCal"></div>
        <div class="ep-rango" id="epRango">Toca el primer dia y despues el ultimo.</div>
        <p class="ep-nota">Los dias en rojo ya tienen un retiro en el predio. Elegi el primer dia y
        despues el ultimo para marcar un rango, o dejalo en blanco si todavia no lo definiste.</p>

        <div class="ep-rot">Consultar por este espacio</div>
        <div class="ep-msg" id="epMsg"></div>
        <form class="ep-form" id="epForm" onsubmit="return epSend(event)">
          <input type="hidden" name="accion" value="consulta_espacio">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="espacio_id" id="epId" value="">
          <input type="hidden" name="boton" value="lr">
          <input type="hidden" name="fecha_tentativa" id="epFecha" value="">
          <input type="hidden" name="fecha_fin" id="epFechaFin" value="">
          <input class="ep-hp" type="text" name="apodo" tabindex="-1" autocomplete="off">
          <div class="ep-g2">
            <div><label>Nombre</label><input type="text" name="nombre" required></div>
            <div><label>E-mail</label><input type="email" name="email" required></div>
          </div>
          <div class="ep-g2">
            <div><label>Telefono</label><input type="text" name="telefono"></div>
            <div><label>Personas</label><input type="number" name="personas" min="1" max="300"></div>
          </div>
          <label>Mensaje</label><textarea name="mensaje" placeholder="Que tenes en mente"></textarea>
          <button type="submit" class="ep-btn" id="epBtn">Solicitar fecha</button>
        </form>

        <a class="ep-wa" id="epWa" href="#" target="_blank" rel="noopener">
          Consultar por WhatsApp<small><?= e(SITE_WA_LABEL) ?></small>
        </a>

        <a href="https://www.instagram.com/<?= e(SITE_IG) ?>/" target="_blank" rel="noopener" class="lb-ig"
           onclick="epTrack('click_ig', null, 'espacios', 'johnny')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          Ver más en @<?= e(SITE_IG) ?>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
var espData = <?= json_encode(array_values(array_map(function($esp) use ($media_map,$tipo_labels,$retiros) {
  $media = $media_map[$esp['id']] ?? [];
  $imgs = array_values(array_filter($media, function($m){ return $m['tipo']==='imagen'; }));
  $vids = array_values(array_filter($media, function($m){ return $m['tipo']==='video'; }));
  $all = array_merge($vids,$imgs);
  if(!$all && $esp['imagen']) $all=[['tipo'=>'imagen','archivo'=>$esp['imagen'],'titulo'=>$esp['nombre']]];
  $icon = ['salon'=>'🏛','alojamiento'=>'🛏','gastronomia'=>'🍽','aire_libre'=>'🌿','servicio'=>'⚙'][$esp['tipo']] ?? '📍';
  return [
    'id'       => (int)$esp['id'],
    'slug'     => $esp['slug'],
    'nombre'   => $esp['nombre'],
    'tipo'     => $tipo_labels[$esp['tipo']] ?? $esp['tipo'],
    'icon'     => $icon,
    'resumen'  => $esp['resumen'] ?? '',
    'desc'     => $esp['descripcion'] ?? '',
    'cap'      => $esp['capacidad'] ? 'Hasta '.$esp['capacidad'].' personas' : '',
    'media'    => array_map(function($m){ return ['tipo'=>$m['tipo'],'src'=>$m['archivo'],'title'=>$m['titulo']??'']; }, $all),
    /* NOTE: the retiros table has no espacio_id column, so this is every
       upcoming retiro on the property, not the ones booked into this space.
       The calendar below is labelled accordingly. */
    'retiros'  => array_values($retiros),
  ];
}, $espacios))) ?>;

var lbCur = 0;
var lbMedia = [];

var EP_WA = <?= json_encode(preg_replace('/\D/','',SITE_WA)) ?>;
var EP_MESES = ['enero','febrero','marzo','abril','mayo','junio','julio',
                'agosto','septiembre','octubre','noviembre','diciembre'];
var EP_DIAS = ['L','M','M','J','V','S','D'];
var epOcupados = {}, epMes = null, epActual = null;
var epDesde = null, epHasta = null;   // 'YYYY-MM-DD' or null

/* Fire-and-forget beacon. Survives the page being left for wa.me. */
function epTrack(tipo, id, detalle, boton){
  var body = JSON.stringify({tipo: tipo, retiro_id: null, detalle: detalle || null, boton: boton || null});
  try { if (navigator.sendBeacon) { navigator.sendBeacon('track.php', new Blob([body], {type:'application/json'})); return; } } catch(e){}
  try { fetch('track.php', {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true}); } catch(e){}
}

function epKey(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
function epParse(s){ var p=String(s||'').split('-'); return p.length===3 ? new Date(+p[0],+p[1]-1,+p[2]) : null; }

/* Every day covered by an upcoming retiro, property-wide. */
function epMarcarOcupados(retiros){
  epOcupados = {};
  (retiros||[]).forEach(function(r){
    var a = epParse(r.fecha_inicio), b = epParse(r.fecha_fin) || a;
    if(!a) return;
    if(b < a) b = a;
    for(var d = new Date(a); d <= b; d.setDate(d.getDate()+1)) epOcupados[epKey(d)] = r.titulo;
  });
}

function epRenderCal(){
  var box = document.getElementById('lbCal');
  if(!box || !epMes) return;
  var y = epMes.getFullYear(), m = epMes.getMonth();
  var lead = (new Date(y,m,1).getDay() + 6) % 7;
  var dias = new Date(y,m+1,0).getDate();
  var hoy = new Date(); hoy.setHours(0,0,0,0);

  var h = '<div class="ep-cal"><div class="ep-cal-h">'
        + '<button type="button" onclick="epMoverMes(-1)">&#8592;</button>'
        + '<b>' + EP_MESES[m] + ' ' + y + '</b>'
        + '<button type="button" onclick="epMoverMes(1)">&#8594;</button>'
        + '</div><div class="ep-cal-g">';
  for(var w=0; w<7; w++) h += '<span class="wd">' + EP_DIAS[w] + '</span>';
  for(var b=0; b<lead; b++) h += '<span></span>';
  for(var d=1; d<=dias; d++){
    var fecha = new Date(y,m,d), k = epKey(fecha), cls, tit = '';
    if(fecha < hoy)                        cls = 'pasado';
    else if(epOcupados[k])               { cls = 'ocupado'; tit = ' title="' + epOcupados[k].replace(/"/g,'') + '"'; }
    else if(k === epDesde || k === epHasta) cls = 'borde';
    else if(epDentro(k))                   cls = 'dentro';
    /* While picking the end day, anything before the start or past the next
       occupied day is unreachable, so show it as such rather than letting
       someone select a range with a booked retiro inside it. */
    else if(epDesde && !epHasta && !epAlcanzable(k)) cls = 'bloqueado';
    else                                   cls = 'libre';
    var click = (cls === 'libre' || cls === 'borde' || cls === 'dentro')
              ? ' onclick="epElegir(\'' + k + '\')"' : '';
    h += '<span class="' + cls + '"' + click + tit + '>' + d + '</span>';
  }
  box.innerHTML = h + '</div></div>';
}
function epMoverMes(n){ epMes = new Date(epMes.getFullYear(), epMes.getMonth()+n, 1); epRenderCal(); }

function epDentro(k){
  if(!epDesde || !epHasta) return false;
  return k > epDesde && k < epHasta;
}
/* True when every day from the start up to k is free. Stops a range being
   drawn straight through a week that already has a retiro in it. */
function epAlcanzable(k){
  if(!epDesde) return true;
  if(k < epDesde) return false;
  var d = epParse(epDesde), fin = epParse(k);
  for(; d <= fin; d.setDate(d.getDate()+1)){
    if(epOcupados[epKey(d)]) return false;
  }
  return true;
}

function epElegir(k){
  if(!epDesde || epHasta){          // start a fresh range
    epDesde = k; epHasta = null;
  } else if(k === epDesde){         // tapping the start again clears it
    epDesde = null;
  } else if(k < epDesde){           // picked backwards, treat as new start
    epDesde = k;
  } else if(epAlcanzable(k)){
    epHasta = k;
  } else {
    return;                         // a booked day sits inside the range
  }
  epSyncRango();
  epRenderCal();
}

function epLimpiarRango(){ epDesde = null; epHasta = null; epSyncRango(); epRenderCal(); }

function epTextoFecha(k){
  var d = epParse(k);
  return d ? d.getDate() + ' de ' + EP_MESES[d.getMonth()] : '';
}
function epSyncRango(){
  document.getElementById('epFecha').value    = epDesde || '';
  document.getElementById('epFechaFin').value = epHasta || '';
  var box = document.getElementById('epRango');
  if(!box) return;
  if(!epDesde){
    box.className = 'ep-rango vacio';
    box.textContent = 'Toca el primer dia y despues el ultimo.';
  } else if(!epHasta){
    box.className = 'ep-rango';
    box.innerHTML = 'Desde el <b>' + epTextoFecha(epDesde) + '</b>. Ahora toca el ultimo dia.'
                  + '<button type="button" onclick="epLimpiarRango()">borrar</button>';
  } else {
    var d1 = epParse(epDesde), d2 = epParse(epHasta);
    var noches = Math.round((d2 - d1) / 86400000);
    box.className = 'ep-rango';
    box.innerHTML = 'Del <b>' + epTextoFecha(epDesde) + '</b> al <b>' + epTextoFecha(epHasta) + '</b>'
                  + ' (' + noches + (noches === 1 ? ' noche' : ' noches') + ')'
                  + '<button type="button" onclick="epLimpiarRango()">borrar</button>';
  }
}

function epSend(ev){
  ev.preventDefault();
  var form = document.getElementById('epForm');
  var btn  = document.getElementById('epBtn');
  var msg  = document.getElementById('epMsg');
  epTrack('click_rsvp', null, epActual ? epActual.nombre : null, 'lr');
  btn.disabled = true; btn.textContent = 'Enviando...';
  fetch('espacios.php', {method:'POST', body: new FormData(form)})
    .then(function(r){ return r.json(); })
    .then(function(j){
      msg.className = 'ep-msg ' + (j.ok ? 'ok' : 'err');
      msg.textContent = j.msg;
      if(j.ok){ form.style.display = 'none'; }
      else { btn.disabled = false; btn.textContent = 'Solicitar fecha'; }
    })
    .catch(function(){
      msg.className = 'ep-msg err';
      msg.textContent = 'No hay conexion. Proba por WhatsApp.';
      btn.disabled = false; btn.textContent = 'Solicitar fecha';
    });
  return false;
}

/* v2.9: llegar con #slug abre el visor de ese espacio directamente.
   Es lo que usan los modulos de la portada: index.php enlaza a
   espacios.php#salon-principal y aca se abre solo. */
function epPorSlug(){
  var slug = (location.hash || '').replace('#','').trim();
  if(!slug) return;
  var d = espData.find(function(x){ return x.slug === slug; });
  if(d){
    var card = document.getElementById(slug);
    if(card) card.scrollIntoView({block:'center'});
    openEsp(d.id);
  }
}
window.addEventListener('DOMContentLoaded', epPorSlug);
window.addEventListener('hashchange', epPorSlug);

function openEsp(id) {
  var d = espData.find(function(e){ return e.id===id; });
  if(!d) return;
  lbMedia = d.media;
  lbCur = 0;
  epActual = d;
  epTrack('abrir_espacio', null, d.nombre);

  /* reset the form between spaces */
  var form = document.getElementById('epForm');
  if(form){ form.style.display=''; form.reset(); }
  var btn = document.getElementById('epBtn');
  if(btn){ btn.disabled=false; btn.textContent='Solicitar fecha'; }
  var msg = document.getElementById('epMsg');
  if(msg){ msg.className='ep-msg'; msg.textContent=''; }
  document.getElementById('epId').value = d.id;
  epDesde = null; epHasta = null;
  epSyncRango();
  epMarcarOcupados(d.retiros);
  epMes = new Date(); epMes.setDate(1);
  epRenderCal();

  var wa = document.getElementById('epWa');
  if(wa){
    wa.href = 'https://wa.me/' + EP_WA + '?text=' +
      encodeURIComponent('Hola, quiero consultar por el espacio ' + d.nombre + ' en La Reserva.');
    wa.onclick = function(){ epTrack('click_wa', null, d.nombre, 'whatsapp'); };
  }

  document.getElementById('lbTag').textContent = d.icon+' '+d.tipo;
  document.getElementById('lbName').textContent = d.nombre;
  document.getElementById('lbDesc').textContent = d.desc;
  document.getElementById('lbCap').textContent = d.cap;

  // Slides
  var slides = document.getElementById('lbSlides');
  var dots   = document.getElementById('lbDots');
  slides.innerHTML = '';
  dots.innerHTML   = '';
  lbMedia.forEach(function(m,i){
    var el;
    if(m.tipo==='video'){
      el = document.createElement('video');
      el.muted=true; el.loop=true; el.playsInline=true; el.preload='metadata'; el.controls=true;
      var src=document.createElement('source'); src.src=m.src; src.type='video/mp4';
      el.appendChild(src);
    } else {
      el = document.createElement('img');
      el.src=m.src; el.alt=m.title;
    }
    if(i===0) el.classList.add('active');
    slides.appendChild(el);
    var dot=document.createElement('div');
    dot.className='lb-dot'+(i===0?' active':'');
    dot.onclick=function(){ lbGo(i); };
    dots.appendChild(dot);
  });

  var hasMulti = lbMedia.length > 1;
  document.getElementById('lbPrev').style.display = hasMulti?'flex':'none';
  document.getElementById('lbNext').style.display = hasMulti?'flex':'none';
  document.getElementById('lbCtr').textContent    = lbMedia.length>1?'1 / '+lbMedia.length:'';

  // Retiros
  var rBox = document.getElementById('lbRetiros');
  var rList = document.getElementById('lbRetirosList');
  if(d.retiros && d.retiros.length){
    rBox.style.display='block';
    rList.innerHTML = d.retiros.slice(0,4).map(function(r){
      return '<a class="lb-retiro-link" href="retiro.php?r='+encodeURIComponent(r.slug)+'">'+r.titulo+'</a>';
    }).join('');
  } else {
    rBox.style.display='none';
  }

  document.getElementById('esp-lightbox').classList.add('open');
  document.body.style.overflow='hidden';
  var v0 = slides.querySelector('video.active');
  if(v0){ v0.muted = true; var p0 = v0.play(); if(p0 && p0.catch) p0.catch(function(){}); lrPlayer(slides.parentNode, v0); }
  else lrPlayerOff(slides.parentNode);
}


/* ── Reproductor: autoplay, barra de progreso y sonido ──────────────────
   Autoplay solo funciona en silencio: todos los navegadores bloquean el
   audio sin un gesto del usuario. Por eso arranca muted y el boton de
   sonido es la puerta de entrada, no un extra. */
function lrPlayer(box, video){
  if(!video || !box) return;
  var vp = box.querySelector('.vp');
  if(!vp){
    vp = document.createElement('div');
    vp.className = 'vp';
    vp.innerHTML = '<div class="vp-barra"><div class="vp-llena"></div></div>'
                 + '<div class="vp-fila"><button data-a="pp">Pausa</button>'
                 + '<button data-a="son">Sonido</button><span class="vp-t">0:00</span></div>';
    box.appendChild(vp);
  }
  var barra = vp.querySelector('.vp-barra'),
      llena = vp.querySelector('.vp-llena'),
      bpp   = vp.querySelector('[data-a="pp"]'),
      bson  = vp.querySelector('[data-a="son"]'),
      reloj = vp.querySelector('.vp-t');
  function mmss(x){ x = Math.max(0, Math.floor(x||0)); return Math.floor(x/60)+':'+('0'+(x%60)).slice(-2); }
  function pintar(){
    if(!video.duration) return;
    llena.style.width = (video.currentTime / video.duration * 100) + '%';
    reloj.textContent = mmss(video.currentTime) + ' / ' + mmss(video.duration);
  }
  video.ontimeupdate = pintar;
  video.onloadedmetadata = pintar;
  video.onplay  = function(){ bpp.textContent = 'Pausa'; };
  video.onpause = function(){ bpp.textContent = 'Reproducir'; };
  bpp.onclick = function(e){
    e.stopPropagation();
    if(video.paused){ var p = video.play(); if(p && p.catch) p.catch(function(){}); }
    else video.pause();
  };
  bson.onclick = function(e){
    e.stopPropagation();
    video.muted = !video.muted;
    bson.textContent = video.muted ? 'Sonido' : 'Silenciar';
    if(!video.muted && video.paused){ var p = video.play(); if(p && p.catch) p.catch(function(){}); }
  };
  barra.onclick = function(e){
    e.stopPropagation();
    if(!video.duration) return;
    var r = barra.getBoundingClientRect();
    video.currentTime = ((e.clientX - r.left) / r.width) * video.duration;
    pintar();
  };
  bson.textContent = video.muted ? 'Sonido' : 'Silenciar';
  vp.classList.add('on');
  pintar();
}
function lrPlayerOff(box){
  var vp = box && box.querySelector('.vp');
  if(vp) vp.classList.remove('on');
}

function lbGo(n){
  var els  = document.getElementById('lbSlides').children;
  var dots = document.getElementById('lbDots').children;
  if(els[lbCur]) els[lbCur].classList.remove('active');
  if(dots[lbCur]) dots[lbCur].classList.remove('active');
  if(els[lbCur] && els[lbCur].tagName==='VIDEO') els[lbCur].pause();
  lbCur = (n+lbMedia.length)%lbMedia.length;
  if(els[lbCur]) els[lbCur].classList.add('active');
  if(dots[lbCur]) dots[lbCur].classList.add('active');
  var caja = document.getElementById('lbSlides').parentNode;
  if(els[lbCur] && els[lbCur].tagName==='VIDEO'){
    els[lbCur].muted = true;
    var pv = els[lbCur].play(); if(pv && pv.catch) pv.catch(function(){});
    lrPlayer(caja, els[lbCur]);
  } else {
    lrPlayerOff(caja);
  }
  document.getElementById('lbCtr').textContent = (lbCur+1)+' / '+lbMedia.length;
}
function lbNav(d){ lbGo(lbCur+d); }

function closeEsp(){
  document.getElementById('esp-lightbox').classList.remove('open');
  document.body.style.overflow='';
  document.querySelectorAll('#lbSlides video').forEach(function(v){ v.pause(); });
}

// Card thumbnail auto-slide
function thumbSlide(id,idx){
  var c=document.getElementById('thumb-'+id);
  if(!c) return;
  var items=c.querySelectorAll('img,video');
  var dots=c.querySelectorAll('.esp-dot');
  items.forEach(function(el,i){ el.classList.toggle('s-active',i===idx); });
  dots.forEach(function(d,i){ d.classList.toggle('active',i===idx); });
}

/* v1.1: card shows the photo by default. Hovering swaps to the video and plays it,
   leaving pauses it, rewinds it and swaps back to the photo. */
document.querySelectorAll('.esp-card').forEach(function(card){
  var box = card.querySelector('.esp-slides');
  if(!box) return;
  var vid = box.querySelector('video');
  if(!vid) return;
  var items = box.querySelectorAll('img,video');
  var vidIdx = Array.prototype.indexOf.call(items, vid);
  var id = box.id.replace('thumb-','');

  if (window.matchMedia('(hover: none)').matches) {
    /* v3.0: en celular no hay hover, asi que la tarjeta se quedaba en la foto
       quieta para siempre. Aca el video arranca cuando la tarjeta entra en
       pantalla al scrollear, y se pausa al salir. Es lo unico que se puede
       hacer sin un gesto del usuario, y va en silencio porque el audio sin
       gesto lo bloquean todos los navegadores. */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function(entradas){
        entradas.forEach(function(en){
          if (en.isIntersecting) {
            thumbSlide(id, vidIdx);
            var pr = vid.play(); if(pr && pr.catch) pr.catch(function(){});
          } else {
            vid.pause();
          }
        });
      }, {threshold: 0.5}).observe(card);
    } else {
      thumbSlide(id, vidIdx);
      var pr0 = vid.play(); if(pr0 && pr0.catch) pr0.catch(function(){});
    }
    return;
  }

  card.addEventListener('mouseenter', function(){
    thumbSlide(id, vidIdx);
    var pr = vid.play();
    if(pr && pr.catch) pr.catch(function(){});
  });
  card.addEventListener('mouseleave', function(){
    vid.pause();
    try { vid.currentTime = 0; } catch(err) {}
    thumbSlide(id, 0);
  });
});

document.addEventListener('keydown',function(e){
  if(!document.getElementById('esp-lightbox').classList.contains('open')) return;
  if(e.key==='Escape') closeEsp();
  if(e.key==='ArrowLeft') lbNav(-1);
  if(e.key==='ArrowRight') lbNav(1);
});
</script>
</body>
</html>
