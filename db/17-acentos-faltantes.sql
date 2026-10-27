USE lareserv_admin;

/* El hero no existia como fila: el UPDATE anterior no afecto nada.
   Con INSERT ... ON DUPLICATE KEY queda creado o actualizado, de las dos
   formas. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_hero_bajada', 'Naturaleza y diversión. Consultá por nuestro Family Plan.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* La pasada anterior dejo `datos` a medias: solo cambiaba baños, años y
   práctica, asi que Galeria y Salon quedaron sin acento.

   Aca se usa REGEXP_REPLACE con \\b para que el reemplazo agarre la palabra
   entera y no un pedazo de otra. Con REPLACE a secas, 'mas' convierte
   'mascotas' en 'máscotas' y 'dia' convierte 'diario' en 'díario'. */
UPDATE verano_media SET
  titulo      = REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(titulo,
                  '\\bSalon\\b','Salón'), '\\bGaleria\\b','Galería'), '\\bPagoda\\b','Pagoda'),
  datos       = REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(
                REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(datos,
                  '\\bGaleria\\b','Galería'), '\\bSalon\\b','Salón'),
                  '\\bmicrofonos\\b','micrófonos'), '\\bpergola\\b','pérgola'),
                  '\\bfogon\\b','fogón'), '\\bMenu\\b','Menú'), '\\bsegun\\b','según'),
  descripcion = REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(
                REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(
                REGEXP_REPLACE(descripcion,
                  '\\bGaleria\\b','Galería'), '\\bSalon\\b','Salón'),
                  '\\bmicrofonos\\b','micrófonos'), '\\bpergola\\b','pérgola'),
                  '\\bfogon\\b','fogón'), '\\bMenu\\b','Menú'),
                  '\\bsegun\\b','según'), '\\bmas\\b','más'), '\\bmusica\\b','música');

UPDATE retiros SET
  incluye = REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(incluye,
              '\\bMenu\\b','Menú'), '\\bpension\\b','pensión'), '\\bPension\\b','Pensión')
 WHERE slug = 'retiro-verano-enero-2027';

/* Control: ninguna de estas tiene que devolver filas. */
SELECT 'datos sin acento' AS control, id, titulo, datos FROM verano_media
 WHERE datos REGEXP '\\b(Galeria|Salon|banos|anos|practica|microfonos|pergola|fogon|Menu|segun)\\b';
SELECT 'descripcion sin acento' AS control, id, titulo FROM verano_media
 WHERE descripcion REGEXP '\\b(Galeria|Salon|cabanas|banos|anos|practica|dias|fogon|Menu|segun|mas)\\b';
SELECT clave, valor FROM ajustes WHERE clave = 'v27_hero_bajada';
SELECT titulo, datos FROM verano_media ORDER BY orden;
