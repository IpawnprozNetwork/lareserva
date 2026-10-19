# DECISIONS

Why things are the way they are. Each entry is a decision that cost something to
reach, usually a bug. If you are about to "clean up" one of these, read the
entry first.

---

## 1. The session starts in config.php, before any output

**Problem.** Every public form rejected its own CSRF token with "La sesión
expiró". `consultas` had zero rows: not one message had ever been stored since
launch.

**Cause.** `csrfToken()` started the session lazily. On public pages it was first
called in the middle of the HTML. By then the response headers were gone,
`session_start()` could not send the cookie, and the token was written to a
`$_SESSION` array that was never persisted. The browser posted a token the
server had no record of.

Admin pages were unaffected because `exigirSesion()` runs on line 2.

**Decision.** The session starts at the top of `config.php`, which every page
requires before printing anything.

**Do not** move it, and do not make it lazy again to "save a session on pages
that do not need one". The saving is a few kilobytes; the cost is every form on
the site.

---

## 2. `ref` and `boton` are separate columns

**Pressure.** A repeated request to make the buttons carry the `ref` tag, so
clicking "Solicitar lugar" would set `ref=lr`.

**Why that is wrong.** `ref` is set on arrival and holds for the visit. If a
button overwrote it, a visitor who arrived from WhatsApp would be relabelled at
the moment they book. Every booking would be credited to the button, and every
real channel would show clicks with zero conversions, permanently. The one
number worth having becomes unreadable.

**Decision.** Two columns on both `eventos` and `consultas`. `ref` answers where
they came from, `boton` answers what they clicked. Both are written on the same
row and neither touches the other.

**Corollary.** A visitor with no code is tagged `directo`, not NULL. Direct
traffic is a channel, not an absence, and NULL made it vanish from the report.

---

## 3. No automatic commission calculation

**Request.** A referral programme that shows what each referrer is owed.

**Why not.** The system has no payment data. A `consulta` is an enquiry, not a
sale. `precio_desde` is a starting price nobody has paid. Espacio enquiries
carry no price at all. Any figure derived from those would look authoritative
and be fiction.

**Decision.** `comision` is a free-text note of what was agreed. Payouts are a
manual ledger of what was actually paid. The report shows enquiries and
conversions, which are real, and no money the system cannot verify.

---

## 4. Disposable-email blocklist, not a provider allowlist

**Request.** Accept only Gmail, Outlook, Yahoo, iCloud and Proton.

**Why not.** That rejects every facilitator with a custom domain, every
corporate organiser and every agency. Those are the highest-value enquiries the
site receives. Tested against the real gate: `a@vstech.app` and
`b@anthropic.com` both get rejected in allowlist mode.

**Decision.** Block known throwaway domains including subdomains, and require
the domain to resolve with an MX or A record. Custom domains pass, `mailinator`
does not.

The allowlist still exists behind `LR_SOLO_PROVEEDORES_CONOCIDOS`, switched off.
Leave it off.

---

## 5. Version markers in an HTML comment

**Constraint.** No SSH. Files are uploaded by hand. There is no way to ask the
server what it is running.

**Decision.** Every file carries its version in a PHP comment and an HTML
comment. `Ctrl+U` and read it.

This is not ceremony. It caught two real incidents on this project where a file
was rebuilt from a stale working copy and silently lost features, and it is what
`healthcheck.py` automates.

---

## 6. `php -l`, never a brace counter

`admin/retiros.php` returned 500 on every request for days. The `if ($accion ===
'guardar')` block was never closed, so the following `elseif` landed inside its
body. A stray extra brace further down made the total count balance, so a brace
counting heuristic passed and reported nothing wrong.

**Balanced braces are not valid syntax.** Run `php -l`. Nothing else counts.

---

## 7. `lrMime()` instead of `mime_content_type()`

The `fileinfo` extension disappeared from the hosting account mid-project and
every upload started dying with an uncaught fatal.

**Decision.** `lrMime()` in `config.php` tries `finfo`, then
`mime_content_type()`, then `getimagesize()`, which is core PHP and needs no
extension. For MP4 it reads the `ftyp` box in the first twelve bytes. It returns
`''` when nothing can read the file, and **every caller treats `''` as a
rejection, never as a pass**.

The `getimagesize()` fallback is a stronger check than what it replaced: it
parses the file rather than sniffing it, so a `.php` renamed to `.jpg` cannot
get through.

---

## 8. The mail pipe always exits 0

A non-zero exit makes Exim bounce the message back to the sender. A parser bug
would therefore turn into a bounce in front of a customer.

**Decision.** `pipe_correo.php` wraps everything in try/catch and always exits
0. Failures go to `pipe_correo.log`. An empty log file means the pipe never
executed at all, which is a path or permissions problem, not a parsing one.

---

## 9. Attachments are counted, not stored

Writing files to disk from unauthenticated senders is the largest attack surface
this codebase could open. The original is always in webmail.

---

## 10. `assets/` is not in git

137 MB. In git, every video ever replaced stays in the history forever, so the
clone would pass a gigabyte within a year and every fresh clone would pay for
it. Media is uploaded through cPanel and registered in the database.

---

## 11. Data lives in the database, not in the code

The static array in `espacios.php` is a fallback for when the query fails, not
the source of truth. When content and code disagree, content wins.

This is why `espacios.php` counts the rows instead of saying "Cinco espacios":
that string was hardcoded and became wrong the moment a sixth space was added.

---

## 12. No multi-column layout for ordered grids

`galeria.php` used `columns: 3`. A multi-column layout flows items **down**
column one and then down column two, so the `orden` set in the panel read
vertically instead of left to right, and the browser balanced columns by height
which left a ragged bottom row.

Every photo is the same aspect ratio, so masonry bought nothing. Replaced with
CSS grid.

---

## 13. Grid items need `minmax(0, 1fr)` to scroll

The espacios lightbox panel could not be scrolled and the form was unreachable.
`max-height` caps the container but a grid **row** still sizes to its content,
so the row grew past the box and `overflow: hidden` clipped it. The panel had no
overflow of its own to scroll.

`min-height: 0` on the item is necessary but not sufficient. The row has to be
capped: `grid-template-rows: minmax(0, 1fr)`.

---

## 14. `width: fit-content` to centre a hero

A block that already spans its container cannot be centred with `margin: auto`.
`historia.php` centred correctly by accident, because its inner container is a
flex item, which shrinks to content and then centres.

The other heroes needed `width: fit-content` to get the same behaviour.

---

## 15. Autoplay is muted, always

Every browser blocks audio autoplay without a user gesture. Attempting it means
the video does not start at all and the visitor gets a frozen frame.

**Decision.** Video autoplays muted and the Sonido button is the way in. It is
the entry point, not a nicety.

---

## 16. PostHog was evaluated and declined

Levels 3 to 5 of the suggested approach need Vercel, feature flags and an agent
pushing to a repo that auto-deploys. This site is uploaded by hand to cPanel.

A/B testing also needs hundreds of visitors per variant before a result means
anything, which this traffic will not produce this year. The funnel is already
measured end to end in the site's own database.

Revisit when traffic makes layout decisions genuinely uncertain.

---

## 17. Rejected: an EN/ES toggle

The switch is an afternoon. The cost is that every translatable field needs a
second column, both admin pages need a second set of inputs, and **every retreat
has to be written twice, forever**. Miss one and an English visitor sees Spanish,
which looks worse than having no toggle.

The audience is overwhelmingly Argentine. Revisit if English enquiries actually
appear; `admin/seguridad.php` already records where visitors come from, so that
will be evidence rather than a guess.

---

## 18. La paleta del logo no esta aplicada, y es una decision pendiente

El sitio entero usa navy `#0a1628` y teal `#00c9a7`. El logo de La Reserva es
verde `#105838`, arena `#c0c098`, marron `#402810` sobre crema. Son paletas
opuestas: una oscura y fria, la otra clara y calida.

`verano2027.php` se hizo primero en la paleta del logo y despues se volvio a
la del sitio, porque una landing que no se parece al sitio al que lleva
confunde mas de lo que suma.

Cambiar todo el sitio al logo es un dia de trabajo: `style.css`, `nav.php`,
`footer.php`, `welcome.html`, el panel entero y cada pagina con `<style>`
propio. No se hace sin confirmacion explicita. Ver `BIBLE.md` seccion 12.2.
