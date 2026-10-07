<?php
/* admin/portada.php - VERSION 1.0
   Edicion de los textos de la portada.

   Que se edita aca: solo los bloques de texto que estaban escritos a mano en
   index.php. El rotulo, el titulo, el parrafo del hero, los dos botones, el
   bloque de ubicacion y las cuatro cifras.

   Que NO se edita aca, a proposito: las fotos, los videos, los nombres y las
   descripciones de los espacios. Todo eso ya sale de la base y se edita en
   Espacios. Duplicar esos campos en dos pantallas es la forma mas rapida de
   terminar con dos versiones distintas del mismo dato.

   Los valores viven en la tabla ajustes, en filas clave/valor. Si una clave
   esta vacia, index.php usa su texto original. O sea que borrar un campo
   equivale a "volver al texto de fabrica", no a dejar la portada en blanco. */

require_once __DIR__ . '/../includes/auth.php';
exigirSesion();

/* Despues del require, porque config.php apaga display_errors al final. */
ini_set('display_errors', '0');

$db  = getDB();
$msg = '';
$err = '';

/* Los campos. clave => [etiqueta, tipo, ayuda, valor por defecto]
   El valor por defecto es lo que hoy dice index.php. */
$campos = [
  'hero_rotulo' => ['Rotulo de arriba', 'texto',
     'La linea chica sobre el titulo.',
     'CAPILLA DEL SEÑOR · A UNA HORA DE BUENOS AIRES'],
  'hero_titulo' => ['Titulo principal', 'texto',
     'El titulo grande del hero. Dos lineas cortas funcionan mejor que una larga.',
     'Un lugar para que tu retiro suceda'],
  'hero_bajada' => ['Parrafo del hero', 'area',
     'Dos o tres lineas debajo del titulo.',
     '250 hectáreas con salones, alojamiento, gastronomía y naturaleza en un mismo predio. Vos traés la propuesta; nosotros preparamos el lugar.'],
  'hero_boton1' => ['Boton principal', 'texto',
     'Lleva al formulario de contacto.', 'Organizá tu retiro'],
  'hero_boton2' => ['Boton secundario', 'texto',
     'Lleva a la agenda.', 'Ver la agenda'],

  'ubic_titulo' => ['Ubicacion, titulo', 'texto', '', 'Dónde queda'],
  'ubic_texto'  => ['Ubicacion, texto', 'area',
     'El parrafo de la seccion de ubicacion.',
     'La Reserva es un predio de 250 hectáreas en Capilla del Señor.'],

  'cifra1_n' => ['Cifra 1, numero', 'texto', '', '250'],
  'cifra1_t' => ['Cifra 1, texto',  'texto', '', 'hectáreas de parque, lago y arboleda'],
  'cifra2_n' => ['Cifra 2, numero', 'texto', '', '1 h'],
  'cifra2_t' => ['Cifra 2, texto',  'texto', '', 'desde CABA por Ruta 8'],
  'cifra3_n' => ['Cifra 3, numero', 'texto', '', 'Todo'],
  'cifra3_t' => ['Cifra 3, texto',  'texto', '', 'salón, alojamiento y comidas en el mismo predio'],
  'cifra4_n' => ['Cifra 4, numero', 'texto', '', '2027'],
  'cifra4_t' => ['Cifra 4, texto',  'texto', '', 'agenda abierta para reservar fechas'],
];

/* ── Guardar ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Cuerpo descartado por tamano: PHP tira $_POST completo cuando el body
       pasa post_max_size, asi que el token no llega y sin esto el codigo
       culparia a la sesion. Aca no deberia pasar nunca porque no hay
       archivos, pero el chequeo cuesta dos lineas. */
    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST)) {
        $err = 'El formulario llego vacio. Probablemente el texto es demasiado largo.';
    } elseif (!csrfOk($_POST['csrf'] ?? '')) {
        $err = 'La sesion expiro. Recarga la pagina y guarda de nuevo.';
    } else {
        try {
            $db->beginTransaction();
            $up = $db->prepare(
                "INSERT INTO ajustes (clave, valor) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
            );
            $n = 0;
            foreach ($campos as $clave => $def) {
                $v = trim((string)($_POST[$clave] ?? ''));
                if (mb_strlen($v) > 2000) $v = mb_substr($v, 0, 2000);
                $up->execute([$clave, $v]);
                $n++;
            }
            $db->commit();
            $msg = "Guardado. $n campos actualizados.";
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $err = 'No se pudo guardar: ' . $e->getMessage();
            error_log('[LR portada] '.$e->getMessage());
        }
    }
}

/* ── Leer lo guardado ──────────────────────────────────────────────────── */
$actual = [];
try {
    foreach ($db->query("SELECT clave, valor FROM ajustes")->fetchAll() as $r) {
        $actual[$r['clave']] = $r['valor'];
    }
} catch (Throwable $e) {
    $err = $err ?: 'No pude leer la tabla ajustes. Corre ajustes_portada.sql y volve a entrar.';
    error_log('[LR portada] '.$e->getMessage());
}

/* Cuantos espacios se ven en la portada, para el aviso de mas abajo. */
$n_esp = 0;
try {
    $n_esp = (int)$db->query("SELECT COUNT(*) FROM espacios WHERE activo=1")->fetchColumn();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Portada · Panel La Reserva</title>
<!-- admin/portada.php VERSION 1.0 -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a1628;color:#e8f0f7;font-family:'Karla',sans-serif;display:flex;min-height:100vh}
main{flex:1;min-width:0;padding:32px 34px 80px;max-width:900px}
h1{font:300 30px 'Fraunces',Georgia,serif;margin-bottom:6px}
.sub{color:#7a9ab8;font-size:.9rem;margin-bottom:24px}
h2{font:300 21px 'Fraunces',Georgia,serif;margin:30px 0 4px;padding-top:18px;border-top:1px solid #1c3247}
h2:first-of-type{border-top:0;padding-top:0;margin-top:8px}
.h2n{color:#7a9ab8;font-size:.82rem;margin-bottom:14px}
label{display:block;margin-bottom:16px}
label .et{display:block;font-size:.74rem;letter-spacing:.1em;text-transform:uppercase;color:#7a9ab8;margin-bottom:5px}
label .ay{display:block;font-size:.78rem;color:#4a6a85;margin-top:4px}
input[type=text],textarea{width:100%;background:#0a1420;border:1px solid #1c3247;border-radius:6px;
  color:#e8f0f7;font:inherit;font-size:.92rem;padding:9px 12px}
input[type=text]:focus,textarea:focus{outline:0;border-color:#00c9a7}
textarea{min-height:82px;resize:vertical;line-height:1.6}
.fila2{display:grid;grid-template-columns:120px 1fr;gap:12px}
.btn{background:#00c9a7;color:#0a1628;border:0;border-radius:6px;padding:10px 22px;font:inherit;font-weight:600;cursor:pointer}
.btn:hover{background:#00e0ba}
.btn-g{background:transparent;border:1px solid #1c3247;color:#7a9ab8;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block}
.acc{display:flex;gap:10px;align-items:center;margin-top:26px;padding-top:20px;border-top:1px solid #1c3247;position:sticky;bottom:0;background:#0a1628;padding-bottom:18px}
.av{padding:11px 15px;border-radius:0 8px 8px 0;font-size:.88rem;margin-bottom:18px}
.ok{background:#08161a;border:1px solid #123a35;border-left:3px solid #00c9a7;color:#8fb5ad}
.no{background:#170a0f;border:1px solid #3d1622;border-left:3px solid #f0398b;color:#c49aa6}
.oj{background:#181205;border:1px solid #3d2f10;border-left:3px solid #f5a623;color:#c4ab7d}
.oj a,.ok a{color:#00c9a7}
code{font:500 .8rem 'JetBrains Mono',monospace;color:#00c9a7;background:#0b1826;padding:1px 5px;border-radius:4px}
@media(max-width:900px){main{padding:70px 18px 80px}.fila2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>
<main>
  <h1>Portada</h1>
  <p class="sub">Los textos de la pagina de inicio.</p>

  <?php if ($msg): ?><div class="av ok"><?= e($msg) ?> <a href="../index.php" target="_blank" rel="noopener">Ver la portada &rarr;</a></div><?php endif; ?>
  <?php if ($err): ?><div class="av no"><?= e($err) ?></div><?php endif; ?>

  <div class="av oj">
    Las <strong>fotos, los videos, los nombres y las descripciones de los
    espacios</strong> no se editan aca: salen de la base y se cambian en
    <a href="espacios.php">Espacios</a>. Hoy hay <?= $n_esp ?> espacios visibles
    y la portada los muestra todos, en el orden que les pusiste ahi.
  </div>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <h2>Hero</h2>
    <p class="h2n">Lo primero que se ve, sobre el video.</p>
    <?php foreach (['hero_rotulo','hero_titulo','hero_bajada','hero_boton1','hero_boton2'] as $k):
      [$et,$tipo,$ay,$def] = $campos[$k]; $v = $actual[$k] ?? ''; ?>
      <label>
        <span class="et"><?= e($et) ?></span>
        <?php if ($tipo === 'area'): ?>
          <textarea name="<?= $k ?>" placeholder="<?= e($def) ?>"><?= e($v) ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= $k ?>" value="<?= e($v) ?>" placeholder="<?= e($def) ?>">
        <?php endif; ?>
        <?php if ($ay): ?><span class="ay"><?= e($ay) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>

    <h2>Ubicacion</h2>
    <p class="h2n">La seccion con el mapa.</p>
    <?php foreach (['ubic_titulo','ubic_texto'] as $k):
      [$et,$tipo,$ay,$def] = $campos[$k]; $v = $actual[$k] ?? ''; ?>
      <label>
        <span class="et"><?= e($et) ?></span>
        <?php if ($tipo === 'area'): ?>
          <textarea name="<?= $k ?>" placeholder="<?= e($def) ?>"><?= e($v) ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= $k ?>" value="<?= e($v) ?>" placeholder="<?= e($def) ?>">
        <?php endif; ?>
        <?php if ($ay): ?><span class="ay"><?= e($ay) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>

    <h2>Las cuatro cifras</h2>
    <p class="h2n">El numero grande y la linea que lo explica.</p>
    <?php for ($i = 1; $i <= 4; $i++):
      $kn = "cifra{$i}_n"; $kt = "cifra{$i}_t"; ?>
      <div class="fila2">
        <label>
          <span class="et">Cifra <?= $i ?></span>
          <input type="text" name="<?= $kn ?>" value="<?= e($actual[$kn] ?? '') ?>" placeholder="<?= e($campos[$kn][3]) ?>">
        </label>
        <label>
          <span class="et">Texto</span>
          <input type="text" name="<?= $kt ?>" value="<?= e($actual[$kt] ?? '') ?>" placeholder="<?= e($campos[$kt][3]) ?>">
        </label>
      </div>
    <?php endfor; ?>

    <div class="acc">
      <button class="btn">Guardar</button>
      <a class="btn-g" href="../index.php" target="_blank" rel="noopener">Ver la portada &rarr;</a>
      <span style="color:#4a6a85;font-size:.8rem">Un campo vacio vuelve al texto original.</span>
    </div>
  </form>
</main>
</body>
</html>
