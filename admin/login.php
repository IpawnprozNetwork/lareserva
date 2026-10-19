<?php
/* admin/login.php - VERSION 1.1 */
require_once __DIR__.'/../includes/auth.php';
if (sesionIniciada()) redirigir('index.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? null)) {
        $error = 'La sesión expiró. Intentá de nuevo.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $clave = (string)($_POST['clave'] ?? '');
        $db = getDB();
        if (!$db) {
            $error = 'Sin conexión a la base de datos. Revisá includes/config.php.';
        } else {
            $st = $db->prepare("SELECT * FROM usuarios WHERE email=:e AND activo=1 LIMIT 1");
            $st->execute([':e' => $email]);
            $u = $st->fetch();
            if ($u && password_verify($clave, $u['pass_hash'])) {
                session_regenerate_id(true);
                $_SESSION['lr_uid']    = $u['id'];
                $_SESSION['lr_nombre'] = $u['nombre'];
                $_SESSION['lr_rol']    = $u['rol'];
                $db->prepare("UPDATE usuarios SET ultimo_ingreso=NOW() WHERE id=:i")->execute([':i' => $u['id']]);
                redirigir('index.php');
            }
            $error = 'E-mail o clave incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Acceso · La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;font-family:'Karla',sans-serif;background:#0a1628;color:#e8f0f7}

/* Video background */
.vid-bg{position:fixed;inset:0;z-index:0}
.vid-bg video{width:100%;height:100%;object-fit:cover;opacity:.3}
.vid-bg::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,22,40,.92),rgba(10,22,40,.78))}

/* Layout */
.wrap{position:relative;z-index:1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px}

/* Card */
.card{width:100%;max-width:420px;background:rgba(17,34,51,.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(0,180,150,.2);border-radius:8px;padding:40px 36px}

/* Brand */
.brand{font-family:'Fraunces',serif;font-weight:300;font-size:1.3rem;color:#e8f0f7;margin-bottom:6px}
.brand span{display:block;font-family:'Karla',sans-serif;font-size:.62rem;letter-spacing:.24em;color:#4a6a85;margin-top:3px}

/* Tag */
.tag{font-size:.68rem;letter-spacing:.18em;color:#00c9a7;text-transform:uppercase;margin:24px 0 6px;font-weight:600}

/* Heading */
h1{font-family:'Fraunces',serif;font-size:1.8rem;font-weight:300;color:#e8f0f7;margin-bottom:6px}
.sub{font-size:.88rem;color:#4a6a85;margin-bottom:28px}

/* Error */
.aviso{padding:12px 16px;background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.3);border-radius:4px;color:#ff8a80;font-size:.86rem;margin-bottom:20px}

/* Fields */
.field{margin-bottom:16px}
.field label{display:block;font-size:.76rem;letter-spacing:.06em;color:#4a6a85;margin-bottom:6px}
.field input{width:100%;font:inherit;font-size:.94rem;color:#e8f0f7;background:rgba(10,22,40,.6);border:1px solid rgba(0,180,150,.2);border-radius:4px;padding:12px 14px;transition:border-color .18s}
.field input:focus{outline:none;border-color:#00c9a7;background:rgba(10,22,40,.8)}
.field input::placeholder{color:#4a6a85}

/* Submit */
.btn-submit{width:100%;padding:14px;background:#00c9a7;color:#0a1628;border:0;border-radius:4px;font:inherit;font-size:.9rem;font-weight:700;letter-spacing:.08em;cursor:pointer;transition:background .2s;margin-top:4px}
.btn-submit:hover{background:#00b898}

/* Divider */
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#4a6a85;font-size:.78rem}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(0,180,150,.15)}

/* Back link */
.btn-back{display:block;text-align:center;font-size:.84rem;color:#4a6a85;text-decoration:none;padding:12px;border:1px solid rgba(0,180,150,.18);border-radius:4px;transition:.18s}
.btn-back:hover{border-color:rgba(0,201,167,.4);color:#00c9a7}
.btn-olvide{display:block;text-align:center;margin-bottom:10px;font-size:.86rem;
  color:#7a9ab8;text-decoration:none}
.btn-olvide:hover{color:#00c9a7}
</style>
</head>
<body>

<!-- Video background -->
<div class="vid-bg">
  <video autoplay muted loop playsinline preload="metadata" poster="../assets/video/hero-poster.jpg">
    <source src="../assets/video/hero.mp4" type="video/mp4">
  </video>
</div>

<div class="wrap">
  <div class="card">
    <div class="brand">La Reserva<span>NATURALEZA ARTE · PANEL</span></div>

    <p class="tag">Acceso seguro</p>
    <h1>Bienvenido</h1>
    <p class="sub">Ingresá con tu e-mail y clave del equipo.</p>

    <?php if ($error): ?>
      <div class="aviso"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <div class="field">
        <label for="em">E-mail</label>
        <input id="em" type="email" name="email" required autofocus placeholder="tu@email.com"
               value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>">
      </div>
      <div class="field">
        <label for="cl">Clave</label>
        <input id="cl" type="password" name="clave" required placeholder="••••••••••">
      </div>
      <button type="submit" class="btn-submit">ENTRAR AL PANEL</button>
    </form>

    <div class="divider">o</div>
    <!-- v1.1: link a recuperar clave. -->
    <a href="recuperar.php" class="btn-olvide">Olvidaste la clave</a>
    <a href="../index.php" class="btn-back">← Volver al sitio público</a>
  </div>
</div>

</body>
</html>
