# ARCHITECTURE

How the pieces fit. Read `DECISIONS.md` for why they fit that way.

---

## Shape

There is no framework, no router and no MVC. Every page is a PHP file that
requires config, queries MySQL, and prints HTML. That is the whole shape.

```
request
  |
  .htaccess          https redirect, clean URLs, PHP limits
  |
  page.php
    |
    includes/config.php     constants, PDO, session, CSRF, ?ref= capture
    includes/auth.php       only on admin pages
    includes/antispam.php   only where forms exist
    |
    query MySQL
    |
    includes/nav.php + HTML + includes/footer.php
```

`config.php` runs first on every single page and does four things before a byte
of output: starts the session, captures `?ref=`, opens the PDO connection, and
defines the site constants. The order matters. See DECISION 1.

---

## Public pages

| File | Reads | Writes |
|---|---|---|
| `index.php` | `retiros` | nothing |
| `espacios.php` | `espacios`, `espacio_media`, `retiros` | `consultas`, `eventos` |
| `retiros.php` | `retiros`, `retiro_media` | `consultas`, `eventos` |
| `retiro.php` | `retiros`, `retiro_media`, `resenas` | `resenas` |
| `galeria.php` | `galeria` | nothing |
| `historia.php` | nothing | nothing |
| `contacto.php` | nothing | `consultas`, `eventos` |
| `acceso.php` | `usuarios` | `consultas`, `eventos` |
| `verano2027.php` | `retiros` | `consultas`, `eventos` |
| `track.php` | nothing | `eventos` |

Four pages write enquiries. They all land in one `consultas` table,
distinguished by `origen`: `retiro:slug`, `espacio:slug`, `contacto` or
`verano2027`.

`verano2027.php` is the odd one. It is a seasonal landing page: it does not
include `nav.php`'s full menu behaviour the way the others rely on it, it
carries its own copy of the tracking beacon, and its "what's included" copy
is written in the file rather than read from the database. That is deliberate
and documented in DECISION 17. It is meant to be replaced or deleted at the
end of the season, not maintained.

---

## The lightbox pattern

`espacios.php` and `retiros.php` both use the same shape, and it is worth
understanding because it is where most of the JavaScript lives.

1. PHP queries the rows and their media
2. It serialises everything into one JSON blob in a `<script>` tag
3. The grid renders as plain HTML, each item a real link
4. JavaScript intercepts the click, finds the record in the blob, and builds
   the lightbox from it

No AJAX for the content. The whole dataset is already in the page, so opening a
card is instant and works offline once loaded. The only network call is the
form submit and the tracking beacon.

If the JSON blob has no entry for a clicked id, the handler returns `true` and
the browser follows the real link. Crawlers and no-JS visitors get a working
site.

---

## Forms

Every public form goes through the same gate, in this order:

```
POST
  |-- body discarded?     over post_max_size, PHP threw away $_POST and $_FILES
  |-- honeypot filled?    bot: return success, write nothing
  |-- CSRF token bad?     session expired
  |-- IP blocked?         generic failure, leak no detail
  |-- over rate limit?    5 submissions per IP per hour
  |-- email disposable?   reject, log the attempt
  '-- INSERT + log event
```

Each check fails **open** if its table is missing. A broken log must never
block a real booking.

The response is JSON and the request is `fetch`, so the lightbox stays open
while the row is written.

---

## Tracking

Two independent axes. Confusing them destroys the reporting, so they are
separate columns on both `eventos` and `consultas`.

**`ref`** is where the visitor came from. Set once on arrival from `?ref=` or
`?utm_source=`, lowercased, held in the session for the whole visit. A visitor
with no code is tagged `directo`.

**`boton`** is what they clicked. Written into the page itself, so it counts
without anyone sharing a link.

```
Arrives from the Instagram bio link
  ?ref=instagram              -> session ref = instagram
Opens Salon Principal
  eventos: abrir_espacio        ref=instagram  boton=NULL
Submits the form
  eventos: consulta_esp_ok      ref=instagram  boton=lr
  consultas:                    ref=instagram  boton=lr
```

Reads as: Instagram brought them, the form converted them. Both facts survive.

Clicks are sent by `lrBoton()` in `includes/footer.php` using
`navigator.sendBeacon`, which survives the browser navigating away to WhatsApp
or Instagram. A normal `fetch` would be cancelled.

`track.php` accepts only whitelisted event types and returns 204 with no body.

---

## Admin panel

Every page starts with `exigirSesion()` on line 2, before any output. That is
also why admin pages never suffered the session bug that broke every public
form: their session starts early enough to set the cookie.

### Roles

Three levels, enforced in `includes/auth.php`:

| Role | Scope |
|---|---|
| `superadmin` | Everything. Only role that can open `seguridad.php`, which shows IP addresses, user agents and the raw event log, and can block an IP |
| `admin` | Everything else. Sees `trafico.php`: the same numbers with no personal data |
| `editor` | Everything except Usuarios and Trafico |

`esSuperadmin()`, `esAdmin()` and `exigirSuperadmin()` do the checking.
`exigirSuperadmin()` does not redirect to the login page: the user already has
a valid session, what they lack is level, and sending them to a login form
would be a lie. It returns 403 with a plain explanation.

`trafico.php` does not hide the IP data behind a condition. The queries that
fetch it are not in the file at all, so there is nothing to leak even if
someone reads the source.

`rol` is an ENUM in the database. **Adding a new value requires an ALTER TABLE
first.** An UPDATE with a value outside the enum does not error: MySQL stores
an empty string and the user ends up with no role. That happened once on this
project and locked the owner out of his own panel. See DECISION 19.

### Soft delete

`mensajes` has a `borrado` column. Deleting sets a timestamp rather than
removing the row; the inbox filters `borrado IS NULL` and the Papelera tab
filters `IS NOT NULL`. A 30 day purge runs on inbox load, because this host
has no scheduled tasks, and it fails silently if the column is missing so a
half-finished deploy cannot take the screen down.

### Password recovery

`admin/recuperar.php`, two screens in one file. Without `?t=` it asks for the
address; with `?t=` it accepts a new password. The token is 64 random
characters, single use, valid 60 minutes, stored in `recuperaciones`.
Changing the password invalidates every outstanding token for that account.
The response is identical whether or not the account exists, so the page
cannot be used to enumerate who has panel access. One request per account
every two minutes.

It depends on `mail()`, which is not reliable on this host, so the screen
never claims the mail was sent and names the fallback path.

One sidebar, `admin/_sidebar.php`, included by all eleven pages. The active item
is derived from `basename($_SERVER['SCRIPT_NAME'])`, so there is nothing to
pass in and nothing to keep in sync. It carries its own `<style>` with literal
colours rather than CSS variables, because several pages never defined
`--teal-dim` and the user chip rendered unstyled without it.

---

## Mail into the panel

PHP cannot receive mail. It can only read mail already delivered.

```
inbound mail
  -> Exim
  -> cPanel Email Filter
       action 1: Deliver to folder INBOX     keeps the webmail copy
       action 2: Pipe to $home/pipe_correo.php
  -> pipe_correo.php reads stdin, parses MIME, INSERT into mensajes
  -> admin/inbox.php reads the table
```

`pipe_correo.php` always exits 0. A non-zero exit makes Exim bounce the message
back to the sender, so a parser bug must never become a bounce in front of a
customer. Failures are written to `pipe_correo.log` instead.

Attachments are counted, never stored. Writing files from unauthenticated
senders is the most dangerous thing this codebase could do, and it deliberately
does not.

---

## File layout on the server

```
/home/USER/
  pipe_correo.php          755, outside the web root
  pipe_correo.log
  logs/php.error.log
  public_html/             everything in this repo
    assets/img/galeria/    every uploaded image
    assets/video/pages/    every uploaded video
    assets/video/hero.mp4  home and welcome background
```

Only two directories are writable by PHP. The upload handlers never call
`mkdir()`: they check the directory exists and fail loudly if it does not.

---

## What is not here

- No cache layer. Page loads are one to four queries
- No queue. Mail goes out synchronously or not at all
- No image resizing. Uploads are served at original size
- No CDN
- No tests. `php -l` plus `healthcheck.py` is the whole safety net
- No CI. The repo is history and backup, not a deploy target
