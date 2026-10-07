# GITHUB

Crear el repositorio y subir el proyecto desde la Mac. Una sola vez, despues
son tres comandos por cambio.

> **El repositorio no es un destino de deploy.** Pushear no cambia nada en
> lareserva.net.ar. El deploy sigue siendo subir archivos por cPanel a mano.
> Esto es historial y respaldo: recuperar un archivo que rompiste, ver que
> cambio y cuando, y poder pasarle el proyecto entero a otra persona.

---

## 1. Crear el repositorio en GitHub

1. Entrar a [github.com](https://github.com) y hacer clic en **+** arriba a la
   derecha, luego **New repository**
2. **Repository name**: `lareserva`
3. **Description**: `Sitio y panel de La Reserva Naturaleza Arte`
4. Marcar **Private**
5. **No marcar nada mas.** Ni README, ni .gitignore, ni licencia. El zip ya
   los trae, y si GitHub crea los suyos el primer push choca y hay que
   resolver un merge antes de empezar
6. **Create repository**

Queda en `https://github.com/TU-USUARIO/lareserva.git`. Copiala.

---

## 2. Preparar la carpeta en la Mac

Abri **Terminal** (Cmd+Espacio, escribir "Terminal").

```bash
cd ~
mkdir -p lareserva
```

Descomprimi `lareserva_repo.zip` dentro de `~/lareserva`. Desde Finder alcanza
con arrastrar el contenido ahi.

Verifica que quedo bien:

```bash
cd ~/lareserva
ls
```

Tenes que ver `index.php`, `admin`, `includes`, `docs`, `db`, `README.md`.
Si en cambio ves una sola carpeta llamada `lareserva_repo`, entra ahi y moveló
un nivel para arriba, o el repositorio queda con una carpeta de mas adentro.

---

## 3. Primera subida

```bash
cd ~/lareserva

git init
git branch -M main
git remote add origin https://github.com/TU-USUARIO/lareserva.git

git add -A
```

**Antes de commitear, confirmar que no se colo nada que no va:**

```bash
git ls-files | grep -E 'config\.php|assets/(img|video)/|__pycache__' || echo "limpio"
```

Tiene que imprimir `limpio`. Si imprime algo, no sigas:

```bash
git rm --cached includes/config.php        # o el archivo que aparezca
```

Y recien ahi:

```bash
git commit -m "Sitio completo: panel, tracking, referidos, editor de portada, documentacion"
git push -u origin main
```

---

## 4. La contrasena no es tu contrasena

Cuando pida credenciales:

- **Username**: tu usuario de GitHub
- **Password**: **no** la de tu cuenta. Es un **token**

Para generarlo: github.com > tu foto > **Settings** > abajo de todo
**Developer settings** > **Personal access tokens** > **Tokens (classic)** >
**Generate new token (classic)**.

- **Note**: `lareserva mac`
- **Expiration**: 90 days, o No expiration si no queres renovarlo
- **Scopes**: marcar solo **`repo`**
- **Generate token**

Copialo en ese momento: **GitHub no lo muestra de nuevo**. Pegalo cuando
Terminal pida la contrasena. Si la Mac ofrece guardarlo en el Llavero, deci
que si y no te lo vuelve a pedir.

Para que el Llavero lo guarde siempre:

```bash
git config --global credential.helper osxkeychain
```

---

## 5. Cada vez que cambies algo

```bash
cd ~/lareserva
# copiar ahi los archivos que editaste

git status                    # que cambio
git diff                      # que dice exactamente

git add -A
git commit -m "espacios: orden de media elegible desde el panel"
git push
```

Un mensaje util dice **que** cambio y **por que**. "update" y "cambios" no le
sirven a nadie, empezando por vos dentro de tres meses.

---

## 6. Recuperar algo que rompiste

Volver un archivo a como estaba en el ultimo commit:

```bash
git checkout -- index.php
```

Ver el historial de un archivo:

```bash
git log --oneline -- index.php
```

Ver como era en un commit viejo, sin tocar lo actual:

```bash
git show a1b2c3d:index.php > /tmp/index-viejo.php
```

---

## 7. Lo que nunca va al repositorio

`.gitignore` ya lo bloquea. Conviene saber por que:

| Que | Por que |
|---|---|
| `includes/config.php` | Tiene la password de MySQL |
| `assets/img/galeria/`, `assets/video/` | 137 MB. En git, cada video reemplazado queda en el historial para siempre |
| `*.log` | Ruido |
| `admin/diag.php`, `pipe_test.php`, `upload_test.php` | Herramientas que se borran del servidor |
| `__pycache__/` | Basura de Python |

> **Si `config.php` entra a un commit, cambia la password de MySQL antes de
> cualquier otra cosa.** Una vez que esta en el historial de git sigue ahi
> aunque borres el archivo. Borrarlo del historial requiere reescribir todos
> los commits, y para entonces ya estuvo expuesto.

---

## 8. Despues de subir

```bash
python3 healthcheck.py
```

Revisa el sitio en vivo: paginas, versiones publicadas, redirecciones, que el
panel no se vea sin sesion, y que no haya quedado ningun archivo de
diagnostico expuesto. Exit code 0 es limpio.

Acordate de actualizar el diccionario `VERSIONS` arriba del script cuando
subas una version nueva, o va a marcar FAIL en un archivo que esta bien.
