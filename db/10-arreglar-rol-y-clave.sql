USE lareserv_admin;

ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','editor') NOT NULL DEFAULT 'editor';

UPDATE usuarios SET rol = 'superadmin' WHERE email = 'merovingian369@outlook.com';
UPDATE usuarios SET rol = 'admin' WHERE rol = '' OR rol IS NULL;

UPDATE usuarios
   SET pass_hash = '$2y$10$lgIDF3M7/LswoOxnsiSHBeavP0E9Vv.ODxPSD1Kf966IEmUu760C2',
       activo    = 1
 WHERE email = 'merovingian369@outlook.com';

CREATE TABLE IF NOT EXISTS recuperaciones (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT          NOT NULL,
  token      CHAR(64)     NOT NULL,
  expira     DATETIME     NOT NULL,
  usado      DATETIME     NULL DEFAULT NULL,
  ip_txt     VARCHAR(45)  NULL,
  creado     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token (token),
  KEY idx_usuario (usuario_id),
  KEY idx_expira (expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT id, email, nombre, rol, activo FROM usuarios ORDER BY id;
