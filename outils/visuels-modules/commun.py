"""Outils communs pour dessiner les images des modules (HTML + SVG rendus par Chrome).

Regénérer : python3 module1.py (… module4.py) puis python3 rendre.py, et copier les .webp
dans assets/img/modules/. Le module 3 a besoin de deux captures du site : site-ordi.png
(1280x860) et site-tel.png (390x760, échelle 2), à placer dans ce dossier.
"""
import math
import os

ICI = os.path.dirname(os.path.abspath(__file__))

CSS_BASE = """
@font-face { font-family: Outfit; src: url(../../assets/fonts/outfit.woff2) format("woff2"); font-weight: 100 900; }
@font-face { font-family: Inter; src: url(../../assets/fonts/inter.woff2) format("woff2"); font-weight: 100 900; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { width: 1600px; height: 1000px; overflow: hidden; }
body { font-family: Inter, sans-serif; color: #1A0719; position: relative; }
.titre { font-family: Outfit, sans-serif; }
"""


def page(fond, contenu, css=""):
    return f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{CSS_BASE}
body {{ background: {fond}; }}
{css}</style></head><body>{contenu}</body></html>"""


def bosses(cx, cy, rx, ry, n, debut=0.0, fin=360.0, gonfle=1.0, sens=1, tour=True):
    """Contour fait d'arcs qui bombent vers l'extérieur (forme de nuage ou de cerveau)."""
    pts = []
    for i in range(n + 1):
        a = math.radians(debut + (fin - debut) * i / n)
        pts.append((cx + rx * math.cos(a), cy + ry * math.sin(a)))
    d = f"M{pts[0][0]:.1f},{pts[0][1]:.1f} "
    for (x1, y1), (x2, y2) in zip(pts, pts[1:]):
        r = math.hypot(x2 - x1, y2 - y1) / 2 * gonfle
        d += f"A{r:.1f},{r:.1f} 0 0 {sens} {x2:.1f},{y2:.1f} "
    return d + ("Z" if tour else "")


def ecrire(nom, html):
    chemin = os.path.join(ICI, nom + ".html")
    with open(chemin, "w", encoding="utf-8") as f:
        f.write(html)
    return chemin


CSS_APPAREILS = """
.ordi { position: absolute; }
.ordi__ecran { position: absolute; inset: 0; background: #17131F; border-radius: 26px; padding: 18px; box-shadow: 0 40px 80px rgba(26, 7, 25, .22); }
.ordi__ecran > .vue { width: 100%; height: 100%; border-radius: 10px; overflow: hidden; background: #fff; position: relative; }
.ordi__socle { position: absolute; left: -6%; right: -6%; bottom: -34px; height: 34px; background: linear-gradient(#D9D6E0, #B7B2C2); border-radius: 0 0 26px 26px; }
.ordi__socle::before { content: ""; position: absolute; left: 50%; top: 0; width: 18%; height: 10px; transform: translateX(-50%); background: #A49FB0; border-radius: 0 0 12px 12px; }
.tel { position: absolute; background: #17131F; border-radius: 46px; padding: 12px; box-shadow: 0 40px 70px rgba(26, 7, 25, .28); }
.tel > .vue { width: 100%; height: 100%; border-radius: 35px; overflow: hidden; position: relative; background: #fff; }
.tel__encoche { position: absolute; top: 22px; left: 50%; transform: translateX(-50%); width: 92px; height: 26px; background: #17131F; border-radius: 14px; z-index: 5; }
.tablette { position: absolute; background: #17131F; border-radius: 34px; padding: 20px; box-shadow: 0 40px 80px rgba(26, 7, 25, .24); }
.tablette > .vue { width: 100%; height: 100%; border-radius: 14px; overflow: hidden; position: relative; background: #fff; }
.puce { position: absolute; display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 20px; padding: 16px 22px 16px 16px; box-shadow: 0 18px 40px rgba(26, 7, 25, .14); font-family: Outfit, sans-serif; font-weight: 700; font-size: 28px; color: #1A0719; }
.puce i { width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; font-style: normal; }
"""


def ordi(x, y, l, h, vue):
    return f'<div class="ordi" style="left:{x}px;top:{y}px;width:{l}px;height:{h}px"><div class="ordi__ecran"><div class="vue">{vue}</div></div><div class="ordi__socle"></div></div>'


def tel(x, y, l, h, vue, rotation=0):
    return f'<div class="tel" style="left:{x}px;top:{y}px;width:{l}px;height:{h}px;transform:rotate({rotation}deg)"><div class="tel__encoche"></div><div class="vue">{vue}</div></div>'


def tablette(x, y, l, h, vue, rotation=0):
    return f'<div class="tablette" style="left:{x}px;top:{y}px;width:{l}px;height:{h}px;transform:rotate({rotation}deg)"><div class="vue">{vue}</div></div>'
