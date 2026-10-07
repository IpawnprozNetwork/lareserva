#!/usr/bin/env python3
"""
build-docs.py - La Reserva

Converts the project markdown into styled HTML in docs/, matching the look of
the guides already there. Run it after editing any .md file.

    python3 build-docs.py

No dependencies. The markdown subset is deliberately small, exactly what these
documents use: headings, tables, fenced code, inline code, bold, links, lists,
blockquotes, horizontal rules. Nothing else is supported, which is fine because
nothing else is used.
"""

import html
import os
import re

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "docs")

DOCS = [
    ("README.md",          "README",          "Vista general del proyecto"),
    ("OPERAR.md",          "GUIA DE DESARROLLO", "GitHub, healthcheck y deploy"),
    ("GITHUB.md",          "GitHub",          "Crear el repo y subir desde la Mac"),
    ("SETUP.md",           "Setup",           "De un cPanel vacio a un sitio andando"),
    ("ARCHITECTURE.md",    "Arquitectura",    "Como encajan las piezas"),
    ("DECISIONS.md",       "Decisiones",      "Por que estan asi, con el bug detras"),
    ("PROJECT-BRAIN.md",   "Project Brain",   "Contexto completo para retomar"),
    ("db/README.md",       "Base de datos",   "Los scripts SQL, en orden"),
]

GUIAS = [
    ("guia-usuario.html",   "GUIA DE USUARIO", "Las 6 paginas del sitio y todo el panel"),
    ("guia-completa.html",  "Guia completa",   "Visitantes, organizadores, equipo y desarrollo"),
    ("manual-usuario.html", "Manual de uso",   "Version corta para el panel"),
    ("guia-tecnica.html",   "Guia tecnica",    "Arquitectura y post mortem de los bugs"),
]

CSS = """
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:#08111c;color:#a8bccf;font-family:'Karla',system-ui,sans-serif;min-height:100vh;scroll-behavior:smooth}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-thumb{background:#1c3247;border-radius:3px}
::selection{background:#00c9a7;color:#08111c}
.wrap{display:flex}
.sidebar{position:sticky;top:0;align-self:flex-start;width:250px;height:100vh;overflow-y:auto;background:#060d16;border-right:1px solid #14283a;flex-shrink:0;padding:0 0 40px;z-index:50}
.brand{padding:20px 22px 16px;border-bottom:1px solid #14283a}
.brand b{display:block;font:300 20px 'Fraunces',Georgia,serif;color:#eef3f8}
.brand span{display:block;font:700 9px 'JetBrains Mono',monospace;color:#3d5a72;letter-spacing:.22em;margin-top:4px}
.nav a{display:block;padding:6px 22px;color:#7d95ab;text-decoration:none;border-left:2px solid transparent;font-size:12.5px}
.nav a:hover{color:#00c9a7;border-left-color:#00c9a7;background:#0b1622}
.nav a.on{color:#00c9a7;border-left-color:#00c9a7;background:#0b1622;font-weight:600}
.nav a small{display:block;color:#3d5a72;font-size:10.5px;margin-top:1px}
.nav-label{padding:14px 22px 4px;font:700 10px 'JetBrains Mono',monospace;color:#3d5a72;letter-spacing:.16em}
.menu-btn{display:none;position:fixed;top:10px;left:10px;z-index:120;background:#0e1c2a;color:#eef3f8;border:1px solid #23405a;border-radius:8px;width:42px;height:42px;font-size:20px;cursor:pointer;align-items:center;justify-content:center}
.scrim{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:49}
main{flex:1;min-width:0;padding:44px 46px 90px;max-width:920px}
h1{font:300 36px 'Fraunces',Georgia,serif;color:#eef3f8;line-height:1.15;margin:0 0 18px}
h2{font:300 25px 'Fraunces',Georgia,serif;color:#eef3f8;margin:34px 0 10px;padding-top:8px;border-top:1px solid #14283a}
h2:first-of-type{border-top:0;margin-top:16px}
h3{font:600 15px 'Karla',sans-serif;color:#00c9a7;margin:22px 0 8px}
p,li{font-size:14px;line-height:1.75;color:#8fa7bd;margin:0 0 10px}
ul,ol{padding-left:20px;margin:0 0 12px}
strong{color:#cfe0ee}
a{color:#00c9a7}
hr{border:0;border-top:1px solid #14283a;margin:30px 0}
blockquote{padding:11px 15px;background:#181205;border:1px solid #3d2f10;border-left:3px solid #f5a623;border-radius:0 8px 8px 0;margin:14px 0}
blockquote p{color:#c4ab7d;font-size:13.5px;margin:0}
blockquote p+p{margin-top:8px}
code{font:500 12px 'JetBrains Mono',monospace;color:#00c9a7;background:#0b1826;padding:1px 5px;border-radius:4px}
pre{margin:14px 0;padding:14px;overflow-x:auto;background:#070f18;border:1px solid #16293a;border-radius:8px}
pre code{background:none;padding:0;color:#b9cddd;font-size:11.5px;line-height:1.65}
.tw{overflow-x:auto;margin:12px 0}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:480px}
th{text-align:left;padding:8px 10px;background:#0b1826;color:#f5a623;font:700 11px 'JetBrains Mono',monospace;border-bottom:1px solid #16293a;white-space:nowrap}
td{padding:8px 10px;border-bottom:1px solid #101d2a;color:#8fa7bd;vertical-align:top}
tr:hover td{background:#0a1420}
.foot{color:#3d5a72;font-size:12px;border-top:1px solid #14283a;padding-top:16px;margin-top:44px}
.tarjetas{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin:16px 0}
.tarjeta{border:1px solid #16293a;border-radius:10px;padding:15px 17px;background:#0a1420;text-decoration:none;display:block}
.tarjeta:hover{border-color:#1f4a5e;background:#0c1a28}
.tarjeta b{display:block;color:#eef3f8;font:600 14px 'Karla',sans-serif;margin-bottom:3px}
.tarjeta span{font-size:12.5px;color:#7d95ab;line-height:1.5}
@media(max-width:900px){
  .menu-btn{display:flex}
  .sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:transform .2s}
  .sidebar.open{transform:none}
  .scrim.on{display:block}
  main{padding:64px 20px 70px}
}
"""


def inline(t):
    """Inline markdown. Code spans are protected before anything else runs."""
    guardados = []

    def guardar(m):
        guardados.append(m.group(1))
        return f"\x00{len(guardados)-1}\x00"

    t = re.sub(r"`([^`]+)`", guardar, t)
    t = html.escape(t, quote=False)
    t = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", t)
    t = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r'<a href="\2">\1</a>', t)
    t = re.sub(r"\x00(\d+)\x00",
               lambda m: "<code>" + html.escape(guardados[int(m.group(1))], quote=False) + "</code>",
               t)
    return t


def convertir(md):
    out, i = [], 0
    lineas = md.split("\n")
    while i < len(lineas):
        l = lineas[i]

        # fenced code
        if l.startswith("```"):
            i += 1
            buf = []
            while i < len(lineas) and not lineas[i].startswith("```"):
                buf.append(lineas[i]); i += 1
            i += 1
            out.append("<pre><code>" + html.escape("\n".join(buf), quote=False) + "</code></pre>")
            continue

        # table
        if l.strip().startswith("|") and i + 1 < len(lineas) and re.match(r"^\s*\|[\s:|-]+\|\s*$", lineas[i+1]):
            def celdas(s):
                return [c.strip() for c in s.strip().strip("|").split("|")]
            cab = celdas(l); i += 2
            filas = []
            while i < len(lineas) and lineas[i].strip().startswith("|"):
                filas.append(celdas(lineas[i])); i += 1
            t = ['<div class="tw"><table><tr>']
            t += [f"<th>{inline(c)}</th>" for c in cab]
            t.append("</tr>")
            for f in filas:
                t.append("<tr>" + "".join(f"<td>{inline(c)}</td>" for c in f) + "</tr>")
            t.append("</table></div>")
            out.append("".join(t))
            continue

        # heading
        m = re.match(r"^(#{1,4})\s+(.*)$", l)
        if m:
            n = len(m.group(1))
            out.append(f"<h{n}>{inline(m.group(2))}</h{n}>")
            i += 1
            continue

        # hr
        if re.match(r"^\s*---+\s*$", l):
            out.append("<hr>"); i += 1; continue

        # blockquote
        if l.startswith(">"):
            buf = []
            while i < len(lineas) and lineas[i].startswith(">"):
                buf.append(lineas[i].lstrip(">").strip()); i += 1
            parrafos = " ".join(buf).split("  ")
            out.append("<blockquote>" + "".join(f"<p>{inline(p)}</p>" for p in parrafos if p.strip()) + "</blockquote>")
            continue

        # lists
        if re.match(r"^\s*[-*]\s+", l) or re.match(r"^\s*\d+\.\s+", l):
            orden = bool(re.match(r"^\s*\d+\.\s+", l))
            tag = "ol" if orden else "ul"
            items = []
            while i < len(lineas) and (re.match(r"^\s*[-*]\s+", lineas[i]) or re.match(r"^\s*\d+\.\s+", lineas[i])):
                items.append(re.sub(r"^\s*(?:[-*]|\d+\.)\s+", "", lineas[i])); i += 1
            out.append(f"<{tag}>" + "".join(f"<li>{inline(x)}</li>" for x in items) + f"</{tag}>")
            continue

        # paragraph
        if l.strip():
            buf = []
            while i < len(lineas) and lineas[i].strip() and not re.match(r"^(#{1,4}\s|```|\s*[-*]\s|\s*\d+\.\s|>|\s*---+\s*$)", lineas[i]) and not lineas[i].strip().startswith("|"):
                buf.append(lineas[i].strip()); i += 1
            out.append(f"<p>{inline(' '.join(buf))}</p>")
            continue

        i += 1
    return "\n".join(out)


def pagina(titulo, cuerpo, actual):
    nav = ['<div class="nav-label">Documentos</div>']
    for archivo, nombre, sub in DOCS:
        destino = archivo.replace("/", "-").replace(".md", ".html").lower()
        on = " class=\"on\"" if destino == actual else ""
        nav.append(f'<a href="{destino}"{on}>{nombre}<small>{sub}</small></a>')
    nav.append('<div class="nav-label">Guias</div>')
    for archivo, nombre, sub in GUIAS:
        on = " class=\"on\"" if archivo == actual else ""
        nav.append(f'<a href="{archivo}"{on}>{nombre}<small>{sub}</small></a>')
    nav.append('<div class="nav-label">Indice</div><a href="index.html">Todos los documentos</a>')

    return f"""<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>La Reserva · {html.escape(titulo)}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,600&family=Karla:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>{CSS}</style>
</head>
<body>
<button class="menu-btn" onclick="tog()">&#9776;</button>
<div class="scrim" id="scrim" onclick="tog()"></div>
<div class="wrap">
<aside class="sidebar" id="sb">
  <div class="brand"><b>La Reserva</b><span>Documentacion</span></div>
  <nav class="nav">{''.join(nav)}</nav>
</aside>
<main>
{cuerpo}
<div class="foot">La Reserva Naturaleza Arte · lareserva.net.ar</div>
</main>
</div>
<script>
function tog(){{
  document.getElementById('sb').classList.toggle('open');
  document.getElementById('scrim').classList.toggle('on');
}}
document.querySelectorAll('.nav a').forEach(function(a){{
  a.addEventListener('click', function(){{ if (window.innerWidth <= 900) tog(); }});
}});
</script>
</body>
</html>"""


def main():
    os.makedirs(SALIDA, exist_ok=True)
    hechos = []
    for archivo, nombre, sub in DOCS:
        ruta = os.path.join(AQUI, archivo)
        if not os.path.exists(ruta):
            print(f"  falta {archivo}")
            continue
        destino = archivo.replace("/", "-").replace(".md", ".html").lower()
        md = open(ruta, encoding="utf-8").read()
        open(os.path.join(SALIDA, destino), "w", encoding="utf-8").write(
            pagina(nombre, convertir(md), destino))
        hechos.append((destino, nombre, sub))
        print(f"  {archivo:22} -> docs/{destino}")

    tarjetas = "".join(
        f'<a class="tarjeta" href="{d}"><b>{n}</b><span>{s}</span></a>' for d, n, s in hechos
    ) + "".join(
        f'<a class="tarjeta" href="{a}"><b>{n}</b><span>{s}</span></a>' for a, n, s in GUIAS
    )
    cuerpo = (
        "<h1>Documentacion de La Reserva</h1>"
        "<p>Hay dos documentos principales y el resto es detalle:</p>"
        "<ul>"
        "<li><a href='guia-usuario.html'><strong>Guia de usuario</strong></a>: "
        "las seis paginas del sitio y cada pantalla del panel. Sin nada tecnico.</li>"
        "<li><a href='operar.html'><strong>Guia de desarrollo</strong></a>: "
        "GitHub, healthcheck y la rutina de deploy.</li>"
        "</ul>"
        "<p>Si retomas el proyecto despues de un tiempo, empeza por "
        "<a href='project-brain.html'>Project Brain</a>.</p>"
        f'<div class="tarjetas">{tarjetas}</div>'
        "<h2>Generado</h2>"
        "<p>Estas paginas salen de los archivos markdown de la raiz del "
        "repositorio. Para regenerarlas despues de editar cualquiera:</p>"
        "<pre><code>python3 build-docs.py</code></pre>"
        "<p>No edites el HTML de <code>docs/</code> a mano: se pisa en la "
        "proxima corrida. Las tres guias largas son la excepcion, esas se "
        "escriben directo en HTML.</p>"
    )
    open(os.path.join(SALIDA, "index.html"), "w", encoding="utf-8").write(
        pagina("Documentacion", cuerpo, "index.html"))
    print(f"  indice                 -> docs/index.html")
    print(f"\n  {len(hechos)} documentos convertidos")


if __name__ == "__main__":
    main()
