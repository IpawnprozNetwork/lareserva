-- La Reserva - referral programme
-- Run in phpMyAdmin > lareserv_admin > SQL tab. Nothing is dropped.

CREATE TABLE IF NOT EXISTS referidos (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo   VARCHAR(40)  NOT NULL,          -- goes in the link as ?ref=codigo
  nombre   VARCHAR(140) NOT NULL,          -- who this code belongs to
  contacto VARCHAR(190) DEFAULT NULL,      -- e-mail or WhatsApp
  comision VARCHAR(160) DEFAULT NULL,      -- free text: "10%", "$20.000 por reserva"
  notas    TEXT         DEFAULT NULL,
  activo   TINYINT(1)   NOT NULL DEFAULT 1,
  creado   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_codigo (codigo),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Manual payout ledger. Nothing here is calculated: you record what you paid.
CREATE TABLE IF NOT EXISTS referido_pagos (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40)    NOT NULL,
  monto  DECIMAL(12,2)  NOT NULL DEFAULT 0,
  nota   VARCHAR(190)   DEFAULT NULL,
  creado TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_codigo (codigo),
  INDEX idx_creado (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SHOW COLUMNS FROM referidos;
SHOW COLUMNS FROM referido_pagos;
