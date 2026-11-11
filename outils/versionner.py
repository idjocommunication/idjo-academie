"""Ajoute ?v=<empreinte> aux CSS et JS des pages HTML statiques.

À relancer avant chaque mise en ligne qui touche assets/css ou assets/js :
    python3 outils/versionner.py
Le navigateur reprend alors tout de suite la nouvelle version au lieu de garder l'ancienne.
(Les pages PHP le font toutes seules avec actif().)
"""
import hashlib
import pathlib
import re

RACINE = pathlib.Path(__file__).resolve().parent.parent
PAGES = ["index.html", "vente.html", "formation.html", "confidentialite.html", "404.html"]
MOTIF = re.compile(r'((?:href|src)=")(/?assets/(?:css|js)/[\w.-]+\.(?:css|js))(?:\?v=[\w]+)?(")')


def empreinte(chemin: str) -> str:
    return hashlib.sha1((RACINE / chemin.lstrip("/")).read_bytes()).hexdigest()[:10]


for nom in PAGES:
    page = RACINE / nom
    texte = page.read_text(encoding="utf-8")
    nouveau = MOTIF.sub(lambda m: f"{m.group(1)}{m.group(2)}?v={empreinte(m.group(2))}{m.group(3)}", texte)
    if nouveau != texte:
        page.write_text(nouveau, encoding="utf-8")
        print("mis à jour :", nom)
