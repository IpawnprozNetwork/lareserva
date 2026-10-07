-- La Reserva - tracking and anti-spam tables
-- Run in phpMyAdmin > lareserv_admin > SQL tab. Nothing is dropped.

CREATE TABLE IF NOT EXISTS eventos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo       VARCHAR(40)   NOT NULL,          -- abrir_retiro, click_rsvp, rsvp_ok, rsvp_rechazado, click_wa
  retiro_id  INT UNSIGNED  DEFAULT NULL,
  detalle    VARCHAR(160)  DEFAULT NULL,      -- rejection reason, retiro title, etc
  ref        VARCHAR(40)   DEFAULT NULL,      -- value of ?ref= or ?utm_source=
  ip         VARBINARY(16) DEFAULT NULL,
  ip_txt     VARCHAR(45)   DEFAULT NULL,      -- readable copy, used for blocking and rate limits
  referer    VARCHAR(255)  DEFAULT NULL,
  user_agent VARCHAR(255)  DEFAULT NULL,
  creado     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tipo (tipo),
  INDEX idx_retiro (retiro_id),
  INDEX idx_creado (creado),
  INDEX idx_ip (ip_txt),
  INDEX idx_ref (ref),
  INDEX idx_ritmo (ip_txt, tipo, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ip_bloqueadas (
  ip     VARCHAR(45) NOT NULL PRIMARY KEY,
  motivo VARCHAR(160) DEFAULT NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store the referral tag and the readable IP on the consulta itself, so a
-- booking can be attributed later without joining on timestamps.
-- If phpMyAdmin answers "#1060 Duplicate column name", the column is already
-- there. Ignore that error and move on.
ALTER TABLE consultas ADD COLUMN ref VARCHAR(40) DEFAULT NULL;
ALTER TABLE consultas ADD COLUMN ip_txt VARCHAR(45) DEFAULT NULL;

-- Check it worked.
SHOW COLUMNS FROM eventos;
SHOW COLUMNS FROM consultas;
