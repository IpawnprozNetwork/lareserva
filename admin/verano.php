<?php
/* admin/verano.php - VERSION 1.5
   Editor de la pagina de Verano 2027.

   Dos cosas en una pantalla:
     1. Los textos, que viven en la tabla `ajustes` igual que los de portada
     2. Las baldosas de foto y video, que viven en `verano_media`

   Mismo criterio que portada.php: si un campo queda vacio, la pagina usa su
   texto original. No hay forma de dejar la pagina en blanco.

   Las baldosas se ordenan con las flechas. La primera de la lista es la
   grande del mosaico. */

require_once __DIR__ . '/../includes/auth.php';
exigirSesion();
ini_set('display_errors', '0');

$db  = getDB();
$msg = $err = null;
$lrPag = 'verano.php';

/* ── Los textos ──────────────────────────────────────────────────────────
   clave => [etiqueta, tipo, ayuda, valor por defecto]
   El valor por defecto es lo que hoy dice verano2027.php. */
$campos = [
  'v27_hero_rotulo' => ['Rotulo de arriba', 'texto', 'La linea chica sobre el titulo.',
     'Capilla del Señor · A una hora de Buenos Aires'],
  'v27_hero_bajada' => ['Parrafo del hero', 'area', 'Dos o tres lineas debajo del titulo.',
     'Tres dias y dos noches en el campo, a una hora de Buenos Aires. Pileta, actividades para los chicos y pension completa o media. Enero, febrero y marzo.'],

  'v27_wa'        => ['WhatsApp de esta pagina', 'texto', 'Solo para Verano 2027. Vacio = el del sitio.', ''],
  'v27_wa_label'  => ['WhatsApp, como se muestra', 'texto', 'Ej: +54 9 11 6939-0659', ''],

  /* v1.4: los campos de Agenda salieron: la pagina ya no lista retiros.
     Las filas viejas quedan en la base sin molestar. */
  'v27_programa_titulo' => ['Programa, titulo', 'texto', '', '4 días y 3 noches, o 3 días y 2 noches'],
  'v27_programa_bajada' => ['Programa, bajada', 'area', '',
     'Elegís vos cuántas noches. En el medio no hay traslados ni horarios que cumplir: la pileta, el lago, el parque y el comedor están todos a distancia de caminar. Los chicos pueden andar sueltos.'],

  'v27_eventos_titulo' => ['Encuentros, titulo', 'texto', '', 'Encuentros con fecha fija'],
  'v27_eventos_bajada' => ['Encuentros, bajada', 'area', '',
     'El Family Plan se reserva cualquier día del verano. Aparte de eso, estos encuentros tienen fecha puesta y cupos propios.'],
  'v27_eventos_nota'   => ['Encuentros, nota al pie', 'area', '',
     'Para reservar cualquiera de los dos, escribile a Johnny por WhatsApp.'],

  'v27_pension_tit'   => ['Pension, titulo', 'texto', 'La que entra en el valor publicado.', 'Pensión completa'],
  'v27_pension_txt'   => ['Pension, detalle', 'area', '',
     'Desayuno, almuerzo y cena, con opciones vegetarianas, veganas y sin gluten. Si preferís media pensión, consultanos el valor.'],

  'v27_precios_titulo' => ['Valores, titulo', 'texto', '', 'Valor de la estadía'],
  'v27_precios_bajada' => ['Valores, bajada', 'area', '',
     'Los valores son por persona e incluyen alojamiento y pensión completa o media. Los más chiquitos casi no pagan.'],
  'v27_p1_nombre' => ['Plan 1, nombre', 'texto', '', '3 dias y 2 noches'],
  'v27_p1_adulto' => ['Plan 1, adulto', 'texto', 'Solo el numero, sin el signo peso.', '442.000'],
  'v27_p1_menor'  => ['Plan 1, de 4 a 10 anos', 'texto', '', '221.000'],
  'v27_p1_bebe'   => ['Plan 1, de 1 a 3 anos', 'texto', '', '46.000'],
  'v27_p2_nombre' => ['Plan 2, nombre', 'texto', '', '4 dias y 3 noches'],
  'v27_p2_adulto' => ['Plan 2, adulto', 'texto', '', '616.000'],
  'v27_p2_menor'  => ['Plan 2, de 4 a 10 anos', 'texto', '', '308.000'],
  'v27_p2_bebe'   => ['Plan 2, de 1 a 3 anos', 'texto', '', '52.000'],
  'v27_precios_nota' => ['Valores, nota al pie', 'area',
     'Aparece debajo de las dos tarifas, en rojo.',
     'Los valores son por persona. No recibimos mascotas.'],

  'v27_horarios_titulo' => ['Horarios, titulo', 'texto', '', 'Entradas y salidas'],
  'v27_h_ingreso'  => ['Dias de ingreso', 'area', '', 'De lunes a jueves, o de jueves a domingo.'],
  'v27_h_checkin'  => ['Check in', 'area', '', 'Desde las 14 horas.'],
  'v27_h_checkout' => ['Check out', 'area', '', 'Las habitaciones se dejan a las 10 de la manana.'],
  'v27_h_ultimo'   => ['Ultimo servicio', 'area', '',
     'El almuerzo del ultimo dia. Despues del almuerzo se retiran.'],
];

/* ── Guardar ─────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
        $err = 'El formulario llego vacio. Suele ser un archivo demasiado grande.';

    } elseif (!csrfOk($_POST['csrf'] ?? null)) {
        $err = 'La sesion expiro. Recarga la pagina y guarda de nuevo.';

    } else {
        $accion = $_POST['accion'] ?? 'textos';
        try {
            /* ── Textos ─────────────────────────────────────────────── */
            if ($accion === 'textos') {
                $db->beginTransaction();
                $up = $db->prepare(
                    "INSERT INTO ajustes (clave, valor) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $n = 0;
                foreach ($campos as $clave => $def) {
                    $v = trim((string)($_POST[$clave] ?? ''));
                    if (mb_strlen($v) > 2000) $v = mb_substr($v, 0, 2000);
                    $up->execute([$clave, $v]);
                    $n++;
                }
                $db->commit();
                $msg = "Guardado. $n campos actualizados.";

            /* ── Baldosa nueva ──────────────────────────────────────── */
            } elseif ($accion === 'media_nueva') {
                $titulo = trim((string)($_POST['m_titulo'] ?? ''));
                $desc   = trim((string)($_POST['m_desc'] ?? ''));
                $datos  = trim((string)($_POST['m_datos'] ?? ''));
                $arch   = trim((string)($_POST['m_archivo'] ?? ''));
                $vid    = trim((string)($_POST['m_video'] ?? ''));

                /* Subida opcional. Si no suben nada, se usa la ruta escrita a
                   mano, que sirve para reutilizar una foto que ya esta en el
                   servidor sin volver a subirla. */
                if (!empty($_FILES['m_subir']['name']) && $_FILES['m_subir']['error'] === UPLOAD_ERR_OK) {
                    $tmp  = $_FILES['m_subir']['tmp_name'];
                    $mime = lrMime($tmp, $_FILES['m_subir']['name']);
                    $ok   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4'];
                    if (!isset($ok[$mime])) {
                        throw new RuntimeException('Formato no admitido. Solo jpg, png, webp o mp4.');
                    }
                    $ext    = $ok[$mime];
                    $limpio = preg_replace('/[^a-z0-9]+/', '-',
                                strtolower(pathinfo($_FILES['m_subir']['name'], PATHINFO_FILENAME)));
                    $limpio = trim($limpio, '-') ?: 'v27';
                    $dir    = $ext === 'mp4' ? 'assets/video/pages' : 'assets/img/verano2027';
                    $base   = dirname(__DIR__) . '/' . $dir;
                    $nombre = $limpio . '.' . $ext;
                    $i = 2;
                    while (file_exists("$base/$nombre")) { $nombre = "$limpio-$i.$ext"; $i++; }
                    if (!move_uploaded_file($tmp, "$base/$nombre")) {
                        throw new RuntimeException('No se pudo guardar el archivo subido.');
                    }
                    if ($ext === 'mp4') $vid  = "$dir/$nombre";
                    else                $arch = "$dir/$nombre";
                }

                if ($arch === '') throw new RuntimeException('Falta la foto de la baldosa.');

                $orden = (int)$db->query("SELECT IFNULL(MAX(orden),0)+10 FROM verano_media")->fetchColumn();
                $db->prepare(
                    "INSERT INTO verano_media (archivo, video, titulo, descripcion, datos, orden, activo)
                     VALUES (?,?,?,?,?,?,1)")
                   ->execute([$arch, $vid ?: null, $titulo, $desc, $datos, $orden]);
                $msg = 'Baldosa agregada.';

            /* ── Editar, mover, borrar ──────────────────────────────── */
            } elseif ($accion === 'media_editar') {
                $db->prepare(
                    "UPDATE verano_media SET titulo=?, descripcion=?, datos=?, activo=? WHERE id=?")
                   ->execute([
                     trim((string)($_POST['m_titulo'] ?? '')),
                     trim((string)($_POST['m_desc'] ?? '')),
                     trim((string)($_POST['m_datos'] ?? '')),
                     empty($_POST['m_activo']) ? 0 : 1,
                     (int)($_POST['id'] ?? 0),
                   ]);
                $msg = 'Baldosa actualizada.';

            } elseif ($accion === 'media_mover') {
                /* Se normaliza a 10,20,30 antes de mover: si todas quedaron en
                   el mismo numero, intercambiar no hace nada visible. */
                $filas = $db->query("SELECT id FROM verano_media ORDER BY orden ASC, id ASC")->fetchAll();
                $norm = $db->prepare("UPDATE verano_media SET orden=? WHERE id=?");
                foreach ($filas as $k => $f) $norm->execute([($k + 1) * 10, $f['id']]);

                $id  = (int)($_POST['id'] ?? 0);
                $dir = ($_POST['dir'] ?? '') === 'arriba' ? 'arriba' : 'abajo';
                $cur = $db->prepare("SELECT orden FROM verano_media WHERE id=?");
                $cur->execute([$id]);
                $o = (int)$cur->fetchColumn();
                $q = $dir === 'arriba'
                   ? "SELECT id,orden FROM verano_media WHERE orden<? ORDER BY orden DESC LIMIT 1"
                   : "SELECT id,orden FROM verano_media WHERE orden>? ORDER BY orden ASC LIMIT 1";
                $st = $db->prepare($q); $st->execute([$o]); $vecino = $st->fetch();
                if ($vecino) {
                    $sw = $db->prepare("UPDATE verano_media SET orden=? WHERE id=?");
                    $sw->execute([(int)$vecino['orden'], $id]);
                    $sw->execute([$o, (int)$vecino['id']]);
                }
                $msg = 'Orden actualizado.';

            } elseif ($accion === 'media_borrar') {
                $db->prepare("DELETE FROM verano_media WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
                $msg = 'Baldosa eliminada. El archivo sigue en el servidor.';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $err = 'No se pudo guardar: ' . $e->getMessage();
            error_log('[LR verano] ' . $e->getMessage());
        }
    }
}

/* ── Leer ────────────────────────────────────────────────────────────── */
$valores = [];
try {
    foreach ($db->query("SELECT clave, valor FROM ajustes")->fetchAll() as $r) {
        $valores[$r['clave']] = $r['valor'];
    }
} catch (Throwable $e) { error_log('[LR verano] ' . $e->getMessage()); }

$media = [];
try {
    $media = $db->query("SELECT * FROM verano_media ORDER BY orden ASC, id ASC")->fetchAll();
} catch (Throwable $e) { error_log('[LR verano] ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- admin/verano.php VERSION 1.5 -->
<title>Verano 2027 · Panel La Reserva</title>
<link rel="stylesheet" href="../css/style.css?v=4">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a1628;color:#e8f0f7;font-family:'Karla',sans-serif;display:flex;min-height:100vh}
main{flex:1;min-width:0;padding:32px 36px 80px;max-width:980px}
h1{font:300 2rem 'Fraunces',Georgia,serif;margin-bottom:4px}
.sub{color:#7a9ab8;font-size:.95rem;margin-bottom:26px;line-height:1.6}
h2{font:300 1.4rem 'Fraunces',Georgia,serif;margin:34px 0 4px}
.ayuda2{color:#4a6a85;font-size:.86rem;margin-bottom:16px;line-height:1.6}
.av{padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:20px;font-size:.93rem}
.av.ok{background:#08161a;border:1px solid #123a35;border-left:3px solid #00c9a7;color:#8fb5ad}
.av.err{background:#170a0f;border:1px solid #3d1622;border-left:3px solid #f0398b;color:#c49aa6}
.caja{background:#112233;border:1px solid rgba(0,201,167,.16);border-radius:12px;padding:24px;margin-bottom:18px}
label{display:block;margin-bottom:16px}
label span{display:block;font-size:.73rem;letter-spacing:.1em;text-transform:uppercase;color:#7a9ab8;margin-bottom:5px}
label small{display:block;color:#4a6a85;font-size:.8rem;margin-top:4px;line-height:1.5}
input[type=text],textarea,input[type=file]{width:100%;background:#0a1420;border:1px solid #1c3247;
  border-radius:8px;color:#e8f0f7;font:inherit;font-size:.94rem;padding:10px 12px}
input:focus,textarea:focus{outline:0;border-color:#00c9a7}
textarea{min-height:76px;resize:vertical;line-height:1.6}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.g4{display:grid;grid-template-columns:1.6fr 1fr 1fr 1fr;gap:0 14px}
.btn{background:#00c9a7;color:#0a1628;border:0;border-radius:24px;padding:11px 26px;
  font:inherit;font-weight:600;font-size:.92rem;cursor:pointer}
.btn:hover{background:#00e0ba}
.btn-t{background:none;border:1px solid #1c3247;color:#7a9ab8;border-radius:16px;
  padding:5px 12px;font:inherit;font-size:.8rem;cursor:pointer}
.btn-t:hover{border-color:#00c9a7;color:#00c9a7}
.btn-d{background:none;border:1px solid #3d1622;color:#c49aa6;border-radius:16px;
  padding:5px 12px;font:inherit;font-size:.8rem;cursor:pointer}
.btn-d:hover{background:#f0398b;color:#fff;border-color:#f0398b}
.tile{display:grid;grid-template-columns:120px 1fr auto;gap:16px;align-items:start;
  padding:16px;border:1px solid #1c3247;border-radius:10px;margin-bottom:12px;background:#0d1b2b}
.tile img,.tile video{width:120px;height:84px;object-fit:cover;border-radius:6px;background:#000}
.tile .pos{font:600 .7rem 'Karla',sans-serif;color:#00c9a7;letter-spacing:.1em}
.tile .acc{display:flex;flex-direction:column;gap:6px;align-items:flex-end}
.tile.off{opacity:.5}
.vid-tag{font-size:.7rem;color:#00c9a7;letter-spacing:.08em}
@media(max-width:780px){
  body{display:block}main{padding:18px 16px 80px}
  .g2,.g4{grid-template-columns:1fr}
  .tile{grid-template-columns:1fr}
  .tile img,.tile video{width:100%;height:150px}
}
</style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>
<main>
  <h1>Verano 2027</h1>
  <p class="sub">Los textos y las fotos de <a href="../verano2027.php" target="_blank" style="color:#00c9a7">la pagina de verano</a>.
     Si dejas un campo vacio, la pagina vuelve a su texto original.</p>

  <?php if ($msg): ?><div class="av ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="av err"><?= e($err) ?></div><?php endif; ?>

  <!-- ══════════ TEXTOS ══════════ -->
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="accion" value="textos">

    <?php
    $grupos = [
      'Encabezado'  => ['v27_hero_rotulo','v27_hero_bajada','v27_wa','v27_wa_label'],
      'Programa'    => ['v27_programa_titulo','v27_programa_bajada'],
      'Encuentros'  => ['v27_eventos_titulo','v27_eventos_bajada','v27_eventos_nota'],
      'Pension'     => ['v27_pension_tit','v27_pension_txt'],
      'Valores'     => ['v27_precios_titulo','v27_precios_bajada'],
      'Plan 1'      => ['v27_p1_nombre','v27_p1_adulto','v27_p1_menor','v27_p1_bebe'],
      'Plan 2'      => ['v27_p2_nombre','v27_p2_adulto','v27_p2_menor','v27_p2_bebe'],
      'Nota'        => ['v27_precios_nota'],
      'Horarios'    => ['v27_horarios_titulo','v27_h_ingreso','v27_h_checkin','v27_h_checkout','v27_h_ultimo'],
    ];
    foreach ($grupos as $titulo => $claves):
      $g = in_array($titulo, ['Plan 1','Plan 2'], true) ? 'g4' : '';
    ?>
    <h2><?= e($titulo) ?></h2>
    <div class="caja">
      <div class="<?= $g ?>">
      <?php foreach ($claves as $c):
        [$etiqueta, $tipo, $ayuda, $def] = $campos[$c];
        $v = $valores[$c] ?? '';
      ?>
        <label><span><?= e($etiqueta) ?></span>
          <?php if ($tipo === 'area'): ?>
            <textarea name="<?= e($c) ?>" placeholder="<?= e($def) ?>"><?= e($v) ?></textarea>
          <?php else: ?>
            <input type="text" name="<?= e($c) ?>" value="<?= e($v) ?>" placeholder="<?= e($def) ?>">
          <?php endif; ?>
          <?php if ($ayuda): ?><small><?= e($ayuda) ?></small><?php endif; ?>
        </label>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <button class="btn">Guardar los textos</button>
  </form>

  <!-- ══════════ BALDOSAS ══════════ -->
  <h2 style="margin-top:44px">Las fotos de la pagina</h2>
  <p class="ayuda2">La primera de la lista es la grande del mosaico. Las flechas cambian el orden.
     Cada una puede tener un video que se reproduce al pasar el mouse.</p>

  <?php if (!$media): ?>
    <div class="caja" style="color:#7a9ab8;font-size:.93rem;line-height:1.7">
      Todavia no hay baldosas cargadas, asi que la pagina esta usando las fijas
      que vinieron con el diseño. En cuanto agregues la primera, la pagina pasa
      a usar estas y deja de usar las fijas.
    </div>
  <?php endif; ?>

  <?php foreach ($media as $k => $m): ?>
  <div class="tile <?= $m['activo'] ? '' : 'off' ?>">
    <div>
      <?php if ($m['video']): ?>
        <video src="<?= e('../' . $m['video']) ?>" muted loop playsinline
               poster="<?= e('../' . $m['archivo']) ?>" onmouseover="this.play()"
               onmouseout="this.pause()"></video>
        <div class="vid-tag">con video</div>
      <?php else: ?>
        <img src="<?= e('../' . $m['archivo']) ?>" alt="">
      <?php endif; ?>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="accion" value="media_editar">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <div class="pos">POSICION <?= $k + 1 ?><?= $k === 0 ? ' · LA GRANDE' : '' ?></div>
      <label style="margin-top:8px"><span>Titulo</span>
        <input type="text" name="m_titulo" value="<?= e($m['titulo']) ?>"></label>
      <label><span>Descripcion</span>
        <textarea name="m_desc"><?= e($m['descripcion']) ?></textarea></label>
      <label><span>Datos sueltos</span>
        <input type="text" name="m_datos" value="<?= e($m['datos']) ?>"
               placeholder="Descubierta | Reposeras y sombra | Abierta todo el dia">
        <small>Separados con una barra. Salen como lista en el visor.</small></label>
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="m_activo" value="1" style="width:auto"
               <?= $m['activo'] ? 'checked' : '' ?>>
        <span style="margin:0;text-transform:none;letter-spacing:0;font-size:.9rem">Visible en la pagina</span>
      </label>
      <button class="btn-t">Guardar</button>
    </form>
    <div class="acc">
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="media_mover">
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="dir" value="arriba">
        <button class="btn-t" <?= $k === 0 ? 'disabled' : '' ?>>&uarr;</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="media_mover">
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="dir" value="abajo">
        <button class="btn-t" <?= $k === count($media) - 1 ? 'disabled' : '' ?>>&darr;</button></form>
      <form method="post" onsubmit="return confirm('Sacar esta baldosa de la pagina?')">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="media_borrar">
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <button class="btn-d">Borrar</button></form>
    </div>
  </div>
  <?php endforeach; ?>

  <h2 style="margin-top:34px">Agregar una foto</h2>
  <div class="caja">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="accion" value="media_nueva">
      <label><span>Subir un archivo</span>
        <input type="file" name="m_subir" accept=".jpg,.jpeg,.png,.webp,.mp4">
        <small>Fotos apaisadas. Video por debajo de 30 MB. Un mp4 se guarda como
        el video de la baldosa; una foto, como la imagen.</small></label>
      <div class="g2">
        <label><span>O una foto que ya esta en el servidor</span>
          <input type="text" name="m_archivo" placeholder="assets/img/verano2027/pileta.jpg">
          <small>Ruta completa desde la raiz del sitio.</small></label>
        <label><span>Video, opcional</span>
          <input type="text" name="m_video" placeholder="assets/video/pages/v27-aereo.mp4"></label>
      </div>
      <label><span>Titulo</span><input type="text" name="m_titulo" placeholder="La pileta"></label>
      <label><span>Descripcion</span>
        <textarea name="m_desc" placeholder="Lo que se ve al abrir la foto en el visor."></textarea></label>
      <label><span>Datos sueltos</span>
        <input type="text" name="m_datos" placeholder="Descubierta | Reposeras y sombra">
        <small>Separados con una barra.</small></label>
      <button class="btn">Agregar</button>
    </form>
  </div>
</main>
</body>
</html>
