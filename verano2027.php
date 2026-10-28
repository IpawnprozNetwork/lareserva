<?php
/* verano2027.php - VERSION 5.5
   Landing de Verano 2027.

   Pagina suelta, no entra en el menu principal. La idea es compartir un link
   directo por Instagram, WhatsApp o un flyer, y que la persona caiga en algo
   que habla de una sola cosa. Por eso no tiene nav con seis destinos: cada
   salida de aca es el formulario o WhatsApp.

   El formulario escribe en `consultas` como cualquier otro del sitio, con
   origen='verano2027' y boton='verano2027'. Asi se filtra en el panel y se
   mide en Referidos sin tocar nada.

   Las fotos salen de assets/img/verano2027/. No pasan por la base: son fijas
   y elegidas para esta campana. Si hay que cambiarlas, se reemplaza el
   archivo con el mismo nombre. */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/antispam.php';
require_once __DIR__ . '/includes/ajustes.php';

$pagina = 'verano2027';  /* nav.php marca activo el item cuyo href coincide */
$raiz   = '';
$aviso  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Cuerpo descartado por tamano: PHP tira $_POST entero cuando el body
       pasa post_max_size y sin esto el codigo culparia a la sesion. */
    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST)) {
        $aviso = ['error', 'El formulario llego vacio. Proba de nuevo.'];

    } elseif (!empty($_POST['apodo'])) {
        /* Trampa para bots: campo oculto lleno. Fingimos exito, no guardamos. */
        $aviso = ['ok', 'Recibimos tu consulta. Te respondemos a la brevedad.'];

    } elseif (!csrfOk($_POST['csrf'] ?? null)) {
        $aviso = ['error', 'La sesion expiro. Volve a enviar el formulario.'];

    } else {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if ($nombre === '' || !$email) {
            $aviso = ['error', 'Falta el nombre o el e-mail no es valido.'];
        } else {
            $adultos = !empty($_POST['adultos']) ? max(0, min(200, (int)$_POST['adultos'])) : 0;
            $chicos  = !empty($_POST['chicos'])  ? max(0, min(200, (int)$_POST['chicos']))  : 0;
            $totalPersonas = ($adultos + $chicos) ?: null;

            $detalle = [];
            if ($adultos) $detalle[] = $adultos . ' adulto' . ($adultos > 1 ? 's' : '');
            if ($chicos)  $detalle[] = $chicos  . ' chico'  . ($chicos  > 1 ? 's' : '');
            if (!empty($_POST['edades'])) $detalle[] = 'edades: ' . limpiar($_POST['edades']);
            if (!empty($_POST['pension'])) {
                $p = $_POST['pension'];
                $detalle[] = 'pension: ' . ($p === 'completa' ? 'completa' : 'media');
            }
            $libre = limpiar($_POST['mensaje'] ?? '');
            $mensajeFinal = trim(($detalle ? implode(', ', $detalle) . ".\n" : '') . $libre) ?: null;

            $datos = [
                'nombre'   => $nombre,
                'email'    => $email,
                'telefono' => limpiar($_POST['telefono'] ?? '') ?: null,
                'tipo'     => 'participar',
                'fecha_tentativa' => (!empty($_POST['fecha_tentativa'])
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_tentativa']))
                    ? $_POST['fecha_tentativa'] : null,
                /* v3.0: adultos y chicos se piden por separado porque cambian
                   el presupuesto y el menu, pero `personas` sigue guardando el
                   total: es la columna que leen el panel, los cupos y los
                   informes. El detalle va en el mensaje. */
                'personas' => $totalPersonas,
                'mensaje'  => $mensajeFinal,
                'origen'   => 'verano2027',
                'ip'       => @inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: null,
                'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'ref'      => $_SESSION['lr_ref'] ?? 'directo',
                'boton'    => 'verano2027',
            ];

            if ($db = getDB()) {
                try {
                    $st = $db->prepare(
                        "INSERT INTO consultas
                         (nombre,email,telefono,tipo,fecha_tentativa,personas,mensaje,origen,ip,user_agent,ref,boton)
                         VALUES (:nombre,:email,:telefono,:tipo,:fecha_tentativa,:personas,:mensaje,:origen,:ip,:ua,:ref,:boton)"
                    );
                    $st->execute($datos);
                    lrEvento($db, 'verano2027_ok', ['boton' => 'verano2027']);
                    lrAvisarConsulta($datos);
                    $aviso = ['ok', 'Listo. Te escribimos para coordinar las fechas.'];
                } catch (Exception $ex) {
                    error_log('[LR verano2027] ' . $ex->getMessage());
                    $aviso = ['error', 'No pudimos guardar la consulta. Escribinos por WhatsApp.'];
                }
            } else {
                $aviso = ['error', 'No pudimos guardar la consulta. Escribinos por WhatsApp.'];
            }
        }
    }
}

/* v5.2: los encuentros del verano vuelven, pero abajo de todo y como lo
   que son: propuestas con fecha fija que se suman al Family Plan, no la
   lista de fechas disponibles. El Family Plan corre todo el verano y eso
   queda dicho arriba, en El programa. */
$eventos = [];
try {
    if ($db = getDB()) {
        $eventos = $db->query(
            "SELECT slug,titulo,bajada,fecha_inicio,fecha_fin,estado,imagen
               FROM retiros
              WHERE estado IN('publicado','agotado')
                AND fecha_inicio BETWEEN '2026-12-01' AND '2027-03-31'
              ORDER BY fecha_inicio ASC"
        )->fetchAll();
    }
} catch (Throwable $e) { error_log('[LR verano eventos] '.$e->getMessage()); }

function v27fecha($i, $f) {
    $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio',
              'agosto','septiembre','octubre','noviembre','diciembre'];
    $a = strtotime((string)$i); $b = $f ? strtotime((string)$f) : null;
    if (!$a) return '';
    /* v5.3: si la fecha de fin quedo antes que la de inicio, cargada mal en
       el panel, antes salia "16 al 4 de enero". Mejor mostrar solo la de
       inicio que un rango imposible. */
    if ($b && $b < $a) $b = null;
    $di = (int)date('j',$a); $mi = (int)date('n',$a); $yi = date('Y',$a);
    if (!$b || date('Y-m-d',$a) === date('Y-m-d',$b)) return "$di de {$meses[$mi]} $yi";
    $df = (int)date('j',$b); $mf = (int)date('n',$b);
    return $mi === $mf ? "$di al $df de {$meses[$mi]} $yi"
                       : "$di de {$meses[$mi]} al $df de {$meses[$mf]} $yi";
}

/* v4.0: las baldosas salen de `verano_media`, editables desde
   admin/verano.php. Si la tabla no existe todavia o esta vacia, la pagina
   usa las fijas del HTML de mas abajo y no se rompe nada. */
$baldosas = [];
try {
    if ($db = getDB()) {
        $baldosas = $db->query(
            "SELECT archivo, video, titulo, descripcion, datos
               FROM verano_media WHERE activo = 1 ORDER BY orden ASC, id ASC"
        )->fetchAll();
    }
} catch (Throwable $e) { error_log('[LR verano media] ' . $e->getMessage()); }


/* v4.8: el WhatsApp de esta pagina sale de `ajustes`, no de SITE_WA. La
   landing de verano la atiende otra persona que el resto del sitio, asi que
   necesita su propio numero sin tocar config.php. Vacio = usa SITE_WA. */
$v27wa    = preg_replace('/\D/', '', (string)lrAjuste('v27_wa', '')) ?: preg_replace('/\D/', '', SITE_WA);
$v27waTxt = trim((string)lrAjuste('v27_wa_label', '')) ?: SITE_WA_LABEL;
$waTexto = rawurlencode('Hola, quiero info sobre Verano 2027 en familia en La Reserva.');
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- verano2027.php VERSION 5.5 -->
<title>Verano 2027 en familia · La Reserva Naturaleza Arte</title>
<meta name="description" content="Verano 2027 en familia: 4 días y 3 noches o 3 días y 2 noches a una hora de Buenos Aires. Pileta, actividades para chicos, paseo a caballo y pensión completa.">
<meta property="og:title" content="Verano 2027 · La Reserva">
<meta property="og:description" content="4 días y 3 noches, o 3 días y 2 noches, en familia. Pileta, actividades para los chicos y pensión completa. Enero, febrero y marzo de 2027.">
<meta property="og:image" content="https://<?= SITE_DOMAIN ?>/assets/img/verano2027/pileta-aerea.jpg">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,600&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
/* v2.0: paleta del sitio, la misma de index.php, para que la landing no se
   sienta como otro sitio. La paleta del logo (verde, arena, marron) queda
   documentada en DECISIONS.md por si algun dia se cambia todo el sitio. */
:root{--bg:#0a1628;--card:#112233;--teal:#00c9a7;--t1:#e8f0f7;--t2:#7a9ab8;--t3:#4a6a85}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--t1);font-family:'Karla',sans-serif;overflow-x:hidden}
img,video{max-width:100%;display:block}
.env{max-width:1180px;margin:0 auto;padding:0 24px}

.hero{position:relative;min-height:78svh;display:flex;align-items:flex-end;overflow:hidden;padding:110px 0 6vh}
.hero video,.hero .posterimg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
/* v2.2: pointer-events:none, igual que en welcome. Un velo a pantalla
   completa se come el boton de play que iOS dibuja si bloquea el autoplay. */
.hero .velo{position:absolute;inset:0;z-index:1;pointer-events:none;background:linear-gradient(180deg,rgba(10,22,40,.55) 0%,rgba(10,22,40,.25) 40%,rgba(10,22,40,.92) 100%)}
.hero .env{position:relative;z-index:2;width:100%}
/* v4.7: 680px obligaba al titulo a partirse justo antes del 2027.
   Con mas ancho y un pelo menos de cuerpo entra en un renglon solo. */
.hero-in{width:fit-content;max-width:min(900px,100%);margin-inline:auto;text-align:center}
.hero h1{text-wrap:balance}
.rot{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--teal);margin-bottom:12px;font-weight:600}
/* v4.6: el hero era demasiado grande. A 5rem el titulo se partia en dos
   renglones y empujaba todo lo demas fuera de la primera pantalla. */
h1{font:300 clamp(1.8rem,3.8vw,3rem) 'Fraunces',Georgia,serif;line-height:1.1;margin-bottom:14px;white-space:nowrap}
h1 em{font-style:normal;color:var(--teal)}
.hero p{font-size:clamp(.95rem,1.5vw,1.06rem);color:#cfe0ee;line-height:1.7;max-width:46ch;margin:0 auto 26px}
.btns{display:flex;gap:14px;flex-wrap:wrap;justify-content:center}
.btn{display:inline-block;padding:13px 28px;border-radius:30px;text-decoration:none;font-weight:600;font-size:.95rem;transition:transform .15s,background .2s}
.btn:hover{transform:translateY(-2px)}
.btn-p{background:var(--teal);color:#0a1628}.btn-p:hover{background:#00e0ba}
.btn-s{border:1px solid rgba(232,240,247,.35);color:var(--t1)}.btn-s:hover{border-color:var(--teal);color:var(--teal)}

section{padding:84px 0}
.alt{background:#081320}
.rot-s{font-size:.72rem;letter-spacing:.22em;text-transform:uppercase;color:var(--teal);margin-bottom:12px;font-weight:600}
h2{font:300 clamp(1.9rem,4vw,2.9rem) 'Fraunces',Georgia,serif;line-height:1.12;margin-bottom:16px}
.lede{font-size:1.04rem;color:var(--t2);line-height:1.75;max-width:62ch}

.mosaico{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:34px}
.mosaico figure{position:relative;overflow:hidden;border-radius:10px;background:var(--card);aspect-ratio:4/3;cursor:pointer}
.mosaico .g2{grid-column:span 2;grid-row:span 2;aspect-ratio:1/1}
.mosaico img,.mosaico video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.mosaico img{transition:transform .6s}
.mosaico video{opacity:0;transition:opacity .35s}
.mosaico figure:hover video{opacity:1}
.mosaico figure:hover img{transform:scale(1.04)}
.mosaico figcaption{position:absolute;left:0;right:0;bottom:0;padding:22px 16px 12px;z-index:2;font-size:.8rem;letter-spacing:.06em;color:#fff;background:linear-gradient(transparent,rgba(6,14,24,.85))}
@media(hover:none){.mosaico video{opacity:1}.mosaico figure:hover img{transform:none}}

.tarifas{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:34px}
.tarifa{border:1px solid rgba(0,201,167,.22);border-radius:12px;padding:24px 22px;background:var(--card)}
.tarifa b{display:block;font:300 1.45rem 'Fraunces',Georgia,serif;color:var(--teal);
  margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid rgba(122,154,184,.16)}
.tarifa .fila{display:flex;justify-content:space-between;align-items:baseline;gap:14px;padding:8px 0}
.tarifa .fila span{color:var(--t2);font-size:.92rem}
.tarifa .fila em{font-style:normal;font-weight:600;color:var(--t1);font-size:1.02rem;white-space:nowrap}
.eventos{display:flex;flex-direction:column;gap:12px;margin-top:30px}
.ev{display:flex;gap:18px;align-items:center;padding:16px;border-radius:12px;
  background:var(--card);border:1px solid rgba(0,201,167,.18);text-decoration:none;
  transition:border-color .2s,transform .15s}
.ev:hover{border-color:var(--teal);transform:translateX(4px)}
.ev img{width:120px;height:84px;object-fit:cover;border-radius:8px;flex:0 0 auto}
.ev-txt{flex:1;min-width:0}
.ev-f{display:block;color:var(--teal);font-size:.84rem;font-weight:600;margin-bottom:3px}
.ev-txt b{display:block;font:300 1.3rem 'Fraunces',Georgia,serif;color:var(--t1);margin-bottom:4px}
.ev-txt p{color:var(--t2);font-size:.9rem;line-height:1.55;margin:0 0 7px}
.ev-e{display:inline-block;font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;
  padding:3px 11px;border-radius:12px;border:1px solid rgba(0,201,167,.35);color:var(--teal)}
.ev-e.ag{border-color:rgba(240,57,139,.4);color:#f0398b}
@media(max-width:620px){.ev{flex-direction:column;align-items:flex-start}.ev img{width:100%;height:150px}}
.calc{margin-top:34px;background:var(--card);border:1px solid rgba(0,201,167,.25);
  border-radius:16px;padding:26px 24px}
.calc-tit{font:300 1.5rem 'Fraunces',Georgia,serif;color:var(--t1);margin-bottom:18px}
.calc-planes{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:20px}
.calc-plan{background:none;border:1px solid #26405c;color:var(--t2);border-radius:22px;
  padding:9px 18px;font:inherit;font-size:.9rem;cursor:pointer;transition:border-color .2s,color .2s}
.calc-plan:hover{border-color:var(--teal);color:var(--teal)}
.calc-plan.on{border-color:var(--teal);color:#0a1628;background:var(--teal);font-weight:600}
.calc-filas{display:flex;flex-direction:column;gap:12px}
.calc-fila{display:flex;justify-content:space-between;align-items:center;gap:14px;
  padding-bottom:12px;border-bottom:1px solid rgba(122,154,184,.14)}
.calc-fila label{color:var(--t2);font-size:.95rem}
.calc-num{display:flex;align-items:center;gap:6px}
.calc-num button{width:34px;height:34px;border-radius:50%;border:1px solid #26405c;
  background:none;color:var(--t1);font-size:1.05rem;cursor:pointer;line-height:1}
.calc-num button:hover{border-color:var(--teal);color:var(--teal)}
.calc-num input{width:56px;text-align:center;background:#0a1420;border:1px solid #1c3247;
  border-radius:8px;color:var(--t1);font:inherit;font-size:1rem;padding:7px 4px}
.calc-num input::-webkit-outer-spin-button,.calc-num input::-webkit-inner-spin-button{
  -webkit-appearance:none;margin:0}
.calc-num input{-moz-appearance:textfield}
.calc-det{margin-top:16px;font-size:.88rem;color:var(--t2);line-height:1.9}
.calc-det div{display:flex;justify-content:space-between;gap:14px}
.calc-det em{font-style:normal;color:var(--t1)}
.calc-total{display:flex;justify-content:space-between;align-items:baseline;gap:14px;
  margin-top:16px;padding-top:16px;border-top:1px solid rgba(0,201,167,.3)}
.calc-total span{color:var(--t2);font-size:.95rem}
.calc-total strong{font:300 2rem 'Fraunces',Georgia,serif;color:var(--teal)}
.calc-nota{margin:12px 0 18px;font-size:.84rem;color:var(--t3);line-height:1.6}
@media(max-width:620px){.calc{padding:20px 16px}.calc-total strong{font-size:1.6rem}}
.nota-precio{margin-top:20px;font-size:.9rem;color:var(--t3);border-left:2px solid rgba(240,57,139,.4);padding-left:14px}
.incluye{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin-top:34px}
.inc{border:1px solid rgba(0,201,167,.18);border-radius:12px;padding:22px 20px;background:var(--card)}
.inc b{display:block;font:600 1.02rem 'Karla',sans-serif;margin-bottom:6px;color:var(--t1)}
.inc p{font-size:.92rem;color:var(--t2);line-height:1.6}

.aviso-fechas{margin-top:22px;padding:16px 20px;border-radius:12px;
  background:rgba(0,201,167,.08);border:1px solid rgba(0,201,167,.3);
  color:var(--t1);font-size:1.02rem;line-height:1.7;max-width:62ch}
.rot-mini{margin-top:30px;font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;
  color:var(--t3);font-weight:600}
.fecha{display:flex;gap:18px;align-items:baseline;flex-wrap:wrap;padding:20px 0;
  border-bottom:1px solid rgba(122,154,184,.16);text-decoration:none;transition:padding .2s}
.fecha:hover{padding-left:10px}
.fecha:hover .t{color:var(--teal)}
.fecha .fl{color:var(--teal);opacity:0;transition:opacity .2s;margin-left:auto}
.fecha:hover .fl{opacity:1}
.fecha .d{color:var(--teal);font-weight:600;min-width:220px;font-size:.96rem}
.fecha .t{font:300 1.3rem 'Fraunces',Georgia,serif;flex:1;min-width:220px}
.fecha .e{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;padding:3px 11px;border-radius:12px;border:1px solid rgba(0,201,167,.3);color:var(--teal)}
.fecha .e.ag{border-color:rgba(240,57,139,.4);color:#f0398b}

.form-wrap{background:var(--card);border:1px solid rgba(0,201,167,.18);border-radius:16px;padding:38px 34px;max-width:720px;margin:34px auto 0}
.f2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.f3{display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:14px}
label{display:block;margin-bottom:14px}
label span{display:block;font-size:.74rem;letter-spacing:.1em;text-transform:uppercase;color:var(--t2);margin-bottom:6px}
input,textarea,select{width:100%;background:#0a1420;border:1px solid #1c3247;border-radius:8px;color:var(--t1);font:inherit;font-size:.95rem;padding:11px 13px}
input:focus,textarea:focus,select:focus{outline:0;border-color:var(--teal)}
textarea{min-height:96px;resize:vertical;line-height:1.6}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.sub{background:var(--teal);color:#0a1628;border:0;border-radius:30px;padding:13px 32px;font:inherit;font-weight:600;font-size:.98rem;cursor:pointer;width:100%;margin-top:6px}
.sub:hover{background:#00e0ba}
.av{padding:13px 16px;border-radius:0 10px 10px 0;margin-bottom:20px;font-size:.93rem}
.av.ok{background:#08161a;border:1px solid #123a35;border-left:3px solid var(--teal);color:#8fb5ad}
.av.error{background:#170a0f;border:1px solid #3d1622;border-left:3px solid #f0398b;color:#c49aa6}
.nota{font-size:.84rem;color:var(--t3);text-align:center;margin-top:16px;line-height:1.6}
.nota a{color:var(--teal)}

.cierre{text-align:center;padding:84px 0 60px}
.pie{border-top:1px solid rgba(122,154,184,.14);padding:26px 0;text-align:center;font-size:.82rem;color:var(--t3)}
.pie a{color:var(--t2);text-decoration:none}.pie a:hover{color:var(--teal)}
.wa-float{position:fixed;right:18px;bottom:18px;z-index:40;width:56px;height:56px;border-radius:50%;background:#25d366;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(0,0,0,.4);text-decoration:none}
.wa-float svg{width:30px;height:30px;fill:#fff}

/* ── Visor, mismo formato que espacios.php ────────────────────── */
.vl{position:fixed;inset:0;z-index:100;background:rgba(5,12,22,.94);display:none;align-items:center;justify-content:center;padding:20px}
.vl.open{display:flex}
.vl-box{width:min(1200px,100%);height:min(88vh,860px);display:grid;grid-template-columns:1.35fr 1fr;grid-template-rows:minmax(0,1fr);background:var(--card);border-radius:14px;overflow:hidden;border:1px solid rgba(0,201,167,.2)}
.vl-media{position:relative;background:#000;min-height:0}
.vl-media img,.vl-media video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.vl-media video{display:none}
.vl-media.conv video{display:block}.vl-media.conv img{display:none}
.vl-info{padding:34px 32px;overflow-y:auto;min-height:0}
.vl-info .rot-s{margin-bottom:8px}
.vl-info h3{font:300 2rem 'Fraunces',Georgia,serif;margin-bottom:14px;line-height:1.1}
.vl-info p{color:var(--t2);line-height:1.75;margin-bottom:18px}
.vl-datos{list-style:none;padding:0;margin:0 0 22px;border-top:1px solid rgba(122,154,184,.16)}
.vl-datos li{padding:9px 0 9px 20px;border-bottom:1px solid rgba(122,154,184,.1);
  color:var(--t2);font-size:.92rem;position:relative}
.vl-datos li::before{content:"";position:absolute;left:0;top:16px;width:7px;height:7px;
  border-radius:50%;background:var(--teal);opacity:.55}
.vl-datos:empty{display:none}
.vl-x{position:absolute;top:14px;right:14px;z-index:5;width:40px;height:40px;border-radius:50%;background:rgba(10,22,40,.8);border:1px solid rgba(0,201,167,.3);color:var(--t1);font-size:1.3rem;cursor:pointer}
.vl-x:hover{background:var(--teal);color:#0a1628}
.vl-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:44px;height:44px;border-radius:50%;background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:var(--t1);font-size:1.2rem;cursor:pointer}
.vl-nav:hover{background:var(--teal);color:#0a1628}
.vl-prev{left:14px}.vl-next{right:14px}
.vl-ctr{position:absolute;bottom:14px;right:14px;z-index:5;font-size:.72rem;color:rgba(232,240,247,.6);background:rgba(10,22,40,.6);padding:3px 9px;border-radius:10px}
.vl-tog{position:absolute;bottom:14px;left:14px;z-index:5;background:rgba(10,22,40,.75);border:1px solid rgba(0,201,167,.3);color:var(--t1);border-radius:16px;padding:5px 13px;font:inherit;font-size:.74rem;cursor:pointer}
.vl-tog:hover{background:var(--teal);color:#0a1628}
@media(max-width:899px){
  .vl{padding:0}.vl-box{grid-template-columns:1fr;grid-template-rows:46vh minmax(0,1fr);height:100vh;border-radius:0}
  .vl-info{padding:22px 20px}
}
@media(max-width:900px){.mosaico{grid-template-columns:repeat(2,1fr)}.mosaico .g2{grid-column:span 2;grid-row:auto;aspect-ratio:4/3}}
@media(max-width:780px){ h1{white-space:normal} }
@media(max-width:620px){section{padding:58px 0}.f2,.f3{grid-template-columns:1fr}.form-wrap{padding:26px 20px}.hero{min-height:88svh;padding-top:96px}.hero-in{width:auto}}
</style>
</head>
<body>

<?php /* v2.1: el nav del sitio, con hamburguesa en celular. La landing
         seguia sin menu y no habia como volver al sitio desde el telefono. */
      include __DIR__ . '/includes/nav.php'; ?>

<!-- ══════════════ HERO ══════════════ -->
<header class="hero">
  <img class="posterimg" src="assets/img/verano2027/hero-poster.jpg" alt="" aria-hidden="true">
  <video autoplay muted loop playsinline webkit-playsinline preload="metadata" disablePictureInPicture
         poster="assets/img/verano2027/hero-poster.jpg">
    <source src="assets/video/pages/verano2027-loop.mp4" type="video/mp4">
  </video>
  <div class="velo"></div>
  <div class="env">
    <div class="hero-in">
      <p class="rot"><?= e(lrAjuste('v27_hero_rotulo', 'Capilla del Señor · A una hora de Buenos Aires')) ?></p>
      <h1>Vení a disfrutar del verano <em>2027</em></h1>
      <?php /* v4.5: nl2br sobre el texto YA escapado. Asi Johnny escribe una
               linea por renglon en el panel y salen como renglones, sin que
               pueda meter HTML: e() primero, los <br> despues. */ ?>
      <p><?= nl2br(e(lrAjuste('v27_hero_bajada',
            "Naturaleza y diversión.\nConsultá por nuestro Family Plan."))) ?></p>
      <div class="btns">
        <a class="btn btn-p" href="#reservar"
           onclick="lrTrack('click_rsvp','Reservar fecha hero')">Reservar mi fecha</a>
        <a class="btn btn-s" href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>"
           target="_blank" rel="noopener"
           onclick="lrTrack('click_wa','WhatsApp hero')">Consultar por WhatsApp</a>
      </div>
    </div>
  </div>
</header>

<?php
/* v4.3: un solo helper para los dos mosaicos.

   Antes cada mosaico era un if/else con una copia fija de respaldo escrita
   a mano, y esas ramas terminaron tragandose secciones enteras del medio de
   la pagina mas de una vez. Un helper no tiene ramas que envuelvan HTML, asi
   que el problema no puede volver.

   Si no hay baldosas cargadas no se dibuja nada: el panel las carga en dos
   minutos y una pagina sin mosaico es mejor que una pagina rota. */
function v27mosaico(array $items, bool $primeraGrande = false): void {
    if (!$items) return;
    echo '<div class="mosaico">';
    foreach ($items as $k => $b) {
        $cls = ($primeraGrande && $k === 0) ? ' class="g2"' : '';
        $vid = $b['video'] ?? '';
        printf(
          '<figure%s data-img="%s"%s data-t="%s" data-d="%s" data-datos="%s">'
        . '<img src="%s" alt="%s" loading="lazy">',
          $cls, e($b['archivo']),
          $vid ? ' data-vid="' . e($vid) . '"' : '',
          e($b['titulo']), e($b['descripcion']), e($b['datos']),
          e($b['archivo']), e($b['titulo'])
        );
        if ($vid) {
            printf('<video muted loop playsinline webkit-playsinline preload="none"'
                 . ' disablePictureInPicture poster="%s"><source src="%s" type="video/mp4"></video>',
                   e($b['archivo']), e($vid));
        }
        printf('<figcaption>%s</figcaption></figure>', e($b['titulo']));
    }
    echo '</div>';
}
?>

<!-- ══════════════ EL LUGAR ══════════════ -->
<section>
  <div class="env">
    <p class="rot-s">El programa</p>
    <h2><?= e(lrAjuste('v27_programa_titulo', '4 días y 3 noches, o 3 días y 2 noches')) ?></h2>
    <p class="lede"><?= e(lrAjuste('v27_programa_bajada',
       'Elegís vos cuántas noches. En el medio no hay traslados ni horarios que cumplir: la pileta, el lago, el parque y el comedor están todos a distancia de caminar. Los chicos pueden andar sueltos.')) ?></p>
    <?php v27mosaico(array_slice($baldosas, 0, 6), true); ?>
  </div>
</section>

<?php /* v5.1: la agenda de retiros salio de esta pagina. Los retiros son
         otra propuesta y tienen su propia seccion en el sitio; mezclarlos
         con el Family Plan confundia a quien entra buscando una escapada
         en familia. Viven en retiros.php. */ ?>

<?php if ($eventos): ?>
<!-- ══════════════ EVENTOS ══════════════ -->
<section class="alt" id="eventos">
  <div class="env">
    <p class="rot-s">Además</p>
    <h2><?= e(lrAjuste('v27_eventos_titulo', 'Encuentros con fecha fija')) ?></h2>
    <p class="lede"><?= e(lrAjuste('v27_eventos_bajada',
       'El Family Plan se reserva cualquier día del verano. Aparte de eso, estos encuentros tienen fecha puesta y cupos propios. Hacé clic para ver cada uno.')) ?></p>

    <div class="eventos">
      <?php foreach ($eventos as $ev): ?>
      <a class="ev" href="retiro.php?r=<?= e($ev['slug']) ?>"
         onclick="lrTrack('abrir_retiro','<?= e($ev['titulo']) ?>')">
        <?php if (!empty($ev['imagen'])): ?>
          <img src="<?= e($ev['imagen']) ?>" alt="" loading="lazy">
        <?php endif; ?>
        <div class="ev-txt">
          <span class="ev-f"><?= e(v27fecha($ev['fecha_inicio'], $ev['fecha_fin'])) ?></span>
          <b><?= e($ev['titulo']) ?></b>
          <?php if (!empty($ev['bajada'])): ?><p><?= e($ev['bajada']) ?></p><?php endif; ?>
          <span class="ev-e <?= $ev['estado'] === 'agotado' ? 'ag' : '' ?>">
            <?= $ev['estado'] === 'agotado' ? 'Sin cupos' : 'Abierto' ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <p class="nota-precio" style="margin-top:22px"><?= e(lrAjuste('v27_eventos_nota',
       'Para reservar, escribile a Jonathan Ramírez por WhatsApp o por mail y te pasa disponibilidad y forma de pago.')) ?></p>
    <div class="btns" style="justify-content:flex-start;margin-top:18px">
      <a class="btn btn-p" href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>"
         target="_blank" rel="noopener"
         onclick="lrTrack('click_wa','WhatsApp eventos')">WhatsApp <?= e($v27waTxt) ?></a>
      <a class="btn btn-s" href="mailto:<?= e(lrAjuste('v27_contacto_mail','Jonathanr@fasedos.com.ar')) ?>?subject=<?= rawurlencode('Consulta Verano 2027') ?>"
         onclick="lrTrack('click_url','Mail eventos')"><?= e(lrAjuste('v27_contacto_mail','Jonathanr@fasedos.com.ar')) ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════ PRECIOS ══════════════ -->
<section class="alt" id="precios">
  <div class="env">
    <p class="rot-s">Valores</p>
    <h2><?= e(lrAjuste('v27_precios_titulo', 'Valor de la estadía')) ?></h2>
    <p class="lede"><?= e(lrAjuste('v27_precios_bajada',
       'Los valores son por persona e incluyen alojamiento y pensión completa. Los más chiquitos casi no pagan.')) ?></p>

    <div class="tarifas">
      <?php
      /* v4.0: las dos tarifas salen de `ajustes`, una clave por celda, asi
         Johnny cambia un numero desde el panel sin tocar el archivo. */
      $planes = [
        ['v27_p1_nombre','3 días y 2 noches','v27_p1_adulto','442.000','v27_p1_menor','221.000','v27_p1_bebe','46.000'],
        ['v27_p2_nombre','4 días y 3 noches','v27_p2_adulto','616.000','v27_p2_menor','308.000','v27_p2_bebe','52.000'],
      ];
      foreach ($planes as $pl): ?>
      <div class="tarifa">
        <b><?= e(lrAjuste($pl[0], $pl[1])) ?></b>
        <div class="fila"><span>Adulto</span><em>$<?= e(lrAjuste($pl[2], $pl[3])) ?></em></div>
        <div class="fila"><span>Menores de 4 a 10 años</span><em>$<?= e(lrAjuste($pl[4], $pl[5])) ?></em></div>
        <div class="fila"><span>De 1 a 3 años</span><em>$<?= e(lrAjuste($pl[6], $pl[7])) ?></em></div>
      </div>
      <?php endforeach; ?>
    </div>

    <p class="nota-precio"><?= e(lrAjuste('v27_precios_nota',
       'Los valores son por persona. No recibimos mascotas.')) ?></p>
  </div>
    <?php /* v5.4: calculadora. Lee los mismos valores de `ajustes` que las
             tarjetas de arriba, asi que si se cambia un precio en el panel la
             cuenta cambia sola. Corre entera en el navegador: no manda nada
             al servidor hasta que la persona aprieta el boton de WhatsApp. */ ?>
    <div class="calc" id="calc">
      <p class="calc-tit"><?= e(lrAjuste('v27_calc_titulo', 'Calculá tu estadía')) ?></p>

      <div class="calc-planes">
        <button type="button" class="calc-plan on" data-plan="1">
          <?= e(lrAjuste('v27_p1_nombre','3 días y 2 noches')) ?></button>
        <button type="button" class="calc-plan" data-plan="2">
          <?= e(lrAjuste('v27_p2_nombre','4 días y 3 noches')) ?></button>
      </div>

      <div class="calc-filas">
        <div class="calc-fila">
          <label for="c-ad">Adultos</label>
          <div class="calc-num">
            <button type="button" data-paso="-1" data-campo="c-ad">&minus;</button>
            <input type="number" id="c-ad" min="0" max="40" value="2" inputmode="numeric">
            <button type="button" data-paso="1" data-campo="c-ad">+</button>
          </div>
        </div>
        <div class="calc-fila">
          <label for="c-me">Chicos de 4 a 10</label>
          <div class="calc-num">
            <button type="button" data-paso="-1" data-campo="c-me">&minus;</button>
            <input type="number" id="c-me" min="0" max="40" value="0" inputmode="numeric">
            <button type="button" data-paso="1" data-campo="c-me">+</button>
          </div>
        </div>
        <div class="calc-fila">
          <label for="c-be">Chicos de 1 a 3</label>
          <div class="calc-num">
            <button type="button" data-paso="-1" data-campo="c-be">&minus;</button>
            <input type="number" id="c-be" min="0" max="40" value="0" inputmode="numeric">
            <button type="button" data-paso="1" data-campo="c-be">+</button>
          </div>
        </div>
      </div>

      <div class="calc-det" id="calc-det"></div>

      <div class="calc-total">
        <span>Total estimado</span>
        <strong id="calc-total">$0</strong>
      </div>
      <p class="calc-nota"><?= e(lrAjuste('v27_calc_nota',
        'Es una estimación. El valor final lo confirmamos por WhatsApp junto con la disponibilidad y la forma de pago.')) ?></p>

      <div class="btns" style="justify-content:flex-start">
        <a class="btn btn-p" id="calc-wa" href="#" target="_blank" rel="noopener"
           onclick="lrTrack('click_wa','WhatsApp calculadora')">Pedir este presupuesto</a>
      </div>
    </div>

</section>

<!-- ══════════════ HORARIOS ══════════════ -->
<section>
  <div class="env">
    <p class="rot-s">Cómo funciona</p>
    <h2><?= e(lrAjuste('v27_horarios_titulo', 'Entradas y salidas')) ?></h2>

    <div class="incluye">
      <div class="inc"><b>Días de ingreso</b><p><?= e(lrAjuste('v27_h_ingreso',
        'De lunes a jueves, o de jueves a domingo.')) ?></p></div>
      <div class="inc"><b>Check in</b><p><?= e(lrAjuste('v27_h_checkin',
        'Desde las 14 horas.')) ?></p></div>
      <div class="inc"><b>Check out</b><p><?= e(lrAjuste('v27_h_checkout',
        'Las habitaciones se dejan a las 10 de la mañana.')) ?></p></div>
      <div class="inc"><b>Último servicio</b><p><?= e(lrAjuste('v27_h_ultimo',
        'El almuerzo del último día. Después del almuerzo se retiran.')) ?></p></div>
    </div>
  </div>
</section>

<!-- ══════════════ INCLUYE ══════════════ -->
<section class="alt">
  <div class="env">
    <p class="rot-s">Qué incluye</p>
    <h2>Los días armados,<br>con la comida resuelta</h2>

    <div class="incluye">
      <div class="inc"><b>2 o 3 noches</b><p>Habitaciones sobre la galería principal o cabañas en el predio. Se arma según cuántos sean y las edades de los chicos.</p></div>
      <?php /* v5.0: antes decia "completa o media" y el valor de arriba no
         aclaraba cual, asi que todos preguntaban lo mismo. Ahora el titulo
         dice la que esta incluida en el precio y el texto aclara la otra
         como opcional. Se edita desde el panel. */ ?>
      <div class="inc"><b><?= e(lrAjuste('v27_pension_tit', 'Pensión completa')) ?></b><p><?= e(lrAjuste('v27_pension_txt', 'Desayuno, almuerzo y cena, con opciones vegetarianas, veganas y sin gluten. Si preferís media pensión, consultanos el valor.')) ?></p></div>
      <div class="inc"><b>Menú para los chicos</b><p>Carta aparte, pensada para los más chicos, con horarios que no dependen de los de los adultos.</p></div>
      <div class="inc"><b>Pileta al aire libre</b><p>Descubierta, con sector de reposeras y sombra al lado. Abierta todo el día durante la estadía.</p></div>
      <div class="inc"><b>Actividades recreativas</b><p>Juegos y propuestas al aire libre para los chicos, coordinadas durante el día.</p></div>
      <div class="inc"><b>Paseo a caballo</b><p>Se puede sumar al programa. Se coordina al reservar según las edades del grupo.</p></div>
    </div>
  </div>
</section>

<!-- ══════════════ MOMENTOS ══════════════ -->
<section>
  <div class="env">
    <p class="rot-s">Los chicos</p>
    <h2>El campo hace<br>la mitad del trabajo</h2>
    <p class="lede">250 hectáreas sin autos. Pileta, pasto, animales sueltos, un lago
      con pasarela y fogon a la noche. Los chicos se cansan solos y los grandes
      descansan de verdad.</p>
    <?php v27mosaico(array_slice($baldosas, 6)); ?>
  </div>
</section>

<!-- ══════════════ FORMULARIO ══════════════ -->
<section id="reservar">
  <div class="env">
    <div style="text-align:center">
      <p class="rot-s">Reservar</p>
      <h2>Consultar disponibilidad</h2>
      <p class="lede" style="margin:0 auto">Decinos cuándo y cuántos son. Te
        respondemos con fechas libres y precio, por mail o por WhatsApp.</p>
    </div>

    <div class="form-wrap">
      <?php if ($aviso): ?>
        <div class="av <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <div class="hp" aria-hidden="true">
          <label>No completar<input type="text" name="apodo" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="f2">
          <label><span>Nombre *</span>
            <input type="text" name="nombre" required value="<?= e($_POST['nombre'] ?? '') ?>"></label>
          <label><span>E-mail *</span>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        </div>
        <div class="f2">
          <label><span>Telefono</span>
            <input type="tel" name="telefono" value="<?= e($_POST['telefono'] ?? '') ?>"></label>
          <label><span>Fecha tentativa</span>
            <input type="date" name="fecha_tentativa" min="2027-01-01" max="2027-03-31"
                   value="<?= e($_POST['fecha_tentativa'] ?? '') ?>"></label>
        </div>
        <div class="f3">
          <label><span>Adultos</span>
            <input type="number" name="adultos" min="0" max="200" value="<?= e($_POST['adultos'] ?? '') ?>"></label>
          <label><span>Chicos</span>
            <input type="number" name="chicos" min="0" max="200" value="<?= e($_POST['chicos'] ?? '') ?>"></label>
          <label><span>Edades</span>
            <input type="text" name="edades" placeholder="4, 7 y 11" value="<?= e($_POST['edades'] ?? '') ?>"></label>
        </div>
        <label><span>Pensión</span>
          <select name="pension">
            <option value="">A definir</option>
            <option value="completa" <?= (($_POST['pension'] ?? '')==='completa')?'selected':'' ?>>Completa, la del valor publicado</option>
            <option value="media" <?= (($_POST['pension'] ?? '')==='media')?'selected':'' ?>>Media, consultar valor</option>
          </select></label>
        <label><span>Algo más que tengamos que saber</span>
          <textarea name="mensaje" placeholder="Dietas, cumpleaños, si quieren sumar el paseo a caballo"><?= e($_POST['mensaje'] ?? '') ?></textarea></label>

        <button class="sub" onclick="lrTrack('click_rsvp','Enviar formulario verano')">Enviar consulta</button>
      </form>

      <p class="nota">O escribinos directo por
        <a href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>" target="_blank" rel="noopener"
           onclick="lrTrack('click_wa','WhatsApp formulario')">WhatsApp <?= e($v27waTxt) ?></a>
      </p>
    </div>
  </div>
</section>

<!-- ══════════════ CIERRE ══════════════ -->
<!-- ══════════════ CONTACTO ══════════════ -->
<section class="alt" id="contacto">
  <div class="env" style="text-align:center">
    <p class="rot-s">Contacto</p>
    <h2><?= e(lrAjuste('v27_contacto_nombre', 'Jonathan Ramírez')) ?></h2>
    <p class="lede" style="margin:0 auto 22px"><?= e(lrAjuste('v27_contacto_cargo',
       'Coordina las reservas del Family Plan y de los encuentros del verano.')) ?></p>
    <div class="btns">
      <a class="btn btn-p" href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>"
         target="_blank" rel="noopener" onclick="lrTrack('click_wa','WhatsApp contacto')">WhatsApp <?= e($v27waTxt) ?></a>
      <a class="btn btn-s" href="mailto:<?= e(lrAjuste('v27_contacto_mail','Jonathanr@fasedos.com.ar')) ?>?subject=<?= rawurlencode('Consulta Verano 2027') ?>"
         onclick="lrTrack('click_url','Mail contacto')"><?= e(lrAjuste('v27_contacto_mail','Jonathanr@fasedos.com.ar')) ?></a>
    </div>
  </div>
</section>

<div class="cierre">
  <div class="env">
    <h2 style="margin-bottom:10px">La Reserva</h2>
    <p class="lede" style="margin:0 auto 26px">Naturaleza y arte, a una hora de Buenos Aires.</p>
    <div class="btns">
      <a class="btn btn-s" href="index.php" onclick="lrTrack('click_url','Ver el sitio')">Ver el sitio completo</a>
      <a class="btn btn-s" href="retiros.php" onclick="lrTrack('click_url','Ver la agenda')">Ver la agenda</a>
    </div>
  </div>
</div>

<div class="pie">
  <div class="env">
    La Reserva Naturaleza Arte · Capilla del Señor, Buenos Aires<br>
    <a href="https://letsmingle.ar/" target="_blank" rel="noopener">Made by Carlos Verbelen</a>
  </div>
</div>

<a class="wa-float" href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>"
   target="_blank" rel="noopener" aria-label="WhatsApp"
   onclick="lrTrack('click_wa','WhatsApp flotante')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.87.85-.87 2.07s.89 2.4 1.02 2.56c.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29Z"/></svg></a>


<!-- Visor: mismo formato que espacios.php. Media a la izquierda, texto y
     accion a la derecha. Se arma desde los data-* de cada figure, asi
     agregar una baldosa nueva no exige tocar el JS. -->
<div class="vl" id="vl" aria-hidden="true">
  <div class="vl-box">
    <div class="vl-media" id="vlMedia">
      <img id="vlImg" src="" alt="">
      <video id="vlVid" muted loop playsinline></video>
      <button class="vl-x" id="vlX" aria-label="Cerrar">&times;</button>
      <button class="vl-nav vl-prev" id="vlPrev" aria-label="Anterior">&larr;</button>
      <button class="vl-nav vl-next" id="vlNext" aria-label="Siguiente">&rarr;</button>
      <button class="vl-tog" id="vlTog">Ver video</button>
      <span class="vl-ctr" id="vlCtr"></span>
    </div>
    <div class="vl-info">
      <p class="rot-s">Verano 2027</p>
      <h3 id="vlT"></h3>
      <p id="vlD"></p>
      <!-- v3.2: los datos sueltos, como en espacios.php. Salen de data-datos
           separados por barra, asi agregar una baldosa no exige tocar el JS. -->
      <ul class="vl-datos" id="vlDatos"></ul>
      <div class="btns" style="justify-content:flex-start">
        <a class="btn btn-p" href="#reservar" id="vlRes">Reservar mi fecha</a>
        <a class="btn btn-s" href="https://wa.me/<?= e($v27wa) ?>?text=<?= $waTexto ?>" target="_blank" rel="noopener"
           onclick="lrTrack('click_wa','WhatsApp visor')">WhatsApp</a>
      </div>
    </div>
  </div>
</div>

<script>
/* Beacon propio: esta pagina no incluye footer.php, asi que lleva su copia.
   sendBeacon sobrevive a que el navegador se vaya a WhatsApp; un fetch
   normal se cancela. boton='verano2027' en todos los eventos para poder
   medir la campana entera por separado en el panel. */
function lrTrack(tipo, detalle){
  var body = JSON.stringify({tipo: tipo, boton: 'verano2027', detalle: detalle || null});
  try { if (navigator.sendBeacon) { navigator.sendBeacon('track.php', new Blob([body], {type:'application/json'})); return true; } } catch(e){}
  try { fetch('track.php', {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true}); } catch(e){}
  return true;
}
var WA_V27 = <?= json_encode($v27wa) ?>;
lrTrack('abrir_espacio','Landing Verano 2027');


/* ── Calculadora ────────────────────────────────────────────────────
   Los precios vienen del HTML que genero PHP, no estan escritos aca:
   cambiarlos en el panel alcanza para que la cuenta cambie. */
(function(){
  var caja = document.getElementById('calc');
  if (!caja) return;
  var P = {
    1: {n: <?= json_encode(lrAjuste('v27_p1_nombre','3 días y 2 noches')) ?>, ad: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p1_adulto','442.000')) ?>, me: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p1_menor','221.000')) ?>, be: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p1_bebe','46.000')) ?>},
    2: {n: <?= json_encode(lrAjuste('v27_p2_nombre','4 días y 3 noches')) ?>, ad: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p2_adulto','616.000')) ?>, me: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p2_menor','308.000')) ?>, be: <?= (int)preg_replace('/\D/','',lrAjuste('v27_p2_bebe','52.000')) ?>}
  };
  var plan = 1;
  var $c = function(i){ return document.getElementById(i); };

  function num(s){ return parseInt(String(s).replace(/\D/g,''), 10) || 0; }
  function plata(n){ return '$' + n.toLocaleString('es-AR'); }

  function recalcular(){
    var ad = Math.max(0, num($c('c-ad').value)),
        me = Math.max(0, num($c('c-me').value)),
        be = Math.max(0, num($c('c-be').value));
    var p = P[plan];
    var filas = [], total = 0;
    [[ad,'Adulto','Adultos',p.ad],[me,'Chico de 4 a 10','Chicos de 4 a 10',p.me],
     [be,'Chico de 1 a 3','Chicos de 1 a 3',p.be]].forEach(function(f){
      if (!f[0]) return;
      var sub = f[0] * f[3];
      total += sub;
      filas.push('<div><span>' + f[0] + ' ' + (f[0] === 1 ? f[1] : f[2])
               + ' x ' + plata(f[3]) + '</span><em>' + plata(sub) + '</em></div>');
    });
    $c('calc-det').innerHTML = filas.join('') ||
      '<div style="color:#4a6a85">Elegí cuántos son para ver el total.</div>';
    $c('calc-total').textContent = plata(total);

    var texto = 'Hola, quiero consultar por el Family Plan de Verano 2027.\n'
      + p.n + '\n'
      + (ad ? ad + ' adulto' + (ad > 1 ? 's' : '') + '\n' : '')
      + (me ? me + ' chico' + (me > 1 ? 's' : '') + ' de 4 a 10\n' : '')
      + (be ? be + ' chico' + (be > 1 ? 's' : '') + ' de 1 a 3\n' : '')
      + 'Total estimado: ' + plata(total);
    $c('calc-wa').href = 'https://wa.me/' + WA_V27 + '?text=' + encodeURIComponent(texto);
  }

  caja.querySelectorAll('.calc-plan').forEach(function(b){
    b.addEventListener('click', function(){
      caja.querySelectorAll('.calc-plan').forEach(function(x){ x.classList.remove('on'); });
      b.classList.add('on'); plan = +b.dataset.plan; recalcular();
    });
  });
  caja.querySelectorAll('.calc-num button').forEach(function(b){
    b.addEventListener('click', function(){
      var i = $c(b.dataset.campo);
      i.value = Math.max(0, Math.min(40, num(i.value) + (+b.dataset.paso)));
      recalcular();
    });
  });
  ['c-ad','c-me','c-be'].forEach(function(i){
    $c(i).addEventListener('input', recalcular);
  });
  recalcular();
})();

/* ── Visor ──────────────────────────────────────────────────────────── */
(function(){
  var figs = Array.prototype.slice.call(document.querySelectorAll('.mosaico figure'));
  var vl = document.getElementById('vl'), img = document.getElementById('vlImg'),
      vid = document.getElementById('vlVid'), media = document.getElementById('vlMedia'),
      t = document.getElementById('vlT'), d = document.getElementById('vlD'),
      ctr = document.getElementById('vlCtr'), tog = document.getElementById('vlTog');
  var cur = 0;
  function show(i){
    cur = (i + figs.length) % figs.length;
    var f = figs[cur];
    img.src = f.dataset.img; img.alt = f.dataset.t;
    t.textContent = f.dataset.t; d.textContent = f.dataset.d;
    var ul = document.getElementById('vlDatos');
    ul.innerHTML = '';
    (f.dataset.datos || '').split('|').filter(Boolean).forEach(function(x){
      var li = document.createElement('li'); li.textContent = x.trim(); ul.appendChild(li);
    });
    ctr.textContent = (cur+1) + ' / ' + figs.length;
    vid.pause(); vid.removeAttribute('src'); vid.load();
    media.classList.remove('conv');
    if (f.dataset.vid) {
      vid.src = f.dataset.vid; tog.style.display = 'block'; tog.textContent = 'Ver video';
    } else { tog.style.display = 'none'; }
  }
  function open(i){ show(i); vl.classList.add('open'); vl.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
    lrTrack('abrir_espacio', 'Visor ' + figs[cur].dataset.t); }
  function close(){ vl.classList.remove('open'); vl.setAttribute('aria-hidden','true'); document.body.style.overflow=''; vid.pause(); }
  figs.forEach(function(f,i){ f.addEventListener('click', function(){ open(i); }); });
  document.getElementById('vlX').onclick = close;
  document.getElementById('vlPrev').onclick = function(){ show(cur-1); };
  document.getElementById('vlNext').onclick = function(){ show(cur+1); };
  document.getElementById('vlRes').onclick = close;
  tog.onclick = function(){
    var on = media.classList.toggle('conv');
    if (on) { var p = vid.play(); if(p&&p.catch) p.catch(function(){}); tog.textContent = 'Ver foto'; }
    else { vid.pause(); tog.textContent = 'Ver video'; }
  };
  vl.addEventListener('click', function(e){ if (e.target === vl) close(); });
  document.addEventListener('keydown', function(e){
    if (!vl.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(cur-1);
    if (e.key === 'ArrowRight') show(cur+1);
  });
})();

/* Mosaicos: video al pasar el mouse en escritorio, al entrar en pantalla en
   touch. Mismo criterio que index.php y espacios.php. */
(function(){
  var touch = window.matchMedia('(hover: none)').matches;
  document.querySelectorAll('.mosaico figure').forEach(function(f){
    var v = f.querySelector('video'); if(!v) return;
    function play(){ var p = v.play(); if(p && p.catch) p.catch(function(){}); }
    if (touch) {
      v.preload = 'metadata';
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function(es){ es.forEach(function(e){ e.isIntersecting ? play() : v.pause(); }); }, {threshold:.45}).observe(f);
      } else play();
      return;
    }
    f.addEventListener('mouseenter', play);
    f.addEventListener('mouseleave', function(){ v.pause(); try{ v.currentTime = 0; }catch(e){} });
  });
})();

/* El video del hero: si el navegador bloquea el autoplay, queda el poster,
   que ya esta debajo. Por eso el <img> va primero en el DOM. */
(function(){
  var v = document.querySelector('.hero video');
  if(!v) return;
  function arrancar(){
    v.muted = true;
    var p = v.play();
    if (p && p.catch) p.catch(function(){ v.style.display = 'none'; });
  }
  arrancar();
  /* iOS en Modo de bajo consumo rechaza el autoplay. El primer toque sirve
     como el gesto que pide el navegador. */
  ['touchstart','click'].forEach(function(ev){
    document.addEventListener(ev, function r(){
      if (v.paused){ v.style.display=''; arrancar(); }
      document.removeEventListener(ev, r);
    }, {once:true, passive:true});
  });
})();
</script>
</body>
</html>
