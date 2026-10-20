USE lareserv_admin;

ALTER TABLE galeria ADD COLUMN video VARCHAR(200) NULL DEFAULT NULL AFTER archivo;

UPDATE galeria SET video='assets/video/pages/v27-aereo.mp4'          WHERE archivo LIKE '%v27-lago-aereo.jpg';
UPDATE galeria SET video='assets/video/pages/v27-lago-atardecer.mp4' WHERE archivo LIKE '%v27-lago-atardecer.jpg';
UPDATE galeria SET video='assets/video/pages/v27-meditacion.mp4'     WHERE archivo LIKE '%v27-meditacion.jpg';
UPDATE galeria SET video='assets/video/pages/v27-casa.mp4'           WHERE archivo LIKE '%v27-casa-aerea.jpg';
UPDATE galeria SET video='assets/video/pages/v27-mesa.mp4'           WHERE archivo LIKE '%v27-mesa.jpg';
UPDATE galeria SET video='assets/video/pages/pileta-1.mp4'           WHERE archivo LIKE '%v27-pileta-aerea.jpg';
UPDATE galeria SET video='assets/video/pages/v27-lago-escultura.mp4' WHERE archivo LIKE '%v27-grupo-lago.jpg';
UPDATE galeria SET video='assets/video/pages/cabanas-1.mp4'          WHERE archivo LIKE '%cabanas.jpg';
UPDATE galeria SET video='assets/video/pages/pagoda-exterior.mp4'    WHERE archivo LIKE '%salon-pagoda.jpg';
UPDATE galeria SET video='assets/video/pages/comedor.mp4'            WHERE archivo LIKE '%mesa-servida.jpg';
UPDATE galeria SET video='assets/video/pages/nature-2.mp4'           WHERE archivo LIKE '%atardecer-lago.jpg';
UPDATE galeria SET video='assets/video/pages/nature-3.mp4'           WHERE archivo LIKE '%pasarela-atardecer.jpg';

SELECT id, SUBSTRING_INDEX(archivo,'/',-1) AS foto,
       IFNULL(SUBSTRING_INDEX(video,'/',-1),'(sin video)') AS clip, titulo
FROM galeria WHERE activo=1 ORDER BY orden, id;
