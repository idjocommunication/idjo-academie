"""Vectorise le logo « idjô » d'Alan (PNG noir sur fond transparent) en tracés SVG séparés.

Les formes sont rangées en 4 groupes pour pouvoir les colorer à part :
  lettres   : i, d, j, o
  points    : les carrés au-dessus du i et du j
  accent    : le chapeau du ô
  ronds     : les deux ronds dans le d et le o

Usage : python3 tracer_logo_idjo.py <logo.png> <sortie.json>
Le JSON contient la boîte du logo et un tracé SVG (attribut d) par groupe.
"""
import json
import sys

import numpy as np
import potrace
from PIL import Image
from scipy import ndimage

SOURCE, SORTIE = sys.argv[1], sys.argv[2]

im = Image.open(SOURCE).convert("RGBA")
rgba = np.array(im)
gris = np.array(im.convert("L"))
masque = (gris < 128) & (rgba[..., 3] > 128)
etiquettes, n = ndimage.label(masque)
boites = ndimage.find_objects(etiquettes)


def ranger(i):
    """Classe une forme selon sa position et sa taille."""
    sl = boites[i - 1]
    y0, y1, x0, x1 = sl[0].start, sl[0].stop, sl[1].start, sl[1].stop
    l, h = x1 - x0, y1 - y0
    aire = int((etiquettes[sl] == i).sum())
    if y1 < masque.shape[0] * 0.33 and l > 2 * h:
        return "accent"
    if aire > 200000:
        return "lettres"
    remplissage = aire / (l * h)
    return "ronds" if remplissage < 0.85 else "points"


groupes = {"lettres": [], "points": [], "accent": [], "ronds": []}
for i in range(1, n + 1):
    groupes[ranger(i)].append(i)


def fmt(v):
    return f"{v:.1f}".rstrip("0").rstrip(".")


def tracer(ids):
    """Trace les formes données et renvoie un attribut d (coordonnées de l'image source)."""
    m = np.isin(etiquettes, ids)
    ys, xs = np.nonzero(m)
    x0, y0 = max(xs.min() - 4, 0), max(ys.min() - 4, 0)
    x1, y1 = xs.max() + 5, ys.max() + 5
    zone = m[y0:y1, x0:x1]
    bm = potrace.Bitmap(~zone)  # potracer : les valeurs basses (sombres) sont les formes pleines
    chemins = bm.trace(turdsize=10, turnpolicy=potrace.POTRACE_TURNPOLICY_MINORITY,
                       alphamax=1.0, opticurve=True, opttolerance=0.2)
    morceaux = []
    for courbe in chemins:
        p = courbe.start_point
        morceaux.append(f"M{fmt(p.x + x0)} {fmt(p.y + y0)}")
        for seg in courbe.segments:
            if seg.is_corner:
                morceaux.append(f"L{fmt(seg.c.x + x0)} {fmt(seg.c.y + y0)}L{fmt(seg.end_point.x + x0)} {fmt(seg.end_point.y + y0)}")
            else:
                morceaux.append(f"C{fmt(seg.c1.x + x0)} {fmt(seg.c1.y + y0)} {fmt(seg.c2.x + x0)} {fmt(seg.c2.y + y0)} {fmt(seg.end_point.x + x0)} {fmt(seg.end_point.y + y0)}")
        morceaux.append("Z")
    return "".join(morceaux)


ys, xs = np.nonzero(masque)
resultat = {
    "boite": [int(xs.min()), int(ys.min()), int(xs.max()) + 1, int(ys.max()) + 1],
    "ligne_de_base": None,
    "groupes": {nom: tracer(ids) for nom, ids in groupes.items() if ids},
}
# Ligne de base et hauteur d'x : jambe du i (colonne près du bord gauche, sous le point carré)
lignes = np.nonzero(masque[:, xs.min() + 20])[0]
coupures = np.nonzero(np.diff(lignes) > 1)[0]
debut_jambe = int(lignes[coupures[-1] + 1]) if len(coupures) else int(lignes[0])
resultat["ligne_de_base"] = int(lignes.max()) + 1
resultat["hauteur_x"] = resultat["ligne_de_base"] - debut_jambe
with open(SORTIE, "w") as f:
    json.dump(resultat, f)
print({k: len(v) for k, v in groupes.items()}, resultat["boite"], "base", resultat["ligne_de_base"], "x", resultat["hauteur_x"])
