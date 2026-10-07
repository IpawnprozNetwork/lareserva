-- ═══════════════════════════════════════════════════════════════════
--  La Reserva - BORRAR DATOS DE PRUEBA  v2
-- ═══════════════════════════════════════════════════════════════════
--
--  POR QUE HAY UN v2
--  La v1 usaba FIND_IN_SET con una variable @mails_prueba. Las tablas
--  de esta base no comparten collation: consultas.email es
--  utf8mb4_general_ci y mensajes.de_email es utf8mb4_unicode_ci.
--  MySQL se niega a comparar entre las dos y tira el error 1267.
--  Esta version usa listas IN con literales, que no tienen ese
--  problema porque el literal adopta la collation de la columna.
--
--  SI YA CORRISTE LA v1 Y SE CORTO EN EL PASO 2:
--  el paso 1 ya se ejecuto, las consultas de prueba estan borradas.
--  Corre el BLOQUE B de aca abajo entero igual: el paso 1 no va a
--  encontrar nada que borrar y sigue de largo sin romper nada.
--
--  Exporta las tablas antes. phpMyAdmin > Exportar > Rapido.
--
--  Corre UN bloque, no los tres.
-- ═══════════════════════════════════════════════════════════════════


-- ═══════════════════════════════════════════════════════════════════
--  BLOQUE A - MIRAR. No borra nada.
-- ═══════════════════════════════════════════════════════════════════

SELECT 'consultas' AS tabla, COUNT(*) AS filas, MIN(creado) AS primera, MAX(creado) AS ultima FROM consultas
UNION ALL SELECT 'eventos',        COUNT(*), MIN(creado), MAX(creado) FROM eventos
UNION ALL SELECT 'mensajes',       COUNT(*), MIN(creado), MAX(creado) FROM mensajes
UNION ALL SELECT 'resenas',        COUNT(*), MIN(creado), MAX(creado) FROM resenas
UNION ALL SELECT 'referido_pagos', COUNT(*), MIN(creado), MAX(creado) FROM referido_pagos
UNION ALL SELECT 'ip_bloqueadas',  COUNT(*), MIN(creado), MAX(creado) FROM ip_bloqueadas;

SELECT id, creado, nombre, email, tipo, estado, origen, ref, boton FROM consultas ORDER BY id;
SELECT id, creado, de_nombre, de_email, asunto FROM mensajes ORDER BY id;


-- ═══════════════════════════════════════════════════════════════════
--  BLOQUE B - BORRAR SOLO LO DE PRUEBA
--  Agrega o saca mails de las listas IN segun lo que usaste.
-- ═══════════════════════════════════════════════════════════════════

-- 1. Consultas de prueba
DELETE FROM consultas
WHERE email IN ('merovingian369@gmail.com',
                'merovingian369@outlook.com',
                'johnny@lareserva.net.ar',
                'prueba@lareserva.net.ar')
   OR email LIKE '%@example.com'
   OR email LIKE '%@test.%'
   OR nombre IN ('carlos','Carlos','test','Test','prueba','Prueba','Ana','Zoe');

-- 2. Mensajes de prueba en el Inbox
DELETE FROM mensajes
WHERE asunto LIKE '%prueba%'
   OR asunto LIKE '%test%'
   OR de_email IN ('merovingian369@gmail.com',
                   'merovingian369@outlook.com',
                   'johnny@lareserva.net.ar',
                   'prueba@lareserva.net.ar');

-- 3. Pagos de referidos cargados probando
DELETE FROM referido_pagos WHERE nota LIKE '%prueba%' OR nota LIKE '%test%';

-- 4. Resenas de prueba sin aprobar
DELETE FROM resenas WHERE aprobada = 0 AND (nombre LIKE '%test%' OR nombre LIKE '%prueba%');

-- 5. Eventos: se borran todos.
--    Un click no deja mail, asi que no hay forma de separar los tuyos.
--    Un historial mezclado con pruebas hace que los porcentajes de
--    conversion mientan durante meses.
DELETE FROM eventos;
ALTER TABLE eventos AUTO_INCREMENT = 1;

-- 6. IPs bloqueadas probando el boton
DELETE FROM ip_bloqueadas WHERE motivo LIKE '%prueba%' OR motivo LIKE '%test%' OR motivo = 'desde el panel';

-- Control
SELECT 'consultas' AS tabla, COUNT(*) AS quedan FROM consultas
UNION ALL SELECT 'eventos',   COUNT(*) FROM eventos
UNION ALL SELECT 'mensajes',  COUNT(*) FROM mensajes
UNION ALL SELECT 'resenas',   COUNT(*) FROM resenas;

SELECT id, creado, nombre, email, estado FROM consultas ORDER BY id;


-- ═══════════════════════════════════════════════════════════════════
--  BLOQUE C - DEJAR TODO EN CERO
-- ═══════════════════════════════════════════════════════════════════

DELETE FROM consultas;       ALTER TABLE consultas      AUTO_INCREMENT = 1;
DELETE FROM eventos;         ALTER TABLE eventos        AUTO_INCREMENT = 1;
DELETE FROM mensajes;        ALTER TABLE mensajes       AUTO_INCREMENT = 1;
DELETE FROM resenas;         ALTER TABLE resenas        AUTO_INCREMENT = 1;
DELETE FROM referido_pagos;  ALTER TABLE referido_pagos AUTO_INCREMENT = 1;
DELETE FROM ip_bloqueadas;

-- Los codigos de referido son configuracion, no datos. No se borran.
-- Para vaciarlos tambien, descomenta:
-- DELETE FROM referidos;    ALTER TABLE referidos      AUTO_INCREMENT = 1;

SELECT 'consultas' AS tabla, COUNT(*) AS filas FROM consultas
UNION ALL SELECT 'eventos',        COUNT(*) FROM eventos
UNION ALL SELECT 'mensajes',       COUNT(*) FROM mensajes
UNION ALL SELECT 'resenas',        COUNT(*) FROM resenas
UNION ALL SELECT 'referido_pagos', COUNT(*) FROM referido_pagos
UNION ALL SELECT 'ip_bloqueadas',  COUNT(*) FROM ip_bloqueadas
UNION ALL SELECT '--- contenido, no se toca ---', NULL
UNION ALL SELECT 'retiros',        COUNT(*) FROM retiros
UNION ALL SELECT 'espacios',       COUNT(*) FROM espacios
UNION ALL SELECT 'galeria',        COUNT(*) FROM galeria
UNION ALL SELECT 'usuarios',       COUNT(*) FROM usuarios;


-- ═══════════════════════════════════════════════════════════════════
--  APARTE: unificar las collations
--
--  El error de la v1 salio porque las tablas no comparten collation.
--  No rompe nada hoy, pero cualquier JOIN o comparacion entre
--  consultas.email y mensajes.de_email va a fallar igual.
--  Conviene emparejarlas cuando tengas un rato tranquilo:
--
--  ALTER TABLE mensajes  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--  ALTER TABLE eventos   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--  ALTER TABLE referidos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--  ALTER TABLE referido_pagos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--
--  Para ver como esta cada una:
--  SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = 'lareserv_admin' ORDER BY TABLE_NAME;
-- ═══════════════════════════════════════════════════════════════════
