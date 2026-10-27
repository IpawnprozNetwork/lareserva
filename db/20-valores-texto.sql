USE lareserv_admin;

/* "Cuanto sale" sonaba a precio de kiosco. En hoteleria argentina se dice
   "valores", y "la estadia" es como lo nombra el huesped. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_precios_titulo', 'Valor de la estadía'),
  ('v27_precios_bajada', 'Los valores son por persona e incluyen alojamiento y pensión completa o media. Los más chiquitos casi no pagan.'),
  ('v27_precios_nota',   'Los valores son por persona. No recibimos mascotas.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes WHERE clave LIKE 'v27_precios%' ORDER BY clave;
