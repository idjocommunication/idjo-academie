"""Module 4 : la publicité. Un ordinateur avec la création d'une campagne (objectif, audience, budget)
et un téléphone avec la publicité sponsorisée. Interface générique, sans logo de réseau social."""
from commun import page, ecrire, ordi, tel, CSS_APPAREILS

css = CSS_APPAREILS + """
.gp { display: grid; grid-template-columns: 74px 1fr 330px; height: 100%; font-size: 18px; }
.rail { background: #1A2B4A; display: grid; gap: 18px; align-content: start; justify-items: center; padding-top: 22px; }
.rail i { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,.14); display: block; }
.rail i:first-child { background: #2F7DF6; }
.form { padding: 24px 28px; display: grid; gap: 14px; align-content: start; background: #F7F8FB; }
.form h3 { font-family: Outfit; font-size: 26px; font-weight: 700; color: #1A2B4A; }
.etape { background: #fff; border: 2px solid #E4E8F0; border-radius: 16px; padding: 14px 18px; display: grid; grid-template-columns: 40px 1fr auto; gap: 14px; align-items: center; }
.etape b { display: block; font-size: 14px; color: #6B7790; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.etape span { font-size: 19px; color: #1A2B4A; font-weight: 600; }
.etape .ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; }
.ok { width: 28px; height: 28px; border-radius: 50%; background: #18A957; display: grid; place-items: center; }
.lancer { justify-self: end; background: #2F7DF6; color: #fff; font-weight: 700; border-radius: 12px; padding: 12px 22px; font-size: 18px; }
.cote { padding: 24px 22px; display: grid; gap: 14px; align-content: start; border-left: 2px solid #EEF1F6; }
.cote h4 { font-size: 15px; color: #6B7790; text-transform: uppercase; letter-spacing: .05em; }
.barres { display: flex; align-items: end; gap: 12px; height: 150px; padding: 12px; background: #F7F8FB; border-radius: 14px; }
.barres span { flex: 1; border-radius: 6px 6px 2px 2px; background: #2F7DF6; }
.ligne { display: flex; justify-content: space-between; font-size: 17px; color: #3C4860; padding: 8px 0; border-bottom: 1px solid #EEF1F6; }
.ligne b { color: #1A2B4A; }
.flux { height: 100%; background: #fff; display: grid; grid-template-rows: auto auto 1fr auto; font-size: 15px; }
.flux__haut { display: flex; align-items: center; gap: 10px; padding: 56px 14px 10px; }
.flux__haut i { width: 38px; height: 38px; border-radius: 50%; background: #FF8A3D; display: block; }
.flux__haut b { display: block; font-size: 15px; }
.flux__haut small { color: #6B7790; font-size: 13px; }
.flux p { padding: 0 14px 10px; line-height: 1.4; color: #2B2230; }
.visuel { background: linear-gradient(160deg, #FFE0A6 0%, #FFB36B 100%); display: grid; place-items: center; position: relative; }
.prix { position: absolute; right: 12px; bottom: 12px; background: #1A0719; color: #fff; font-family: Outfit; font-weight: 800; font-size: 22px; padding: 6px 12px; border-radius: 10px; }
.cta { margin: 12px 14px 18px; background: #18A957; color: #fff; text-align: center; font-weight: 700; border-radius: 10px; padding: 12px; font-size: 16px; }
"""


def ic(fond, svg):
    return f'<span class="ic" style="background:{fond}">{svg}</span>'


ok = '<span class="ok"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>'
cible = '<svg width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="#2F7DF6" stroke-width="2.4"/><circle cx="12" cy="12" r="4" fill="#2F7DF6"/></svg>'
gens = '<svg width="24" height="24" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" fill="#B606AE"/><path d="M2.5 20c.8-4 3.4-6 6.5-6s5.7 2 6.5 6" fill="#B606AE"/><circle cx="17" cy="9" r="2.6" fill="#E59AE1"/><path d="M15 14.5c3 0 5.4 1.8 6 5.5h-4" fill="#E59AE1"/></svg>'
argent = '<svg width="24" height="24" viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="3" fill="none" stroke="#18A957" stroke-width="2.4"/><circle cx="12" cy="12" r="2.6" fill="#18A957"/></svg>'
temps = '<svg width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="#F08C00" stroke-width="2.4"/><path d="M12 7v5l3 2" fill="none" stroke="#F08C00" stroke-width="2.4" stroke-linecap="round"/></svg>'

etapes = "".join(
    f'<div class="etape">{ic(f, s)}<div><b>{t}</b><span>{v}</span></div>{ok}</div>'
    for f, s, t, v in [
        ("#E8F0FF", cible, "Objectif", "Recevoir des messages WhatsApp"),
        ("#FBE7FA", gens, "Audience", "Abidjan · 18 à 45 ans"),
        ("#E3F6EC", argent, "Budget", "5 000 FCFA par jour"),
        ("#FFF1DB", temps, "Durée", "7 jours"),
    ])

barres = "".join(f'<span style="height:{h}%"></span>' for h in (28, 40, 36, 55, 62, 78, 92))
vue_ordi = f"""
<div class="gp">
  <div class="rail"><i></i><i></i><i></i><i></i><i></i></div>
  <div class="form"><h3>Nouvelle campagne</h3>{etapes}<div class="lancer">Lancer la campagne</div></div>
  <div class="cote"><h4>Résultats de la semaine</h4><div class="barres">{barres}</div>
    <div class="ligne">Personnes touchées <b>en hausse</b></div>
    <div class="ligne">Messages reçus <b>chaque jour</b></div>
    <div class="ligne">Coût par message <b>suivi</b></div>
  </div>
</div>"""

plat = """<svg viewBox="0 0 220 160" width="220" height="160">
  <ellipse cx="110" cy="110" rx="100" ry="40" fill="#fff" stroke="#1A0719" stroke-width="5"/>
  <ellipse cx="110" cy="102" rx="78" ry="28" fill="#FFF3D6"/>
  <g fill="#FFF8E6" stroke="#E8C77A" stroke-width="2"><circle cx="80" cy="96" r="7"/><circle cx="95" cy="104" r="7"/><circle cx="72" cy="108" r="7"/><circle cx="90" cy="88" r="6"/><circle cx="104" cy="94" r="6"/></g>
  <path d="M118 82 C140 64 178 70 190 92 C176 110 140 114 118 100 L104 112 L106 84 Z" fill="#C96A2B" stroke="#1A0719" stroke-width="5" stroke-linejoin="round"/>
  <circle cx="172" cy="88" r="4" fill="#1A0719"/>
  <path d="M140 84 L150 100 M156 82 L164 98" stroke="#8A3F12" stroke-width="4" stroke-linecap="round"/>
  <g stroke="#2E9E4F" stroke-width="5" stroke-linecap="round"><path d="M60 118 l14 -6"/><path d="M150 120 l12 -8"/></g>
</svg>"""

vue_tel = f"""
<div class="flux">
  <div class="flux__haut"><i></i><div><b>Maquis Chez Tantie</b><small>Sponsorisé</small></div></div>
  <p>Ton attiéké poisson braisé livré à Cocody en 30 min 🔥</p>
  <div class="visuel">{plat}<span class="prix">2 000 FCFA</span></div>
  <div class="cta">Envoyer un message WhatsApp</div>
</div>"""

html = page("radial-gradient(1200px 760px at 45% 40%, #F2FBF6 0%, #E1F4EA 65%, #CFEBDD 100%)",
            ordi(90, 130, 1110, 690, vue_ordi) + tel(1175, 180, 330, 650, vue_tel, rotation=4), css)
ecrire("module-4", html)
