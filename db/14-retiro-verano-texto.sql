USE lareserv_admin;

UPDATE retiros SET
  bajada = 'Tres dias y dos noches en el campo, con la comida resuelta y actividades para los chicos.',
  descripcion = CONCAT(
    'Un retiro de verano en La Reserva, pensado para que vengan en familia. ',
    'Tres dias y dos noches en 250 hectareas, con pileta al aire libre, lago, ',
    'arboleda y senderos, a una hora de Buenos Aires.',
    CHAR(10), CHAR(10),
    'El predio se recorre caminando: la pileta, el comedor, el parque y las ',
    'habitaciones estan todos a pocos metros. No hay autos adentro, asi que los ',
    'chicos pueden andar sueltos.',
    CHAR(10), CHAR(10),
    'Los dias no tienen horarios que cumplir. Hay actividades recreativas ',
    'coordinadas para los mas chicos, y se puede sumar un paseo a caballo.',
    CHAR(10), CHAR(10),
    'Ingresos de lunes a jueves o de jueves a domingo. Check in desde las 14 ',
    'horas. Las habitaciones se dejan a las 10 de la manana del ultimo dia, y ',
    'el almuerzo de ese dia es el ultimo servicio.'
  ),
  incluye = CONCAT(
    '# 3 dias y 2 noches',                    CHAR(10),
    'Adulto | $442.000',                      CHAR(10),
    'Menores de 4 a 10 anos | $221.000',      CHAR(10),
    'De 1 a 3 anos | $46.000',                CHAR(10),
    '# 4 dias y 3 noches',                    CHAR(10),
    'Adulto | $616.000',                      CHAR(10),
    'Menores de 4 a 10 anos | $308.000',      CHAR(10),
    'De 1 a 3 anos | $52.000',                CHAR(10),
    '# Que incluye el precio',                CHAR(10),
    'Alojamiento',                            CHAR(10),
    'Pension completa o media',               CHAR(10),
    'Menu aparte para los chicos',            CHAR(10),
    'Actividades recreativas coordinadas',    CHAR(10),
    'Uso de la pileta, el lago y los senderos', CHAR(10),
    '# A tener en cuenta',                    CHAR(10),
    'Precio por persona',                     CHAR(10),
    'No se aceptan mascotas',                 CHAR(10),
    'El paseo a caballo se coordina al reservar'
  ),
  precio_desde = 442000
WHERE slug = 'retiro-verano-enero-2027';

SELECT slug, titulo, precio_desde,
       CHAR_LENGTH(descripcion) AS largo_desc,
       (CHAR_LENGTH(incluye) - CHAR_LENGTH(REPLACE(incluye, CHAR(10), '')) + 1) AS lineas_incluye
FROM retiros WHERE slug = 'retiro-verano-enero-2027';
