# BIBLE

El manual de operaciones. Como agregar, sacar y cambiar cada pieza, y como
clonar el sitio entero para otro proyecto.

`ARCHITECTURE.md` dice como encajan las piezas. Esto dice que hacer con ellas.

---

## 1. Anatomia de una pagina publica

Toda pagina publica tiene exactamente esta forma. Copiar una existente es
siempre mas rapido que escribir desde cero.

```php
<?php
/* nombre.php - VERSION 1.0 */
require_once __DIR__.'/includes/config.php';     // sesion, PDO, constantes, ?ref=
require_once __DIR__.'/includes/antispam.php';   // solo si tiene formulario
require_once __DIR__.'/includes/ajustes.php';    // solo si lee textos editables
$pagina = 'nombre';   // para marcar el item activo en el nav
$raiz   = '';         // '' en la raiz, '../' desde una subcarpeta

// 1. manejar POST si hay formulario
// 2. consultar la base
// 3. HTML
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<!-- nombre.php VERSION 1.0 -->
...
<link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
<?php include __DIR__.'/includes/nav.php'; ?>
... contenido ...
<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
```

Reglas que no se negocian:

- El marcador `VERSION` va dos veces: en el comentario PHP de la linea 2 y en
  un comentario HTML dentro del `<head>`. Los dos tienen que decir lo mismo
- `config.php` siempre primero. Arranca la sesion antes de cualquier salida
- `display_errors` nunca antes del `require`: config lo apaga al final
- Guion comun, nunca raya larga, en codigo y en texto

---

## 2. Agregar una pagina

1. Copiar `historia.php` (la mas simple) a `nueva.php`
2. Cambiar `$pagina`, el `<title>`, los dos marcadores de version
3. Si tiene que aparecer en el menu, agregar el `<li>` en
   `includes/nav.php` **y** marcar la version del nav
4. Agregarla a `sitemap.php`
5. Agregarla a `healthcheck.py` en `PAGES`, y en `VERSIONS` si queres que
   verifique la version
6. Subir, `Ctrl+U`, confirmar marcador, `python3 healthcheck.py`

Una landing como `verano2027.php` **no** incluye `nav.php` ni `footer.php`:
tiene un solo destino y el menu completo le quitaria foco. Lleva su propia
copia del beacon de tracking por eso mismo.

## 3. Sacar una pagina

1. Borrar el archivo del servidor
2. Sacarla de `nav.php`, `sitemap.php` y `healthcheck.py`
3. Si alguien la enlazo desde afuera, agregar una redireccion en `.htaccess`:
   ```apache
   Redirect 301 /vieja.php /nueva.php
   ```
4. No borrar sus filas en la base si otra pagina las usa

---

## 4. Agregar un formulario

Todos los formularios escriben en `consultas`. No crear tablas nuevas por
formulario: el panel, los informes y el inbox leen una sola.

Copiar el bloque POST de `contacto.php`. Tiene, en este orden:

```
body descartado por tamano     -> aviso de tamano, no de sesion
honeypot lleno                 -> fingir exito, no guardar
csrfOk() falla                 -> "la sesion expiro"
validar nombre y mail          -> aviso
INSERT en consultas            -> con origen, ref y boton
lrEvento(db, 'algo_ok', ...)   -> para los informes
```

Lo que distingue un formulario de otro son tres campos:

| Campo | Que poner |
|---|---|
| `origen` | De donde vino: `contacto`, `retiro:slug`, `espacio:slug`, `verano2027` |
| `tipo` | `organizar`, `participar`, `visita` u `otro` |
| `boton` | La etiqueta del boton que lo mando. Ver seccion 9 |

`ref` no se toca: viene de la sesion, lo puso `config.php` al llegar.

En el HTML: `csrf` oculto, honeypot `apodo` fuera de pantalla, y el mismo
`<input type="hidden" name="origen">` que el handler espera.

---

## 5. Agregar una pantalla al panel

```php
<?php
/* admin/nueva.php - VERSION 1.0 */
require_once __DIR__ . '/../includes/auth.php';
exigirSesion();                       // linea 2, antes de cualquier salida
ini_set('display_errors', '0');       // despues del require
$db = getDB();
// POST, consultas, HTML
?>
...
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>
<main> ... </main>
```

Para que aparezca en el menu, en `admin/_sidebar.php` agregar una linea
`lrNav('nueva.php', 'Nueva', $lrPag);` en la seccion que corresponda, y subir
la version del sidebar. Nada mas: el item activo se detecta solo por el nombre
del archivo.

Si solo la puede ver rol admin: `if ($lrRol === 'admin') lrNav(...)`.

---

## 6. Espacios y retiros

Se cargan desde el panel. No hay que tocar codigo.

**Un espacio nuevo:** `admin/espacios.php` > Nuevo espacio. Nombre, tipo,
resumen, descripcion, capacidad, orden, visible. Guardar y recien ahi
aparece la pestana de fotos y videos.

**Un retiro nuevo:** `admin/retiros.php` > Nuevo retiro. Titulo y fecha de
inicio es el minimo. Dejarlo en borrador mientras se arma.

**Media:** cada espacio y retiro tiene su pestana. El primero de la lista es
la portada de la tarjeta y lo que abre el visor. Con las flechas se reordena.
Imagen y video son archivos distintos: una tarjeta muestra la primera imagen
quieta y reproduce el primer video al pasar el mouse.

**Con muchos archivos de una vez:** subir por cPanel File Manager a
`assets/img/galeria/` y `assets/video/pages/` con nombres limpios, y
registrarlos con SQL. Ver `db/README.md` y los scripts `media_*.sql` del
historial como modelo. Siempre: subir primero, correr el SQL despues.

**Nombres de archivo:** minusculas, ASCII, guiones. Sin espacios, acentos ni
parentesis. `Salón amor (2).jpeg` rompe en una URL; `salon-amor-2.jpg` no.

**Video:** apaisado para fondos de pagina y tarjetas. Un vertical de
Instagram funciona en el visor pero nunca como fondo. Comprimir antes de
subir: 1280 de ancho, CRF 28, sin audio, `+faststart`. Un hero de 20
segundos pesa 4 MB, no 170.

---

## 7. Textos de la portada

`admin/portada.php`. Hero, ubicacion y las cuatro cifras. Un campo vacio
vuelve al texto original: no hay forma de dejar la portada en blanco.

Para agregar un texto editable nuevo a cualquier pagina:

1. En la pagina: `<?= e(lrAjuste('mi_clave', 'Texto original')) ?>`
2. En `admin/portada.php`: una entrada mas en el array `$campos`

La clave tiene que ser unica. El texto por defecto es el que se muestra si la
clave esta vacia o la tabla no existe: siempre poner el real, nunca `''`.

---

## 8. El visor

`espacios.php`, `retiros.php` y `verano2027.php` usan el mismo patron:

1. PHP serializa los datos en un `<script>` como JSON, o en `data-*` sobre
   cada tarjeta
2. Las tarjetas son HTML real con links reales
3. JS intercepta el clic y arma el visor desde esos datos
4. Si el JS no encuentra el dato, deja pasar el link: sin JS la pagina sigue
   funcionando

Para agregar una tarjeta al mosaico de `verano2027.php`: un `<figure>` mas
con `data-img`, `data-t`, `data-d` y opcionalmente `data-vid`. El visor y el
hover los leen solos. No hay que tocar el JS.

Para llegar al visor con un link directo: `espacios.php#slug`. La pagina lee
el hash al cargar y abre ese espacio.

---

## 9. Tracking: etiquetas de boton

Cada boton del sitio lleva una etiqueta fija. Van en `track.php` y en
`admin/referidos.php`. Para agregar una:

1. Elegir el nombre: minusculas, sin espacios. `lr`, `whatsapp`, `verano2027`
2. En el boton: `onclick="lrBoton('click_rsvp','etiqueta','detalle')"`
3. Si el tipo de evento es nuevo, agregarlo a la lista blanca de `track.php`
   o se descarta en silencio
4. Agregarlo a la tabla "Etiquetas del sitio" en `admin/referidos.php`

**Nunca cambiar una etiqueta que ya esta en uso.** Los numeros historicos
dejan de compararse con los nuevos.

Tipos de evento que `track.php` acepta hoy: `abrir_retiro`, `abrir_espacio`,
`click_rsvp`, `click_wa`, `click_ig`, `click_url`. Los `*_ok` los escribe el
servidor desde `antispam.php`, no el navegador.

---

## 10. Base de datos

Las migraciones estan en `db/`, numeradas. Para un cambio de esquema nuevo:

1. Escribir `db/07-lo-que-sea.sql`, idempotente si se puede
2. Correrlo en phpMyAdmin con la base seleccionada en el panel izquierdo
3. Agregarlo a `db/README.md`

Antes de cualquier `DELETE` o `UPDATE` masivo: Exportar > Rapido. Es el unico
seguro que hay.

Las tablas no comparten collation: las viejas son `utf8mb4_general_ci`, las
nuevas `utf8mb4_unicode_ci`. Comparar texto entre una y otra tira error 1267.
Usar literales en `IN (...)`, nunca variables `@x`. O emparejarlas con
`ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`.

---

## 11. Deploy

No hay pipeline. Subir por cPanel File Manager. Un zip con la estructura
interna correcta (`admin/`, `includes/`, `assets/...`) y Extract en
`public_html` deja todo en su lugar de una vez.

Despues de cada deploy:

```bash
python3 healthcheck.py
```

Y actualizar `VERSIONS` en el script cuando sube una version, o marca FAIL en
un archivo que esta bien.

**La trampa mas cara del proyecto:** armar un archivo sobre una copia vieja.
Paso tres veces y las tres borro funcionalidad en silencio. Antes de empaquetar
cualquier cosa, confirmar que el archivo base tiene las funciones que ya
deberia tener.

---

## 12. Clonar el sitio para otro proyecto

Todo lo que es de La Reserva esta en cuatro lugares. Cambiando esos cuatro, el
resto sirve para cualquier predio, estudio, centro o espacio que publique una
agenda y reciba consultas.

### 12.1 Identidad

`includes/config.php`, a partir de `config.example.php`:

```php
SITE_NAME, SITE_DOMAIN, SITE_EMAIL
SITE_WA, SITE_WA_LABEL, SITE_IG
SITE_LAT, SITE_LNG, SITE_HECTAREAS
DB_NAME, DB_USER, DB_PASS
```

### 12.2 Colores y tipografia

`css/style.css`, las variables de `:root` al principio:

```css
--bg:#0a1628  --card:#112233  --teal:#00c9a7
--text:#e8f0f7  --text2:#7a9ab8  --text3:#4a6a85
```

Las paginas con `<style>` propio (`welcome.html`, `verano2027.php`, el panel)
repiten estos valores. Buscar `#00c9a7` y `#0a1628` en el repositorio y
reemplazar.

Para La Reserva existe una segunda paleta, la del logo, que no esta aplicada:
verde `#105838`, arena `#c0c098`, marron `#402810`, crema `#f4f1e8`. Esta en
`DECISIONS.md` por si se decide usarla.

### 12.3 Contenido

Todo en la base: `espacios`, `retiros`, `galeria`, `ajustes`. Lo unico escrito
en codigo es `historia.php` y la seccion "Que incluye" de `verano2027.php`.

Para arrancar vacio: `db/99-borrar-pruebas.sql`, bloque C, y despues
`DELETE FROM espacios; DELETE FROM retiros; DELETE FROM galeria;`.

### 12.4 Vocabulario

El sitio dice "retiros" y "espacios". Si el proyecto nuevo tiene "clases" y
"salas", son busquedas y reemplazos en el texto visible, no en los nombres de
tabla ni de columna. Los nombres de tabla se dejan: cambiarlos toca cincuenta
archivos para no ganar nada.

### 12.5 Lo que no hay que cambiar

- `includes/antispam.php`: funciona igual para cualquier sitio
- `includes/auth.php` y el sistema de usuarios
- `track.php` y el modelo `ref` / `boton`
- La estructura de `consultas`, `eventos`, `referidos`
- `healthcheck.py`: solo cambiar `HOST_DEFAULT` y las listas

### 12.6 Orden para levantar un clon

1. `SETUP.md` de punta a punta con los valores del proyecto nuevo
2. `config.php` con la identidad
3. `style.css` con la paleta
4. Cargar espacios, retiros y fotos desde el panel
5. `admin/portada.php` para los textos del hero
6. `healthcheck.py` con el host nuevo
7. Repositorio nuevo, mismo `.gitignore`

Un clon completo con contenido cargado es un dia de trabajo. Lo que lo hace
posible es que nada de La Reserva esta hardcodeado fuera de esos cuatro
lugares, y conviene que siga asi.

---

## 13. Lo que falta

| Que | Donde empezar |
|---|---|
| `espacio_id` en `retiros` | Columna nueva, dropdown en `admin/retiros.php`, filtro en `espacios.php` |
| Politica de privacidad | Pagina nueva, link en `footer.php`. Se guardan IPs |
| Paleta del logo en todo el sitio | Seccion 12.2. Decision pendiente |

---

## 14. Recuperar el acceso al panel

Tres caminos, del mas simple al ultimo recurso.

**1. La persona recuerda su mail.** `admin/recuperar.php`, escribe la
direccion, le llega un link que vence en una hora y elige clave nueva. El
link del login dice "Olvidaste la clave".

**2. El mail no llega.** Pasa: `mail()` no es confiable en este hosting. Otra
persona con rol admin entra a Usuarios y le cambia la clave directamente.

**3. Nadie puede entrar al panel.** Unico caso que obliga a tocar la base.
phpMyAdmin, tabla `usuarios`, reemplazar `pass_hash` por un hash bcrypt
generado aparte. Nunca dejar un generador de hashes en el servidor: se sube,
se usa, se borra en la misma sesion.

### Los roles

| Rol | Puede |
|---|---|
| `superadmin` | Todo, incluido Trafico y seguridad, que muestra IPs |
| `admin` | Todo menos Trafico y seguridad |
| `editor` | Todo menos Usuarios y Trafico |

`rol` es un ENUM en la base. **Agregar un rol nuevo exige un ALTER TABLE
primero.** Si se hace un UPDATE con un valor que no esta en el enum, MySQL
no da error: guarda una cadena vacia y el usuario queda sin rol. Paso una vez
en este proyecto.

### Las dos pantallas de trafico

| Pantalla | Quien la ve | Que muestra |
|---|---|---|
| `admin/trafico.php` | admin y editor | Totales, por retiro, por origen. Sin datos personales |
| `admin/seguridad.php` | solo superadmin | Lo mismo mas IPs, user agents, log de eventos y bloqueo |

El sidebar muestra una o la otra, nunca las dos. `trafico.php` es de solo
lectura: no tiene formularios, no bloquea ni purga nada.
