<?php
require_once __DIR__.'/includes/config.php';
$pagina='historia'; $raiz='';
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Historia · La Reserva Naturaleza Arte</title>
<meta name="description" content="La historia de La Reserva: del campo pampeano a espacio de encuentro, arte y bienestar. Capilla del Señor, Buenos Aires.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600&family=Karla:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<style>
/* ── Historia page extras ── */
.hist-hero{
  min-height:70vh;display:flex;align-items:flex-end;
  position:relative;overflow:hidden;
  background:#0d1208;
  padding:0 24px 72px;
}
.hist-hero video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
.hist-hero .hist-veil{position:absolute;inset:0;z-index:1;
  background:linear-gradient(180deg,rgba(13,18,8,.4) 0%,rgba(13,18,8,.85) 100%)}
.hist-hero-inner{max-width:1180px;margin:0 auto;color:#fff}
.hist-hero .rotulo{font-size:.75rem;letter-spacing:.2em;color:var(--verde-claro);margin-bottom:14px}
.hist-hero h1{font-size:clamp(2.4rem,5vw,4rem);font-family:'Fraunces',Georgia,serif;font-weight:300;line-height:1.1;max-width:16ch}
.hist-hero p{color:rgba(255,255,255,.72);font-size:1.05rem;max-width:52ch;margin-top:18px;line-height:1.7}

/* Timeline */
.timeline{max-width:820px;margin:0 auto;padding:80px 24px 100px;position:relative}
.timeline::before{content:'';position:absolute;left:28px;top:80px;bottom:60px;width:1px;background:var(--linea)}
@media(min-width:640px){
  .timeline::before{left:50%;transform:translateX(-50%)}
}
.era{position:relative;margin-bottom:64px}
.era-dot{
  width:10px;height:10px;border-radius:50%;
  background:var(--verde);border:2px solid var(--hueso);
  position:absolute;left:24px;top:6px;
}
@media(min-width:640px){.era-dot{left:50%;transform:translateX(-50%)}}
.era-inner{padding-left:52px}
@media(min-width:640px){
  .era:nth-child(odd) .era-inner{padding-left:0;padding-right:calc(50% + 40px)}
  .era:nth-child(even) .era-inner{padding-left:calc(50% + 40px);padding-right:0}
}
.era-year{font-size:.78rem;letter-spacing:.2em;color:var(--verde-claro);font-family:'Karla',sans-serif;margin-bottom:6px;font-weight:500}
.era h2{font-family:'Fraunces',Georgia,serif;font-weight:400;font-size:1.45rem;color:var(--tinta);margin-bottom:12px;line-height:1.2}
.era p{font-size:.96rem;color:var(--gris);line-height:1.72;margin:0 0 10px}
.era-tag{display:inline-block;font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;padding:3px 10px;border-radius:2px;background:rgba(61,92,64,.12);color:var(--verde);font-weight:500;margin-bottom:10px}

/* Stat strip */
.stat-strip{background:#0a1628;border-top:1px solid rgba(0,180,150,.15);border-bottom:1px solid rgba(0,180,150,.15);padding:52px 0}
.stat-strip .envoltura{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;text-align:center}
.stat-n{font-family:'Fraunces',Georgia,serif;font-size:2.6rem;font-weight:300;color:#00c9a7;line-height:1}
.stat-l{font-size:.8rem;letter-spacing:.14em;color:var(--verde-claro);margin-top:8px}
@media(max-width:680px){.stat-strip .envoltura{grid-template-columns:1fr 1fr;gap:32px 16px}}

/* Partners */
.partners{padding:80px 0;background:var(--niebla)}
.partners h2{font-family:'Fraunces',Georgia,serif;font-weight:400;font-size:2rem;margin-bottom:10px}
.partner-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;margin-top:40px}
.partner-card{background:var(--papel);padding:28px 26px;border:1px solid var(--linea);border-radius:2px}
.partner-card h3{font-size:1.1rem;font-family:'Fraunces',Georgia,serif;font-weight:400;margin-bottom:8px}
.partner-card p{font-size:.92rem;color:var(--gris);line-height:1.65;margin:0}
.partner-card .badge{font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;color:var(--verde);margin-bottom:10px;display:block}

/* Quote */
.pullquote{max-width:680px;margin:0 auto;padding:80px 24px;text-align:center}
.pullquote blockquote{font-family:'Fraunces',Georgia,serif;font-size:clamp(1.3rem,2.5vw,1.9rem);font-weight:300;line-height:1.4;color:var(--tinta);font-style:italic;margin:0 0 18px}
.pullquote cite{font-size:.82rem;letter-spacing:.14em;color:var(--gris);font-style:normal}

/* Notice */
.notice{max-width:820px;margin:0 auto 80px;padding:0 24px}
.notice-box{background:rgba(61,92,64,.08);border:1px solid rgba(61,92,64,.2);border-left:3px solid var(--verde);padding:16px 20px;font-size:.9rem;color:var(--gris);line-height:1.65}
.notice-box strong{color:var(--tinta)}
</style>
</head>
<body>
<?php $portada_transparente=false; include __DIR__.'/includes/nav.php'; ?>

<!-- Hero -->
<section class="hist-hero" style="padding-top:80px">
  <video autoplay muted loop playsinline preload="metadata"
         poster="assets/video/hero-poster.jpg"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0">
    <source src="assets/video/pages/historia.mp4" type="video/mp4"
            onerror="this.parentElement.style.display='none'">
  </video>
  <div class="hist-veil"></div>
  <div class="hist-hero-inner" style="position:relative;z-index:2">
    <p class="rotulo">HISTORIA · DESDE 1735 HASTA HOY</p>
    <h1>El lugar y su historia</h1>
    <p>Una estancia centenaria en la pampa húmeda que se convirtió en espacio de encuentro, arte y bienestar. La historia de La Reserva es inseparable de la historia de Capilla del Señor.</p>
  </div>
</section>

<!-- Stats -->
<div class="stat-strip">
  <div class="envoltura">
    <div><div class="stat-n">250</div><div class="stat-l">HECTÁREAS</div></div>
    <div><div class="stat-n">1735</div><div class="stat-l">FUNDACIÓN DEL PUEBLO</div></div>
    <div><div class="stat-n">77</div><div class="stat-l">KM DESDE BUENOS AIRES</div></div>
    <div><div class="stat-n">2010+</div><div class="stat-l">AÑOS DE RETIROS</div></div>
  </div>
</div>

<!-- Timeline -->
<div class="timeline">

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">1735</div>
      <span class="era-tag">Fundación del territorio</span>
      <h2>Capilla del Señor: el pueblo más antiguo de la llanura</h2>
      <p>La localidad de Capilla del Señor, cabecera del partido de Exaltación de la Cruz, fue fundada en 1735 junto al curso del Arroyo de la Cruz. Desde sus primeros años fue un punto de parada obligado en la ruta a las estancias ganaderas del norte bonaerense.</p>
      <p>El entorno que hoy rodea a La Reserva era, por entonces, campo puro: <em>pampa húmeda</em> con pastizales naturales, lagunas estacionales y montes de espinillos. Las grandes estancias dominaban el paisaje, muchas de las cuales permanecen activas hasta hoy.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">1994</div>
      <span class="era-tag">Patrimonio histórico nacional</span>
      <h2>Capilla del Señor declarada Bien de Interés Histórico Nacional</h2>
      <p>En 1994, el Estado Nacional reconoció a Capilla del Señor como Bien de Interés Histórico Nacional, protegiendo su arquitectura y tejido urbano que conservó prácticamente intacto el carácter del pueblo pampeano del siglo XIX.</p>
      <p>Esta declaración impulsó el turismo cultural en la zona y abrió la puerta a una nueva valoración del entorno rural circundante. Las estancias y campos del área comenzaron a reconvertirse lentamente hacia actividades de turismo rural y bienestar.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">Principios de 2000</div>
      <span class="era-tag">El predio toma forma</span>
      <h2>De campo ganadero a espacio de encuentro</h2>
      <p>El predio que hoy ocupa La Reserva tiene raíces en una estancia de campo de la pampa húmeda, con casco colonial, laguna propia y más de 250 hectáreas. A principios de los 2000, con el auge del turismo alternativo y el interés creciente por el bienestar y las prácticas holísticas, los propietarios comenzaron a explorar una reconversión del campo.</p>
      <p>La laguna en el centro del predio, la arboleda centenaria, los galpones de ladrillo y el espejo de agua resultaron ser el escenario ideal para retiros, talleres y encuentros grupales que la ciudad no podía ofrecer.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">2010 - 2015</div>
      <span class="era-tag">Nacimiento del proyecto</span>
      <h2>La Reserva Naturaleza + Arte abre sus puertas</h2>
      <p>La Reserva Naturaleza + Arte comenzó a recibir grupos de retiro, talleres de yoga, meditación, constelaciones familiares y artes marciales. El casco principal fue adaptado para alojar grupos, manteniendo el espíritu rural del edificio original: paredes de ladrillo, techos de teja, galerías abiertas al parque.</p>
      <p>El proyecto se posicionó rápidamente en la escena de bienestar del área metropolitana de Buenos Aires como una de las pocas propuestas que combinaba alojamiento, gastronomía y espacios de práctica en un mismo predio rural, a menos de una hora de la ciudad.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">2015 - 2020</div>
      <span class="era-tag">Crecimiento y reconocimiento</span>
      <h2>Referente del turismo holístico en el norte bonaerense</h2>
      <p>La Reserva se consolidó como referente del turismo consciente en la provincia de Buenos Aires. Facilitadores de biodanza, gestalt, meditación vipassana, constelaciones familiares y yoga comenzaron a elegir el predio para sus retiros anuales, atraídos por la combinación única de silencio, naturaleza y logística completa.</p>
      <p>Publicaciones como <em>Radar de Viajes</em> y guías turísticas provinciales mencionaron La Reserva como destino destacado de la región de Capilla del Señor, junto a las estancias de polo y las posadas históricas del partido de Exaltación de la Cruz.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">2020 - 2023</div>
      <span class="era-tag">Pandemia y reinvención</span>
      <h2>El campo como respuesta a la ciudad cerrada</h2>
      <p>La pandemia de 2020 aceleró una tendencia que ya estaba en marcha: la búsqueda de naturaleza, espacio y silencio como antídoto al encierro urbano. La Reserva, como muchos espacios rurales, vivió una demanda sin precedentes en cuanto se habilitaron los movimientos entre distritos.</p>
      <p>Este período consolidó la identidad del lugar: no se trata solo de un salón de retiros, sino de un ecosistema completo donde el entorno-los árboles, la laguna, el sonido del campo-es parte central de la experiencia que ofrecen los facilitadores que lo eligen.</p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">Uso consolidado</div>
      <span class="era-tag">Turismo educativo · Partner</span>
      <h2>Fase 2 Turismo Educativo: inglés en el campo</h2>
      <p>Una de las alianzas más sólidas del predio es con <strong>Fase 2 Turismo Educativo</strong>, operador especializado en turismo educativo y estudio de idiomas. Su programa <em>Immersion Experience</em> lleva grupos de alumnos de 8 a 17 años al predio de La Reserva para campamentos de inglés intensivo de 2 noches.</p>
      <p>Durante el programa, la comunicación se realiza exclusivamente en inglés. Los participantes interactúan con hablantes nativos de distintos países en actividades lúdicas, culturales, deportivas y recreativas diseñadas para ejercitar el idioma en contexto real. El predio-con sus espacios de alojamiento, comedor, parque y canchas-resulta ideal para este formato de inmersión completa.</p>
      <p>También usan el predio para <em>Foreigner in Your Own Land</em>, otro de sus programas de inmersión en inglés. Sede: Esmeralda 561, Piso 3, Buenos Aires. <a href="https://www.fase2.tur.ar" target="_blank" rel="noopener" style="color:var(--verde)">fase2.tur.ar</a></p>
    </div>
  </div>

  <div class="era">
    <div class="era-dot"></div>
    <div class="era-inner">
      <div class="era-year">Hoy</div>
      <span class="era-tag">La Reserva actual</span>
      <h2>Un lugar para que tu retiro suceda</h2>
      <p>Hoy La Reserva recibe grupos de retiro propios y de facilitadores externos que alquilan el predio para sus propuestas. El lugar ofrece salón principal con piso de madera y ventanales al parque, alojamiento en el casco, comedor con cocina adaptable, parque, laguna y senderos, todo dentro del predio.</p>
      <p>La propuesta sigue siendo la misma desde el principio: el entorno hace la mitad del trabajo. El facilitador trae el contenido; La Reserva pone el silencio, el espacio y la naturaleza.</p>
    </div>
  </div>

</div>

<!-- Pullquote -->
<div class="pullquote">
  <blockquote>"Un paraíso en la pampa húmeda, para el encuentro con uno mismo, los demás y la naturaleza."</blockquote>
  <cite>- La Reserva Naturaleza Arte</cite>
</div>

<!-- Partners -->
<section class="partners">
  <div class="envoltura">
    <div class="dos-columnas">
      <h2 class="titulo-bloque">Alianzas y partners</h2>
      <p class="medida" style="color:var(--gris)">La Reserva trabaja con facilitadores, organizaciones y emprendedores del ecosistema de bienestar, arte y desarrollo personal.</p>
    </div>
    <div class="partner-grid">
      <div class="partner-card">
        <span class="badge">TURISMO EDUCATIVO · PARTNER PRINCIPAL</span>
        <h3><a href="https://www.fase2.tur.ar" target="_blank" rel="noopener" style="color:var(--tinta);text-decoration:none">Fase 2 Turismo Educativo</a></h3>
        <p>Operador de turismo educativo con sede en Buenos Aires (Esmeralda 561, Piso 3). Desarrollan en el predio de La Reserva su programa <strong>Immersion Experience</strong>: campamentos de inglés intensivo de 2 noches para alumnos de 8 a 17 años, con actividades lúdicas, culturales y deportivas realizadas íntegramente en inglés junto a hablantes nativos. Es uno de los usos más frecuentes y consolidados del predio.</p>
        <p style="margin-top:10px;font-size:.82rem"><a href="https://www.fase2.tur.ar/destino/experiencia" target="_blank" rel="noopener" style="color:var(--verde)">Ver programa Immersion Experience →</a></p>
      </div>
      <div class="partner-card">
        <span class="badge">FACILITADORES · BIENESTAR</span>
        <h3>Facilitadores del ecosistema holístico</h3>
        <p>Equipos de yoga, meditación, biodanza, constelaciones familiares y otras prácticas que alquilan el predio para sus retiros anuales y contribuyen a la agenda abierta del lugar.</p>
      </div>
      <div class="partner-card">
        <span class="badge">GASTRONOMÍA</span>
        <h3>Cocina consciente</h3>
        <p>Proveedores locales de la región de Capilla del Señor y el norte bonaerense que abastecen la cocina del predio con productos de temporada, orgánicos y de cercanía.</p>
      </div>
      <div class="partner-card">
        <span class="badge">TERRITORIO</span>
        <h3>Exaltación de la Cruz</h3>
        <p>El municipio y las organizaciones culturales y turísticas del partido, con quienes La Reserva comparte la valorización del patrimonio natural e histórico de la zona.</p>
      </div>
      <div class="partner-card">
        <span class="badge">RED DE BIENESTAR</span>
        <h3>Organizaciones del ecosistema</h3>
        <p>Redes de turismo consciente, bienestar integral y desarrollo personal de Buenos Aires y la región que derivan grupos y retiros al predio.</p>
      </div>
    </div>

    <div class="notice" style="margin-top:48px;padding:0">
      <div class="notice-box">
        <strong>Nota sobre esta historia:</strong> La información publicada aquí combina datos verificados públicamente (dirección, descripción del entorno, menciones en medios) con el relato general del proyecto basado en el material disponible en redes sociales. Los detalles específicos sobre propietarios, fechas exactas de fundación del proyecto y partners deberán ser confirmados y completados por el equipo de La Reserva antes de publicar esta página en el sitio definitivo.
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="bloque oscuro">
  <div class="envoltura dos-columnas">
    <h2 class="titulo-bloque" style="color:#fff">¿Querés ser parte?</h2>
    <div class="medida">
      <p style="color:var(--niebla)">Si sos facilitador o tenés una propuesta para traer al predio, escribinos y coordinamos una visita.</p>
      <p style="margin-top:20px"><a href="contacto.php" onclick="lrBoton('click_rsvp','johnny','historia CTA')" class="boton claro">Consultar disponibilidad</a></p>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
