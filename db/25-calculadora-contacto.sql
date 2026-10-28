USE lareserv_admin;

INSERT INTO ajustes (clave, valor) VALUES
  ('v27_calc_titulo',     'Calculá tu estadía'),
  ('v27_calc_nota',       'Es una estimación. El valor final lo confirmamos por WhatsApp junto con la disponibilidad y la forma de pago.'),
  ('v27_contacto_nombre', 'Jonathan Ramírez'),
  ('v27_contacto_cargo',  'Coordina las reservas del Family Plan y de los encuentros del verano.'),
  ('v27_contacto_mail',   'Jonathanr@fasedos.com.ar'),
  ('v27_eventos_nota',    'Para reservar, escribile a Jonathan Ramírez por WhatsApp o por mail y te pasa disponibilidad y forma de pago.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes
 WHERE clave LIKE 'v27_calc%' OR clave LIKE 'v27_contacto%' OR clave = 'v27_eventos_nota'
 ORDER BY clave;
