USE lareserv_admin;

INSERT INTO ajustes (clave, valor) VALUES
  ('pie_wa2',       '+54 9 11 6939-0659'),
  ('pie_wa2_label', '+54 9 11 6939-0659'),
  ('pie_email2',    'Jonathanr@fasedos.com.ar')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes WHERE clave LIKE 'pie_%' ORDER BY clave;
