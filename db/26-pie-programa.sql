USE lareserv_admin;

INSERT INTO ajustes (clave, valor) VALUES
  ('v27_programa_pie', 'Temporada familiar disponible de enero a marzo. Para gestionar su reserva y confirmar disponibilidad, requerimos que nos indique la fecha exacta de su visita junto con el número total de acompañantes.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes WHERE clave = 'v27_programa_pie';
