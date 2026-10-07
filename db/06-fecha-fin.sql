-- La Reserva - date range on enquiries
-- Run in phpMyAdmin > lareserv_admin > SQL tab.
-- If you get "#1060 Duplicate column name", it already exists. Ignore it.

ALTER TABLE consultas ADD COLUMN fecha_fin DATE DEFAULT NULL AFTER fecha_tentativa;

SHOW COLUMNS FROM consultas;
