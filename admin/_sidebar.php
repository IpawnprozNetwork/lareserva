<?php
/* admin/_sidebar.php - VERSION 2.8
   The one sidebar for every admin page.

   Replaces the <nav class="sb" id="sb">...</nav>
<!-- v2.3: credito al pie del sidebar, igual que en el sitio publico. -->
<a class="sb-credito" href="https://letsmingle.ar/" target="_blank" rel="noopener">Made by Carlos Verbelen</a> block in each page with:

       <?php include __DIR__.'/_sidebar.php'; ?>

   2.0 is rebuilt from the live admin/index.php markup, so the classes and the
   look are exactly the ones already on the site: .sb, .sb-brand, .sb-user,
   .sb-avatar, .sb-view, .sb-section, .sb-nav, .sb-badge, .sb-ext, .sb-foot.
   The style block below repeats those rules with literal colours instead of
   CSS variables, because retiros.php and espacios.php never defined
   --teal-dim or --teal-b and would render the user chip unstyled otherwise.

   Also emits the mobile hamburger, which only index.php had.

   Active item is worked out from the filename. Nothing to pass in.

   2.1: Editor de galeria dropped from the menu. media.php covers it. The file
   itself is untouched and still reachable at admin/galeria-editor.php.

   2.2: Referidos added under Contenido.
   2.4: Portada added under Contenido, plus the letsmingle credit at the foot. */

if (!function_exists('usuarioActual')) require_once __DIR__.'/../includes/auth.php';

$lrPag = basename($_SERVER['SCRIPT_NAME'] ?? '');
$lrU   = usuarioActual();
$lrRol = $lrU['rol'] ?? '';
$lrNom = $lrU['nombre'] ?: 'Usuario';

/* mbstring is normally present on cPanel, but this include runs on every
   admin page: a fatal here takes down the whole panel, so do not rely on it. */
$lrIni = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($lrNom, 0, 1), 'UTF-8')
    : strtoupper(substr($lrNom, 0, 1));

/* Badges. Both stay silent if their table is not there yet. */
$lrSinLeer = 0; $lrNuevas = 0;
if (function_exists('getDB') && ($lrDb = getDB())) {
    try { $lrSinLeer = (int)$lrDb->query("SELECT COUNT(*) FROM mensajes WHERE leido = 0")->fetchColumn(); }
    catch (Exception $e) {}
    try { $lrNuevas = (int)$lrDb->query("SELECT COUNT(*) FROM consultas WHERE estado = 'nueva'")->fetchColumn(); }
    catch (Exception $e) {}
}

function lrNav(string $file, string $label, string $actual, int $badge = 0, bool $ext = false): void {
    $href = $ext ? '../'.$file : $file;
    $on   = (!$ext && $file === $actual) ? ' class="active"' : '';
    $tgt  = $ext ? ' target="_blank" rel="noopener"' : '';
    echo '<li><a href="'.htmlspecialchars($href, ENT_QUOTES).'"'.$on.$tgt.'>'
       . htmlspecialchars($label, ENT_QUOTES)
       . ($ext ? ' <span class="sb-ext">&#8599;</span>' : '')
       . ($badge > 0 ? '<span class="sb-badge">'.$badge.'</span>' : '')
       . '</a></li>';
}
?>
<style>
.sb{width:230px;flex-shrink:0;background:#0a1628;border-right:1px solid rgba(0,180,150,.15);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb a{text-decoration:none}
.sb-brand{padding:16px 16px 13px;border-bottom:1px solid rgba(0,180,150,.15)}
.sb-brand .name{font-family:'Fraunces',Georgia,serif;font-weight:300;font-size:1.05rem;color:#e8f0f7}
.sb-brand .sub{font-size:.58rem;letter-spacing:.22em;color:#4a6a85;margin-top:3px;text-transform:uppercase}
.sb-user{padding:13px 16px;border-bottom:1px solid rgba(0,180,150,.15);display:flex;align-items:center;gap:11px}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:rgba(0,201,167,.12);border:1px solid rgba(0,201,167,.22);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#00c9a7;flex-shrink:0;letter-spacing:.04em}
.sb-name{font-size:.86rem;font-weight:600;color:#e8f0f7;line-height:1.3}
.sb-role{font-size:.63rem;color:#4a6a85;letter-spacing:.1em;text-transform:uppercase}
.sb-view{margin:10px 10px 2px;padding:8px 12px;background:rgba(0,201,167,.12);border:1px solid rgba(0,201,167,.22);border-radius:4px;color:#00c9a7;font:inherit;font-size:.78rem;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:.2s;width:calc(100% - 20px)}
.sb-view:hover{background:rgba(0,201,167,.2)}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:#4a6a85;font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 2px;margin:0}
.sb-nav li a{display:flex;align-items:center;justify-content:space-between;padding:7px 10px;color:#7a9ab8;font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s;gap:6px}
.sb-nav li a:hover{background:rgba(0,201,167,.12);color:#e8f0f7}
.sb-nav li a.active{background:rgba(0,201,167,.12);color:#00c9a7;border-left-color:#00c9a7;font-weight:500}
.sb-badge{background:rgba(255,82,82,.2);color:#ff5252;font-size:.64rem;padding:2px 7px;border-radius:20px;font-weight:700;flex-shrink:0}
.sb-ext{font-size:.68rem;color:#4a6a85}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid rgba(0,180,150,.15)}
.sb-foot a{display:block;font-size:.78rem;color:#4a6a85;padding:3px 0;transition:.18s}
.sb-foot a:hover{color:#7a9ab8}
.sb-toggle{display:none;position:fixed;top:11px;left:11px;z-index:100;width:38px;height:38px;background:#0a1628;border:1px solid rgba(0,180,150,.15);border-radius:4px;color:#e8f0f7;font-size:18px;cursor:pointer;align-items:center;justify-content:center}
@media(max-width:768px){
  .sb-toggle{display:flex}
  .sb{position:fixed;top:0;left:0;height:100vh;z-index:90;transform:translateX(-100%);transition:transform .25s ease;width:240px}
  .sb.open{transform:none}
}
.sb-credito{display:block;margin:18px 20px 0;padding-top:14px;border-top:1px solid #16293a;
  font-size:.66rem;letter-spacing:.1em;text-transform:uppercase;color:#3d5a72;text-decoration:none}
.sb-credito:hover{color:#00c9a7}
</style>

<button class="sb-toggle" id="sbt" onclick="document.getElementById('sb').classList.toggle('open')">&#9776;</button>

<nav class="sb" id="sb">

  <div class="sb-brand">
    <div class="name">La Reserva</div>
    <div class="sub">Naturaleza Arte &middot; Panel</div>
  </div>

  <div class="sb-user">
    <div class="sb-avatar"><?= htmlspecialchars($lrIni, ENT_QUOTES) ?></div>
    <div>
      <div class="sb-name"><?= htmlspecialchars($lrNom, ENT_QUOTES) ?></div>
      <div class="sb-role"><?= htmlspecialchars($lrRol, ENT_QUOTES) ?></div>
    </div>
  </div>

  <button class="sb-view" onclick="window.open('../index.php','_blank')">Ver el sitio <span>&#8599;</span></button>

  <div class="sb-section">Principal</div>
  <ul class="sb-nav">
    <?php
      lrNav('index.php', 'Dashboard', $lrPag);
      lrNav('inbox.php', 'Inbox', $lrPag, $lrSinLeer);
    ?>
  </ul>

  <div class="sb-section">Contenido</div>
  <ul class="sb-nav">
    <?php
      lrNav('portada.php',        'Portada',           $lrPag);
      /* v2.7: editor de la landing de temporada. Cuando termine el verano
         se saca esta linea y el archivo. */
      if (is_file(__DIR__.'/verano.php')) lrNav('verano.php', 'Verano 2027', $lrPag);
      /* v2.8: armador de flyers. Vive en Herramientas porque no es contenido
         del sitio: es material para imprimir y para redes. */
      lrNav('retiros.php',        'Retiros',           $lrPag);
      lrNav('espacios.php',       'Espacios',          $lrPag);
      lrNav('consultas.php',      'Consultas',         $lrPag, $lrNuevas);
      lrNav('referidos.php',      'Referidos',         $lrPag);
      lrNav('galeria.php',        'Galeria',           $lrPag, 0, true);
      if (in_array($lrRol, ['admin','superadmin'], true)) lrNav('usuarios.php', 'Usuarios', $lrPag);
    ?>
  </ul>

  <div class="sb-section">Herramientas</div>
  <ul class="sb-nav">
    <?php
      lrNav('media.php', 'Imágenes y videos', $lrPag);
      if (is_file(__DIR__.'/videos.php'))    lrNav('videos.php',    'Gestor de videos',    $lrPag);
      /* v2.5: Trafico y seguridad solo para superadmin: muestra IPs. */
      /* v2.6: dos niveles. Admin ve Trafico, que son los mismos numeros sin
         datos personales. Superadmin ve ademas Trafico y seguridad, con las
         IPs y el bloqueo. */
      if ($lrRol === 'superadmin') { if (is_file(__DIR__.'/seguridad.php')) lrNav('seguridad.php', 'Trafico y seguridad', $lrPag); }
      elseif (is_file(__DIR__.'/trafico.php'))                               lrNav('trafico.php',   'Trafico',             $lrPag);
      if (is_file(__DIR__.'/flyer.php')) lrNav('flyer.php', 'Armar un flyer', $lrPag);
    ?>
  </ul>

  <div class="sb-foot">
      <a href="../retiros.php" target="_blank" rel="noopener">Ver agenda publica</a>
    <a href="logout.php" style="color:#ff5252">Cerrar sesion</a>
  </div>

</nav>
