<?php $raiz = $raiz ?? ''; ?>
<style>
.lr-credito{color:#7a9ab8;text-decoration:none;border-bottom:1px solid transparent;transition:color .2s,border-color .2s}
.lr-credito:hover{color:#00c9a7;border-bottom-color:rgba(0,201,167,.4)}
</style>
<!-- includes/footer.php VERSION 1.1 -->
<footer class="pie">
  <div class="envoltura">
    <div class="pie-grilla">
      <div>
        <a href="<?= $raiz ?>index.php" class="marca" style="text-decoration:none;font-family:'Fraunces',Georgia,serif;font-size:1.3rem">
          La Reserva <span style="display:block;font-family:'Karla',sans-serif;font-size:.62rem;letter-spacing:.22em;opacity:.7;margin-top:3px">NATURALEZA ARTE</span>
        </a>
        <p style="max-width:34ch;color:var(--gris);margin-top:18px">
          <?= SITE_HECTAREAS ?> hectáreas en <?= e(SITE_CIUDAD) ?>, a una hora de Buenos Aires.
        </p>
      </div>

      <div>
        <h4>Contacto</h4>
        <ul>
          <li><a href="<?= e(waLink('Hola, quiero consultar por fechas en La Reserva.')) ?>" target="_blank" rel="noopener">WhatsApp <?= e(SITE_WA_LABEL) ?></a></li>
          <li><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></li>
          <li><a href="https://instagram.com/<?= e(SITE_IG) ?>" target="_blank" rel="noopener">@<?= e(SITE_IG) ?></a></li>
        </ul>
      </div>

      <div>
        <h4>Secciones</h4>
        <ul>
          <li><a href="<?= $raiz ?>espacios.php">Espacios</a></li>
          <li><a href="<?= $raiz ?>retiros.php">Agenda de retiros</a></li>
          <li><a href="<?= $raiz ?>galeria.php">Galería</a></li>
          <li><a href="<?= $raiz ?>index.php#organizar">Organizá tu retiro</a></li>
          <li><a href="<?= $raiz ?>contacto.php">Consultar fechas</a></li>
        </ul>
      </div>
    </div>

    <div class="legal" style="color:#e8f0f7">
      <span style="color:#e8f0f7"><?= e(SITE_LEGAL) ?> © <?= date('Y') ?></span>
      <!-- v1.1: el credito ahora es un link a letsmingle.ar, en pestana nueva.
           rel="noopener" es obligatorio con target="_blank": sin eso la pagina
           destino puede tocar window.opener y redirigir esta. -->
      <a href="https://letsmingle.ar/" target="_blank" rel="noopener"
         class="lr-credito"
         onclick="lrBoton('click_url','letsmingle','Made by Carlos Verbelen')">Made by Carlos Verbelen</a>
    </div>
  </div>
</footer>

<a class="flotante" href="<?= e(waLink('Hola, quiero consultar por fechas en La Reserva.')) ?>" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.87.85-.87 2.07s.89 2.4 1.02 2.56c.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29Z"/></svg>
</a>

<script>
/* lrBoton - VERSION 1.0
   One beacon for every tagged button on the public site. Fire and forget:
   sendBeacon survives the page being left for wa.me or Instagram, fetch with
   keepalive is the fallback. Never blocks or delays the click. */
function lrBoton(tipo, boton, detalle){
  var body = JSON.stringify({tipo: tipo, boton: boton || null, detalle: detalle || null});
  try {
    if (navigator.sendBeacon) {
      navigator.sendBeacon('track.php', new Blob([body], {type: 'application/json'}));
      return true;
    }
  } catch (e) {}
  try {
    fetch('track.php', {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true});
  } catch (e) {}
  return true;
}
</script>
