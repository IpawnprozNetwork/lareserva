<?php
/* admin/recuperar.php - VERSION 1.0
   Recuperacion de clave del panel.

   Dos pantallas en un archivo:
     sin ?t=   -> pedis el link, se manda al mail
     con ?t=   -> elegis la clave nueva

   El token es aleatorio de 64 caracteres, sirve una sola vez y vence en 60
   minutos. Se guarda en la tabla `recuperaciones`.

   OJO CON EL CORREO: mail() no es confiable en este hosting. Por eso la
   pantalla nunca promete que el mail llego, y existe un camino alternativo:
   otro usuario con rol admin puede generar el link desde admin/usuarios.php
   y pasartelo por WhatsApp. Si nadie puede entrar al panel, la unica salida
   es cambiar el hash desde phpMyAdmin. Esta escrito en el BIBLE. */

require_once __DIR__ . '/../includes/config.php';

ini_set('display_errors', '0');

$db    = getDB();
$aviso = null;
$token = trim($_GET['t'] ?? '');
$modo  = $token !== '' ? 'clave' : 'pedir';
$fila  = null;

/* ── Validar el token antes de mostrar nada ──────────────────────────── */
if ($modo === 'clave') {
    try {
        $st = $db->prepare(
            "SELECT r.id, r.usuario_id, u.email, u.nombre
               FROM recuperaciones r
               JOIN usuarios u ON u.id = r.usuario_id
              WHERE r.token = :t AND r.usado IS NULL AND r.expira > NOW()
                AND u.activo = 1
              LIMIT 1");
        $st->execute([':t' => $token]);
        $fila = $st->fetch();
    } catch (Throwable $e) { error_log('[LR recuperar] '.$e->getMessage()); }

    if (!$fila) {
        $modo  = 'pedir';
        $aviso = ['error', 'Ese link ya se uso o vencio. Pedi uno nuevo.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST)) {
        $aviso = ['error', 'El formulario llego vacio. Proba de nuevo.'];

    } elseif (!csrfOk($_POST['csrf'] ?? '')) {
        $aviso = ['error', 'La sesion expiro. Recarga la pagina.'];

    /* ── Paso 1: pedir el link ──────────────────────────────────────── */
    } elseif (($_POST['accion'] ?? '') === 'pedir') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        /* La respuesta es siempre la misma, exista o no la cuenta. Si
           dijera "ese mail no esta registrado", cualquiera podria averiguar
           que direcciones tienen acceso al panel. */
        $generico = 'Si esa direccion tiene una cuenta activa, va a recibir un link para cambiar la clave. Revisa tambien el correo no deseado.';

        if (!$email) {
            $aviso = ['error', 'Escribi una direccion de e-mail valida.'];
        } else {
            try {
                /* Limite: un pedido cada 2 minutos por cuenta, para que nadie
                   use esto para inundar una casilla de mails. */
                $st = $db->prepare(
                    "SELECT u.id, u.nombre,
                            (SELECT MAX(creado) FROM recuperaciones WHERE usuario_id = u.id) AS ultimo
                       FROM usuarios u WHERE u.email = :e AND u.activo = 1 LIMIT 1");
                $st->execute([':e' => $email]);
                $u = $st->fetch();

                if ($u && (!$u['ultimo'] || strtotime($u['ultimo']) < time() - 120)) {
                    $tk = bin2hex(random_bytes(32));
                    $db->prepare(
                        "INSERT INTO recuperaciones (usuario_id, token, expira, ip_txt)
                         VALUES (:u, :t, DATE_ADD(NOW(), INTERVAL 60 MINUTE), :ip)")
                       ->execute([
                         ':u'  => (int)$u['id'],
                         ':t'  => $tk,
                         ':ip' => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
                       ]);

                    $link = 'https://' . SITE_DOMAIN . '/admin/recuperar.php?t=' . $tk;
                    $cuerpo =
                        "Hola " . ($u['nombre'] ?: '') . ",\n\n" .
                        "Pediste cambiar la clave del panel de La Reserva.\n\n" .
                        "Entra en este link, vence en una hora:\n$link\n\n" .
                        "Si no fuiste vos, ignora este mensaje: la clave actual sigue funcionando.\n";
                    @mail($email, 'Cambiar la clave del panel - La Reserva', $cuerpo,
                          "From: " . SITE_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8");
                }
                $aviso = ['ok', $generico];
            } catch (Throwable $e) {
                error_log('[LR recuperar] '.$e->getMessage());
                $aviso = ['ok', $generico];
            }
        }

    /* ── Paso 2: guardar la clave nueva ─────────────────────────────── */
    } elseif (($_POST['accion'] ?? '') === 'cambiar' && $fila) {
        $c1 = (string)($_POST['clave1'] ?? '');
        $c2 = (string)($_POST['clave2'] ?? '');

        if (mb_strlen($c1) < 10) {
            $aviso = ['error', 'La clave tiene que tener al menos 10 caracteres.'];
        } elseif ($c1 !== $c2) {
            $aviso = ['error', 'Las dos claves no coinciden.'];
        } else {
            try {
                $db->beginTransaction();
                $db->prepare("UPDATE usuarios SET pass_hash = :h WHERE id = :u")
                   ->execute([':h' => password_hash($c1, PASSWORD_DEFAULT), ':u' => (int)$fila['usuario_id']]);
                /* Se invalidan TODOS los pedidos de esa cuenta, no solo este:
                   si alguien pidio dos links, el otro tambien queda muerto. */
                $db->prepare("UPDATE recuperaciones SET usado = NOW() WHERE usuario_id = :u AND usado IS NULL")
                   ->execute([':u' => (int)$fila['usuario_id']]);
                $db->commit();
                $modo  = 'listo';
                $aviso = ['ok', 'Clave cambiada. Ya podes entrar al panel.'];
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('[LR recuperar] '.$e->getMessage());
                $aviso = ['error', 'No se pudo guardar la clave. Proba de nuevo.'];
            }
        }
    }
}

/* Limpieza: los tokens vencidos hace mas de un dia no sirven para nada. */
try { $db->exec("DELETE FROM recuperaciones WHERE expira < (NOW() - INTERVAL 1 DAY)"); }
catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- admin/recuperar.php VERSION 1.0 -->
<title>Recuperar clave · Panel La Reserva</title>
<meta name="robots" content="noindex,nofollow">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a1628;color:#e8f0f7;font-family:'Karla',sans-serif;min-height:100vh;
  display:flex;align-items:center;justify-content:center;padding:24px}
.caja{width:100%;max-width:430px;background:#112233;border:1px solid rgba(0,201,167,.18);
  border-radius:16px;padding:38px 34px}
.marca{font:300 1.6rem 'Fraunces',Georgia,serif;margin-bottom:2px}
.marca small{display:block;font-family:'Karla',sans-serif;font-size:.64rem;letter-spacing:.22em;
  text-transform:uppercase;color:#4a6a85;margin-top:4px}
.rot{font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;color:#00c9a7;margin:26px 0 8px;font-weight:600}
h1{font:300 1.7rem 'Fraunces',Georgia,serif;margin-bottom:8px}
p.sub{color:#7a9ab8;font-size:.92rem;line-height:1.65;margin-bottom:22px}
label{display:block;margin-bottom:16px}
label span{display:block;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:#7a9ab8;margin-bottom:6px}
input{width:100%;background:#0a1420;border:1px solid #1c3247;border-radius:8px;color:#e8f0f7;
  font:inherit;font-size:.95rem;padding:12px 13px}
input:focus{outline:0;border-color:#00c9a7}
.btn{width:100%;background:#00c9a7;color:#0a1628;border:0;border-radius:8px;padding:13px;
  font:inherit;font-weight:700;font-size:.9rem;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.btn:hover{background:#00e0ba}
.av{padding:12px 15px;border-radius:0 8px 8px 0;margin-bottom:20px;font-size:.9rem;line-height:1.6}
.av.ok{background:#08161a;border:1px solid #123a35;border-left:3px solid #00c9a7;color:#8fb5ad}
.av.error{background:#170a0f;border:1px solid #3d1622;border-left:3px solid #f0398b;color:#c49aa6}
.pie{text-align:center;margin-top:22px;padding-top:18px;border-top:1px solid #1c3247;font-size:.84rem}
.pie a{color:#7a9ab8;text-decoration:none}.pie a:hover{color:#00c9a7}
.nota{font-size:.8rem;color:#4a6a85;line-height:1.6;margin-top:14px}
</style>
</head>
<body>
<div class="caja">
  <div class="marca">La Reserva<small>Naturaleza Arte · Panel</small></div>

  <?php if ($modo === 'listo'): ?>
    <p class="rot">Listo</p>
    <h1>Clave cambiada</h1>
    <?php if ($aviso): ?><div class="av <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <a class="btn" href="login.php" style="display:block;text-align:center;text-decoration:none;line-height:1.4">Entrar al panel</a>

  <?php elseif ($modo === 'clave'): ?>
    <p class="rot">Clave nueva</p>
    <h1>Elegi tu clave</h1>
    <p class="sub">Para <strong><?= e($fila['email']) ?></strong>. Minimo 10 caracteres.</p>
    <?php if ($aviso): ?><div class="av <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="accion" value="cambiar">
      <label><span>Clave nueva</span>
        <input type="password" name="clave1" required minlength="10" autocomplete="new-password"></label>
      <label><span>Repetila</span>
        <input type="password" name="clave2" required minlength="10" autocomplete="new-password"></label>
      <button class="btn">Guardar la clave</button>
    </form>

  <?php else: ?>
    <p class="rot">Recuperar acceso</p>
    <h1>Olvidaste la clave</h1>
    <p class="sub">Escribi tu e-mail y te mandamos un link para elegir una nueva. Vence en una hora.</p>
    <?php if ($aviso): ?><div class="av <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="accion" value="pedir">
      <label><span>E-mail</span>
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></label>
      <button class="btn">Mandar el link</button>
    </form>
    <p class="nota">Si el mail no llega en unos minutos, pedile a otra persona
    con acceso de admin que te genere el link desde Usuarios. El correo
    automatico de este servidor no siempre entrega.</p>
  <?php endif; ?>

  <div class="pie"><a href="login.php">&larr; Volver al login</a></div>
</div>
</body>
</html>
