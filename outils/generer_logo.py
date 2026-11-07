"""Génère tous les fichiers du logo Idjô Académie dans assets/img/.

Couleurs choisies par Alan : idjô noir, chapeau et ronds néon, « Académie » en Outfit moyenne, taille moyenne.
Icône : carré néon, idjô blanc.

Usage (depuis la racine du dépôt) :
  python3 outils/tracer_logo_idjo.py outils/logo-idjo-original.png outils/logo-idjo-traces.json   # seulement si le logo change
  python3 outils/generer_logo.py <Outfit[wght].ttf>
Besoin de rsvg-convert pour les PNG.
"""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from build_logo_idjo import Logo  # noqa: E402

OUTFIT = sys.argv[1]
ICI = os.path.dirname(os.path.abspath(__file__))
IMG = os.path.join(ICI, "..", "assets", "img")
L = Logo(os.path.join(ICI, "logo-idjo-traces.json"), OUTFIT)

ENCRE, NEON, ROSE, BLANC, NUIT = "#1A0719", "#B606AE", "#FB7AF5", "#FFFFFF", "#130112"
CLAIR = dict(lettres=ENCRE, points=ENCRE, accent=NEON, ronds=NEON, mot=ENCRE)
SOMBRE = dict(lettres=BLANC, points=BLANC, accent=ROSE, ronds=ROSE, mot=BLANC)
ICONE = dict(lettres=BLANC, points=BLANC, accent=BLANC, ronds=BLANC)
POIDS, TAILLE = 500, 0.78
SLOGAN = "Apprendre à gagner de l'argent grâce à l'IA"


def ecrire(nom, contenu):
    with open(os.path.join(IMG, nom), "w") as f:
        f.write(contenu)


def png(svg, sortie, largeur=None, hauteur=None, fond=None):
    cmd = ["rsvg-convert", os.path.join(IMG, svg), "-o", os.path.join(IMG, sortie)]
    if largeur:
        cmd += ["-w", str(largeur)]
    if hauteur:
        cmd += ["-h", str(hauteur)]
    if fond:
        cmd += ["-b", fond]
    subprocess.run(cmd, check=True)


def avec_slogan(couleurs, couleur_slogan):
    """Logo complet avec le slogan dessous, aligné à gauche, même largeur."""
    svg, _ = L.svg(couleurs, wght=POIDS, taille=TAILLE, marge=0)
    debut = svg.index('viewBox="') + 9
    vx, vy, vw, vh = map(float, svg[debut:svg.index('"', debut)].split())
    corps = svg[svg.index("</title>") + 8:svg.rindex("</svg>")]
    _, largeur_unite = L._mot(SLOGAN, 400, 1, 0, 0)
    echelle = vw / largeur_unite
    base = vy + vh + 0.42 * vh + L.cap * echelle
    d, _ = L._mot(SLOGAN, 400, echelle, vx, base)
    m = 0.08 * vh
    h_total = base - vy + 0.25 * L.cap * echelle
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vx - m:.0f} {vy - m:.0f} {vw + 2 * m:.0f} {h_total + 2 * m:.0f}" '
            f'role="img" aria-label="Idjô Académie, {SLOGAN}"><title>Idjô Académie</title>{corps}'
            f'<path fill="{couleur_slogan}" d="{d}"/></svg>')


# Logos principaux (sans marge : prêts pour la barre du site)
clair, ratio = L.svg(CLAIR, wght=POIDS, taille=TAILLE, marge=0)
sombre, _ = L.svg(SOMBRE, wght=POIDS, taille=TAILLE, marge=0)
ecrire("logo.svg", clair)
ecrire("logo-blanc.svg", sombre)
ecrire("logo-slogan.svg", avec_slogan(CLAIR, "#3A0238"))
ecrire("logo-slogan-blanc.svg", avec_slogan(SOMBRE, "#FDE8FC"))

# Icônes
ecrire("logo-symbole.svg", L.icone(ICONE, NEON, rayon=0.22))
ecrire("favicon.svg", L.icone(ICONE, NEON, rayon=0.22))
ecrire("_apple.svg", L.icone(ICONE, NEON, rayon=0))  # iOS arrondit lui-même les coins

# Image de partage 1200 x 630
_, l_titre = L._mot("Apprendre à gagner de l'argent", 600, 1, 0, 0)
e = 1040 / l_titre
d1, _ = L._mot("Apprendre à gagner de l'argent", 600, e, 80, 330)
d2, _ = L._mot("grâce à l'IA.", 600, e, 80, 330 + 1.15 * L.cap * e + 30)
d3, _ = L._mot("Formation vidéo · 4 modules · 26 leçons · Afrique francophone", 400, 24 / L.cap, 80, 556)
corps_logo = sombre[sombre.index("</title>") + 8:sombre.rindex("</svg>")]
debut = sombre.index('viewBox="') + 9
vx, vy, vw, vh = map(float, sombre[debut:sombre.index('"', debut)].split())
k = 78 / vh
og = (f'<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">'
      f'<rect width="1200" height="630" fill="{NUIT}"/>'
      f'<g transform="translate(80 64) scale({k:.5f}) translate({-vx:.1f} {-vy:.1f})">{corps_logo}</g>'
      f'<path fill="{BLANC}" d="{d1}"/><path fill="{ROSE}" d="{d2}"/>'
      f'<path fill="{BLANC}" fill-opacity=".7" d="{d3}"/>'
      f'<rect x="80" y="596" width="1040" height="6" rx="3" fill="{NEON}"/></svg>')
ecrire("_og.svg", og)

png("logo.svg", "logo.png", largeur=1000)
png("logo-blanc.svg", "logo-blanc.png", largeur=1000)
png("logo-slogan.svg", "logo-slogan.png", largeur=1200)
png("logo-slogan-blanc.svg", "logo-slogan-blanc.png", largeur=1200)
png("logo-symbole.svg", "logo-symbole.png", largeur=512, hauteur=512)
png("favicon.svg", "favicon-32.png", largeur=32, hauteur=32)
png("_apple.svg", "apple-touch-icon.png", largeur=180, hauteur=180, fond=NEON)
png("_og.svg", "og-image.png", largeur=1200, hauteur=630)
for tmp in ("_apple.svg", "_og.svg"):
    os.remove(os.path.join(IMG, tmp))
print("ratio du logo (largeur / hauteur) :", round(ratio, 4))
