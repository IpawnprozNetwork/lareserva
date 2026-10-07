# SETUP

From an empty cPanel account to a running site. Roughly 40 minutes, most of it
waiting for uploads.

> These developer docs are in English to match the other repos in this account.
> The user-facing documentation in `docs/` is in Spanish.

---

## 0. What you need first

| Thing | Where |
|---|---|
| cPanel access | `shadow.hobohost.com:2083` |
| A domain pointed at the account | NIC.ar for `.net.ar` |
| The MySQL password | You set it in step 2 |
| The media files | Not in this repo. 137 MB, see step 6 |

There is **no SSH, no composer, no npm and no build step**. If a set of
instructions anywhere tells you to run a terminal command on the server, those
instructions are for a different host.

---

## 1. Files

Upload the contents of this repo to `public_html/`, preserving folders.

The fastest route is to zip the repo, upload the zip through cPanel File
Manager, and use **Extract**. Uploading 33 files one at a time is a waste of
your evening.

```
public_html/
  index.php  espacios.php  retiros.php  retiro.php
  galeria.php  historia.php  contacto.php  acceso.php
  track.php  404.php  sitemap.php  welcome.html  robots.txt
  .htaccess
  css/style.css
  includes/   admin/   assets/
```

---

## 2. Database

cPanel > **MySQL Databases**.

1. Create a database. It ends up prefixed, for example `lareserv_admin`
2. Create a user, for example `lareserv_charliee`
3. Add the user to the database with **ALL PRIVILEGES**
4. Write the password down. You need it in step 3

Then cPanel > **phpMyAdmin**, select the database, **SQL** tab, and run the
scripts in `db/` in numerical order:

```
01-consultas.sql     enquiries from every form
02-tracking.sql      eventos, ip_bloqueadas, ref and ip_txt columns
03-mensajes.sql      the panel inbox
04-referidos.sql     referral codes and payouts
05-boton.sql         boton column on consultas and eventos
06-fecha-fin.sql     date range on enquiries
```

They are idempotent. `#1060 Duplicate column name` means that column already
exists: ignore it and continue.

You still need `retiros`, `espacios`, `galeria`, `resenas`, `usuarios` and
`ajustes`, which predate these migrations. If you are starting from nothing,
export them from a working install rather than recreating them by hand.

---

## 3. config.php

```bash
cp includes/config.example.php includes/config.php
```

Then edit `includes/config.php` and fill in:

| Constant | Value |
|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASS` | From step 2 |
| `SITE_EMAIL` | A mailbox that **exists on a domain this account hosts** |
| `SITE_WA`, `SITE_WA_LABEL` | WhatsApp, international format, no plus sign |
| `SITE_IG` | Instagram handle, no at sign |
| `SITE_LAT`, `SITE_LNG` | Coordinates for the map |

> **`DB_PASS` is the MySQL password, not a bcrypt hash.** The example file used
> to have a bcrypt hash sitting in that slot. The hash belongs in the `usuarios`
> table and is for the panel login, which is a different thing entirely.

`includes/config.php` is in `.gitignore`. Never commit it. If it ever lands in a
commit, change the MySQL password before doing anything else: once it is in the
git history it stays there even after you delete the file.

---

## 4. PHP limits

`.user.ini` is ignored on this account, so the limits live in `.htaccess`. The
block is already in the file:

```apache
<IfModule lsapi_module>
  php_value upload_max_filesize 180M
  php_value post_max_size       210M
  php_value memory_limit        256M
</IfModule>
```

Verify by loading `admin/diag.php` and reading the **local** value of
`post_max_size`. If it says 8M, the block is not taking effect and you need
cPanel > Software > **MultiPHP INI Editor** instead.

Delete `admin/diag.php` once you have confirmed it.

---

## 5. First admin user

Passwords are bcrypt. Generate a hash and insert it directly:

```sql
INSERT INTO usuarios (nombre, email, clave, rol, activo)
VALUES ('Tu Nombre', 'vos@ejemplo.com',
        '$2y$10$REEMPLAZAR_POR_EL_HASH_REAL', 'admin', 1);
```

To produce the hash, run `password_hash('tu-clave', PASSWORD_DEFAULT)` in any
PHP environment. Do not leave the generator script on the server: upload it,
use it, delete it in the same sitting.

Log in at `/admin/` and confirm the sidebar loads.

---

## 6. Media

`assets/` is not in this repo. It is 137 MB and git is the wrong place for it.

Upload photos to `assets/img/galeria/` and videos to `assets/video/pages/`
through File Manager, then register them in the database. `db/` does not
include a media script because filenames are install specific.

**Rename before uploading.** Spaces, accents, parentheses and double dots break
in URLs, and a name like `Alimentación consiente..mp4` is exactly the kind of
thing a mod_security rule rejects outright. Lowercase ASCII with hyphens.

---

## 7. Mail into the panel

Optional. Skip it if you will read mail in webmail.

1. cPanel > **Email Accounts** > create the mailbox
2. Upload `pipe_correo.php` to **`/home/USER/`**, outside `public_html`, and
   chmod it to **755**
3. cPanel > **Email Filters** > Manage Filters for that address > Create a New
   Filter:
   - Rule: `To` `contains` `your@address`
   - Action 1: **Deliver to folder** → `INBOX`
   - Action 2: **Pipe to a Program** → `$home/pipe_correo.php`

Both actions are required. Without the first, mail stops arriving in webmail.
The pipe path must be `$home/...` or absolute: a bare relative filename is not
resolved against your home directory and the script silently never runs.

Use the **Filter Test** box on that page before sending anything real.

---

## 8. Verify

From your own machine, not the server:

```bash
python3 healthcheck.py
```

It checks every page, the deployed version markers, the redirects, that the
admin panel is not reachable without a session, and that no diagnostic file is
left exposed. Exit code 0 means everything passed.

---

## 9. Delete these from the server

- `generar-hash.php`
- `admin/diag.php`
- `admin/pipe_test.php`
- `admin/upload_test.php`

The last one has no session check. Leaving it there means anyone can reach it
and write files to your disk.
