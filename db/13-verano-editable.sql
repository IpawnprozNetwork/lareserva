USE lareserv_admin;

CREATE TABLE IF NOT EXISTS verano_media (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  archivo     VARCHAR(200) NOT NULL,
  video       VARCHAR(200) NULL DEFAULT NULL,
  titulo      VARCHAR(160) NULL,
  descripcion TEXT NULL,
  datos       VARCHAR(400) NULL,
  orden       SMALLINT NOT NULL DEFAULT 0,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  creado      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orden (orden),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO verano_media (archivo, video, titulo, descripcion, datos, orden, activo)
SELECT * FROM (
 SELECT 'assets/img/verano2027/pileta-aerea.jpg' a, NULL b, 'La pileta' c,
        'Pileta descubierta con sector de reposeras y una pergola de sombra al lado. Para los ratos libres entre actividades, o para una tarde entera sin hacer nada.' d,
        'Descubierta|Reposeras y sombra|Abierta todo el dia' e, 10 f, 1 g
 UNION ALL SELECT 'assets/img/verano2027/lago-aereo.jpg','assets/video/pages/v27-aereo.mp4','El lago',
        'Un lago de agua quieta rodeado de arboleda, con una pasarela de madera que lo cruza. Las esculturas de la orilla son de artistas que pasaron por el predio.',
        'Pasarela de madera|Esculturas en la orilla|Sin acceso al agua',20,1
 UNION ALL SELECT 'assets/img/verano2027/la-casa.jpg','assets/video/pages/v27-casa.mp4','La casa',
        'La casa principal, con la galeria que recorre todo el frente. Arriba estan las habitaciones; abajo, el salon y el comedor.',
        'Habitaciones arriba|Galeria al frente|Salon y comedor abajo',30,1
 UNION ALL SELECT 'assets/img/verano2027/camino-arboles.jpg','assets/video/pages/v27-camino.mp4','Los senderos',
        'Circuitos para caminar entre la arboleda, usados para caminatas conscientes y trabajo individual en silencio.',
        'Circuitos entre arboleda|Para caminar o andar en bici|Sin autos',40,1
 UNION ALL SELECT 'assets/img/verano2027/ovejas-parque.jpg','assets/video/pages/v27-lago-escultura.mp4','El parque',
        'Pasto abierto y sectores con sombra para practicar al aire libre. Hay ovejas pastando en el predio.',
        'Pasto abierto y sombra|Ovejas sueltas|Juegos al aire libre',50,1
 UNION ALL SELECT 'assets/img/galeria/nature-04.jpg',NULL,'Los caballos',
        'Paseo a caballo dentro del predio. Se coordina al reservar, segun las edades de los chicos.',
        'Se suma al programa|Se coordina al reservar|Segun la edad',55,1
 UNION ALL SELECT 'assets/img/verano2027/grupo-lago.jpg','assets/video/pages/v27-lago-atardecer.mp4','Tardes junto al agua',
        'Las tardes se pasan en la orilla. El lago al atardecer es el momento que mas se recuerda.',
        'La hora preferida|Atardecer sobre el lago|Fogon despues',60,1
 UNION ALL SELECT 'assets/img/verano2027/mesa.jpg','assets/video/pages/v27-mesa.mp4','Mesas compartidas',
        'Mesas largas, comida casera con opciones vegetarianas, veganas y sin gluten, coordinada con el grupo.',
        'Mesas largas|Menu aparte para los chicos|Veg, vegano y sin gluten',70,1
 UNION ALL SELECT 'assets/img/verano2027/meditacion.jpg','assets/video/pages/v27-meditacion.mp4','Practica al amanecer',
        'El predio al amanecer, con niebla sobre el lago. Muchos grupos arrancan el dia ahi.',
        'Niebla sobre el lago|Para los que madrugan|Opcional',80,1
 UNION ALL SELECT 'assets/img/verano2027/cabanas-noche.jpg','assets/video/pages/cabanas-1.mp4','Las cabanas',
        'Cabanas de madera dentro del predio, a pasos del salon y del comedor. El grupo duerme, come y practica en el mismo lugar.',
        'Madera, dentro del predio|A pasos del comedor|Se arma segun el grupo',90,1
 UNION ALL SELECT 'assets/img/verano2027/cabana.jpg','assets/video/pages/v27-pagoda.mp4','Salon Pagoda',
        'Salon octogonal de madera con deck, para practicas y ceremonias en ronda. Tiene dos banos propios y parlante con microfonos.',
        'Octogonal, de madera|Dos banos propios|Parlante con microfonos',100,1
) nuevas
WHERE NOT EXISTS (SELECT 1 FROM verano_media);

SELECT orden, titulo, SUBSTRING_INDEX(archivo,'/',-1) foto,
       IFNULL(SUBSTRING_INDEX(video,'/',-1),'-') clip, activo
FROM verano_media ORDER BY orden;
