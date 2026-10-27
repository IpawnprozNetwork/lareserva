<?php
/* retiros.php - VERSION 3.0
   2.0: clicking an agenda row opens a lightbox with the same shape as the
   espacios popup: media carousel on the left, detail panel on the right.
   The panel carries a month calendar with the retiro dates marked, an RSVP
   form that writes to the consultas table, and a WhatsApp button.
   2.2: IP blocking, per-IP rate limiting, disposable-email rejection, and
   click tracking for the lightbox, the RSVP button and the WhatsApp button.
   Needs includes/antispam.php, track.php and tracking_tables.sql.
   2.3: lightbox close button moves to the left on mobile.
   2.4: the calendar is labelled as informational. It shows the retiro's own
   dates and is deliberately not a picker, because you book the encounter and
   not individual nights. Day cells are thumb-sized under 760px.
   2.5: every button carries its own tag (lr, whatsapp), stored in the new
   boton column on eventos and consultas. This is separate from ref, which
   keeps recording where the visitor came from. Needs boton_columns.sql.
   The row is still a real link to retiro.php, so no-JS and crawlers still work. */
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/antispam.php';
require_once __DIR__.'/includes/ajustes.php';
$pagina='retiros'; $raiz='';

$db  = getDB();
$ref = lrCapturarRef();

/* ── RSVP endpoint. Answers the lightbox form with JSON and stops. ───────── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '')==='rsvp') {
  header('Content-Type: application/json; charset=utf-8');
  $out = ['ok'=>false, 'msg'=>'No se pudo enviar la solicitud.'];

  if (!empty($_POST['apodo'])) {                       // honeypot
    $out = ['ok'=>true, 'msg'=>'Recibimos tu solicitud.'];
  } elseif (!csrfOk($_POST['csrf'] ?? null)) {
    $out['msg'] = 'La sesion expiro. Recargá la página e intentá de nuevo.';
  } elseif (!$db) {
    $out['msg'] = 'La base de datos no responde en este momento.';
  } elseif (lrIpBloqueada($db, lrIp())) {
    /* Silently generic. Telling a spammer exactly why it failed just tells
       them what to change. */
    $out['msg'] = 'No pudimos procesar la solicitud. Escribinos por WhatsApp.';
  } elseif (!lrRitmoOk($db, lrIp(), 'rsvp_ok')) {
    $out['msg'] = 'Ya enviaste varias solicitudes. Esperá un rato o escribinos por WhatsApp.';
  } else {
    $nombre = limpiar($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $rid    = (int)($_POST['retiro_id'] ?? 0);
    $motivo = null;

    if ($nombre === '') {
      $out['msg'] = 'Falta el nombre.';
    } elseif (!lrEmailAceptable($email, $motivo)) {
      $out['msg'] = lrMotivoTexto($motivo);
      lrEvento($db, 'rsvp_rechazado', ['retiro_id'=>$rid, 'detalle'=>'email:'.$motivo]);
    } else {
      $titulo_r = ''; $slug_r = ''; $fecha_r = null;
      $q = $db->prepare("SELECT titulo,slug,fecha_inicio FROM retiros WHERE id=:i LIMIT 1");
      $q->execute([':i'=>$rid]);
      if ($rr = $q->fetch()) { $titulo_r=$rr['titulo']; $slug_r=$rr['slug']; $fecha_r=$rr['fecha_inicio']; }

      $fecha_post = $_POST['fecha_tentativa'] ?? '';
      $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_post) ? $fecha_post : $fecha_r;

      $mensaje = limpiar($_POST['mensaje'] ?? '');
      $mensaje = 'Reserva para: '.($titulo_r ?: 'retiro sin identificar').($mensaje ? "\n".$mensaje : '');

      try {
        /* 2.1: the consultas table already carries an indexed retiro_id column.
           Fill it so an RSVP is linked to its retiro properly instead of only
           by the text prefix in mensaje. */
        $db->prepare(
          "INSERT INTO consultas
           (nombre,email,telefono,tipo,fecha_tentativa,personas,mensaje,origen,retiro_id,ref,boton,ip,ip_txt,user_agent)
           VALUES (:nombre,:email,:telefono,:tipo,:fecha_tentativa,:personas,:mensaje,:origen,:retiro_id,:ref,:boton,:ip,:ip_txt,:ua)"
        )->execute([
          'boton'           => substr(preg_replace('/[^a-z0-9_-]/','',strtolower($_POST['boton'] ?? 'lr')), 0, 24) ?: 'lr',
          'retiro_id'       => $rid ?: null,
          'ref'             => $_SESSION['lr_ref'] ?? null,
          'ip_txt'          => lrIp() ?: null,
          'nombre'          => $nombre,
          'email'           => $email,
          'telefono'        => limpiar($_POST['telefono'] ?? '') ?: null,
          'tipo'            => 'participar',
          'fecha_tentativa' => $fecha,
          'personas'        => !empty($_POST['personas']) ? max(1, min(500, (int)$_POST['personas'])) : null,
          'mensaje'         => $mensaje,
          'origen'          => substr('retiro:'.($slug_r ?: $rid), 0, 60),
          'ip'              => @inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: null,
          'ua'              => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        lrEvento($db, 'rsvp_ok', ['retiro_id'=>$rid, 'detalle'=>$titulo_r, 'boton'=>'lr']);
        /* v2.9: aviso por mail, rearmado porque el INSERT va en linea. */
        lrAvisarConsulta([
          'nombre'          => $nombre,
          'email'           => $email,
          'telefono'        => limpiar($_POST['telefono'] ?? '') ?: null,
          'personas'        => !empty($_POST['personas']) ? (int)$_POST['personas'] : null,
          'fecha_tentativa' => $fecha,
          'mensaje'         => $mensaje,
          'origen'          => 'retiro:' . ($titulo_r ?: $rid),
          'ref'             => $_SESSION['lr_ref'] ?? 'directo',
        ]);
        $out = ['ok'=>true, 'msg'=>'Listo. Te escribimos para confirmar tu lugar.'];
      } catch (Exception $ex) {
        error_log('[LR rsvp] '.$ex->getMessage());
        $out['msg'] = 'No pudimos guardar la solicitud. Escribinos por WhatsApp.';
      }
    }
  }
  echo json_encode($out);
  exit;
}

/* ── Data ────────────────────────────────────────────────────────────────── */
$proximos=[]; $pasados=[]; $media_map=[]; $ocupados=[];
if ($db) {
  try {
    $proximos = $db->query(
      "SELECT id,slug,titulo,bajada,facilitador,facilitador_ig,fecha_inicio,fecha_fin,
              modalidad,estado,es_propio,imagen,descripcion,incluye,precio_desde,moneda,
              cupos,wa_contacto,url_inscripcion
       FROM retiros WHERE estado IN('publicado','agotado') AND fecha_inicio>=CURDATE()
       ORDER BY fecha_inicio ASC")->fetchAll();

    /* v2.8: cupos ocupados por retiro.
       Suma de `personas` de las consultas que pasaste a coordinada o cerrada.
       Solo esos dos estados cuentan: una consulta en "nueva" o "contactada"
       es una persona que pregunto, no un lugar tomado. Si alguien no cargo
       cantidad, cuenta como 1, que es lo minimo razonable. */
    try {
      foreach ($db->query(
        "SELECT retiro_id, SUM(CASE WHEN COALESCE(personas,0) < 1 THEN 1 ELSE personas END) AS gente
           FROM consultas
          WHERE retiro_id IS NOT NULL AND estado IN('coordinada','cerrada')
          GROUP BY retiro_id")->fetchAll() as $c) {
        $ocupados[(int)$c['retiro_id']] = (int)$c['gente'];
      }
    } catch (Throwable $e) { error_log('[LR cupos] '.$e->getMessage()); }

    $pasados = $db->query(
      "SELECT slug,titulo,facilitador,fecha_inicio,fecha_fin FROM retiros
       WHERE estado IN('publicado','agotado','finalizado') AND fecha_inicio<CURDATE()
       ORDER BY fecha_inicio DESC LIMIT 8")->fetchAll();

    if ($proximos) {
      $ids = implode(',', array_map(function($r){ return (int)$r['id']; }, $proximos));
      $mm = $db->query("SELECT * FROM retiro_media WHERE retiro_id IN($ids) AND activo=1
                        ORDER BY tipo ASC, orden ASC")->fetchAll();
      foreach ($mm as $m) { $media_map[$m['retiro_id']][] = $m; }
    }
  } catch (Exception $e) { error_log('[LR retiros] '.$e->getMessage()); }
}

/* Payload for the lightbox. Images first so the viewer opens on a photo. */
$retData = array_values(array_map(function($r) use ($media_map, $ocupados) {
  $media = $media_map[$r['id']] ?? [];
  $imgs  = array_values(array_filter($media, function($m){ return $m['tipo']==='imagen'; }));
  $vids  = array_values(array_filter($media, function($m){ return $m['tipo']==='video'; }));
  $all   = array_merge($imgs, $vids);
  if (!$all && $r['imagen']) { $all = [['tipo'=>'imagen','archivo'=>$r['imagen'],'titulo'=>$r['titulo']]]; }
  $wa = preg_replace('/\D/', '', $r['wa_contacto'] ?: SITE_WA);
  return [
    'id'        => (int)$r['id'],
    'slug'      => $r['slug'],
    'titulo'    => $r['titulo'],
    'bajada'    => $r['bajada'] ?? '',
    'fechaTxt'  => rangoFecha($r['fecha_inicio'], $r['fecha_fin']),
    'ini'       => $r['fecha_inicio'],
    'fin'       => $r['fecha_fin'] ?: $r['fecha_inicio'],
    'coord'     => $r['facilitador'] ?? '',
    'coordIg'   => ltrim((string)($r['facilitador_ig'] ?? ''), '@'),
    'modalidad' => strtoupper((string)$r['modalidad']),
    'estado'    => $r['estado'],
    'propio'    => (int)$r['es_propio'] === 1,
    'desc'      => $r['descripcion'] ?? '',
    'incluye'   => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$r['incluye'])))),
    'precio'    => $r['precio_desde'] ? ($r['moneda'] ?: 'ARS').' '.number_format((float)$r['precio_desde'], 0, ',', '.') : '',
    'cupos'     => $r['cupos'] ? (int)$r['cupos'] : 0,
    'ocupados'  => $ocupados[(int)$r['id']] ?? 0,
    /* libres es null cuando el retiro no declara cupos: ahi no se puede
       decir cuantos quedan, y mostrar un numero inventado seria peor que
       no mostrar nada. */
    'libres'    => $r['cupos'] ? max(0, (int)$r['cupos'] - ($ocupados[(int)$r['id']] ?? 0)) : null,
    'lleno'     => $r['estado'] === 'agotado'
                   || ($r['cupos'] && ($ocupados[(int)$r['id']] ?? 0) >= (int)$r['cupos']),
    'wa'        => $wa,
    'waLabel'   => $r['wa_contacto'] ? $r['wa_contacto'] : SITE_WA_LABEL,
    'url'       => $r['url_inscripcion'] ?? '',
    'media'     => array_map(function($m){
                     return ['tipo'=>$m['tipo'], 'src'=>$m['archivo'], 'title'=>$m['titulo'] ?? ''];
                   }, $all),
  ];
}, $proximos));
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agenda de retiros · La Reserva</title>
<meta name="description" content="Retiros, talleres y formaciones abiertos al público en La Reserva, Capilla del Señor.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<!-- retiros.php VERSION 3.0 -->
<style>
/* ── Retiro lightbox ─────────────────────────────────────── */
.rt-lb{position:fixed;inset:0;background:rgba(4,10,20,.9);backdrop-filter:blur(6px);z-index:120;
       display:none;align-items:center;justify-content:center;padding:22px}
.rt-lb.open{display:flex}
.rt-box{background:var(--card,#112233);border:1px solid rgba(0,180,150,.18);border-radius:12px;
        width:min(1080px,100%);max-height:92vh;display:grid;grid-template-columns:1fr 400px;overflow:hidden}
@media(max-width:900px){.rt-box{grid-template-columns:1fr;overflow-y:auto}}
.rt-media{position:relative;background:#000;min-height:300px}
.rt-media img,.rt-media video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;display:none}
.rt-media img.active,.rt-media video.active{display:block}
@media(max-width:900px){.rt-media{min-height:240px;height:44vw}}
.rt-nav{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;border-radius:50%;
        background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:#e8f0f7;cursor:pointer;
        display:flex;align-items:center;justify-content:center;font-size:1rem;z-index:3;transition:.18s}
.rt-nav:hover{background:#00c9a7;color:#0a1628}
.rt-prev{left:12px}.rt-next{right:12px}
.rt-count{position:absolute;bottom:58px;right:14px;background:rgba(10,22,40,.7);color:rgba(232,240,247,.65);
          font-size:.72rem;padding:3px 9px;border-radius:10px;z-index:3}
.rt-dots{position:absolute;bottom:58px;left:50%;transform:translateX(-50%);display:flex;gap:5px;z-index:3}
.rt-dot{width:7px;height:7px;border-radius:50%;background:rgba(232,240,247,.35);cursor:pointer}
.rt-dot.active{background:#00c9a7}
.rt-play{position:absolute;bottom:12px;left:14px;background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);
         color:#e8f0f7;border-radius:16px;padding:5px 13px;font-size:.74rem;cursor:pointer;z-index:3;display:none}
/* v2.7: reproductor real. Autoplay va en silencio porque todos los
   navegadores bloquean el audio sin un gesto del usuario. */
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

.rt-info{padding:26px 26px 30px;overflow-y:auto;max-height:92vh}
@media(max-width:900px){.rt-info{max-height:none}}
.rt-close{position:absolute;top:14px;right:16px;width:32px;height:32px;border-radius:50%;
          background:rgba(10,22,40,.8);border:1px solid rgba(0,201,167,.25);color:#e8f0f7;cursor:pointer;z-index:4}
/* Under the nav breakpoint the hamburger owns the top right corner, so the
   close button moves left rather than fighting it for the tap. */
@media(max-width:899px){.rt-close{right:auto;left:14px}}
.rt-badge{display:inline-block;font-size:.64rem;letter-spacing:.14em;text-transform:uppercase;font-weight:600;
          color:#00c9a7;border:1px solid rgba(0,201,167,.35);border-radius:10px;padding:3px 10px;margin-bottom:12px}
.rt-badge.agotado{color:#ff5252;border-color:rgba(255,82,82,.4)}
.rt-title{font-family:'Fraunces',Georgia,serif;font-size:1.7rem;line-height:1.2;color:#e8f0f7;margin:0 0 6px}
.rt-when{color:#00c9a7;font-size:.9rem;margin-bottom:14px}
.rt-desc p{color:#7a9ab8;font-size:.92rem;line-height:1.7;margin:0 0 12px}
.rt-rot{font-size:.68rem;letter-spacing:.14em;color:#4a6a85;text-transform:uppercase;margin:20px 0 8px;font-weight:600}
.rt-inc{list-style:none;padding:0;margin:0}
.rt-inc .rt-sub{list-style:none;margin-left:-18px;padding:14px 0 5px;color:#00c9a7;
  font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;font-weight:600;
  border-bottom:1px solid rgba(0,201,167,.18)}
.rt-inc .rt-par{list-style:none;margin-left:-18px;display:flex;justify-content:space-between;
  align-items:baseline;gap:14px}
.rt-inc .rt-par strong{color:#e8f0f7;font-weight:600;white-space:nowrap}
.rt-inc li{border-bottom:1px solid rgba(0,180,150,.1);padding:8px 0;color:#7a9ab8;font-size:.88rem}
.rt-price{color:#e8f0f7;font-size:1rem;margin:16px 0 0}
.rt-price strong{color:#00c9a7}
/* Calendar */
.rt-cal{background:rgba(10,22,40,.5);border:1px solid rgba(0,180,150,.14);border-radius:8px;padding:14px;margin-top:8px}
.rt-cal + .rt-cal{margin-top:10px}
.rt-cal-h{text-align:center;color:#e8f0f7;font-size:.86rem;font-weight:600;margin-bottom:10px;text-transform:capitalize}
.rt-cal-g{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.rt-cal-g span{text-align:center;font-size:.72rem;padding:5px 0;color:#7a9ab8;border-radius:4px}
.rt-cal-g span.wd{color:#4a6a85;font-size:.62rem;letter-spacing:.06em;padding-bottom:2px}
.rt-cal-g span.on{background:rgba(0,201,167,.18);color:#00c9a7;font-weight:600}
.rt-cal-g span.edge{background:#00c9a7;color:#0a1628;font-weight:700}
/* v2.8: dia sin lugares. Cruz, no solo un color: el color solo no se lee
   si alguien no distingue bien el verde del rojo. */
.rt-cal-g span.lleno{background:rgba(240,57,139,.16);color:#f0398b;font-weight:700}
.rt-libres{color:#00c9a7;font-size:.86rem;font-weight:600;margin:6px 0 0}
.rt-libres.pocos{color:#f5a623}
.rt-libres.cero{color:#f0398b}
.rt-cal-nota{color:#4a6a85;font-size:.76rem;line-height:1.5;margin-top:6px}
/* Thumb-sized cells. The desktop 5px padding is unusable on a phone. */
@media(max-width:760px){
  .rt-cal-g span{padding:10px 0;font-size:.85rem}
  .rt-cal-g span.wd{padding:4px 0;font-size:.66rem}
}
/* RSVP */
.rt-form{margin-top:8px}
.rt-form .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:520px){.rt-form .g2{grid-template-columns:1fr}}
.rt-form label{display:block;font-size:.7rem;letter-spacing:.08em;color:#4a6a85;margin:0 0 4px;text-transform:uppercase}
.rt-form input,.rt-form textarea{width:100%;background:rgba(10,22,40,.6);border:1px solid rgba(0,180,150,.2);
  border-radius:5px;padding:9px 11px;color:#e8f0f7;font:inherit;font-size:.88rem;margin-bottom:10px}
.rt-form input:focus,.rt-form textarea:focus{outline:none;border-color:#00c9a7}
.rt-form textarea{resize:vertical;min-height:64px}
.rt-hp{position:absolute;left:-9999px}
.rt-btn{width:100%;background:#00c9a7;color:#0a1628;border:0;border-radius:5px;padding:11px;
        font:inherit;font-weight:600;cursor:pointer;transition:.18s}
.rt-btn:hover{filter:brightness(1.08)}
.rt-btn[disabled]{opacity:.55;cursor:default}
.rt-wa{display:block;text-align:center;margin-top:10px;padding:11px;border-radius:5px;
       border:1px solid rgba(0,201,167,.35);color:#00c9a7;text-decoration:none;font-size:.9rem}
.rt-wa:hover{background:rgba(0,201,167,.1)}
.rt-wa small{display:block;color:#4a6a85;font-size:.72rem;margin-top:2px}
.rt-msg{border-radius:5px;padding:11px;font-size:.86rem;margin-bottom:10px;display:none}
.rt-msg.ok{display:block;background:rgba(0,201,167,.1);border:1px solid rgba(0,201,167,.3);color:#00c9a7}
.rt-msg.err{display:block;background:rgba(255,82,82,.1);border:1px solid rgba(255,82,82,.3);color:#ff5252}
.rt-full{background:rgba(255,82,82,.08);border:1px solid rgba(255,82,82,.25);color:#ff5252;
         border-radius:5px;padding:11px;font-size:.86rem;margin-top:8px}
</style>
<style>
/* Hero centrado, igual que historia.php. historia centra porque su contenedor
   es un flex item con margin:0 auto, asi que se encoge al ancho del contenido.
   Aca .interior es un bloque dentro de un .texto de ancho completo, asi que
   margin:0 auto no tiene nada que centrar. width:fit-content es la pieza. */
.portada .texto .interior{
  width:fit-content;
  max-width:min(var(--ancho,1180px), 100%);
  margin-inline:auto;
}
@media(max-width:760px){ .portada .texto .interior{width:auto} }
</style>
</head>
<body>
<?php include __DIR__.'/includes/nav.php'; ?>

<!-- v2.7: clearance for the fixed nav. Without it the first line of the
     hero lands under the header on any short viewport. -->
<section class="portada" style="min-height:64vh;padding-top:110px">
  <video autoplay muted loop playsinline preload="metadata"
         poster="assets/video/pages/retiros-poster.jpg" aria-hidden="true">
    <source src="assets/video/pages/retiros.mp4" type="video/mp4">
  </video>
  <div class="velo"></div>
  <div class="texto" style="padding-bottom:6vh">
    <div class="interior">
      <p class="lugar">AGENDA · RETIROS Y ENCUENTROS</p>
      <h1 style="font-size:clamp(2.4rem,5vw,4.2rem)">Agenda</h1>
      <p>Encuentros abiertos al público en el predio. La inscripción va siempre a quien coordina cada retiro.</p>
    </div>
  </div>
</section>

<section class="bloque" style="padding-top:60px">
  <div class="envoltura">
    <div class="agenda">
      <?php if($proximos): foreach($proximos as $r): ?>
      <a class="fila" href="retiro.php?r=<?= urlencode($r['slug']) ?>"
         onclick="return openRet(<?= (int)$r['id'] ?>, event)">
        <span class="fila-fecha"><?= e(rangoFecha($r['fecha_inicio'],$r['fecha_fin'])) ?></span>
        <span>
          <span class="fila-titulo"><?= e($r['titulo']) ?></span>
          <?php if($r['facilitador']): ?><span class="fila-quien">Coordina <?= e($r['facilitador']) ?></span><?php endif; ?>
        </span>
        <?php if($r['estado']==='agotado'): ?><span class="fila-estado agotado">SIN CUPOS</span>
        <?php elseif((int)$r['es_propio']===1): ?><span class="fila-estado propio">DE LA RESERVA</span>
        <?php else: ?><span class="fila-estado"><?= e(strtoupper($r['modalidad'])) ?></span><?php endif; ?>
      </a>
      <?php endforeach; else: ?>
      <div class="agenda-vacia">
        Todavía no hay fechas publicadas.
        <a href="<?= e(waLink('Hola, quiero saber qué retiros hay disponibles.')) ?>" target="_blank" rel="noopener">Escribinos</a>
        y te contamos qué se viene.
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if($pasados): ?>
<section class="bloque ajustado niebla">
  <div class="envoltura">
    <p class="rotulo">YA SUCEDIERON</p>
    <div class="agenda">
      <?php foreach($pasados as $r): ?>
      <div class="fila" style="cursor:default">
        <span class="fila-fecha"><?= e(rangoFecha($r['fecha_inicio'],$r['fecha_fin'])) ?></span>
        <span><span class="fila-titulo" style="font-size:1.1rem"><?= e($r['titulo']) ?></span>
        <?php if($r['facilitador']): ?><span class="fila-quien"><?= e($r['facilitador']) ?></span><?php endif; ?></span>
        <span></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="bloque oscuro">
  <div class="envoltura dos-columnas">
    <h2 class="titulo-bloque" style="color:#fff">¿Querés traer tu propuesta?</h2>
    <div class="medida">
      <p style="color:var(--niebla)">Coordinamos una visita para que conozcas el predio y armemos la fecha.</p>
      <p style="margin-top:20px"><a href="contacto.php" class="boton claro">Consultar disponibilidad</a></p>
    </div>
  </div>
</section>

<!-- ══ LIGHTBOX ══════════════════════════════════════════════ -->
<div class="rt-lb" id="rtLb" onclick="if(event.target===this) closeRet()">
  <div class="rt-box">
    <div class="rt-media" id="rtMedia">
      <button class="rt-nav rt-prev" onclick="rtNav(-1)" aria-label="Anterior">&#8592;</button>
      <button class="rt-nav rt-next" onclick="rtNav(1)" aria-label="Siguiente">&#8594;</button>
      <button class="rt-play" id="rtPlay" onclick="rtTogglePlay()">Play</button>
      <div class="rt-count" id="rtCount">1 / 1</div>
      <div class="rt-dots" id="rtDots"></div>
    </div>
    <div class="rt-info" id="rtInfo"></div>
    <button class="rt-close" onclick="closeRet()" aria-label="Cerrar">&times;</button>
  </div>
</div>

<script>
var retData = <?= json_encode($retData, JSON_UNESCAPED_UNICODE) ?>;
var RT_CSRF = <?= json_encode(csrfToken()) ?>;
var rtCur = 0, rtMediaList = [], rtActive = null;

var MESES = ['enero','febrero','marzo','abril','mayo','junio','julio',
             'agosto','septiembre','octubre','noviembre','diciembre'];
var DIAS  = ['L','M','M','J','V','S','D'];

function esc(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function parseD(s){
  var p = String(s || '').split('-');
  if(p.length !== 3) return null;
  return new Date(+p[0], +p[1] - 1, +p[2]);
}
function keyOf(d){ return d.getFullYear()*10000 + (d.getMonth()+1)*100 + d.getDate(); }

/* Month grid with the retiro range marked. Weeks start on Monday. */
function calMonth(year, month, d0, d1, lleno){
  var first = new Date(year, month, 1);
  var lead  = (first.getDay() + 6) % 7;            // Monday = 0
  var days  = new Date(year, month + 1, 0).getDate();
  var k0 = keyOf(d0), k1 = keyOf(d1);
  var h = '<div class="rt-cal"><div class="rt-cal-h">' + MESES[month] + ' ' + year + '</div><div class="rt-cal-g">';
  for (var w = 0; w < 7; w++) h += '<span class="wd">' + DIAS[w] + '</span>';
  for (var b = 0; b < lead; b++) h += '<span></span>';
  for (var d = 1; d <= days; d++) {
    var k = year*10000 + (month+1)*100 + d;
    var cls = '';
    var txt = d;
    if (k >= k0 && k <= k1) {
      if (lleno) { cls = 'lleno'; txt = '&times;'; }
      else cls = (k === k0 || k === k1) ? 'edge' : 'on';
    }
    h += '<span class="' + cls + '" title="' + (cls === 'lleno' ? 'Sin lugares' : '') + '">' + txt + '</span>';
  }
  return h + '</div></div>';
}
function buildCal(ini, fin, lleno){
  var d0 = parseD(ini), d1 = parseD(fin) || d0;
  if(!d0) return '';
  if (d1 < d0) d1 = d0;
  var out = '', y = d0.getFullYear(), m = d0.getMonth(), guard = 0;
  while (guard < 4 && (y < d1.getFullYear() || (y === d1.getFullYear() && m <= d1.getMonth()))) {
    out += calMonth(y, m, d0, d1, lleno);
    m++; if (m > 11) { m = 0; y++; }
    guard++;
  }
  return out;
}

function rtRenderMedia(d){
  var box = document.getElementById('rtMedia');
  box.querySelectorAll('img,video').forEach(function(el){ el.remove(); });
  var dots = document.getElementById('rtDots');
  dots.innerHTML = '';
  rtMediaList = d.media || [];
  rtCur = 0;

  rtMediaList.forEach(function(m, i){
    var el;
    if (m.tipo === 'video') {
      el = document.createElement('video');
      el.muted = true; el.loop = true; el.playsInline = true; el.preload = 'metadata';
      var src = document.createElement('source');
      src.src = m.src; src.type = 'video/mp4';
      el.appendChild(src);
    } else {
      el = document.createElement('img');
      el.src = m.src; el.alt = m.title || d.titulo; el.loading = 'lazy';
    }
    if (i === 0) el.className = 'active';
    box.insertBefore(el, box.firstChild);

    var dot = document.createElement('div');
    dot.className = 'rt-dot' + (i === 0 ? ' active' : '');
    dot.onclick = function(){ rtGo(i); };
    dots.appendChild(dot);
  });

  var many = rtMediaList.length > 1;
  box.querySelector('.rt-prev').style.display = many ? 'flex' : 'none';
  box.querySelector('.rt-next').style.display = many ? 'flex' : 'none';
  dots.style.display = many ? 'flex' : 'none';
  document.getElementById('rtCount').style.display = many ? 'block' : 'none';
  rtGo(0);
}

function rtGo(i){
  var box = document.getElementById('rtMedia');
  var items = box.querySelectorAll('img,video');
  if (!items.length) return;
  if (i < 0) i = items.length - 1;
  if (i >= items.length) i = 0;
  items.forEach(function(el, n){
    el.classList.toggle('active', n === i);
    if (el.tagName === 'VIDEO' && n !== i) el.pause();
  });
  var dots = document.getElementById('rtDots').children;
  for (var n = 0; n < dots.length; n++) dots[n].classList.toggle('active', n === i);
  rtCur = i;
  document.getElementById('rtCount').textContent = (i + 1) + ' / ' + items.length;

  var el = items[i];
  var play = document.getElementById('rtPlay');
  if (el.tagName === 'VIDEO') {
    rtActive = el;
    play.style.display = 'none';
    el.muted = true;
    var pr = el.play(); if (pr && pr.catch) pr.catch(function(){});
    lrPlayer(box, el);
  } else {
    rtActive = null;
    play.style.display = 'none';
    lrPlayerOff(box);
  }
}

/* ── Reproductor: autoplay, barra de progreso y sonido ────────────────── */
function lrPlayer(box, video){
  if(!video || !box) return;
  var vp = box.querySelector('.vp');
  if(!vp){
    vp = document.createElement('div'); vp.className = 'vp';
    vp.innerHTML = '<div class="vp-barra"><div class="vp-llena"></div></div>'
                 + '<div class="vp-fila"><button data-a="pp">Pausa</button>'
                 + '<button data-a="son">Sonido</button><span class="vp-t">0:00</span></div>';
    box.appendChild(vp);
  }
  var barra=vp.querySelector('.vp-barra'), llena=vp.querySelector('.vp-llena'),
      bpp=vp.querySelector('[data-a="pp"]'), bson=vp.querySelector('[data-a="son"]'),
      reloj=vp.querySelector('.vp-t');
  function mmss(x){ x=Math.max(0,Math.floor(x||0)); return Math.floor(x/60)+':'+('0'+(x%60)).slice(-2); }
  function pintar(){
    if(!video.duration) return;
    llena.style.width=(video.currentTime/video.duration*100)+'%';
    reloj.textContent=mmss(video.currentTime)+' / '+mmss(video.duration);
  }
  video.ontimeupdate=pintar; video.onloadedmetadata=pintar;
  video.onplay=function(){ bpp.textContent='Pausa'; };
  video.onpause=function(){ bpp.textContent='Reproducir'; };
  bpp.onclick=function(e){ e.stopPropagation();
    if(video.paused){ var p=video.play(); if(p&&p.catch) p.catch(function(){}); } else video.pause(); };
  bson.onclick=function(e){ e.stopPropagation();
    video.muted=!video.muted; bson.textContent=video.muted?'Sonido':'Silenciar';
    if(!video.muted&&video.paused){ var p=video.play(); if(p&&p.catch) p.catch(function(){}); } };
  barra.onclick=function(e){ e.stopPropagation();
    if(!video.duration) return;
    var r=barra.getBoundingClientRect();
    video.currentTime=((e.clientX-r.left)/r.width)*video.duration; pintar(); };
  bson.textContent=video.muted?'Sonido':'Silenciar';
  vp.classList.add('on'); pintar();
}
function lrPlayerOff(box){ var vp=box&&box.querySelector('.vp'); if(vp) vp.classList.remove('on'); }

function rtNav(step){ rtGo(rtCur + step); }
function rtTogglePlay(){
  if (!rtActive) return;
  var play = document.getElementById('rtPlay');
  if (rtActive.paused) {
    var pr = rtActive.play();
    if (pr && pr.catch) pr.catch(function(){});
    play.textContent = 'Pause';
  } else {
    rtActive.pause();
    play.textContent = 'Play';
  }
}

function rtRenderInfo(d){
  var h = '';
  var badge = d.estado === 'agotado' ? 'SIN CUPOS' : (d.propio ? 'DE LA RESERVA' : d.modalidad);
  h += '<span class="rt-badge' + (d.estado === 'agotado' ? ' agotado' : '') + '">' + esc(badge) + '</span>';
  h += '<h2 class="rt-title">' + esc(d.titulo) + '</h2>';
  h += '<p class="rt-when">' + esc(d.fechaTxt) + '</p>';

  if (d.coord) {
    h += '<div class="rt-rot">Coordina</div><p style="color:#e8f0f7;font-size:.95rem;margin:0">' + esc(d.coord);
    if (d.coordIg) h += ' <a href="https://instagram.com/' + esc(d.coordIg) + '" target="_blank" rel="noopener" style="color:#00c9a7;font-size:.85rem">@' + esc(d.coordIg) + '</a>';
    h += '</p>';
  }

  var texto = d.desc || d.bajada;
  if (texto) {
    h += '<div class="rt-desc" style="margin-top:16px">';
    texto.split(/\n\s*\n/).forEach(function(p){
      if (p.trim()) h += '<p>' + esc(p.trim()).replace(/\n/g, '<br>') + '</p>';
    });
    h += '</div>';
  }

  if (d.incluye && d.incluye.length) {
    /* v3.0: el campo Incluye entiende tres formas, igual que en retiro.php.
         # Titulo          subtitulo que separa grupos
         Etiqueta | valor  etiqueta a la izquierda, valor a la derecha
         Texto suelto      item normal
       Antes esta pagina los mostraba crudos y se veia "# 3 dias y 2 noches"
       y "Adulto | $442.000" con el simbolo a la vista. */
    h += '<div class="rt-rot">Incluye</div><ul class="rt-inc">';
    d.incluye.forEach(function(li){
      li = String(li).trim();
      if (!li) return;
      if (li.charAt(0) === '#') {
        h += '<li class="rt-sub">' + esc(li.replace(/^#\s*/, '')) + '</li>';
      } else if (li.indexOf('|') >= 0) {
        var par = li.split('|');
        h += '<li class="rt-par"><span>' + esc(par[0].trim())
           + '</span><strong>' + esc(par.slice(1).join('|').trim()) + '</strong></li>';
      } else {
        h += '<li>' + esc(li) + '</li>';
      }
    });
    h += '</ul>';
  }

  if (d.precio) h += '<p class="rt-price">Desde <strong>' + esc(d.precio) + '</strong></p>';
  /* v2.8: lugares que quedan, no el total.
     "15 lugares" no le dice nada a alguien que quiere saber si entra.
     libres es null cuando el retiro no declara cupos: ahi se muestra el
     total y nada mas, porque no hay con que calcular. */
  if (d.libres !== null && d.libres !== undefined) {
    var cl = d.libres === 0 ? 'cero' : (d.libres <= 3 ? 'pocos' : '');
    var txt = d.libres === 0
      ? 'Sin lugares disponibles'
      : (d.libres === 1 ? 'Queda 1 lugar' : 'Quedan ' + d.libres + ' lugares')
        + ' de ' + d.cupos;
    h += '<p class="rt-libres ' + cl + '">' + txt + '</p>';
  } else if (d.cupos) {
    h += '<p style="color:#7a9ab8;font-size:.86rem;margin:6px 0 0">' + d.cupos + ' lugares</p>';
  }

  h += '<div class="rt-rot">Fechas</div>' + buildCal(d.ini, d.fin, d.lleno);
  /* Informational, not a picker: you book the encounter, not selected nights. */
  h += '<p class="rt-cal-nota">Estas son las fechas del encuentro. La reserva es por el retiro completo,'
     + ' no por dias sueltos.</p>';
  if (d.lleno) h += '<p class="rt-cal-nota">Las fechas con <strong style="color:#f0398b">&times;</strong> ya no tienen lugares.</p>';

  /* d.lleno cubre las dos formas de estar completo: que lo hayas puesto en
     estado agotado a mano, o que los confirmados ya igualen los cupos. */
  if (d.lleno) {
    h += '<div class="rt-full">Este encuentro no tiene lugares disponibles.'
       + ' Escribinos y te avisamos si se libera uno.</div>';
  } else {
    h += '<div class="rt-rot">Reservar tu lugar</div>';
    h += '<div class="rt-msg" id="rtMsg"></div>';
    h += '<form class="rt-form" id="rtForm" onsubmit="return rtSend(event)">';
    h += '<input type="hidden" name="accion" value="rsvp">';
    h += '<input type="hidden" name="csrf" value="' + esc(RT_CSRF) + '">';
    h += '<input type="hidden" name="retiro_id" value="' + d.id + '">';
    h += '<input type="hidden" name="boton" value="lr">';
    h += '<input type="hidden" name="fecha_tentativa" value="' + esc(d.ini) + '">';
    h += '<input class="rt-hp" type="text" name="apodo" tabindex="-1" autocomplete="off">';
    h += '<div class="g2"><div><label>Nombre</label><input type="text" name="nombre" required></div>';
    h += '<div><label>E-mail</label><input type="email" name="email" required></div></div>';
    h += '<div class="g2"><div><label>Teléfono</label><input type="text" name="telefono"></div>';
    h += '<div><label>Personas</label><input type="number" name="personas" min="1" max="99" value="1"></div></div>';
    h += '<label>Mensaje</label><textarea name="mensaje" placeholder="Contanos algo si querés"></textarea>';
    h += '<button type="submit" class="rt-btn" id="rtSubmit">Solicitar lugar</button>';
    h += '</form>';
  }

  if (d.url) {
    h += '<a class="rt-wa" href="' + esc(d.url) + '" target="_blank" rel="noopener">Inscripción externa</a>';
  }
  var txt = encodeURIComponent('Hola, quiero información sobre ' + d.titulo + ' (' + d.fechaTxt + ')');
  h += '<a class="rt-wa" href="https://wa.me/' + esc(d.wa) + '?text=' + txt + '" target="_blank" rel="noopener"'
     + ' onclick="rtTrack(\'click_wa\',' + d.id + ',' + JSON.stringify(d.titulo) + ',\'whatsapp\')">';
  h += 'Consultar por WhatsApp<small>' + esc(d.waLabel) + '</small></a>';
  h += '<a class="rt-wa" style="border-color:rgba(122,154,184,.25);color:#7a9ab8" href="retiro.php?r=' + encodeURIComponent(d.slug) + '">Ver la página completa</a>';

  document.getElementById('rtInfo').innerHTML = h;
  document.getElementById('rtInfo').scrollTop = 0;
}

/* Fire-and-forget beacon. sendBeacon survives the page being left for
   wa.me; fetch with keepalive is the fallback. Neither delays the click. */
function rtTrack(tipo, id, detalle, boton){
  var body = JSON.stringify({tipo: tipo, retiro_id: id || null, detalle: detalle || null, boton: boton || null});
  try {
    if (navigator.sendBeacon) {
      navigator.sendBeacon('track.php', new Blob([body], {type: 'application/json'}));
      return;
    }
  } catch (err) {}
  try { fetch('track.php', {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true}); } catch (err) {}
}

function openRet(id, ev){
  var d = retData.find(function(x){ return x.id === id; });
  if (!d) return true;                       // no data, let the link through
  if (ev) ev.preventDefault();
  rtTrack('abrir_retiro', id, d.titulo);
  rtRenderMedia(d);
  rtRenderInfo(d);
  document.getElementById('rtLb').classList.add('open');
  document.body.style.overflow = 'hidden';
  return false;
}
function closeRet(){
  document.getElementById('rtLb').classList.remove('open');
  document.body.style.overflow = '';
  document.querySelectorAll('#rtMedia video').forEach(function(v){ v.pause(); });
}

function rtSend(ev){
  ev.preventDefault();
  var form = document.getElementById('rtForm');
  var btn  = document.getElementById('rtSubmit');
  var msg  = document.getElementById('rtMsg');
  btn.disabled = true;
  btn.textContent = 'Enviando...';
  rtTrack('click_rsvp', parseInt(form.retiro_id.value, 10) || null, null, 'lr');
  fetch('retiros.php', { method: 'POST', body: new FormData(form) })
    .then(function(r){ return r.json(); })
    .then(function(j){
      msg.className = 'rt-msg ' + (j.ok ? 'ok' : 'err');
      msg.textContent = j.msg;
      if (j.ok) { form.style.display = 'none'; }
      else { btn.disabled = false; btn.textContent = 'Solicitar lugar'; }
    })
    .catch(function(){
      msg.className = 'rt-msg err';
      msg.textContent = 'No hay conexión. Probá por WhatsApp.';
      btn.disabled = false;
      btn.textContent = 'Solicitar lugar';
    });
  return false;
}

document.addEventListener('keydown', function(e){
  if (!document.getElementById('rtLb').classList.contains('open')) return;
  if (e.key === 'Escape') closeRet();
  if (e.key === 'ArrowLeft') rtNav(-1);
  if (e.key === 'ArrowRight') rtNav(1);
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
