"""Assemble le logo « idjô » d'Alan (tracés de tracer_logo_idjo.py) avec le mot « Académie » en Outfit.

Fonctions utilisées par les scripts de propositions et de génération des fichiers du site.
"""
import json

import uharfbuzz as hb
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont


class Logo:
    def __init__(self, traces_json, outfit_ttf):
        with open(traces_json) as f:
            d = json.load(f)
        self.boite = d["boite"]              # x0, y0, x1, y1 dans l'image source
        self.base = d["ligne_de_base"]       # bas des lettres
        self.hx = d["hauteur_x"]             # hauteur d'x du logo
        self.groupes = d["groupes"]          # lettres, points, accent, ronds
        self.outfit = outfit_ttf
        self.tt = TTFont(outfit_ttf)
        self.upm = self.tt["head"].unitsPerEm
        self.cap = self.tt["OS/2"].sCapHeight

    def _mot(self, texte, wght, echelle, x, base):
        """Tracé SVG d'un mot en Outfit, posé sur la ligne de base donnée. Renvoie (d, largeur)."""
        blob = hb.Blob.from_file_path(self.outfit)
        police = hb.Font(hb.Face(blob))
        police.set_variations({"wght": wght})
        buf = hb.Buffer()
        buf.add_str(texte)
        buf.guess_segment_properties()
        hb.shape(police, buf, {"kern": True, "liga": True})
        ordre = self.tt.getGlyphOrder()
        gs = self.tt.getGlyphSet(location={"wght": wght})
        cx = 0
        d = []
        fin = 0
        for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
            nom = ordre[info.codepoint]
            pen = SVGPathPen(gs, ntos=lambda v: f"{v:.1f}".rstrip("0").rstrip("."))
            gs[nom].draw(TransformPen(pen, (echelle, 0, 0, -echelle, x + (cx + pos.x_offset) * echelle, base)))
            d.append(pen.getCommands())
            bp = BoundsPen(gs)
            gs[nom].draw(bp)
            if bp.bounds:
                fin = max(fin, (cx + pos.x_offset + bp.bounds[2]) * echelle)
            cx += pos.x_advance
        return "".join(d), fin

    def svg(self, couleurs, wght=500, taille=1.0, avec_mot=True, marge=0.12, fond=None, titre="Idjô Académie"):
        """couleurs : dict lettres, points, accent, ronds, mot. taille : 1 = « A » aussi haut que la hauteur d'x du logo."""
        x0, y0, x1, y1 = self.boite
        morceaux = [f'<path fill="{couleurs[g]}" fill-rule="evenodd" d="{self.groupes[g]}"/>'
                    for g in ("lettres", "points", "accent", "ronds") if g in self.groupes]
        droite = x1
        if avec_mot:
            # « A » aussi haut que la hauteur d'x du logo, posé sur la même ligne de base
            echelle = taille * self.hx / self.cap
            ecart = 0.34 * self.hx
            d, largeur = self._mot("Académie", wght, echelle, x1 + ecart, self.base)
            morceaux.append(f'<path fill="{couleurs["mot"]}" d="{d}"/>')
            droite = x1 + ecart + largeur
        m = marge * (y1 - y0)
        vx, vy, vw, vh = x0 - m, y0 - m, (droite - x0) + 2 * m, (y1 - y0) + 2 * m
        rect = f'<rect x="{vx:.0f}" y="{vy:.0f}" width="{vw:.0f}" height="{vh:.0f}" fill="{fond}"/>' if fond else ""
        return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vx:.0f} {vy:.0f} {vw:.0f} {vh:.0f}" '
                f'role="img" aria-label="{titre}"><title>{titre}</title>{rect}{"".join(morceaux)}</svg>'), vw / vh

    def icone(self, couleurs, fond, rayon=0.22):
        """Icône carrée (favicon, écran d'accueil du téléphone) : idjô entier sur un carré arrondi."""
        x0, y0, x1, y1 = self.boite
        contenu = "".join(f'<path fill="{couleurs[g]}" fill-rule="evenodd" d="{self.groupes[g]}"/>'
                          for g in ("lettres", "points", "accent", "ronds"))
        taille = (x1 - x0) * 1.22
        vx, vy = (x0 + x1) / 2 - taille / 2, (y0 + y1) / 2 - taille / 2
        r = rayon * taille
        return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vx:.0f} {vy:.0f} {taille:.0f} {taille:.0f}">'
                f'<rect x="{vx:.0f}" y="{vy:.0f}" width="{taille:.0f}" height="{taille:.0f}" rx="{r:.0f}" fill="{fond}"/>'
                f'{contenu}</svg>')
