USE lareserv_admin;
ALTER TABLE mensajes ADD COLUMN borrado DATETIME NULL DEFAULT NULL;
ALTER TABLE mensajes ADD INDEX idx_borrado (borrado);
UPDATE usuarios SET rol = 'superadmin' WHERE email = 'merovingian369@outlook.com';
SELECT id, nombre, email, rol, activo FROM usuarios ORDER BY id;
SELECT COUNT(*) AS mensajes_en_bandeja FROM mensajes WHERE borrado IS NULL;
