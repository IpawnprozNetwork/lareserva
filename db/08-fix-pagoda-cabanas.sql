USE lareserv_admin;
UPDATE espacio_media SET espacio_id = 10, titulo = 'El Pagoda desde afuera', orden = 3 WHERE id = 50 AND archivo LIKE '%v27-cabana.jpg';
UPDATE espacios SET imagen = 'assets/img/galeria/cabanas.jpg' WHERE slug = 'alojamiento-cabanas';
UPDATE espacios SET imagen = 'assets/img/galeria/v27-cabana.jpg' WHERE slug = 'salon-pagoda';
UPDATE espacio_media SET orden = 1 WHERE id = 35 AND archivo LIKE '%cabanas.jpg';
SELECT e.slug, e.imagen AS portada, COUNT(m.id) AS archivos FROM espacios e LEFT JOIN espacio_media m ON m.espacio_id = e.id WHERE e.slug IN ('alojamiento-cabanas','salon-pagoda') GROUP BY e.id, e.slug, e.imagen;
