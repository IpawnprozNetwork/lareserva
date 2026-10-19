# La Reserva Naturaleza Arte

Sitio y panel de administracion de **lareserva.net.ar**, un predio de 250 hectareas
en Capilla del Senor, Buenos Aires, donde se organizan retiros y encuentros.

PHP 8.1 + MySQL + JavaScript sin frameworks. Sin composer, sin npm, sin build.
Los archivos se suben a mano por cPanel File Manager.

---

## Antes de tocar nada

**`includes/config.php` no esta en el repositorio y no tiene que estar nunca.**
Tiene la password de MySQL. Esta en `.gitignore`. Si alguna vez aparece en un
commit, cambia la password de la base antes de hacer cualquier otra cosa: una vez
que entro al historial de git, sigue ahi aunque borres el archivo.

Para arrancar:

```bash
cp includes/config.example.php includes/config.php
# completar DB_PASS y el resto de los valores
```

`DB_PASS` es la password de MySQL, **no** un hash bcrypt. El hash vive en la
tabla `usuarios` y sirve para el login del panel, que es otra cosa.

---

## Estructura

```
/                     paginas publicas
  index.php           inicio
  espacios.php        los espacios, con visor, calendario y consulta
  retiros.php         agenda, con visor y formulario de reserva
  retiro.php          un retiro, por slug, con resenas
  galeria.php         fotos y feed de Instagram
  historia.php        el relato del lugar
  contacto.php        formulario principal
  acceso.php          puerta al panel
  track.php           endpoint de tracking, responde 204 sin contenido

/includes
  config.php          NO VERSIONADO. Constantes, PDO, sesion, CSRF, captura de ?ref=
  auth.php            sesion del panel, exigirSesion()
  antispam.php        bloqueo de IP, limite de envios, validacion de mail, eventos
  nav.php             nav publico, con estilos propios y selectores por id
  footer.php          pie publico y el helper lrBoton()

/admin
  _sidebar.php        el unico sidebar, incluido por todas las paginas del panel
  index.php           dashboard
  inbox.php           correo que llega a la casilla, dentro del panel
  retiros.php         alta y edicion de retiros, media y resenas
  espacios.php        alta y edicion de espacios y su media
  consultas.php       bandeja de consultas
  referidos.php       codigos de difusion, botones y pagos
  seguridad.php       trafico, conversion y bloqueo de IP
  media.php           galeria y videos de fondo
  usuarios.php        usuarios del panel, solo rol admin

/assets               NO VERSIONADO. 137 MB de fotos y videos
/css/style.css        hoja principal, versionada con ?v= en cada pagina
```

---

## Base de datos

| Tabla | Contiene |
|---|---|
| `retiros`, `retiro_media` | encuentros y su material |
| `espacios`, `espacio_media` | los espacios del predio y su material |
| `galeria` | fotos de la galeria publica |
| `consultas` | todo lo que mandan los formularios |
| `resenas` | resenas, ocultas hasta aprobarlas |
| `mensajes` | correo entrante, lo llena `pipe_correo.php` |
| `eventos` | cada click registrado |
| `ip_bloqueadas` | IPs bloqueadas |
| `referidos`, `referido_pagos` | codigos de difusion y pagos manuales |
| `usuarios` | logins del panel |
| `ajustes` | configuracion clave/valor |

### `ref` y `boton` son dos cosas distintas

Es lo unico de este proyecto que conviene entender antes de tocar el codigo.

- **`ref`** es de donde vino la persona. Se fija una sola vez al llegar, desde
  `?ref=` o `?utm_source=`, y queda en la sesion toda la visita. Quien llega sin
  codigo queda como `directo`.
- **`boton`** es que toco una vez adentro. Va escrito en la pagina, asi que
  cuenta sin que nadie comparta ningun link.

Si se pisan, se pierde la atribucion: todas las reservas quedarian acreditadas al
boton y los canales mostrarian clicks con cero conversiones para siempre.

---

## Reglas del proyecto

- **Nada de rayas largas.** Guion comun, en codigo y en texto visible.
- **Version en el nombre del archivo y en un comentario HTML.** Sin SSH es la
  unica forma de confirmar que hay en el servidor. `Ctrl+U` y buscar
  `<!-- espacios.php VERSION 2.8 -->`.
- **Reescribir de cero antes que parchear** un archivo que ya acumulo demasiados
  arreglos.
- **No abrir Sitejet en cPanel.** Sobreescribe los archivos del sitio.
- **`mail()` no es confiable en este hosting.** Las consultas se revisan en
  `admin/consultas.php`, nunca por correo.
- **El nav usa selectores por id, nunca clases.** Las clases chocan con el CSS
  cacheado.
- **`display_errors` se define despues del `require`**, porque `config.php` lo
  apaga al final.
- **Nada de `mkdir()` en los handlers de subida.** Solo directorios que ya existen.
- Borrar del servidor `generar-hash.php`, `admin/diag.php`, `admin/pipe_test.php`
  y `admin/upload_test.php` apenas dejan de hacer falta.

---

## Trampas conocidas

| Sintoma | Causa real |
|---|---|
| Todos los formularios dicen "La sesion expiro" | La sesion arranca despues de que salio HTML, la cookie nunca se envia |
| 500 en blanco | Error de sintaxis: pasa antes de que `display_errors` pueda activarse |
| "Sesion expirada" al subir un video grande | El body supero `post_max_size`, PHP tira `$_POST` y `$_FILES` juntos |
| Un panel en grid no scrollea | Los grid items son `min-height:auto`; hace falta `minmax(0,1fr)` en la fila |
| El pipe de correo no corre | La ruta relativa no se resuelve contra el home. Usar `$home/pipe_correo.php` |
| `mime_content_type()` indefinido | Se cayo la extension `fileinfo`. Usar `lrMime()` de config.php |
| Llaves balanceadas pero igual rompe | Contar llaves no valida sintaxis. Correr `php -l` |

---

## Subir cambios

No hay pipeline. El repositorio es historial y respaldo, no destino de deploy.

1. Editar, probar en local si se puede
2. Subir el archivo por cPanel File Manager
3. `Ctrl+U` y confirmar el marcador de version
4. Commitear con un mensaje que diga que cambio y por que

---

## Documentacion

### Para desarrollo, en ingles

| Archivo | Que tiene |
|---|---|
| `PROJECT-BRAIN.md` | Contexto completo. **Empezar aca** si retomas el proyecto |
| `BIBLE.md` | **El manual de operaciones.** Agregar, sacar, cambiar cada pieza, y clonar el sitio para otro proyecto |
| `GITHUB.md` | Crear el repositorio y subir desde la Mac |
| `SETUP.md` | De un cPanel vacio a un sitio andando |
| `ARCHITECTURE.md` | Como encajan las piezas |
| `DECISIONS.md` | Por que estan asi, con el bug que hay detras de cada una |
| `healthcheck.py` | Audita el sitio en vivo desde tu maquina |

### Para operar, en castellano

| Archivo | Que tiene |
|---|---|
| `OPERAR.md` | Las paginas del sitio, como subir a GitHub desde la Mac, y como correr el healthcheck |

### Para uso diario, en castellano

| Archivo | Para quien |
|---|---|
| `docs/guia-completa.html` | Visitantes, organizadores, equipo y desarrollo |
| `docs/manual-usuario.html` | Quien usa el panel todos los dias |
| `docs/guia-tecnica.html` | Arquitectura y post mortem de los bugs |

Todos los `.md` de arriba tambien estan en HTML, con navegacion entre ellos:

```bash
open docs/index.html          # macOS
python3 build-docs.py         # regenerar despues de editar un .md
```

No edites el HTML de `docs/` a mano: `build-docs.py` lo pisa en la proxima
corrida. Las tres guias largas son la excepcion, esas se escriben directo en
HTML y el script no las toca.

```bash
python3 healthcheck.py        # despues de cada deploy
```

---

Carlos Verbelen · [Verbelen Senatus Technologies](https://vstech.app)
