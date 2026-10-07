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
