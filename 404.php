<?php
require_once __DIR__.'/includes/config.php';
http_response_code(404);
$pagina=''; $raiz='';
?>
<!DOCTYPE html>
<html lang="es-AR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Página no encontrada · La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400&family=Karla:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=2">
</head><body>
<?php include __DIR__.'/includes/nav.php'; ?>
<section class="bloque" style="padding-top:150px">
  <div class="envoltura">
    <h1 class="titulo-bloque">Esa página no existe</h1>
    <p class="medida" style="color:var(--gris)">Puede que el enlace haya cambiado. Probá desde el inicio o escribinos.</p>
    <p style="margin-top:26px"><a href="/index.php" class="boton">Volver al inicio</a></p>
  </div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
</body></html>
