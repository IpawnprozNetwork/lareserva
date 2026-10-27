USE lareserv_admin;

/* La bajada de Valores seguia diciendo "completa o media": el script 20 la
   dejo asi y el valor de la base le gana al del archivo. Johnny confirmo
   que es PENSION COMPLETA. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_precios_bajada', 'Los valores son por persona e incluyen alojamiento y pensión completa. Los más chiquitos casi no pagan.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* La seccion de encuentros, abajo de todo. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_eventos_titulo', 'Encuentros con fecha fija'),
  ('v27_eventos_bajada', 'El Family Plan se reserva cualquier día del verano. Aparte de eso, estos encuentros tienen fecha puesta y cupos propios. Hacé clic para ver cada uno.'),
  ('v27_eventos_nota',   'Para reservar cualquiera de los dos, escribile a Johnny por WhatsApp y te pasa disponibilidad y forma de pago.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* El Family Plan corre todo el verano: que lo diga El programa. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_programa_bajada', 'Elegís vos cuántas noches y qué día venir: el Family Plan está disponible todo enero, febrero y marzo. No hay traslados ni horarios que cumplir, la pileta, el lago, el parque y el comedor están todos a distancia de caminar, y los chicos pueden andar sueltos.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes
 WHERE clave IN ('v27_precios_bajada','v27_eventos_titulo','v27_eventos_bajada',
                 'v27_eventos_nota','v27_programa_bajada') ORDER BY clave;
SELECT 'ambiguo?' AS control, clave FROM ajustes WHERE valor LIKE '%completa o media%';
