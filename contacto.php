<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/antispam.php';
$pagina = 'contacto';
$raiz   = '';

$aviso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trampa para bots: si el campo oculto vino lleno, fingimos éxito y no guardamos.
    if (!empty($_POST['apodo'])) {
        $aviso = ['ok', 'Recibimos tu consulta. Te respondemos a la brevedad.'];
    } elseif (!csrfOk($_POST['csrf'] ?? null)) {
        $aviso = ['error', 'La sesión expiró. Volvé a enviar el formulario.'];
    } else {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $tipos  = ['organizar','participar','visita','otro'];
        $tipo   = in_array($_POST['tipo'] ?? '', $tipos, true) ? $_POST['tipo'] : 'otro';

        if ($nombre === '' || !$email) {
            $aviso = ['error', 'Falta el nombre o el e-mail no es válido.'];
        } else {
            $datos = [
                'nombre'   => $nombre,
                'email'    => $email,
                'telefono' => limpiar($_POST['telefono'] ?? '') ?: null,
                'tipo'     => $tipo,
                'fecha_tentativa' => !empty($_POST['fecha_tentativa'])
                    ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_tentativa']) ? $_POST['fecha_tentativa'] : null)
                    : null,
                'personas' => !empty($_POST['personas']) ? max(1, min(500, (int)$_POST['personas'])) : null,
                'mensaje'  => limpiar($_POST['mensaje'] ?? '') ?: null,
                'origen'   => limpiar($_POST['origen'] ?? 'contacto'),
                'ip'       => @inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: null,
                'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ];

            $guardado = false;
            if ($db = getDB()) {
                try {
                    $st = $db->prepare(
                        "INSERT INTO consultas
                         (nombre,email,telefono,tipo,fecha_tentativa,personas,mensaje,origen,ip,user_agent,ref,boton)
                         VALUES (:nombre,:email,:telefono,:tipo,:fecha_tentativa,:personas,:mensaje,:origen,:ip,:ua,:ref,:boton)"
                    );
                    /* ref = where the visitor came from, boton = what they
                       clicked. Two different facts, two different columns. */
                    $datos['ref']   = $_SESSION['lr_ref'] ?? 'directo';
                    $datos['boton'] = 'contacto';
                    $st->execute($datos);
                    lrEvento($db, 'contacto_ok', ['boton'=>'contacto']);
                    $guardado = true;
                } catch (Exception $ex) { error_log('[LR consulta] '.$ex->getMessage()); }
            }

            // Aviso por mail. En cPanel conviene que el remitente sea del propio dominio.
            $cuerpo = "Nueva consulta desde ".SITE_DOMAIN."

"
                    . "Nombre: {$datos['nombre']}
"
                    . "E-mail: {$datos['email']}
"
                    . "Teléfono: ".($datos['telefono'] ?: '-')."
"
                    . "Motivo: {$datos['tipo']}
"
                    . "Fecha tentativa: ".($datos['fecha_tentativa'] ?: '-')."
"
                    . "Personas: ".($datos['personas'] ?: '-')."
"
                    . "Origen: {$datos['origen']}

"
                    . ($datos['mensaje'] ?: '(sin mensaje)')."
";
            @mail(SITE_EMAIL, 'Consulta web: '.$datos['nombre'], $cuerpo,
                  "From: web@".SITE_DOMAIN."
Reply-To: {$datos['email']}
Content-Type: text/plain; charset=UTF-8");

            $aviso = $guardado
                ? ['ok', 'Recibimos tu consulta. Te respondemos a la brevedad.']
                : ['error', 'No pudimos registrar la consulta. Escribinos por WhatsApp a '.SITE_WA_LABEL.'.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contacto · La Reserva</title>
<meta name="description" content="Consultá disponibilidad de fechas para tu retiro, taller o formación en La Reserva, Capilla del Señor.">
<meta name="robots" content="index,follow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
<?php include __DIR__.'/includes/nav.php'; ?>

<section class="bloque" style="padding-top:140px">
  <div class="envoltura">
    <?php if ($aviso): ?>
      <div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div>
    <?php endif; ?>

    <div class="dos-columnas">
      <div>
        <h1 class="titulo-bloque">Consultar fechas</h1>
        <p class="medida" style="color:var(--gris)">
          Contanos qué estás organizando, para cuántas personas y en qué fecha
          aproximada. Te respondemos con disponibilidad y una propuesta.
        </p>
        <p style="margin-top:22px">
          <a href="<?= e(waLink('Hola, quiero consultar por fechas en La Reserva.')) ?>" target="_blank" rel="noopener">WhatsApp <?= e(SITE_WA_LABEL) ?></a><br>
          <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a><br>
          <a href="https://instagram.com/<?= e(SITE_IG) ?>" target="_blank" rel="noopener">@<?= e(SITE_IG) ?></a>
        </p>
      </div>

      <form class="formulario" method="post" action="contacto.php">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="origen" value="contacto">
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
        <div class="campo ancho"><label for="ms">Mensaje</label><textarea id="ms" name="mensaje" rows="5"></textarea></div>
        <div class="campo ancho"><button type="submit" class="boton"
              onclick="lrBoton('click_rsvp','contacto','Enviar consulta')">Enviar consulta</button></div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
