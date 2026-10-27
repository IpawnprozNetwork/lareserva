USE lareserv_admin;

INSERT INTO ajustes (clave, valor) VALUES
  ('v27_hero_bajada', CONCAT('Naturaleza y diversión.', CHAR(10),
                             'Consultá por nuestro Family Plan.'))
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor,
       (CHAR_LENGTH(valor) - CHAR_LENGTH(REPLACE(valor, CHAR(10), '')) + 1) AS renglones
FROM ajustes WHERE clave = 'v27_hero_bajada';
