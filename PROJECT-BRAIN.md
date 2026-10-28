# PROJECT BRAIN

Everything a new session needs to be useful immediately. Read this first, then
`ARCHITECTURE.md` for how it works and `DECISIONS.md` for why.

---

## Who and what

**Carlos Verbelen**, co-founder and CMO at Verbelen Senatus Technologies,
Buenos Aires. He builds and maintains this himself. He knows PHP, MySQL, HTML,
CSS and JavaScript. He does not need concepts explained. He catches bugs, spots
inconsistencies and pushes back when something does not work.

**What he wants from a collaborator:** working files, not option lists.
Diagnosis from evidence, not the same fix attempted twice. If an approach has
failed twice, stop and ask for the actual error message.

**La Reserva Naturaleza Arte**, a 250 hectare property in Capilla del Señor,
Buenos Aires, about 77 km from the city. Retreats and gatherings. The site shows
the property, publishes the agenda and captures enquiries.

**Johnny** runs the panel day to day.

---

## Hard constraints

| Constraint | Consequence |
|---|---|
| No SSH | Every change is a file uploaded by hand through cPanel |
| No composer, no npm | No dependencies, no build, no autoloader |
| `.user.ini` ignored on this account | PHP limits live in `.htaccess` |
| `fileinfo` extension absent | Use `lrMime()`, never `mime_content_type()` |
| `mail()` unreliable | Enquiries are read in the panel, never by email |
| Shared hosting | No cron, no queue, no background jobs |

---

## Coordinates

```
Site      lareserva.net.ar
cPanel    shadow.hobohost.com:2083     user lareserv
DB        lareserv_admin               user lareserv_charliee
Panel     lareserva.net.ar/admin/
Mail      johnny@lareserva.net.ar
WhatsApp  +54 9 11 3362-9514
Instagram @lareservanaturalezaarte
Repo      github.com/IpawnprozNetwork/lareserva   private
```

Credentials are in `includes/config.php` on the server, which is not in this
repo and must never be.

---

## House rules

- **No em dashes anywhere.** Plain hyphen, in code and in visible copy
- **No references to AI tools** in code or comments
- **Version in the filename and in an HTML comment.** Without SSH it is the only
  way to confirm what is deployed
- **From-scratch rewrites over patches** when a file has accumulated too many
  fixes
- **Never open Sitejet in cPanel.** It overwrites the site files
- **Nav uses id selectors, never classes.** Classes collide with cached CSS
- **`display_errors` goes after the `require`**, because `config.php` turns it
  off at the bottom
- **No `mkdir()` in upload handlers.** Existing directories only
- **`DB_PASS` is the MySQL password, not a bcrypt hash**

---

## The one concept to understand

`ref` and `boton` are separate and must stay separate.

- **`ref`** = where the visitor came from. Set once on arrival, held in the
  session. No code means `directo`
- **`boton`** = what they clicked. Baked into the page, counts without anyone
  sharing a link

Merging them destroys attribution permanently. See DECISION 2.

---

## Current state

Everything below is live and working.

**Public:** home, espacios with lightbox + occupancy calendar + date range
picker + enquiry form, agenda with lightbox + RSVP, single retreat page with
reviews, gallery, history, contact, access.

**Panel:** dashboard, inbox fed by a mail pipe, retreats with media and review
approval, spaces with media, enquiries with states, referral codes and button
counters, traffic and IP blocking, media manager, users.

**Cross-cutting:** CSRF on every form, honeypot, IP blocking, per-IP rate
limiting, disposable-email rejection, event tracking on every button, referral
attribution, video player with progress bar and mute.

Content is in Argentine Spanish. Eight spaces, four retreats, 22 gallery photos.

---

## Deployed versions

Bump these here and in `healthcheck.py` when you deploy.

```
index.php                1.8    espacios.php             3.2
verano2027.php           2.1    welcome.html             2.0
retiros.php              3.0    retiro.php               1.9
galeria.php              1.4
track.php                1.3

includes/config.php      2.8    includes/nav.php         2.2
includes/footer.php      1.2    includes/ajustes.php     1.1
includes/antispam.php    1.3    includes/auth.php        1.1

admin/_sidebar.php       2.5    admin/portada.php        1.1
admin/espacios.php       3.7    admin/retiros.php        4.0
admin/referidos.php      1.4    admin/seguridad.php      1.3
admin/inbox.php          1.3    admin/recuperar.php      1.0
admin/login.php          1.1
```

---

## Open items

| Item | Why it matters | Size |
|---|---|---|
| `espacio_id` on `retiros` | Every space lists every retreat, and the occupancy calendar is property-wide rather than per space | Medium |
| Privacy policy | IP addresses are stored. Ley 25.326 applies, GDPR for European visitors | Small |

---

## Things that will waste your time if you do not know them

**A blank 500 is a syntax error.** It happens before `display_errors` can be
turned on. Read `/home/lareserv/logs/php.error.log`.

**Balanced braces are not valid syntax.** Run `php -l`.

**"Sesión expirada" on a large upload is not a session problem.** The body
exceeded `post_max_size` and PHP discarded `$_POST` and `$_FILES` together, so
the CSRF token never arrived.

**A grid item will not scroll without `minmax(0, 1fr)` on the row.**
`min-height: 0` alone changes nothing.

**A full-width block cannot be centred with `margin: auto`.** It needs
`width: fit-content`.

**A relative path in an Exim pipe is not resolved against the home directory.**
Use `$home/pipe_correo.php`.

**Never rebuild a file from a stale working copy.** It happened twice on this
project and both times silently removed working features. Check the base file
has the functions it should before packaging anything.

**A UI label is not evidence of a code path.** "media.php uploads 86 MB fine"
was `filesize()` of a file that File Manager had put there. That reading cost an
hour.

---

## Verifying a deploy

```bash
python3 healthcheck.py
```

Checks every page, the version markers, the redirects, that the panel is not
reachable without a session, and that no diagnostic file is exposed. Exit 0
means clean.

---

## Documentation

| File | For |
|---|---|
| `README.md` | Overview, Spanish |
| `BIBLE.md` | How to add, remove, change and clone every piece. The SOP |
| `SETUP.md` | Empty server to running site |
| `ARCHITECTURE.md` | How the pieces fit |
| `DECISIONS.md` | Why, with the bug behind each one |
| `docs/guia-completa.html` | Visitors, organisers, staff, developers. Spanish |
| `docs/manual-usuario.html` | Daily panel use. Spanish |
| `docs/guia-tecnica.html` | Technical guide with the post mortems |
