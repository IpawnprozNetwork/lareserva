USE lareserv_admin;
INSERT INTO ajustes (clave, valor) VALUES ('mail_copia', 'Jonathanr@fasedos.com.ar')
  ON DUPLICATE KEY UPDATE valor = VALUES(valor);
SELECT clave, valor FROM ajustes WHERE clave = 'mail_copia';
