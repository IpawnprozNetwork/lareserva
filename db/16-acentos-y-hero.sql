USE lareserv_admin;

UPDATE ajustes SET valor = 'Naturaleza y diversión. Consultá por nuestro Family Plan.'
 WHERE clave = 'v27_hero_bajada';

UPDATE verano_media SET
  descripcion = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                  descripcion,
                  'cabanas','cabañas'), 'Cabanas','Cabañas'),
                  'banos','baños'), 'anos','años'),
                  'practica','práctica'), 'dias','días'),
                  'salon','salón'), 'Salon','Salón'),
  titulo = REPLACE(REPLACE(REPLACE(titulo,'cabanas','cabañas'),'Cabanas','Cabañas'),'Salon','Salón'),
  datos  = REPLACE(REPLACE(REPLACE(datos,'banos','baños'),'anos','años'),'practica','práctica');

UPDATE retiros SET
  incluye = REPLACE(REPLACE(REPLACE(incluye,'anos','años'),'dias','días'),'Alojamiento','Alojamiento'),
  descripcion = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                  descripcion,
                  'hectareas','hectáreas'), 'dias','días'),
                  'manana','mañana'), 'ultimo','último'),
                  'Despues','Después'), 'pension','pensión'), 'mas','más'),
  bajada = REPLACE(bajada,'dias','días')
 WHERE slug = 'retiro-verano-enero-2027';

SELECT clave, valor FROM ajustes WHERE clave = 'v27_hero_bajada';
SELECT titulo, LEFT(datos, 60) AS datos FROM verano_media ORDER BY orden LIMIT 4;
SELECT LEFT(incluye, 80) AS incluye FROM retiros WHERE slug = 'retiro-verano-enero-2027';
