"""Module 1 : le mindset. Un cerveau dessiné, entouré d'une ampoule, d'une cible, d'une courbe qui monte et d'un objectif à 90 jours."""
from commun import page, bosses, ecrire

TRAIT = "#3A0238"
ROSE = "#F7A8CB"
ROSE_FONCE = "#E07AAA"
SILLON = "#C7578F"

cerveau = f"""
<svg viewBox="0 0 760 620" width="820" height="669" style="position:absolute;left:300px;top:175px;overflow:visible">
  <!-- ombre au sol -->
  <ellipse cx="380" cy="590" rx="250" ry="22" fill="#3A0238" opacity=".08"/>
  <!-- tronc -->
  <path d="M430 420 C440 480 445 520 470 565 C478 578 505 576 508 560 C500 520 498 470 505 420 Z" fill="{ROSE_FONCE}" stroke="{TRAIT}" stroke-width="9" stroke-linejoin="round"/>
  <!-- cervelet -->
  <path d="{bosses(540, 430, 120, 72, 9, debut=-20, fin=200, gonfle=1.05)}" fill="{ROSE_FONCE}" stroke="{TRAIT}" stroke-width="9" stroke-linejoin="round"/>
  <path d="M455 425 C500 438 560 440 625 420 M465 455 C510 466 565 466 615 452" fill="none" stroke="{SILLON}" stroke-width="7" stroke-linecap="round"/>
  <!-- cerveau -->
  <path d="{bosses(370, 270, 300, 205, 20, gonfle=1.08)}" fill="{ROSE}" stroke="{TRAIT}" stroke-width="9" stroke-linejoin="round"/>
  <!-- sillons -->
  <g fill="none" stroke="{SILLON}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round">
    <path d="M180 330 C240 300 300 330 360 312 C420 294 470 320 540 300"/>
    <path d="M375 90 C350 130 395 160 365 205 C345 235 380 255 370 290"/>
    <path d="M200 170 C235 150 260 190 300 175"/>
    <path d="M135 255 C170 230 205 265 250 245"/>
    <path d="M450 130 C480 150 520 130 545 165"/>
    <path d="M470 220 C510 200 560 235 610 215"/>
    <path d="M250 395 C290 380 330 405 380 385"/>
    <path d="M560 300 C590 320 620 300 640 275"/>
    <path d="M110 330 C130 360 165 365 175 395"/>
  </g>
  <!-- reflets -->
  <path d="M175 135 C215 100 270 90 310 95" fill="none" stroke="#FFFFFF" stroke-width="12" stroke-linecap="round" opacity=".7"/>
  <circle cx="148" cy="170" r="8" fill="#FFFFFF" opacity=".7"/>
</svg>"""

ampoule = f"""
<svg viewBox="0 0 200 260" width="190" height="247" style="position:absolute;left:1150px;top:95px;transform:rotate(12deg)">
  <path d="M100 20 C45 20 18 62 22 105 C26 145 60 160 64 195 H136 C140 160 174 145 178 105 C182 62 155 20 100 20 Z" fill="#FFD84D" stroke="{TRAIT}" stroke-width="8" stroke-linejoin="round"/>
  <path d="M80 140 C80 115 120 115 120 140 M100 140 V190" fill="none" stroke="#E0A800" stroke-width="7" stroke-linecap="round"/>
  <rect x="66" y="195" width="68" height="22" rx="6" fill="#B9B2C8" stroke="{TRAIT}" stroke-width="8"/>
  <rect x="74" y="217" width="52" height="20" rx="6" fill="#B9B2C8" stroke="{TRAIT}" stroke-width="8"/>
  <path d="M55 70 C62 52 78 42 95 40" fill="none" stroke="#FFFFFF" stroke-width="9" stroke-linecap="round" opacity=".85"/>
</svg>
<svg viewBox="0 0 120 120" width="120" height="120" style="position:absolute;left:1300px;top:70px">
  <g stroke="#F5A300" stroke-width="9" stroke-linecap="round">
    <path d="M20 95 L45 75"/><path d="M40 30 L58 52"/><path d="M95 20 L85 48"/>
  </g>
</svg>"""

cible = f"""
<svg viewBox="0 0 240 240" width="230" height="230" style="position:absolute;left:105px;top:620px">
  <circle cx="110" cy="130" r="100" fill="#FFFFFF" stroke="{TRAIT}" stroke-width="8"/>
  <circle cx="110" cy="130" r="68" fill="#B606AE" stroke="{TRAIT}" stroke-width="8"/>
  <circle cx="110" cy="130" r="36" fill="#FFFFFF" stroke="{TRAIT}" stroke-width="8"/>
  <circle cx="110" cy="130" r="12" fill="#B606AE"/>
  <path d="M112 128 L205 35" stroke="{TRAIT}" stroke-width="9" stroke-linecap="round"/>
  <path d="M190 22 L222 18 L218 50 L205 35 Z" fill="#FFD84D" stroke="{TRAIT}" stroke-width="7" stroke-linejoin="round"/>
</svg>"""

courbe = f"""
<div style="position:absolute;left:1140px;top:560px;width:330px;height:250px;background:#fff;border:8px solid {TRAIT};border-radius:28px;box-shadow:10px 12px 0 rgba(58,2,56,.12)">
  <svg viewBox="0 0 300 220" width="314" height="234" style="position:absolute;left:0;top:0">
    <g fill="#F3D6E6"><rect x="40" y="150" width="34" height="40" rx="6"/><rect x="95" y="120" width="34" height="70" rx="6"/><rect x="150" y="95" width="34" height="95" rx="6"/><rect x="205" y="55" width="34" height="135" rx="6"/></g>
    <path d="M35 160 L105 120 L160 132 L250 50" fill="none" stroke="#18A957" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M222 44 L258 40 L254 76" fill="none" stroke="#18A957" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</div>"""

calendrier = f"""
<div style="position:absolute;left:150px;top:150px;width:200px;height:210px;background:#fff;border:8px solid {TRAIT};border-radius:26px;overflow:hidden;transform:rotate(-8deg);box-shadow:10px 12px 0 rgba(58,2,56,.12)">
  <div style="height:58px;background:#B606AE;border-bottom:8px solid {TRAIT}"></div>
  <div class="titre" style="text-align:center;font-weight:800;font-size:76px;line-height:1;margin-top:14px;color:{TRAIT}">90</div>
  <div class="titre" style="text-align:center;font-weight:700;font-size:28px;color:#B606AE">jours</div>
</div>
<div style="position:absolute;left:186px;top:128px;width:16px;height:44px;background:{TRAIT};border-radius:8px;transform:rotate(-8deg)"></div>
<div style="position:absolute;left:290px;top:115px;width:16px;height:44px;background:{TRAIT};border-radius:8px;transform:rotate(-8deg)"></div>"""

pieces = f"""
<svg viewBox="0 0 200 160" width="200" height="160" style="position:absolute;left:1360px;top:385px">
  <ellipse cx="80" cy="120" rx="62" ry="22" fill="#F5B700" stroke="{TRAIT}" stroke-width="7"/>
  <path d="M18 100 V120 M142 100 V120" stroke="{TRAIT}" stroke-width="7"/>
  <ellipse cx="80" cy="100" rx="62" ry="22" fill="#FFD84D" stroke="{TRAIT}" stroke-width="7"/>
  <path d="M18 78 V100 M142 78 V100" stroke="{TRAIT}" stroke-width="7"/>
  <ellipse cx="80" cy="78" rx="62" ry="22" fill="#FFD84D" stroke="{TRAIT}" stroke-width="7"/>
  <text x="80" y="87" text-anchor="middle" font-family="Outfit" font-weight="800" font-size="24" fill="{TRAIT}">FCFA</text>
</svg>"""

points = """
<svg width="1600" height="1000" style="position:absolute;inset:0">
  <g fill="#B606AE" opacity=".18"><circle cx="560" cy="120" r="10"/><circle cx="1010" cy="890" r="14"/><circle cx="1500" cy="300" r="9"/><circle cx="80" cy="480" r="12"/></g>
  <g fill="none" stroke="#B606AE" stroke-width="6" stroke-linecap="round" opacity=".25">
    <path d="M960 130 l18 18 m0 -18 l-18 18"/><path d="M430 880 l18 18 m0 -18 l-18 18"/><path d="M1490 860 l18 18 m0 -18 l-18 18"/>
  </g>
</svg>"""

html = page("radial-gradient(1100px 700px at 45% 45%, #FFF6FA 0%, #FDE6F1 60%, #F9D7E8 100%)",
            points + calendrier + cerveau + ampoule + cible + courbe + pieces)
ecrire("module-1", html)
