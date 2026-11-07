"""Construit le logo Idjô Académie en SVG (texte vectorisé, aucun dégradé).

Usage : python3 build_logo.py <Archivo[wdth,wght].ttf> <dossier_sortie>
"""
import sys
import uharfbuzz as hb
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.transformPen import TransformPen

FONT_PATH, OUT = sys.argv[1], sys.argv[2]

NOIR = "#000000"
VIOLET = "#B606AE"  # néon de l'affiche
ROSE = "#FB7AF5"  # rose néon (accent sur fond sombre)
PRUNE = "#2D022B"
BLANC = "#FFFFFF"

tt = TTFont(FONT_PATH)
UPM = tt["head"].unitsPerEm
blob = hb.Blob.from_file_path(FONT_PATH)
face = hb.Face(blob)


def shape(text, wdth, wght, tracking=0):
    """Retourne [(glyph_name, x_offset_en_unites)], largeur totale, glyphset."""
    font = hb.Font(face)
    font.set_variations({"wdth": wdth, "wght": wght})
    buf = hb.Buffer()
    buf.add_str(text)
    buf.guess_segment_properties()
    hb.shape(font, buf, {"kern": True, "liga": True})
    order = tt.getGlyphOrder()
    gs = tt.getGlyphSet(location={"wdth": wdth, "wght": wght})
    x = 0
    out = []
    for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
        out.append((order[info.codepoint], x + pos.x_offset))
        x += pos.x_advance + tracking
    return out, x - tracking, gs


def glyph_path(gs, name, dx, scale, baseline_y, x0):
    pen = SVGPathPen(gs)
    # coordonnées police (y vers le haut) -> SVG (y vers le bas)
    tpen = TransformPen(pen, (scale, 0, 0, -scale, x0 + dx * scale, baseline_y))
    gs[name].draw(tpen)
    return pen.getCommands()


def glyph_bounds(gs, name):
    bp = BoundsPen(gs)
    gs[name].draw(bp)
    return bp.bounds  # xMin, yMin, xMax, yMax (unités police)


GS_MARK = tt.getGlyphSet(location={"wdth": 125, "wght": 800})


def mark(x, y, size, fond, tige, chevron):
    """Le symbole : un « î » — la tige de la lettre i d'Archivo et un chevron
    (l'accent circonflexe d'Idjô, qui se lit aussi comme une flèche vers le haut)."""
    s = size / 120.0
    r = 24 * s
    bx0, by0, bx1, by1 = glyph_bounds(GS_MARK, "dotlessi")
    stem_h = 56 * s                       # hauteur de la tige dans le carré
    sc = stem_h / (by1 - by0)
    stem_w = (bx1 - bx0) * sc
    baseline = y + 101 * s                 # bas de la tige
    gx = x + size / 2 - stem_w / 2 - bx0 * sc
    pen = SVGPathPen(GS_MARK)
    GS_MARK["dotlessi"].draw(TransformPen(pen, (sc, 0, 0, -sc, gx, baseline + by0 * sc)))
    top = baseline - stem_h
    cx = x + size / 2
    half = stem_w * 1.02
    hgt = half * 0.62
    stroke = stem_w * 0.56
    yb = top - 9 * s - stroke / 2
    return (
        f'<rect x="{x:.2f}" y="{y:.2f}" width="{size:.2f}" height="{size:.2f}" rx="{r:.2f}" fill="{fond}"/>'
        f'<path d="{pen.getCommands()}" fill="{tige}"/>'
        f'<path d="M{cx - half:.2f} {yb:.2f} L{cx:.2f} {yb - hgt:.2f} L{cx + half:.2f} {yb:.2f}" '
        f'fill="none" stroke="{chevron}" stroke-width="{stroke:.2f}" stroke-linecap="square" stroke-linejoin="miter"/>'
    )


def wordmark(x0, baseline, cap_px, couleur_texte, couleur_accent):
    """« Idjô » large et gras + « Académie » en largeur normale. Retourne (svg, largeur)."""
    cap_units = tt["OS/2"].sCapHeight or 700
    scale = cap_px / cap_units

    parts = []
    # Idjo (sans l'accent : on dessine notre propre chevron)
    g1, w1, gs1 = shape("Idjo", 125, 800, tracking=-10)
    d = "".join(glyph_path(gs1, n, dx, scale, baseline, x0) for n, dx in g1)
    parts.append(f'<path d="{d}" fill="{couleur_texte}"/>')

    # chevron au-dessus du « o »
    o_name, o_dx = g1[-1]
    bx0, by0, bx1, by1 = glyph_bounds(gs1, o_name)
    cx = x0 + (o_dx + (bx0 + bx1) / 2) * scale
    top_o = baseline - by1 * scale
    half = (bx1 - bx0) * scale * 0.34
    h = half * 0.78
    gap = cap_px * 0.10
    stroke = cap_px * 0.15
    yb = top_o - gap - stroke / 2
    parts.append(
        f'<path d="M{cx - half:.2f} {yb:.2f} L{cx:.2f} {yb - h:.2f} L{cx + half:.2f} {yb:.2f}" fill="none" '
        f'stroke="{couleur_accent}" stroke-width="{stroke:.2f}" stroke-linecap="square" stroke-linejoin="miter"/>'
    )

    # Académie
    space = cap_px * 0.42
    x2 = x0 + w1 * scale + space
    g2, w2, gs2 = shape("Académie", 100, 500, tracking=0)
    d2 = "".join(glyph_path(gs2, n, dx, scale, baseline, x2) for n, dx in g2)
    parts.append(f'<path d="{d2}" fill="{couleur_texte}"/>')

    width = (x2 - x0) + w2 * scale
    return "".join(parts), width


def svg_doc(w, h, body, title):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:.0f} {h:.0f}" width="{w:.0f}" height="{h:.0f}" '
        f'role="img" aria-label="{title}"><title>{title}</title>{body}</svg>\n'
    )


def lockup(fond_mark, tige, chevron_mark, texte, accent, nom):
    H = 120
    cap = 52
    pad = 0
    m = mark(pad, 0, H, fond_mark, tige, chevron_mark)
    x_text = pad + H + 30
    baseline = H / 2 + cap / 2 + 2
    wm, wmw = wordmark(x_text, baseline, cap, texte, accent)
    W = x_text + wmw + 4
    with open(f"{OUT}/{nom}", "w") as f:
        f.write(svg_doc(W, H, m + wm, "Idjô Académie"))
    return W, H


# 1) logo principal (fond clair)
lockup(VIOLET, BLANC, BLANC, NOIR, VIOLET, "logo.svg")
# 2) logo pour fond noir
lockup(VIOLET, BLANC, BLANC, BLANC, ROSE, "logo-blanc.svg")
# 3) symbole seul
with open(f"{OUT}/logo-symbole.svg", "w") as f:
    f.write(svg_doc(120, 120, mark(0, 0, 120, VIOLET, BLANC, BLANC), "Idjô Académie"))
with open(f"{OUT}/favicon.svg", "w") as f:
    f.write(svg_doc(120, 120, mark(0, 0, 120, VIOLET, BLANC, BLANC), "Idjô Académie"))

# 4) image de partage (1200x630) : fond prune, logo blanc, accroche
W, H = 1200, 630
body = f'<rect width="{W}" height="{H}" fill="{PRUNE}"/>'
body += mark(96, 150, 150, VIOLET, BLANC, BLANC)
wm, wmw = wordmark(96 + 150 + 38, 150 + 75 + 34, 66, BLANC, ROSE)
body += wm
g, w, gs = shape("Apprendre à gagner de l'argent", 112, 700)
g2, w2, gs2 = shape("grâce à l'IA.", 112, 700)
sc = min(52 / (tt["OS/2"].sCapHeight or 700), 1008 / max(w, w2))
body += f'<path d="{"".join(glyph_path(gs, n, dx, sc, 430, 96) for n, dx in g)}" fill="{BLANC}"/>'
body += f'<path d="{"".join(glyph_path(gs2, n, dx, sc, 500, 96) for n, dx in g2)}" fill="{ROSE}"/>'
body += f'<rect x="96" y="548" width="1008" height="10" fill="#4A0F46"/>'
body += f'<rect x="96" y="548" width="250" height="10" fill="{VIOLET}"/>'
with open(f"{OUT}/og-image.svg", "w") as f:
    f.write(svg_doc(W, H, body, "Idjô Académie"))
print("ok")


# 5) logo avec slogan (texte du slogan calé sur la largeur du nom)
def lockup_slogan(fond_mark, tige, chevron_mark, texte, accent, couleur_slogan, nom):
    H = 120
    cap = 46
    m = mark(0, 0, H, fond_mark, tige, chevron_mark)
    x_text = H + 30
    base1 = 66
    wm, wmw = wordmark(x_text, base1, cap, texte, accent)
    gS, wS, gsS = shape("Apprendre à gagner de l'argent grâce à l'IA", 100, 500)
    scS = wmw / wS
    base2 = base1 + 43
    d = "".join(glyph_path(gsS, n, dx, scS, base2, x_text) for n, dx in gS)
    body = m + wm + f'<path d="{d}" fill="{couleur_slogan}"/>'
    W = x_text + wmw + 4
    with open(f"{OUT}/{nom}", "w") as f:
        f.write(svg_doc(W, H, body, "Idjô Académie : apprendre à gagner de l'argent grâce à l'IA"))


lockup_slogan(VIOLET, BLANC, BLANC, NOIR, VIOLET, "#3A0238", "logo-slogan.svg")
lockup_slogan(VIOLET, BLANC, BLANC, BLANC, ROSE, "#FDE8FC", "logo-slogan-blanc.svg")
print("slogan ok")
