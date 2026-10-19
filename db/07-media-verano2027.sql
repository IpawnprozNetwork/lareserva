UPDATE espacios SET imagen='assets/img/galeria/v27-pileta-aerea.jpg' WHERE slug='pileta';
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-pileta-aerea.jpg','La pileta desde arriba',5,1
FROM espacios e WHERE e.slug='pileta' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-pileta-aerea.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-pileta.jpg','La pileta',6,1
FROM espacios e WHERE e.slug='pileta' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-pileta.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'video','assets/video/pages/v27-pagoda.mp4','El Pagoda desde afuera',25,1
FROM espacios e WHERE e.slug='salon-pagoda' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/video/pages/v27-pagoda.mp4');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-lago-aereo.jpg','El lago desde el aire',5,1
FROM espacios e WHERE e.slug='lago-y-senderos' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-lago-aereo.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'video','assets/video/pages/v27-lago-atardecer.mp4','El lago al atardecer',15,1
FROM espacios e WHERE e.slug='lago-y-senderos' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/video/pages/v27-lago-atardecer.mp4');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'video','assets/video/pages/v27-aereo.mp4','El predio desde el aire',5,1
FROM espacios e WHERE e.slug='parque-y-arboleda' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/video/pages/v27-aereo.mp4');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-grupo-parque.jpg','En el parque',8,1
FROM espacios e WHERE e.slug='parque-y-arboleda' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-grupo-parque.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-mesa.jpg','La mesa',5,1
FROM espacios e WHERE e.slug='comedor' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-mesa.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-limonada.jpg','Limonada en la galeria',6,1
FROM espacios e WHERE e.slug='comedor' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-limonada.jpg');
UPDATE espacios SET imagen='assets/img/galeria/v27-cabana.jpg' WHERE slug='alojamiento-cabanas';
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-cabana.jpg','Las cabanas',5,1
FROM espacios e WHERE e.slug='alojamiento-cabanas' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-cabana.jpg');
INSERT INTO espacio_media (espacio_id, tipo, archivo, titulo, orden, activo)
SELECT e.id,'imagen','assets/img/galeria/v27-la-casa.jpg','La casa',45,1
FROM espacios e WHERE e.slug='salon-principal' AND NOT EXISTS
 (SELECT 1 FROM espacio_media m WHERE m.espacio_id=e.id AND m.archivo='assets/img/galeria/v27-la-casa.jpg');
INSERT INTO galeria (archivo, titulo, alt, orden, activo)
SELECT * FROM (
  SELECT 'assets/img/galeria/v27-pileta-aerea.jpg' a,'La pileta' b,'La pileta del predio vista desde arriba' c,5 d,1 e UNION ALL
  SELECT 'assets/img/galeria/v27-lago-aereo.jpg','El lago','El lago del predio visto desde el aire',6,1 UNION ALL
  SELECT 'assets/img/galeria/v27-lago-atardecer.jpg','Atardecer en el lago','El lago al atardecer',7,1 UNION ALL
  SELECT 'assets/img/galeria/v27-casa-aerea.jpg','La casa desde el aire','La casa principal vista desde arriba',8,1 UNION ALL
  SELECT 'assets/img/galeria/v27-grupo-lago.jpg','Tarde junto al lago','Un grupo descansando junto al lago',9,1 UNION ALL
  SELECT 'assets/img/galeria/v27-meditacion.jpg','Practica al amanecer','Practica frente al lago al amanecer',11,1 UNION ALL
  SELECT 'assets/img/galeria/v27-mesa.jpg','La mesa','Mesa servida en el comedor',12,1
) nuevas
WHERE NOT EXISTS (SELECT 1 FROM galeria g WHERE g.archivo = nuevas.a);
SELECT e.orden, e.slug, e.nombre, COUNT(m.id) AS archivos
FROM espacios e LEFT JOIN espacio_media m ON m.espacio_id=e.id AND m.activo=1
WHERE e.activo=1 GROUP BY e.id, e.orden, e.slug, e.nombre ORDER BY e.orden;
SELECT COUNT(*) AS fotos_galeria FROM galeria WHERE activo=1;
