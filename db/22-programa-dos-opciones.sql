USE lareserv_admin;

INSERT INTO ajustes (clave, valor) VALUES
  ('v27_programa_titulo', '4 días y 3 noches, o 3 días y 2 noches'),
  ('v27_programa_bajada', 'Elegís vos cuántas noches. En el medio no hay traslados ni horarios que cumplir: la pileta, el lago, el parque y el comedor están todos a distancia de caminar. Los chicos pueden andar sueltos.'),
  ('v27_hero_bajada',     CONCAT('Naturaleza y diversión.', CHAR(10),
                                 'Consultá por nuestro Family Plan.'))
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* Los de la agenda quedan sin uso: la pagina ya no lista retiros. No se
   borran por si mas adelante se quiere volver a mostrar algo ahi. */
SELECT clave, valor FROM ajustes WHERE clave LIKE 'v27_programa%' ORDER BY clave;
