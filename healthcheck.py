#!/usr/bin/env python3
"""
healthcheck.py - La Reserva

Runs from YOUR machine, not the server. HoboHost gives no SSH, no Python and
no cron, so a server-side checker is not an option. This hits the live site
over HTTPS instead, which is arguably better: it verifies what the public
actually receives rather than what the filesystem claims.

What it checks:
  1. Every page returns 200
  2. The deployed VERSION marker matches what it should be
  3. HTTP redirects to HTTPS, and www redirects to the bare domain
  4. The forms, calendars and tracking beacons are present in the HTML
  5. The admin panel is NOT reachable without a session
  6. No diagnostic file is left exposed
  7. Page weight and load time

Usage:
    python3 healthcheck.py
    python3 healthcheck.py --json          machine readable
    python3 healthcheck.py --host lareserva.net.ar

No dependencies outside the standard library.
Exit code 0 if everything passes, 1 if anything fails.
"""

import argparse
import json
import re
import ssl
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone

HOST_DEFAULT = "lareserva.net.ar"

# Version each public file should be serving. Bump these when you deploy.
VERSIONS = {
    "/index.php":    "1.9",
    "/espacios.php": "3.2",
    "/retiros.php":  "3.0",
    "/galeria.php":  "1.4",
    "/retiro.php?r=retiro-verano-enero-2027": "1.9",
    "/verano2027.php": "5.6",
}

# Strings that must appear in a page. If one goes missing, a deploy overwrote
# a newer file with an older one, which has happened twice on this project.
FEATURES = {
    # lrAjuste() es una funcion PHP: corre en el servidor y NUNCA aparece en
    # el HTML. Buscarla aca daba FAIL siempre. Se cambia por marcas que si
    # salen en la pagina: el video del hero y el id que usa su arranque.
    "/index.php":    ["lrBoton", "width:fit-content", "IntersectionObserver",
                      "hero-verano.mp4", "heroVid"],
    "/espacios.php": ["ep-rango", "lrPlayer", "esp-encabezado", "width:fit-content", "epPorSlug"],
    "/retiros.php":  ["lrPlayer", "rt-cal-nota", "width:fit-content", "rt-libres"],
    "/galeria.php":  ["grid-template-columns:repeat(3", "lrBoton"],
    "/contacto.php": ["lrBoton", "csrf"],
    "/verano2027.php": ["lrTrack", "id=\"vl\"", "data-vid", "csrf"],
    "/retiro.php?r=retiro-verano-enero-2027": ["retiro-side", "width:fit-content"],
}

PAGES = [
    "/", "/index.php", "/verano2027.php", "/espacios.php", "/retiros.php", "/galeria.php",
    "/historia.php", "/contacto.php", "/acceso.php",
    "/retiro.php?r=retiro-verano-enero-2027",
]

# These must never be reachable by an anonymous visitor.
MUST_BE_PROTECTED = [
    "/admin/", "/admin/index.php", "/admin/consultas.php",
    "/admin/referidos.php", "/admin/seguridad.php", "/admin/inbox.php",
    "/admin/usuarios.php",
]

# Diagnostic and setup scripts that should have been deleted from the server.
MUST_BE_GONE = [
    "/admin/upload_test.php", "/admin/pipe_test.php", "/generar-hash.php",
    "/includes/config.php", "/admin/diag.php",
]

TIMEOUT = 20
# v1.3: el User-Agent propio hacia que el WAF del hosting tratara al script
# como un scraper y respondiera a TODO con una pagina de desafio de 12 KB.
# El resultado eran 17 fallas falsas, incluidos archivos borrados que
# "existian". Con un User-Agent de navegador y una pausa entre pedidos, el
# script deja de parecer un bot.
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36")
PAUSA = 0.4        # segundos entre pedidos

GREEN, RED, YELLOW, GREY, RESET = "\033[32m", "\033[31m", "\033[33m", "\033[90m", "\033[0m"


class Result:
    def __init__(self):
        self.rows = []
        self.fail = 0
        self.warn = 0

    def add(self, estado, prueba, detalle=""):
        self.rows.append({"estado": estado, "prueba": prueba, "detalle": detalle})
        if estado == "FAIL":
            self.fail += 1
        elif estado == "WARN":
            self.warn += 1

    def line(self, row):
        c = {"OK": GREEN, "FAIL": RED, "WARN": YELLOW}[row["estado"]]
        d = f"  {GREY}{row['detalle']}{RESET}" if row["detalle"] else ""
        return f"  {c}{row['estado']:4}{RESET}  {row['prueba']:<46}{d}"


def es_desafio(body):
    """La pagina de espera del WAF, por su texto."""
    if not body or len(body) > 20000:
        return False
    marcas = ("One moment, please", "window.location.reload", "Checking your browser",
              "Just a moment", "cf-browser-verification", "DDoS protection",
              "Please wait while", "ddos-guard", "Attention Required")
    return any(m in body for m in marcas)


def hay_catch_all(base):
    """v1.3: la prueba que no falla nunca.

    Pide una direccion que no puede existir. Si el servidor responde 200 con
    una pagina, entonces esta contestando lo mismo para todo y ningun chequeo
    de abajo significa nada. No depende de adivinar el texto del WAF: solo
    depende de que un 404 tiene que ser un 404.
    """
    import uuid
    code, body, _, _ = fetch(base + "/zz-" + uuid.uuid4().hex + ".php")
    return code == 200 and body and len(body) > 500


def fetch(url, follow=True):
    """Returns (status, body, seconds, final_url). Never raises."""
    ctx = ssl.create_default_context()
    req = urllib.request.Request(url, headers={
        "User-Agent": UA,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "es-AR,es;q=0.9,en;q=0.8",
        "Connection": "close",
    })
    time.sleep(PAUSA)
    t0 = time.time()

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None

    opener = urllib.request.build_opener(
        urllib.request.HTTPSHandler(context=ctx),
        *([] if follow else [NoRedirect()])
    )
    try:
        with opener.open(req, timeout=TIMEOUT) as r:
            return r.status, r.read().decode("utf-8", "replace"), time.time() - t0, r.geturl()
    except urllib.error.HTTPError as e:
        body = ""
        try:
            body = e.read().decode("utf-8", "replace")
        except Exception:
            pass
        return e.code, body, time.time() - t0, url
    except Exception as e:
        return 0, f"{type(e).__name__}: {e}", time.time() - t0, url


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default=HOST_DEFAULT)
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()
    base = f"https://{args.host}"
    r = Result()

    if not args.json:
        print(f"\n  La Reserva healthcheck   {base}")
        print(f"  {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')}\n")

    # ── 1. pages respond ───────────────────────────────────────
    bodies = {}
    for path in PAGES:
        code, body, secs, _ = fetch(base + path)
        bodies[path] = body
        if code == 200:
            kb = len(body) / 1024
            nota = f"{secs:.2f}s  {kb:.0f} KB"
            estado = "OK"
            if secs > 4:
                estado, nota = "WARN", nota + "  lento"
            elif kb > 900:
                estado, nota = "WARN", nota + "  pesado"
            r.add(estado, f"GET {path}", nota)
        else:
            r.add("FAIL", f"GET {path}", f"HTTP {code}" if code else body[:60])

    # Antes de todo: si el WAF esta mostrando su pagina de espera, cada
    # chequeo de aca en adelante va a fallar por la misma razon. Mejor decirlo
    # una vez que repetirlo treinta y nueve veces.
    code0, body0, _, _ = fetch(base + "/")
    if es_desafio(body0) or hay_catch_all(base):
        print()
        print("  El servidor esta contestando lo mismo para CUALQUIER direccion,")
        print("  incluso para una inventada. Es la proteccion del hosting, no el")
        print("  sitio. Todos los chequeos de abajo fallarian por esa razon.")
        print()
        print("  Que hacer:")
        print("    1. Abri lareserva.net.ar en el navegador. Si carga, es solo")
        print("       contra este script y se destraba solo en unos minutos.")
        print("    2. Si usas VPN, apagala y proba de nuevo.")
        print("    3. Esperar 10 minutos y volver a correr.")
        print()
        sys.exit(2)

    # 404.php responde 404 a proposito: es la pagina de error. Pedirla y
    # recibir 200 seria el problema, no al reves.
    code, body, _, _ = fetch(base + "/404.php")
    if code == 404 and body and "<!DOCTYPE" in body:
        r.add("OK", "GET /404.php", "HTTP 404 con pagina, correcto")
    elif code == 404:
        r.add("WARN", "GET /404.php", "HTTP 404 sin cuerpo")
    else:
        r.add("FAIL", "GET /404.php", f"HTTP {code}, deberia ser 404")

    # ── 2. deployed versions ───────────────────────────────────
    for path, esperado in VERSIONS.items():
        body = bodies.get(path, "")
        # Cada pagina incluye nav, footer y ajustes, que traen su propio
        # marcador. Se busca primero el de la pagina misma, por nombre de
        # archivo; si no esta, se cae a la lista completa.
        archivo = path.split("?")[0].lstrip("/")
        propio = re.findall(rf"{re.escape(archivo)}\s+VERSION\s+([0-9][0-9.]*)", body)
        found = set(propio) if propio else set(re.findall(r"VERSION\s+([0-9][0-9.]*)", body))
        if not body:
            r.add("FAIL", f"version {path}", "sin respuesta")
        elif esperado in found:
            r.add("OK", f"version {path}", esperado)
        else:
            r.add("FAIL", f"version {path}",
                  f"esperaba {esperado}, encontro {','.join(sorted(found)) or 'ninguna'}")

    # ── 3. features still in the HTML ──────────────────────────
    for path, marcas in FEATURES.items():
        body = bodies.get(path)
        if body is None:
            code, body, _, _ = fetch(base + path)
        faltan = [m for m in marcas if m not in body]
        if faltan:
            r.add("FAIL", f"features {path}", "falta: " + ", ".join(faltan))
        else:
            r.add("OK", f"features {path}", f"{len(marcas)} presentes")

    # ── 4. redirects ───────────────────────────────────────────
    code, _, _, final = fetch(f"http://{args.host}/", follow=True)
    r.add("OK" if final.startswith("https://") else "FAIL",
          "http redirige a https", final[:56])

    code, _, _, final = fetch(f"https://www.{args.host}/", follow=True)
    r.add("OK" if f"//{args.host}" in final else "WARN",
          "www redirige al dominio pelado", final[:56])

    # ── 5. admin protected ─────────────────────────────────────
    for path in MUST_BE_PROTECTED:
        code, body, _, final = fetch(base + path)
        expuesto = code == 200 and ("logout.php" in body or "sb-avatar" in body)
        if expuesto:
            r.add("FAIL", f"protegido {path}", "SE VE SIN SESION")
        elif code in (200, 302, 301, 403):
            r.add("OK", f"protegido {path}", "pide login")
        else:
            r.add("WARN", f"protegido {path}", f"HTTP {code}")

    # ── 6. nothing sensitive exposed ───────────────────────────
    for path in MUST_BE_GONE:
        code, body, _, _ = fetch(base + path)
        if code == 200 and len(body) > 40:
            severidad = "FAIL" if "config.php" in path or "upload_test" in path else "WARN"
            r.add(severidad, f"no expuesto {path}", f"HTTP 200, {len(body)} bytes")
        else:
            r.add("OK", f"no expuesto {path}", f"HTTP {code}")

    # ── 7. tracking endpoint ───────────────────────────────────
    code, body, _, _ = fetch(base + "/track.php")
    r.add("OK" if code in (204, 200) and len(body) < 40 else "WARN",
          "track.php responde sin contenido", f"HTTP {code}, {len(body)} bytes")

    # ── output ─────────────────────────────────────────────────
    if args.json:
        print(json.dumps({
            "host": args.host,
            "cuando": datetime.now(timezone.utc).isoformat(),
            "fallas": r.fail, "avisos": r.warn,
            "pruebas": r.rows,
        }, indent=2, ensure_ascii=False))
    else:
        for row in r.rows:
            print(r.line(row))
        total = len(r.rows)
        ok = total - r.fail - r.warn
        print(f"\n  {ok} ok, {r.warn} avisos, {r.fail} fallas, de {total}\n")
        if r.fail:
            print(f"  {RED}Hay fallas. Lo mas probable, por orden:{RESET}")
            print("    1. Un archivo se subio a medias o no se subio")
            print("    2. Se subio una version vieja encima de una nueva")
            print("    3. Quedo un archivo de diagnostico en el servidor")
            print("    4. Se rompio algo en la base y la pagina tira 500\n")

    return 1 if r.fail else 0


if __name__ == "__main__":
    sys.exit(main())
