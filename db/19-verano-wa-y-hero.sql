USE lareserv_admin;

/* El WhatsApp de la landing de verano, aparte del resto del sitio. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_wa',       '5491169390659'),
  ('v27_wa_label', '+54 9 11 6939-0659')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* El texto de abajo del titulo, en tres renglones. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_hero_bajada', CONCAT('Vení a disfrutar del verano 2027', CHAR(10),
                             'Naturaleza y diversión.',          CHAR(10),
                             'Consultá por nuestro Family Plan.'))
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor,
       (CHAR_LENGTH(valor) - CHAR_LENGTH(REPLACE(valor, CHAR(10), '')) + 1) AS renglones
FROM ajustes WHERE clave IN ('v27_wa','v27_wa_label','v27_hero_bajada') ORDER BY clave;
