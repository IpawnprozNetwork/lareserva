# Scripts SQL

Se corren en phpMyAdmin, base `lareserv_admin`, en este orden si se arma todo
de cero. Todos son idempotentes: se pueden correr dos veces sin romper nada.

| Archivo | Que hace |
|---|---|
| `01-consultas.sql` | tabla de consultas |
| `02-tracking.sql` | eventos, ip_bloqueadas, columnas ref e ip_txt |
| `03-mensajes.sql` | bandeja de correo del panel |
| `04-referidos.sql` | codigos de difusion y pagos |
| `05-boton.sql` | columna boton en consultas y eventos |
| `06-fecha-fin.sql` | rango de fechas en consultas |
| `07-media-verano2027.sql` | registra las fotos y clips del verano 2027 en espacios y galeria |
| `08-fix-pagoda-cabanas.sql` | mueve la foto del Pagoda que estaba cargada como cabana y corrige las dos portadas |
| `09-superadmin-papelera.sql` | agrega el rol superadmin y la papelera del inbox |
| `10-arreglar-rol-y-clave.sql` | agrega superadmin al enum de rol, restaura el rol y crea la tabla de recuperaciones |
| `11-mail-copia.sql` | direccion que recibe copia de cada consulta. Cambiarla es re-correr este script con otro valor |
| `12-galeria-video.sql` | columna `video` en galeria y los clips que se reproducen al pasar el mouse |
| `13-verano-editable.sql` | tabla `verano_media` y las baldosas iniciales de la pagina de verano |
| `14-retiro-verano-texto.sql` | descripcion y precios del Retiro de Verano 2027, con el formato nuevo de Incluye |
| `15-pie-contactos.sql` | segundo WhatsApp y segundo mail en el pie del sitio |
| `16-acentos-y-hero.sql` | acentos y enyes en los textos de la base, y el titulo nuevo del hero |
| `17-acentos-faltantes.sql` | lo que la pasada 16 dejo a medias: datos de las baldosas y el hero |
| `18-hero-tres-lineas.sql` | el texto del hero de verano en dos renglones |
| `19-verano-wa-y-hero.sql` | WhatsApp propio de la landing de verano y el texto del hero en tres renglones |
| `20-valores-texto.sql` | los textos de precios en castellano rioplatense |
| `21-fechas-y-pension.sql` | la agenda deja de leerse como fechas unicas, y la pension deja de decir "completa o media" |
| `22-programa-dos-opciones.sql` | el programa muestra las dos duraciones; la agenda de retiros salio de la landing |
| `23-eventos-y-pension.sql` | seccion de encuentros al pie de la landing y la bajada de Valores sin ambiguedad |
| `99-borrar-pruebas.sql` | **BORRA DATOS.** Limpieza de datos de prueba. Leer antes de correr |

Si phpMyAdmin responde `#1060 Duplicate column name`, esa columna ya existia.
Se ignora y se sigue.

## 99-borrar-pruebas.sql

No es una migracion. Es una herramienta de limpieza y **borra datos que no se
recuperan**. Tiene tres bloques y hay que correr uno solo:

- **A** mira que hay, no borra nada
- **B** borra solo lo de prueba, por direccion de mail
- **C** deja todo en cero

Exportar las tablas desde phpMyAdmin antes de correr B o C.
