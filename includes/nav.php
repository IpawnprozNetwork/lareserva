<?php
$pagina = $pagina ?? '';
$raiz   = $raiz ?? '';
$portada_transparente = $portada_transparente ?? false;
?>
<style>
/* includes/nav.php - VERSION 2.1 */
html,body{overflow-x:hidden}
/* NAV v5 - unique classes, no conflicts possible */
/* v2.0: grid de tres columnas en vez de flex con space-between.
   Con space-between y white-space:nowrap los items no se encogen, asi que
   cuando la suma pasaba el ancho de pantalla el conjunto desbordaba y la
   marca quedaba cortada contra el borde izquierdo. Con 1fr auto 1fr los
   links quedan centrados respecto de la pantalla, no del espacio que sobra,
   y las columnas laterales absorben lo que haga falta. */
#lr-header{position:fixed;top:0;left:0;right:0;z-index:9999;
  display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:16px;
  padding:14px 28px;transition:background .3s,padding .3s;
  background:<?= $portada_transparente?'transparent':'rgba(10,22,40,.96)' ?>}
#lr-acciones{display:flex;align-items:center;gap:12px;justify-self:end}
#lr-acciones a{white-space:nowrap}
#lr-header.scrolled{background:rgba(10,22,40,.96)!important;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:0 1px 0 rgba(0,180,150,.15);padding:10px 28px}
/* v2.1: la marca centrada dentro de su columna, en vez de pegada al borde.
   No se puede centrar respecto de la pantalla: ahi ya estan los links, y
   chocarian. Esto la corre hacia adentro, que es lo que se pedia. */
#lr-brand{justify-self:center;text-align:center}
@media(max-width:1280px){#lr-brand{justify-self:start;text-align:left}}
#lr-brand{font-family:'Fraunces',Georgia,serif;font-size:1.05rem;color:#e8f0f7;text-decoration:none;white-space:nowrap;line-height:1.2}
#lr-brand small{display:block;font-family:'Karla',sans-serif;font-size:.58rem;letter-spacing:.22em;opacity:.5;margin-top:2px}
#lr-hamburger{display:none;background:none;border:0;color:#e8f0f7;font-size:1.5rem;cursor:pointer;padding:6px;line-height:1}
#lr-nav-list{display:flex;align-items:center;justify-content:center;gap:22px;list-style:none;margin:0;padding:0}
#lr-nav-list li a{color:rgba(232,240,247,.72);text-decoration:none;font-size:.86rem;white-space:nowrap;padding-bottom:2px;border-bottom:1px solid transparent;transition:color .18s,border-color .18s}
#lr-nav-list li a:hover,#lr-nav-list li a.activo{color:#e8f0f7;border-bottom-color:#00c9a7}
#lr-nav-list .lr-cta a{border:1px solid rgba(0,201,167,.35);border-radius:20px;padding:6px 16px;color:#00c9a7;background:rgba(0,201,167,.1);font-size:.83rem}
#lr-nav-list .lr-cta a:hover{background:rgba(0,201,167,.2)}
#lr-nav-list .lr-signin a{border:1px solid rgba(232,240,247,.2);border-radius:20px;padding:6px 12px;color:rgba(232,240,247,.7);font-size:.8rem}
#lr-nav-list .lr-signin a:hover{border-color:rgba(0,201,167,.4);color:#00c9a7}
#lr-acciones .lr-cta-btn{border:1px solid rgba(0,201,167,.35);border-radius:20px;padding:6px 16px;color:#00c9a7;background:rgba(0,201,167,.1);font-size:.83rem;text-decoration:none}
#lr-acciones .lr-cta-btn:hover{background:rgba(0,201,167,.2)}
#lr-acciones .lr-signin-btn{border:1px solid rgba(232,240,247,.2);border-radius:20px;padding:6px 12px;color:rgba(232,240,247,.7);font-size:.8rem;text-decoration:none}
#lr-acciones .lr-signin-btn:hover{border-color:rgba(0,201,167,.4);color:#00c9a7}
@media(min-width:1041px){#lr-nav-list .lr-cta,#lr-nav-list .lr-signin{display:none}}
#lr-scrim{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9998}
/* Rango apretado antes de pasar a hamburguesa. Antes empezaba en 1100 y en
   una pantalla de 1400 con zoom al 125% ya no entraba: la marca se cortaba. */
@media(min-width:1041px) and (max-width:1280px){
  #lr-header{padding:12px 18px;gap:10px}
  #lr-nav-list{gap:14px}
  #lr-nav-list li a{font-size:.79rem}
  #lr-acciones{gap:8px}
  #lr-brand{font-size:.95rem}
}
@media(min-width:1041px) and (max-width:1150px){
  #lr-brand small{display:none}
}
@media(max-width:1040px){
  #lr-hamburger{display:block}
  #lr-header{display:flex;justify-content:space-between}
  #lr-acciones{display:none}
  #lr-nav-list{
    position:fixed;top:0;bottom:0;
    left:100vw;right:auto;
    width:260px;max-width:82vw;
    flex-direction:column;align-items:flex-start;justify-content:center;
    gap:0;padding:60px 24px 40px;
    background:#0d1e2c;
    border-left:1px solid rgba(0,180,150,.2);
    box-shadow:-8px 0 30px rgba(0,0,0,.6);
    transform:none;
    transition:left .28s ease;
    z-index:9999;
  }
  #lr-nav-list.open{left:calc(100vw - 260px)}
  #lr-nav-list li{width:100%;border-bottom:1px solid rgba(0,180,150,.1)}
  #lr-nav-list li:last-child{border-bottom:0}
  #lr-nav-list li a,#lr-nav-list .lr-cta a,#lr-nav-list .lr-signin a{display:block;width:100%;font-size:1rem;color:#e8f0f7;padding:14px 0;background:transparent;border:0;border-radius:0;white-space:normal}
  #lr-nav-list li a:hover,#lr-nav-list .lr-cta a:hover,#lr-nav-list .lr-signin a:hover{color:#00c9a7;border-bottom-color:transparent}
}
</style>

<header id="lr-header">
  <a href="<?= $raiz ?>index.php" id="lr-brand">La Reserva<small>NATURALEZA ARTE</small></a>
  <button id="lr-hamburger" aria-label="Menu">&#9776;</button>
  <ul id="lr-nav-list">
    <li><a href="<?= $raiz ?>index.php#el-lugar"<?= $pagina==='inicio'?' class="activo"':'' ?>>El lugar</a></li>
    <li><a href="<?= $raiz ?>espacios.php"<?= $pagina==='espacios'?' class="activo"':'' ?>>Espacios</a></li>
    <li><a href="<?= $raiz ?>retiros.php"<?= $pagina==='retiros'?' class="activo"':'' ?>>Agenda</a></li>
    <li><a href="<?= $raiz ?>galeria.php"<?= $pagina==='galeria'?' class="activo"':'' ?>>Galeria</a></li>
    <li><a href="<?= $raiz ?>historia.php"<?= $pagina==='historia'?' class="activo"':'' ?>>Historia</a></li>
    <li><a href="<?= $raiz ?>index.php#organizar">Organiza tu retiro</a></li>
    <!-- Estos dos viven en el <ul> solo para el menu mobile, donde la lista
         es la unica columna. En escritorio los oculta la regla de abajo y se
         muestran en #lr-acciones. -->
    <li class="lr-cta"><a href="<?= $raiz ?>contacto.php">Consultar fechas</a></li>
    <li class="lr-signin"><a href="<?= $raiz ?>acceso.php">&#128274; Ingresar</a></li>
  </ul>
  <div id="lr-acciones">
    <a class="lr-cta-btn" href="<?= $raiz ?>contacto.php">Consultar fechas</a>
    <a class="lr-signin-btn" href="<?= $raiz ?>acceso.php">&#128274; Ingresar</a>
  </div>
</header>
<div id="lr-scrim"></div>

<script>
(function(){
  var h=document.getElementById('lr-header'),
      btn=document.getElementById('lr-hamburger'),
      nav=document.getElementById('lr-nav-list'),
      scrim=document.getElementById('lr-scrim'),
      isT=<?= $portada_transparente?'true':'false' ?>;
  if(isT){
    window.addEventListener('scroll',function(){h.classList.toggle('scrolled',window.scrollY>window.innerHeight*.6);},{passive:true});
  }
  function open(){nav.classList.add('open');scrim.style.display='block';btn.innerHTML='&#10005;';document.body.style.overflow='hidden';}
  function close(){nav.classList.remove('open');scrim.style.display='none';btn.innerHTML='&#9776;';document.body.style.overflow='';}
  btn.addEventListener('click',function(e){e.stopPropagation();nav.classList.contains('open')?close():open();});
  scrim.addEventListener('click',close);
  nav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',close);});
  window.addEventListener('resize',function(){if(window.innerWidth>899)close();});
})();
</script>
