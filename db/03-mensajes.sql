-- La Reserva - inbox table
-- Run in phpMyAdmin > lareserv_admin > SQL tab. Nothing is dropped.

CREATE TABLE IF NOT EXISTS mensajes (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id  VARCHAR(255) DEFAULT NULL,
  de_nombre   VARCHAR(160) DEFAULT NULL,
  de_email    VARCHAR(190) DEFAULT NULL,
  para        VARCHAR(190) DEFAULT NULL,
  asunto      VARCHAR(255) DEFAULT NULL,
  cuerpo      MEDIUMTEXT   DEFAULT NULL,
  adjuntos    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  tam_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
  leido       TINYINT(1)   NOT NULL DEFAULT 0,
  creado      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_msgid (message_id),
  INDEX idx_leido (leido),
  INDEX idx_creado (creado),
  INDEX idx_de (de_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SHOW COLUMNS FROM mensajes;
