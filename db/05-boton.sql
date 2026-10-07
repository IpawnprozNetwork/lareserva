-- La Reserva - button tagging
-- Run in phpMyAdmin. "#1060 Duplicate column name" means it already exists: ignore.

ALTER TABLE consultas ADD COLUMN boton VARCHAR(24) DEFAULT NULL;
ALTER TABLE eventos   ADD COLUMN boton VARCHAR(24) DEFAULT NULL;

CREATE INDEX idx_boton ON eventos (boton);

-- Everything already stored came from before buttons were tagged.
UPDATE consultas SET ref = 'directo' WHERE ref IS NULL OR ref = '';

SHOW COLUMNS FROM consultas;
