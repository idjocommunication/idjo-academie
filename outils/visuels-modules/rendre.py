"""Rend les pages HTML des modules en PNG 1600x1000, puis en WebP (grand et carte)."""
import os
import sys
from PIL import Image
from playwright.sync_api import sync_playwright

ICI = os.path.dirname(os.path.abspath(__file__))
noms = sys.argv[1:] or ["module-1", "module-2", "module-3", "module-4"]
with sync_playwright() as p:
    nav = p.chromium.launch(executable_path="/usr/bin/google-chrome")
    pg = nav.new_page(viewport={"width": 1600, "height": 1000})
    for nom in noms:
        pg.goto("file://" + os.path.join(ICI, nom + ".html"))
        pg.wait_for_timeout(400)
        png = os.path.join(ICI, nom + ".png")
        pg.screenshot(path=png)
        im = Image.open(png).convert("RGB")
        im.resize((1376, 860), Image.LANCZOS).save(os.path.join(ICI, nom + "-grand.webp"), quality=86, method=6)
        im.resize((960, 600), Image.LANCZOS).save(os.path.join(ICI, nom + ".webp"), quality=86, method=6)
        print(nom, "ok")
    nav.close()
