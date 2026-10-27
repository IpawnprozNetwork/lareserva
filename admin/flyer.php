<?php
/* admin/flyer.php - VERSION 1.2
   Armador de flyers.

   Elegis fotos del sitio o subis las tuyas, escribis los textos, elegis
   formato y tipografia, y te lo bajas en PNG o JPG, o lo imprimis a PDF.

   Todo pasa en el navegador: la vista previa ES el flyer. Lo que se ve es
   lo que se baja, asi que no hay sorpresas entre la pantalla y el archivo.

   La descarga usa html2canvas desde CDN. Si no hay internet, el boton de
   Imprimir sigue funcionando y de ahi sale el PDF. */

require_once __DIR__ . '/../includes/auth.php';
exigirSesion();
ini_set('display_errors', '0');

$db = getDB();
$lrPag = 'flyer.php';
$msg = $err = null;

/* Subida de una foto propia, al mismo lugar que el resto del material. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? null)) {
        $err = 'La sesion expiro. Recarga y proba de nuevo.';
    } elseif (!empty($_FILES['foto']['name'])) {
        try {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir.');
            $mime = lrMime($_FILES['foto']['tmp_name'], $_FILES['foto']['name']);
            $ok = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($ok[$mime])) throw new RuntimeException('Solo jpg, png o webp.');
            $base = dirname(__DIR__) . '/assets/img/galeria';
            $n = preg_replace('/[^a-z0-9]+/','-', strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_FILENAME)));
            $n = trim($n,'-') ?: 'foto';
            $arch = $n . '.' . $ok[$mime]; $i = 2;
            while (file_exists("$base/$arch")) { $arch = "$n-$i." . $ok[$mime]; $i++; }
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], "$base/$arch"))
                throw new RuntimeException('No se pudo guardar.');
            $msg = 'Foto subida. Ya aparece en la lista de abajo.';
        } catch (Throwable $e) { $err = $e->getMessage(); }
    }
}

/* Las fotos disponibles: lo que ya esta en el sitio. */
$fotos = [];
foreach (['assets/img/verano2027', 'assets/img/galeria'] as $dir) {
    $abs = dirname(__DIR__) . '/' . $dir;
    if (!is_dir($abs)) continue;
    foreach (scandir($abs) as $f) {
        if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) $fotos[] = "$dir/$f";
    }
}
sort($fotos);

/* Precios y textos del sitio, para arrancar con algo real cargado. */
$aj = [];
try { foreach ($db->query("SELECT clave, valor FROM ajustes")->fetchAll() as $r) $aj[$r['clave']] = $r['valor']; }
catch (Throwable $e) {}
function a($k, $d = '') { global $aj; return trim((string)($aj[$k] ?? '')) ?: $d; }
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- admin/flyer.php VERSION 1.2 -->
<title>Armador de flyers · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,600&family=Karla:wght@400;600;700&family=Playfair+Display:wght@400;700&family=Montserrat:wght@400;600;800&family=Lora:wght@400;600&family=Bebas+Neue&family=Cormorant+Garamond:wght@300;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a1628;color:#e8f0f7;font-family:'Karla',sans-serif;display:flex;min-height:100vh}
main{flex:1;min-width:0;display:flex;gap:20px;padding:22px;align-items:flex-start}
.panel{width:330px;flex-shrink:0;max-height:calc(100vh - 44px);overflow-y:auto;padding-right:6px}
.lienzo{flex:1;min-width:0;display:flex;justify-content:center;align-items:flex-start}
h1{font:300 1.5rem 'Fraunces',Georgia,serif;margin-bottom:4px}
.sub{color:#7a9ab8;font-size:.86rem;margin-bottom:16px;line-height:1.5}
fieldset{border:1px solid #1c3247;border-radius:10px;padding:14px;margin-bottom:12px}
legend{font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:#00c9a7;padding:0 6px}
label{display:block;margin-bottom:10px}
label span{display:block;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;color:#7a9ab8;margin-bottom:4px}
input[type=text],textarea,select,input[type=file]{width:100%;background:#0a1420;border:1px solid #1c3247;
  border-radius:7px;color:#e8f0f7;font:inherit;font-size:.86rem;padding:8px 10px}
textarea{min-height:58px;resize:vertical;line-height:1.5}
input:focus,textarea:focus,select:focus{outline:0;border-color:#00c9a7}
.fila2{display:flex;gap:8px}.fila2>*{flex:1;min-width:0}
.chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px}
.chip{background:none;border:1px solid #26405c;color:#9ab4cc;border-radius:14px;padding:4px 10px;
  font:inherit;font-size:.74rem;cursor:pointer}
.chip:hover,.chip.on{border-color:#00c9a7;color:#00c9a7}
.btn{background:#00c9a7;color:#0a1628;border:0;border-radius:20px;padding:9px 18px;font:inherit;
  font-weight:600;font-size:.86rem;cursor:pointer;width:100%;margin-bottom:7px}
.btn:hover{background:#00e0ba}
.btn-s{background:none;border:1px solid #26405c;color:#9ab4cc}
.btn-s:hover{border-color:#00c9a7;color:#00c9a7}
.galeria{display:flex;flex-wrap:wrap;gap:5px;max-height:190px;overflow-y:auto;padding:4px;
  border:1px solid #1c3247;border-radius:8px;background:#0a1420}
.galeria img{width:52px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;border:2px solid transparent}
.galeria img:hover{border-color:#26405c}
.galeria img.sel{border-color:#00c9a7}
.ranuras{display:flex;gap:6px;margin-bottom:9px}
.ranura{flex:1;aspect-ratio:1/1;border:2px dashed #26405c;border-radius:7px;background:#0a1420;
  position:relative;cursor:pointer;overflow:hidden;display:flex;align-items:center;justify-content:center}
.ranura.on{border-style:solid;border-color:#00c9a7;box-shadow:0 0 0 2px rgba(0,201,167,.2)}
.ranura img{width:100%;height:100%;object-fit:cover}
.ranura .n{position:absolute;top:3px;left:4px;font:700 .64rem 'Karla',sans-serif;color:#fff;
  background:rgba(6,13,24,.75);border-radius:8px;padding:1px 6px;z-index:2}
.ranura .x{position:absolute;top:2px;right:3px;z-index:2;background:rgba(6,13,24,.8);border:0;
  color:#c49aa6;border-radius:50%;width:17px;height:17px;font-size:.72rem;cursor:pointer;line-height:1}
.ranura .mas{color:#3d5a75;font-size:1.3rem}
.av{padding:9px 12px;border-radius:0 7px 7px 0;margin-bottom:12px;font-size:.84rem}
.av.ok{background:#08161a;border-left:3px solid #00c9a7;color:#8fb5ad}
.av.err{background:#170a0f;border-left:3px solid #f0398b;color:#c49aa6}
.ayuda{font-size:.74rem;color:#4a6a85;line-height:1.5;margin-top:5px}

/* ── El flyer ─────────────────────────────────────────────── */
#hoja{background:#0a1628;overflow:hidden;display:flex;flex-direction:column;
  transform-origin:top center;box-shadow:0 14px 48px rgba(0,0,0,.6);flex-shrink:0}
#hoja.a4{width:794px;height:1123px}
#hoja.cuad{width:1080px;height:1080px}
#hoja.story{width:1080px;height:1920px}
#tapa{position:relative;flex:0 0 36%;overflow:hidden}
#tapa img{width:100%;height:100%;object-fit:cover}
#velo{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,22,40,.45),rgba(10,22,40,.12) 45%,rgba(10,22,40,.97))}
#cab{position:absolute;left:0;right:0;bottom:0;padding:0 8% 4%;text-align:center}
#marca{font:700 .7rem 'Karla',sans-serif;letter-spacing:.28em;text-transform:uppercase;color:#00c9a7;margin-bottom:9px}
#tit{font:300 2.6rem var(--tipo,'Fraunces'),Georgia,serif;line-height:1.05;color:#fff}
#baj{font-size:1rem;color:#cfe0ee;margin-top:9px;line-height:1.5}
#cuerpo{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;justify-content:space-between;
  padding:4% 8% 3%;overflow:hidden}
.rotf{font:700 .62rem 'Karla',sans-serif;letter-spacing:.22em;text-transform:uppercase;color:#00c9a7;
  text-align:center;margin-bottom:10px}
#planes{display:flex;gap:10px}
.plan{flex:1 1 0;min-width:0;border:1px solid rgba(0,201,167,.3);border-radius:9px;padding:12px;background:#112233}
.plan b{display:block;font:300 1.2rem var(--tipo,'Fraunces'),Georgia,serif;color:#00c9a7;margin-bottom:8px;
  padding-bottom:7px;border-bottom:1px solid rgba(154,180,204,.18)}
.pf{display:flex;justify-content:space-between;gap:7px;padding:4px 0}
.pf span{color:#9ab4cc;font-size:.76rem}
.pf em{font-style:normal;font-weight:700;color:#fff;font-size:.84rem;white-space:nowrap}
#notaf{margin-top:9px;text-align:center;font-size:.72rem;color:#5d7f9c}
/* v1.2: con menos de cuatro fotos, flex:1 las estiraba a lo ancho y una
   sola ocupaba toda la franja. Ahora se centran, cada una conserva su
   proporcion y lleva borde, asi una, dos o tres se ven igual de
   intencionales que cuatro. */
#tira{display:flex;gap:7px;margin-top:4%;justify-content:center}
#tira img{flex:0 1 auto;width:calc((100% - 21px) / 4);max-width:calc((100% - 21px) / 4);
  height:118px;object-fit:cover;border-radius:7px;
  border:1px solid rgba(0,201,167,.28);box-shadow:0 3px 10px rgba(0,0,0,.3)}
#tira.n1 img{width:46%;max-width:46%;height:170px}
#tira.n2 img{width:calc((100% - 7px) / 2);max-width:calc((100% - 7px) / 2);height:150px}
#tira.n3 img{width:calc((100% - 14px) / 3);max-width:calc((100% - 14px) / 3);height:132px}
#pief{flex:0 0 auto;padding:3.2% 8%;text-align:center;border-top:1px solid rgba(154,180,204,.16);background:rgba(6,13,24,.5)}
#ctaf{font:300 1.3rem var(--tipo,'Fraunces'),Georgia,serif;color:#fff;margin-bottom:6px}
#datosf{font-size:.78rem;color:#9ab4cc;line-height:1.6}
#datosf b{color:#00c9a7}
#hoja.cuad #tit{font-size:3.4rem}#hoja.story #tit{font-size:4rem}
#hoja.cuad #baj,#hoja.story #baj{font-size:1.4rem}
#hoja.cuad .plan b,#hoja.story .plan b{font-size:1.6rem}
#hoja.cuad .pf span,#hoja.story .pf span{font-size:1.05rem}
#hoja.cuad .pf em,#hoja.story .pf em{font-size:1.2rem}
#hoja.cuad #tira img,#hoja.story #tira img{height:240px}
#hoja.cuad #tira.n1 img,#hoja.story #tira.n1 img{height:340px}
#hoja.cuad #tira.n2 img,#hoja.story #tira.n2 img{height:300px}
#hoja.cuad #tira.n3 img,#hoja.story #tira.n3 img{height:264px}
#hoja.cuad #ctaf,#hoja.story #ctaf{font-size:1.8rem}
#hoja.cuad #datosf,#hoja.story #datosf{font-size:1.05rem}
#hoja.cuad #marca,#hoja.story #marca{font-size:.95rem}
#hoja.cuad .rotf,#hoja.story .rotf{font-size:.9rem}
/* v1.1: al imprimir salia todo el panel. La barra lateral es #sb, no
   #sidebar, asi que la regla vieja no tapaba nada. En vez de ir nombrando
   cada pieza, se esconde todo y se vuelve a mostrar solo la hoja: lo que
   se agregue despues al panel no va a aparecer en el PDF por accidente. */
@media print{
  body *{visibility:hidden!important}
  #hoja,#hoja *{visibility:visible!important}
  body{display:block!important;background:#fff!important;margin:0;padding:0}
  main{display:block!important;padding:0!important;margin:0!important}
  .panel,#sb,nav,aside,header,footer,form{display:none!important}
  .lienzo{display:block!important;height:auto!important;margin:0!important;padding:0!important}
  #hoja{position:absolute;top:0;left:0;box-shadow:none!important;transform:none!important;
        margin:0!important;page-break-after:avoid;page-break-inside:avoid}
  @page{size:A4 portrait;margin:0}
}
@media(max-width:1100px){main{flex-direction:column}.panel{width:100%;max-height:none}}
</style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>
<main>
<div class="panel">
  <h1>Armador de flyers</h1>
  <p class="sub">Lo que ves es lo que se baja. Cambia lo que quieras y descarga.</p>
  <?php if ($msg): ?><div class="av ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="av err"><?= e($err) ?></div><?php endif; ?>

  <fieldset><legend>Formato</legend>
    <div class="chips">
      <button class="chip on" data-fmt="a4">A4 imprimir</button>
      <button class="chip" data-fmt="cuad">Instagram</button>
      <button class="chip" data-fmt="story">Historia</button>
    </div>
    <label><span>Tipografia</span>
      <select id="tipo">
        <option value="Fraunces">Fraunces, la del sitio</option>
        <option value="Playfair Display">Playfair Display</option>
        <option value="Cormorant Garamond">Cormorant Garamond</option>
        <option value="Lora">Lora</option>
        <option value="Montserrat">Montserrat</option>
        <option value="Bebas Neue">Bebas Neue</option>
      </select></label>
  </fieldset>

  <fieldset><legend>Textos</legend>
    <div class="chips" id="variantes">
      <button class="chip" data-v="0">Familia</button>
      <button class="chip" data-v="1">Descanso</button>
      <button class="chip" data-v="2">Grupos</button>
      <button class="chip" data-v="3">Ultimo momento</button>
    </div>
    <label><span>Rotulo</span><input type="text" id="i-marca" value="La Reserva · Naturaleza Arte"></label>
    <label><span>Titulo</span><textarea id="i-tit">Vení a disfrutar del verano 2027</textarea></label>
    <label><span>Bajada</span><textarea id="i-baj"><?= e(a('v27_hero_bajada', "Naturaleza y diversión.\nConsultá por nuestro Family Plan.")) ?></textarea></label>
    <label><span>Cierre</span><input type="text" id="i-cta" value="250 hectáreas a una hora de Buenos Aires"></label>
    <label><span>Contacto</span><textarea id="i-datos">WhatsApp <?= e(a('v27_wa_label','+54 9 11 6939-0659')) ?>
lareserva.net.ar/verano2027.php
@lareservanaturalezaarte</textarea></label>
  </fieldset>

  <fieldset><legend>Precios</legend>
    <label><span>Plan 1</span><input type="text" id="p1n" value="<?= e(a('v27_p1_nombre','3 días y 2 noches')) ?>"></label>
    <div class="fila2">
      <input type="text" id="p1a" value="<?= e(a('v27_p1_adulto','442.000')) ?>" placeholder="adulto">
      <input type="text" id="p1b" value="<?= e(a('v27_p1_menor','221.000')) ?>" placeholder="4 a 10">
      <input type="text" id="p1c" value="<?= e(a('v27_p1_bebe','46.000')) ?>" placeholder="1 a 3">
    </div>
    <label style="margin-top:10px"><span>Plan 2</span><input type="text" id="p2n" value="<?= e(a('v27_p2_nombre','4 días y 3 noches')) ?>"></label>
    <div class="fila2">
      <input type="text" id="p2a" value="<?= e(a('v27_p2_adulto','616.000')) ?>" placeholder="adulto">
      <input type="text" id="p2b" value="<?= e(a('v27_p2_menor','308.000')) ?>" placeholder="4 a 10">
      <input type="text" id="p2c" value="<?= e(a('v27_p2_bebe','52.000')) ?>" placeholder="1 a 3">
    </div>
    <label style="margin-top:10px"><span>Nota</span><input type="text" id="i-nota" value="<?= e(a('v27_precios_nota','Enero, febrero y marzo de 2027 · No se aceptan mascotas')) ?>"></label>
  </fieldset>

  <fieldset><legend>Foto de portada</legend>
    <div class="galeria" id="g-tapa"></div>
    <p class="ayuda">Hace clic en una. Conviene apaisada y clara.</p>
  </fieldset>

  <fieldset><legend>Tira de abajo, las 4 fotos</legend>
    <?php /* v1.1: cuatro ranuras numeradas. Antes era una sola lista donde
             se sumaba y se sacaba, y no se entendia cual era cual ni en que
             orden iban a salir. */ ?>
    <div class="ranuras" id="ranuras"></div>
    <p class="ayuda" id="pista-ranura">Hace clic en una ranura y despues elegi la foto.</p>
    <div class="galeria" id="g-tira"></div>
  </fieldset>

  <fieldset><legend>Subir una foto nueva</legend>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <label><input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></label>
      <button class="btn btn-s">Subir</button>
    </form>
  </fieldset>

  <fieldset><legend>Descargar</legend>
    <button class="btn" id="d-png">PNG</button>
    <button class="btn btn-s" id="d-jpg">JPG</button>
    <button class="btn btn-s" id="d-pdf">Imprimir o guardar PDF</button>
    <p class="ayuda">El PNG y el JPG salen en el tamano real del formato
      elegido. Para iPhone, el JPG anda en cualquier app.</p>
  </fieldset>
</div>

<div class="lienzo">
  <div id="hoja" class="a4">
    <div id="tapa"><img id="imgtapa" src="" alt=""><div id="velo"></div>
      <div id="cab"><p id="marca"></p><h2 id="tit"></h2><p id="baj"></p></div>
    </div>
    <div id="cuerpo">
      <div>
        <p class="rotf">Tarifas por persona</p>
        <div id="planes">
          <div class="plan"><b id="v-p1n"></b>
            <div class="pf"><span>Adulto</span><em id="v-p1a"></em></div>
            <div class="pf"><span>4 a 10 años</span><em id="v-p1b"></em></div>
            <div class="pf"><span>1 a 3 años</span><em id="v-p1c"></em></div></div>
          <div class="plan"><b id="v-p2n"></b>
            <div class="pf"><span>Adulto</span><em id="v-p2a"></em></div>
            <div class="pf"><span>4 a 10 años</span><em id="v-p2b"></em></div>
            <div class="pf"><span>1 a 3 años</span><em id="v-p2c"></em></div></div>
        </div>
        <p id="notaf"></p>
      </div>
      <div id="tira"></div>
    </div>
    <div id="pief"><p id="ctaf"></p><p id="datosf"></p></div>
  </div>
</div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const FOTOS = <?= json_encode($fotos, JSON_UNESCAPED_SLASHES) ?>;
const RAIZ  = '../';

/* Cuatro juegos de texto listos. Cambian el tono, no los precios. */
const VARIANTES = [
 {tit:'Vení a disfrutar del verano 2027',
  baj:'Naturaleza y diversión.\nConsultá por nuestro Family Plan.',
  cta:'250 hectáreas a una hora de Buenos Aires'},
 {tit:'Tres días sin mirar el reloj',
  baj:'Pileta, lago y arboleda.\nLa comida, resuelta.',
  cta:'A una hora de Buenos Aires, y parece otro mundo'},
 {tit:'El predio entero para tu grupo',
  baj:'Hasta 120 personas.\nSalones, alojamiento y pensión completa.',
  cta:'Capilla del Señor · 250 hectáreas'},
 {tit:'Quedan fechas para enero',
  baj:'Reservá ahora y asegurá tu lugar.\nFebrero y marzo también.',
  cta:'Escribinos y te confirmamos en el día'},
];

const $ = s => document.getElementById(s);
let tapa = FOTOS.find(f => f.indexOf('pileta-aerea') >= 0) || FOTOS[0] || '';
let tira = FOTOS.filter(f => /lago-aereo|nature-04|mesa\.jpg|cabanas-noche/.test(f)).slice(0, 4);

/* La ranura activa: a cual de las cuatro le toca la proxima foto que se
   elija de la lista. Null significa que todavia no eligio ranura. */
let activa = 0;
while (tira.length < 4) tira.push('');

function pintarRanuras(){
  const c = $('ranuras'); c.innerHTML = '';
  for (let i = 0; i < 4; i++) {
    const d = document.createElement('div');
    d.className = 'ranura' + (activa === i ? ' on' : '');
    d.innerHTML = '<span class="n">' + (i + 1) + '</span>';
    if (tira[i]) {
      const im = document.createElement('img'); im.src = RAIZ + tira[i]; d.appendChild(im);
      const x = document.createElement('button'); x.className = 'x'; x.textContent = '×';
      x.title = 'Sacar esta foto';
      x.onclick = function(ev){ ev.stopPropagation(); tira[i] = ''; pintarRanuras(); pintarGalerias(); pintar(); };
      d.appendChild(x);
    } else {
      const m = document.createElement('span'); m.className = 'mas'; m.textContent = '+'; d.appendChild(m);
    }
    d.onclick = function(){ activa = i; pintarRanuras(); pintarGalerias(); };
    c.appendChild(d);
  }
  $('pista-ranura').textContent = 'Ranura ' + (activa + 1)
    + ' seleccionada. Elegi una foto de abajo para ponerla ahi.';
}

function pintarGalerias(){
  [['g-tapa', false], ['g-tira', true]].forEach(function(par){
    const cont = $(par[0]); cont.innerHTML = '';
    FOTOS.forEach(function(f){
      const im = document.createElement('img');
      im.src = RAIZ + f; im.loading = 'lazy'; im.title = f.split('/').pop();
      const sel = par[1] ? (tira[activa] === f) : (tapa === f);
      if (sel) im.className = 'sel';
      im.onclick = function(){
        if (par[1]) { tira[activa] = f; activa = (activa + 1) % 4; pintarRanuras(); }
        else tapa = f;
        pintarGalerias(); pintar();
      };
      cont.appendChild(im);
    });
  });
}

function pintar(){
  $('imgtapa').src = RAIZ + tapa;
  $('marca').textContent = $('i-marca').value;
  $('tit').textContent   = $('i-tit').value;
  $('baj').innerHTML     = $('i-baj').value.split('\n')
      .map(function(l){ return l.replace(/[<>&]/g, ''); }).join('<br>');
  $('ctaf').textContent  = $('i-cta').value;
  $('datosf').innerHTML  = $('i-datos').value.split('\n')
      .map(function(l){ return l.replace(/[<>&]/g, ''); }).join('<br>');
  $('notaf').textContent = $('i-nota').value;
  ['p1n','p1a','p1b','p1c','p2n','p2a','p2b','p2c'].forEach(function(k){
    const v = $(k).value;
    $('v-' + k).textContent = (k.length === 3 && k[2] !== 'n') ? '$' + v : v;
  });
  const puestas = tira.filter(Boolean);
  $('tira').innerHTML = '';
  $('tira').className = puestas.length < 4 ? 'n' + puestas.length : '';
  puestas.forEach(function(f){
    const im = document.createElement('img'); im.src = RAIZ + f; $('tira').appendChild(im);
  });
  $('hoja').style.setProperty('--tipo', "'" + $('tipo').value + "'");
  encajar();
}

function encajar(){
  const h = $('hoja');
  h.style.transform = 'none';
  const disp = document.querySelector('.lienzo').clientWidth - 10;
  const alto = window.innerHeight - 70;
  const k = Math.min(1, disp / h.offsetWidth, alto / h.offsetHeight);
  h.style.transform = 'scale(' + k + ')';
  document.querySelector('.lienzo').style.height = (h.offsetHeight * k) + 'px';
}

document.querySelectorAll('[data-fmt]').forEach(function(b){
  b.onclick = function(){
    document.querySelectorAll('[data-fmt]').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    $('hoja').className = b.dataset.fmt;
    pintar();
  };
});
document.querySelectorAll('#variantes .chip').forEach(function(b){
  b.onclick = function(){
    document.querySelectorAll('#variantes .chip').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    const v = VARIANTES[+b.dataset.v];
    $('i-tit').value = v.tit; $('i-baj').value = v.baj; $('i-cta').value = v.cta;
    pintar();
  };
});
['i-marca','i-tit','i-baj','i-cta','i-datos','i-nota','tipo',
 'p1n','p1a','p1b','p1c','p2n','p2a','p2b','p2c'].forEach(function(k){
  $(k).addEventListener('input', pintar);
  $(k).addEventListener('change', pintar);
});

/* La descarga. html2canvas dibuja el nodo tal cual, asi que hay que sacarle
   la escala primero: si no, baja la version chica de la pantalla. */
function bajar(tipo){
  const h = $('hoja'), prev = h.style.transform;
  h.style.transform = 'none';
  html2canvas(h, {backgroundColor:'#0a1628', scale:2, useCORS:true, logging:false})
   .then(function(c){
      h.style.transform = prev;
      const a = document.createElement('a');
      a.download = 'flyer-la-reserva.' + (tipo === 'jpg' ? 'jpg' : 'png');
      a.href = c.toDataURL(tipo === 'jpg' ? 'image/jpeg' : 'image/png', .92);
      a.click();
   }).catch(function(){
      h.style.transform = prev;
      alert('No se pudo generar la imagen. Proba con Imprimir o guardar PDF.');
   });
}
$('d-png').onclick = function(){ bajar('png'); };
$('d-jpg').onclick = function(){ bajar('jpg'); };
$('d-pdf').onclick = function(){ window.print(); };
window.addEventListener('resize', encajar);
pintarRanuras(); pintarGalerias(); pintar();
</script>
</body>
</html>
