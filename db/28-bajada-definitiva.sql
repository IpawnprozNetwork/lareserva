USE lareserv_admin;

/* El texto que mando Johnny va como BAJADA del titulo, no al pie de las
   fotos: ahi lo habia puesto por error. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_programa_bajada', 'Temporada familiar disponible de enero a marzo. Para gestionar su reserva y confirmar disponibilidad, requerimos que nos indique la fecha exacta de su visita junto con el número total de acompañantes.'),
  ('v27_programa_pie',    '')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave,
       CASE WHEN valor = '' THEN '(vacio, no se muestra)' ELSE valor END AS valor
FROM ajustes WHERE clave LIKE 'v27_programa%' ORDER BY clave;
