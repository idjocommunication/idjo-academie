"""Module 2 : les bases de l'IA. Un ordinateur avec une discussion avec un assistant IA,
les 4 usages (texte, images, vidéos, voix) et une affiche créée avec l'IA sur un téléphone.
Interface générique, sans logo de marque (à remplacer par les captures d'Alan s'il le souhaite)."""
from commun import page, ecrire, ordi, tel, CSS_APPAREILS

css = CSS_APPAREILS + """
.app { display: grid; grid-template-columns: 210px 1fr; height: 100%; font-size: 17px; }
.barre { background: #F4F2F7; padding: 18px 14px; display: grid; gap: 10px; align-content: start; }
.nouveau { background: #1A0719; color: #fff; border-radius: 12px; padding: 12px 14px; font-weight: 600; font-size: 16px; }
.histo { height: 14px; border-radius: 7px; background: #E2DEE8; }
.fil { position: relative; padding: 24px 30px; display: grid; gap: 22px; align-content: start; font-size: 20px; }
.entete { display: flex; align-items: center; gap: 10px; font-family: Outfit; font-weight: 700; font-size: 20px; padding-bottom: 14px; border-bottom: 1px solid #EEE9F1; }
.etoile { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #6C5CE7, #B606AE); display: grid; place-items: center; }
.moi { justify-self: end; max-width: 78%; background: #1A0719; color: #fff; border-radius: 18px 18px 4px 18px; padding: 14px 18px; line-height: 1.45; }
.ia { display: grid; grid-template-columns: 34px 1fr; gap: 12px; line-height: 1.5; color: #2B2230; }
.ia ol { padding-left: 22px; display: grid; gap: 6px; margin-top: 6px; }
.saisie { position: absolute; left: 28px; right: 28px; bottom: 20px; display: flex; align-items: center; justify-content: space-between; border: 2px solid #E6E0EC; border-radius: 16px; padding: 12px 12px 12px 18px; color: #9A90A3; }
.envoi { width: 38px; height: 38px; border-radius: 10px; background: #B606AE; display: grid; place-items: center; }
.affiche { height: 100%; background: linear-gradient(160deg, #FFB347 0%, #FF5E7E 55%, #B606AE 100%); display: grid; align-content: space-between; padding: 96px 22px 26px; color: #fff; text-align: center; }
.affiche b { font-family: Outfit; font-weight: 800; font-size: 46px; line-height: 1; display: block; }
.affiche small { font-size: 17px; font-weight: 600; opacity: .95; }
.badge-ia { position: absolute; top: 52px; left: 50%; transform: translateX(-50%); white-space: nowrap; background: rgba(255,255,255,.92); color: #1A0719; font-size: 14px; font-weight: 700; padding: 6px 10px; border-radius: 999px; }
"""

etoile = '<svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2.5C12.7 8 14.5 10 21 11.5 14.5 13 12.7 15 12 21.5 11.3 15 9.5 13 3 11.5 9.5 10 11.3 8 12 2.5Z" fill="#fff"/></svg>'

vue_ordi = f"""
<div class="app">
  <div class="barre">
    <div class="nouveau">+ Nouvelle discussion</div>
    <div class="histo" style="width:90%"></div><div class="histo" style="width:70%"></div><div class="histo" style="width:82%"></div><div class="histo" style="width:60%"></div>
  </div>
  <div class="fil">
    <div class="entete"><span class="etoile">{etoile}</span>Assistant IA</div>
    <div class="moi">Donne-moi 3 idées de posts Facebook pour un salon de coiffure à Yopougon.</div>
    <div class="ia"><span class="etoile">{etoile}</span><div>Voici 3 idées qui marchent bien :
      <ol><li>Avant / après de la coiffure de la semaine</li><li>Le conseil du jeudi : faire durer ses tresses</li><li>Mercredi étudiantes : -20 % sur présentation de la carte</li></ol></div></div>
    <div class="saisie">Écris ta demande…<span class="envoi"><svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div>
  </div>
</div>"""

vue_tel = """
<div class="affiche">
  <small>SALON BELLE TRESSE · YOPOUGON</small>
  <div><b>-20 %</b><small style="display:block;margin-top:10px;font-size:22px">tous les mercredis</small></div>
  <svg viewBox="0 0 120 120" width="120" height="120" style="justify-self:center"><circle cx="38" cy="88" r="18" fill="none" stroke="#fff" stroke-width="8"/><circle cx="82" cy="88" r="18" fill="none" stroke="#fff" stroke-width="8"/><path d="M48 74 L92 14 M72 74 L28 14" stroke="#fff" stroke-width="8" stroke-linecap="round"/></svg>
  <small>Réserve sur WhatsApp</small>
</div>
<span class="badge-ia">Créée avec l'IA</span>"""

ico = {
    "Texte": ('#EDE7FF', '<svg width="28" height="28" viewBox="0 0 24 24"><path d="M5 6h14M12 6v13" stroke="#6C5CE7" stroke-width="2.6" stroke-linecap="round"/></svg>'),
    "Images": ('#FFE8F0', '<svg width="28" height="28" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3" fill="none" stroke="#E0457B" stroke-width="2.4"/><circle cx="9" cy="10" r="2" fill="#E0457B"/><path d="M4 18l5-5 4 4 3-3 4 4" fill="none" stroke="#E0457B" stroke-width="2.4" stroke-linejoin="round"/></svg>'),
    "Vidéos": ('#FFF1DB', '<svg width="28" height="28" viewBox="0 0 24 24"><path d="M8 5.5L19 12 8 18.5Z" fill="#F08C00"/></svg>'),
    "Voix": ('#E3F6EC', '<svg width="28" height="28" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3" fill="#18A957"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21" fill="none" stroke="#18A957" stroke-width="2.4" stroke-linecap="round"/></svg>'),
}
puces = ""
for i, (nom, (fond, svg)) in enumerate(ico.items()):
    puces += f'<div class="puce" style="left:{1265 + (i % 2) * 18}px;top:{80 + i * 104}px"><i style="background:{fond}">{svg}</i>{nom}</div>'

html = page("radial-gradient(1200px 760px at 40% 40%, #F5F6FF 0%, #E8EAFF 65%, #DCDFFB 100%)",
            ordi(110, 120, 1080, 680, vue_ordi) + puces + tel(1170, 545, 270, 430, vue_tel, rotation=6), css)
ecrire("module-2", html)
