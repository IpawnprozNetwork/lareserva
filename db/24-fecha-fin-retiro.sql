USE lareserv_admin;

/* La pagina mostraba "16 al 4 de enero 2027". La funcion esta bien: el dato
   esta mal. La fecha de fin quedo ANTES que la de inicio, probablemente por
   el formato mm/dd/yyyy del campo de fecha del panel.

   Primero mira que hay, antes de cambiar nada. */
SELECT slug, titulo, fecha_inicio, fecha_fin,
       CASE WHEN fecha_fin IS NULL            THEN 'sin fecha de fin'
            WHEN fecha_fin < fecha_inicio     THEN 'AL REVES'
            ELSE 'ok' END AS estado_fechas
FROM retiros ORDER BY fecha_inicio;

/* Si la de arriba dice AL REVES en el retiro de verano, corre esta linea.
   Si la fecha correcta no es el 19, cambia el numero. */
UPDATE retiros SET fecha_fin = '2027-01-19'
 WHERE slug = 'retiro-verano-enero-2027' AND fecha_fin < fecha_inicio;

SELECT slug, fecha_inicio, fecha_fin FROM retiros WHERE slug = 'retiro-verano-enero-2027';
