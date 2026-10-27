# OPERAR

Guia de desarrollo: GitHub, healthcheck y la rutina de deploy.

Las paginas del sitio y el uso del panel estan en
[la guia de usuario](docs/guia-usuario.html). Aca no se repiten.

---

## 1. Las dos cosas que hay que entender

### `ref` y `boton` no son lo mismo

- **`ref`** es de donde vino la persona. Se fija al llegar con `?ref=algo` y
  queda toda la visita. Sin codigo queda `directo`
- **`boton`** es que toco una vez adentro. Ya viene puesto en cada boton del
  sitio, sin compartir ningun link

Una persona puede venir de `instagram` y convertir por el boton `whatsapp`. Son
dos datos distintos y se guardan por separado. Mezclarlos rompe el informe para
siempre.

### El estado de las consultas es trabajo manual

Las columnas "Concretadas" de Referidos y Trafico cuentan solo las consultas en
estado `coordinada` o `cerrada`. Si todo queda en `nueva`, la conversion es cero
para siempre y **no es un error del sistema**.

---

## 2. Subir el sitio a GitHub, desde tu Mac

El repositorio es historial y respaldo. **Pushear no cambia nada en el
servidor**: el deploy sigue siendo subir archivos por cPanel a mano.

### La primera vez

```bash
cd ~
mkdir -p lareserva && cd lareserva

# descomprimir aca el zip del repo, despues:
git init
git branch -M main
git remote add origin https://github.com/IpawnprozNetwork/lareserva.git

git add -A
git commit -m "Sitio completo: panel, tracking, referidos, inbox, documentacion"
git push -u origin main
```

Si GitHub pide usuario y contrasena, la contrasena **no** es la de tu cuenta.
Es un token: github.com > Settings > Developer settings > Personal access
tokens > Tokens (classic) > Generate new token, con el permiso `repo`. Guardalo
en el Llavero cuando el Mac te lo ofrezca.

### Cada vez que cambies algo

```bash
cd ~/lareserva
git add -A
git commit -m "espacios: calendario por rango de fechas"
git push
```

Un mensaje util dice **que** cambio y **por que**. "cambios" y "update" no le
sirven a nadie, empezando por vos dentro de tres meses.

### Traer lo que esta en GitHub

```bash
git pull
```

### Antes de cada commit, mira que estas por subir

```bash
git status
git diff
```

### Lo que NUNCA va al repositorio

`.gitignore` ya lo bloquea, pero conviene saber por que:

| Que | Por que |
|---|---|
| `includes/config.php` | Tiene la password de MySQL |
| `assets/` | 137 MB. En git, cada video reemplazado queda en el historial para siempre |
| `*.log` | Ruido |
| `admin/diag.php`, `pipe_test.php`, `upload_test.php` | Herramientas que se borran del servidor |

> **Si `config.php` alguna vez entra a un commit, cambia la password de MySQL
> antes de hacer cualquier otra cosa.** Una vez que esta en el historial de git
> sigue ahi aunque borres el archivo.

Para confirmar que no se te colo nada:

```bash
git ls-files | grep -E 'config\.php|assets/' || echo "limpio"
```

---

## 3. Verificar la salud del sitio, desde tu Mac

`healthcheck.py` corre **en tu maquina**, no en el servidor. HoboHost no da SSH
ni Python ni cron, asi que revisa el sitio en vivo por HTTPS. Que es mejor:
verifica lo que recibe el publico, no lo que dice el disco.

### Correrlo

```bash
cd ~/lareserva
python3 healthcheck.py
```

macOS ya trae Python 3. Si `python3` no existe:

```bash
xcode-select --install
```

No hace falta instalar nada mas. El script usa solo la libreria estandar.

### Que revisa

1. Que todas las paginas devuelvan 200
2. Que la **version publicada** coincida con la que deberia estar
3. Que http redirija a https, y www al dominio pelado
4. Que sigan presentes los formularios, calendarios y beacons
5. Que el panel **no** se vea sin sesion
6. Que no haya quedado ningun archivo de diagnostico expuesto
7. Peso y tiempo de carga de cada pagina

### Como leer la salida

```
  OK    GET /espacios.php                    0.42s  86 KB
  OK    version /espacios.php                2.8
  FAIL  version /retiros.php                 esperaba 2.7, encontro 2.3
  OK    protegido /admin/consultas.php       pide login
  FAIL  no expuesto /admin/upload_test.php   HTTP 200, 1840 bytes
```

- **OK** anda
- **WARN** mirarlo, no urgente. Una pagina lenta o pesada
- **FAIL** arreglarlo. Sale exit code 1

Un `FAIL` de version significa casi siempre que subiste un archivo viejo encima
de uno nuevo. Paso dos veces en este proyecto.

### Salida para scripts

```bash
python3 healthcheck.py --json > salud.json
```

### Cuando correrlo

Despues de cada deploy, siempre. Y una vez por semana aunque no hayas tocado
nada: sirve para detectar que el hosting cambio algo por su cuenta, que es
exactamente lo que paso cuando desaparecio la extension `fileinfo` y se cayeron
todas las subidas.

### Cuando cambies una version

Editar el diccionario `VERSIONS` arriba del script:

```python
VERSIONS = {
    "/index.php":    "1.1",
    "/espacios.php": "2.8",
    "/retiros.php":  "2.7",
    "/galeria.php":  "1.3",
}
```

Si no lo actualizas, el script va a marcar FAIL en un archivo que esta bien.

---

## 4. Rutina despues de tocar algo

```
1. Editar el archivo
2. php -l archivo.php          llaves balanceadas no es sintaxis valida
3. Subir por cPanel File Manager
4. Ctrl+U en la pagina y confirmar el marcador de version
5. python3 healthcheck.py
6. git add -A && git commit -m "..." && git push
```

El paso 4 y el paso 5 hacen lo mismo. El 5 lo hace en todas las paginas a la
vez y no se olvida de ninguna.

---

## 5. Si algo se rompe

| Sintoma | Donde mirar |
|---|---|
| 500 en blanco | `/home/lareserv/logs/php.error.log`. Es un error de sintaxis |
| Los formularios dicen "sesion expiro" | Se subio un `config.php` viejo |
| Una subida falla | `admin/diag.php`, valor **local** de `post_max_size` |
| No llega correo al panel | `/home/lareserv/pipe_correo.log`. Si no existe, el pipe nunca corrio |
| Los numeros estan en cero | Las consultas siguen en estado `nueva` |

**Los datos estan a salvo.** Todo vive en MySQL. Romper un archivo PHP no borra
nada: se sube la version anterior y el sitio vuelve. Lo unico irreversible es un
`DROP` o un `DELETE` sin `WHERE`, y por eso se exporta antes de correr SQL que
borre.


---

## Versiones desplegadas

Actualizar esta tabla con cada deploy. Es lo que compara `healthcheck.py`.

| Archivo | Version |
|---|---|
| `index.php` | 1.9 |
| `espacios.php` | 3.2 |
| `retiros.php` | 2.9 |
| `retiro.php` | 1.6 |
| `galeria.php` | 1.4 |
| `welcome.html` | 2.1 |
| `verano2027.php` | 5.1 |
| `track.php` | 1.3 |
| `includes/config.php` | 2.8 |
| `includes/nav.php` | 2.2 |
| `includes/auth.php` | 1.1 |
| `includes/footer.php` | 1.2 |
| `includes/ajustes.php` | 1.1 |
| `includes/antispam.php` | 1.3 |
| `admin/_sidebar.php` | 2.8 |
| `admin/inbox.php` | 1.3 |
| `admin/login.php` | 1.1 |
| `admin/recuperar.php` | 1.0 |
| `admin/trafico.php` | 1.0 |
| `admin/seguridad.php` | 1.3 |
| `admin/espacios.php` | 3.7 |
| `admin/retiros.php` | 4.0 |
| `admin/portada.php` | 1.1 |
| `admin/verano.php` | 1.4 |
| `admin/flyer.php` | 1.2 |
| `admin/videos.php` | 1.1 |
| `admin/usuarios.php` | 1.1 |
| `admin/referidos.php` | 1.4 |

---

## Marcar una version en el repositorio

Un commit dice que cambio. Un tag dice que eso es un estado entero del sitio
que funcionaba. Sirve para volver a un punto conocido sin leer el historial.

```bash
cd ~/LR
git tag -a v1.2 -m "Verano 2027 para familias, roles, papelera, recuperacion de clave"
git push origin v1.2
```

Para ver los que hay:

```bash
git tag -l -n1
```

Para volver a mirar como estaba el sitio en un tag, sin romper nada:

```bash
git checkout v1.1      # mira
git checkout main      # vuelve
```

Conviene marcar despues de un healthcheck en verde, no antes. Un tag sobre
un estado roto es peor que no tener tags.

### Los que hay

| Tag | Que incluye |
|---|---|
| `v1.0` | Sitio completo: panel, tracking, referidos, editor de portada |
| `v1.1` | Landing Verano 2027, visor, inbox clickeable, BIBLE.md |
| `v1.2` | Verano para familias, roles, papelera, recuperacion de clave, guias de Johnny |
