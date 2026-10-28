USE lareserv_admin;

/* Johnny pidio sacar "los chicos pueden andar sueltos" y el arranque
   "Elegi cuantas noches y que dia venir". */
INSERT INTO ajustes (clave, valor) VALUES
  ('v27_programa_bajada', 'El Family Plan está disponible durante todo enero, febrero y marzo. Dentro del predio no hay traslados ni horarios que cumplir: la pileta, el lago, el parque y el comedor están a pocos metros, todo a distancia de caminar.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SELECT clave, valor FROM ajustes WHERE clave = 'v27_programa_bajada';
SELECT 'queda la frase?' AS control, clave FROM ajustes WHERE valor LIKE '%andar sueltos%';
