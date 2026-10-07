<?php
require_once __DIR__.'/includes/config.php';
$pagina = 'acceso'; $raiz = '';

// Which portal to show
$tipo = $_GET['tipo'] ?? '';  // 'staff' or '' (guest)
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Ingresar · La Reserva</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--card:#0f2035;--card2:#112233;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.1);--teal-b:rgba(0,201,167,.2);--blue:#1a9fd4;--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg);font-family:'Karla',sans-serif;color:var(--text)}

.page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 24px;position:relative}
.page::before{content:'';position:absolute;inset:0;background:url('assets/video/hero-poster.jpg') center/cover no-repeat;opacity:.08;z-index:0}

.inner{position:relative;z-index:1;width:100%;max-width:520px}

.back{display:inline-flex;align-items:center;gap:8px;font-size:.8rem;color:var(--text3);text-decoration:none;margin-bottom:32px;transition:.2s}
.back:hover{color:var(--text2)}

.brand{font-family:'Fraunces',serif;font-weight:300;font-size:1.3rem;color:var(--text);margin-bottom:6px}
.brand span{display:block;font-family:'Karla',sans-serif;font-size:.6rem;letter-spacing:.24em;opacity:.45;margin-top:3px}

/* Portal selector */
.portals{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:28px}
.portal-card{padding:22px 20px;border:1px solid var(--border);border-radius:6px;cursor:pointer;text-decoration:none;color:var(--text);transition:.2s;display:block;position:relative}
.portal-card:hover,.portal-card.active{border-color:var(--teal-b);background:var(--teal-dim)}
.portal-card h3{font-size:1rem;font-weight:600;margin-bottom:5px;color:var(--text)}
.portal-card p{font-size:.8rem;color:var(--text3);line-height:1.5}
.portal-icon{font-size:1.5rem;margin-bottom:10px}
.portal-card.active::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--teal);border-radius:6px 6px 0 0}

/* Login form */
.login-box{background:var(--card2);border:1px solid var(--border);border-radius:8px;padding:32px;margin-top:16px}
.login-box h2{font-family:'Fraunces',serif;font-size:1.4rem;font-weight:300;margin-bottom:6px}
.login-box .sub{font-size:.86rem;color:var(--text3);margin-bottom:26px}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.field label{font-size:.78rem;letter-spacing:.06em;color:var(--text3)}
.field input{font:inherit;font-size:.94rem;color:var(--text);background:var(--card);border:1px solid var(--border);border-radius:4px;padding:12px 14px;width:100%;transition:.18s}
.field input:focus{outline:none;border-color:var(--teal)}
.aviso{padding:11px 16px;border-radius:4px;font-size:.86rem;margin-bottom:16px;background:rgba(255,82,82,.1);border:1px solid var(--red);color:var(--red)}
.btn-main{width:100%;padding:14px;background:var(--teal);color:#0a1628;border:0;border-radius:4px;font:inherit;font-size:.88rem;font-weight:600;cursor:pointer;transition:.18s;letter-spacing:.04em}
.btn-main:hover{background:#00b898}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text3);font-size:.78rem}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}

/* Guest form */
.guest-form .field input,.guest-form .field textarea,.guest-form .field select{background:var(--card);border:1px solid var(--border);border-radius:4px;padding:11px 13px;width:100%;font:inherit;font-size:.92rem;color:var(--text);transition:.18s}
.guest-form .field input:focus,.guest-form .field textarea:focus{outline:none;border-color:var(--teal)}
.guest-form textarea{min-height:90px;resize:vertical}
.note-box{background:var(--teal-dim);border:1px solid var(--teal-b);border-radius:4px;padding:14px 16px;font-size:.84rem;color:var(--text2);margin-top:16px;line-height:1.6}
</style>
</head>
<body>
<div class="page">
  <div class="inner">

    <a href="index.php" class="back">← Volver al sitio</a>

    <div class="brand">La Reserva<span>NATURALEZA ARTE</span></div>

    <!-- Portal selector -->
    <div class="portals">
      <a href="acceso.php?tipo=guest" class="portal-card <?= $tipo===''||$tipo==='guest'?'active':'' ?>">
        <div class="portal-icon">🌿</div>
        <h3>Visitantes y consultas</h3>
        <p>Contactar al equipo, consultar disponibilidad o hacer seguimiento de tu retiro</p>
      </a>
      <a href="admin/login.php" class="portal-card">
        <div class="portal-icon">🔐</div>
        <h3>Staff y administracion</h3>
        <p>Acceso para el equipo de La Reserva, facilitadores y propietarios</p>
      </a>
    </div>

    <!-- Guest section -->
    <div class="login-box guest-form">
      <h2>Contactar al equipo</h2>
      <p class="sub">Escribinos y te respondemos a la brevedad. Tambien podes escribirnos por WhatsApp.</p>

      <?php
      $enviado = false;
      $err = null;
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre   = limpiar($_POST['nombre'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $asunto   = limpiar($_POST['asunto'] ?? 'consulta-general');
        $mensaje  = limpiar($_POST['mensaje'] ?? '');
        if ($nombre && $email && $mensaje) {
          // Save to DB if available
          if ($db = getDB()) {
            try {
              $db->prepare("INSERT INTO consultas (nombre,email,tipo,mensaje,origen)
                            VALUES (:n,:e,:t,:m,'acceso')")
                 ->execute([':n'=>$nombre,':e'=>$email,':t'=>$asunto,':m'=>$mensaje]);
            } catch(Exception $ex){ error_log($ex->getMessage()); }
          }
          @mail(SITE_EMAIL, 'Consulta desde portal: '.$nombre,
            "Nombre: $nombre\nEmail: $email\nAsunto: $asunto\n\n$mensaje",
            "From: web@".SITE_DOMAIN."\r\nReply-To: $email");
          $enviado = true;
        } else {
          $err = 'Completá nombre, e-mail y mensaje.';
        }
      }
      ?>

      <?php if ($err): ?>
        <div class="aviso"><?= e($err) ?></div>
      <?php endif; ?>

      <?php if ($enviado): ?>
        <div style="background:rgba(0,201,167,.1);border:1px solid var(--teal-b);border-radius:4px;padding:20px;text-align:center;color:var(--teal)">
          <div style="font-size:1.4rem;margin-bottom:8px">✓</div>
          <strong>Mensaje recibido</strong><br>
          <span style="font-size:.86rem;color:var(--text2)">Te respondemos a la brevedad por e-mail.</span>
        </div>
      <?php else: ?>
        <form method="post">
          <div class="field">
            <label>Nombre</label>
            <input type="text" name="nombre" required placeholder="Tu nombre">
          </div>
          <div class="field">
            <label>E-mail</label>
            <input type="email" name="email" required placeholder="tu@email.com">
          </div>
          <div class="field">
            <label>Asunto</label>
            <select name="asunto" style="background:var(--card);border:1px solid var(--border);border-radius:4px;padding:11px 13px;width:100%;font:inherit;font-size:.92rem;color:var(--text)">
              <option value="organizar">Quiero organizar un retiro</option>
              <option value="participar">Quiero participar de un retiro</option>
              <option value="visita">Quiero coordinar una visita</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="field">
            <label>Mensaje</label>
            <textarea name="mensaje" required placeholder="Contanos que necesitas..."></textarea>
          </div>
          <button type="submit" class="btn-main"
                  onclick="lrBoton('click_rsvp','contacto','acceso Enviar mensaje')">Enviar mensaje</button>
        </form>
        <div class="divider">o contactanos por</div>
        <a href="<?= e(waLink('Hola, quiero consultar por La Reserva.')) ?>" target="_blank" rel="noopener"
           onclick="lrBoton('click_wa','whatsapp','acceso WhatsApp')"
           style="display:flex;align-items:center;justify-content:center;gap:10px;padding:13px;border:1px solid var(--border);border-radius:4px;color:var(--text2);font-size:.88rem;transition:.18s;text-decoration:none"
           onmouseover="this.style.borderColor='var(--teal-b)';this.style.color='var(--text)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text2)'">
          WhatsApp <?= e(SITE_WA_LABEL) ?>
        </a>
      <?php endif; ?>

      <div class="note-box">
        El equipo de La Reserva responde consultas de lunes a viernes. Para retiros urgentes, WhatsApp es la via mas rapida.
      </div>
    </div>

  </div>
</div>
<script>
/* acceso.php does not include the public footer, so it carries its own copy
   of the beacon helper. Same behaviour as includes/footer.php. */
function lrBoton(tipo, boton, detalle){
  var body = JSON.stringify({tipo: tipo, boton: boton || null, detalle: detalle || null});
  try { if (navigator.sendBeacon) { navigator.sendBeacon('track.php', new Blob([body], {type:'application/json'})); return true; } } catch(e){}
  try { fetch('track.php', {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true}); } catch(e){}
  return true;
}
</script>
</body>
</html>
