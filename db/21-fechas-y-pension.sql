USE lareserv_admin;

/* ── 1. La agenda ya no se lee como "estas son las unicas fechas" ────── */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_agenda_titulo', 'Enero, febrero y marzo'),
  ('v27_agenda_aviso',  'Recibimos familias todos los días de enero, febrero y marzo. Elegís vos cuándo venir: decinos la fecha y cuántos son, y te confirmamos disponibilidad.'),
  ('v27_agenda_rotulo', 'Además, estos encuentros con fecha fija')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* ── 2. La pension: una sola, la que entra en el valor publicado ──────
   OJO: esto asume PENSION COMPLETA. Si el valor de $442.000 es con media
   pension, cambia las dos filas de abajo y la linea del retiro, o editalo
   desde el panel en Verano 2027 > Pension. */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_pension_tit', 'Pensión completa'),
  ('v27_pension_txt', 'Desayuno, almuerzo y cena, con opciones vegetarianas, veganas y sin gluten. Si preferís media pensión, consultanos el valor.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

/* ── 3. La misma correccion en el retiro ─────────────────────────────── */
UPDATE retiros
   SET incluye = REPLACE(incluye,
         'Pensión completa o media',
         'Pensión completa')
 WHERE slug = 'retiro-verano-enero-2027';

UPDATE retiros
   SET incluye = REPLACE(incluye,
         'Pension completa o media',
         'Pensión completa')
 WHERE slug = 'retiro-verano-enero-2027';

SELECT clave, valor FROM ajustes
 WHERE clave IN ('v27_agenda_titulo','v27_agenda_aviso','v27_agenda_rotulo',
                 'v27_pension_tit','v27_pension_txt') ORDER BY clave;
SELECT 'queda ambiguo?' AS control, slug FROM retiros
 WHERE incluye LIKE '%completa o media%';
