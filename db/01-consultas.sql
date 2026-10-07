-- La Reserva - consultas table
-- Run in phpMyAdmin > lareserv_admin > SQL tab.
-- Safe to run even if the table already exists: nothing is dropped.

CREATE TABLE IF NOT EXISTS consultas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(120)  NOT NULL,
  email           VARCHAR(160)  NOT NULL,
  telefono        VARCHAR(60)   DEFAULT NULL,
  tipo            ENUM('organizar','participar','visita','otro') NOT NULL DEFAULT 'otro',
  fecha_tentativa DATE          DEFAULT NULL,
  personas        SMALLINT UNSIGNED DEFAULT NULL,
  mensaje         TEXT          DEFAULT NULL,
  origen          VARCHAR(60)   NOT NULL DEFAULT 'contacto',
  ip              VARBINARY(16) DEFAULT NULL,
  user_agent      VARCHAR(255)  DEFAULT NULL,
  estado          ENUM('nueva','contactada','coordinada','cerrada','descartada') NOT NULL DEFAULT 'nueva',
  notas           TEXT          DEFAULT NULL,
  creado          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_estado (estado),
  INDEX idx_creado (creado),
  INDEX idx_origen (origen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Then run this and check the output against the column list above.
-- If the table already existed with a different shape, send me this result.
SHOW COLUMNS FROM consultas;

-- Sanity check: how many rows are actually stored right now.
SELECT COUNT(*) AS total,
       SUM(estado = 'nueva') AS nuevas,
       MAX(creado) AS ultima
FROM consultas;
