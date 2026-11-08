"""Module 3 : créer des sites. Une tablette et un téléphone qui affichent le vrai site Idjô Académie."""
from commun import page, ecrire, tablette, tel, CSS_APPAREILS

css = CSS_APPAREILS + """
.vue img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.adresse { position: absolute; left: 0; right: 0; top: 0; height: 46px; background: #F3F1F5; display: flex; align-items: center; gap: 12px; padding: 0 16px; z-index: 2; }
.adresse span { flex: 1; background: #fff; border-radius: 999px; height: 30px; display: flex; align-items: center; padding: 0 16px; font-size: 16px; color: #3B2B39; }
.adresse i { width: 12px; height: 12px; border-radius: 50%; display: block; }
.capture { position: absolute; left: 0; right: 0; top: 46px; bottom: 0; }
"""

vue_tablette = """
<div class="adresse"><i style="background:#FF5F57"></i><i style="background:#FEBC2E"></i><i style="background:#28C840"></i>
  <span>🔒&nbsp; academie.idjôcommunication.com</span></div>
<div class="capture"><img src="site-ordi.png" alt=""></div>"""

vue_tel = '<img src="site-tel.png" alt="">'

coche = '<svg width="28" height="28" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="#18A957" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'
mobile = '<svg width="28" height="28" viewBox="0 0 24 24"><rect x="6" y="2.5" width="12" height="19" rx="3" fill="none" stroke="#F08C00" stroke-width="2.4"/><path d="M10.5 18h3" stroke="#F08C00" stroke-width="2.4" stroke-linecap="round"/></svg>'
puces = (f'<div class="puce" style="left:70px;top:90px"><i style="background:#E3F6EC">{coche}</i>Site publié</div>'
         f'<div class="puce" style="left:1150px;top:860px"><i style="background:#FFF1DB">{mobile}</i>Adapté au téléphone</div>')

html = page("radial-gradient(1200px 760px at 45% 40%, #FFF9F0 0%, #FDEFD9 65%, #F8E3C4 100%)",
            tablette(140, 170, 1060, 700, vue_tablette, rotation=-3) + tel(1170, 150, 330, 660, vue_tel, rotation=5) + puces, css)
ecrire("module-3", html)
