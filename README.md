# Idjô Académie

Site vitrine de la formation vidéo **« Apprendre à gagner de l'argent grâce à l'IA »** : 4 modules, 26 leçons.

Site statique (HTML, CSS, JavaScript), sans installation ni compilation. Il se publie tel quel sur GitHub Pages, Hostinger ou n'importe quel hébergeur.

## Pages

| Fichier | Contenu |
| --- | --- |
| `index.html` | Accueil : haut de page sombre avec vidéo, les 4 modules, assistant de questions avec aperçu des leçons (parallaxe), onglets par module, étapes, formules, formateur, questions fréquentes |
| `formation.html` | Page de la formation : ce que tu vas apprendre, contenu en accordéon, carte d'inscription, prérequis, description |

## Ce que tu peux modifier toi-même

| Pour changer… | Ouvre le fichier |
| --- | --- |
| Le numéro WhatsApp des boutons « S'inscrire » | `assets/js/config.js`, ligne `whatsapp` (format `2250700000000`) |
| Un lien de paiement (Chariow, CinetPay…) | `assets/js/config.js`, ligne `lienPaiement` |
| Les prix et le contenu des 3 formules | `assets/js/config.js`, bloc `formules` |
| Les titres, résumés et durées des leçons, les images des modules | `assets/js/data.js` |
| Les réponses de l'assistant | `assets/js/main.js`, fonction `repondre` |
| La photo du formateur | ajoute `assets/img/formateur.jpg`, puis remplace le « A » dans les blocs `formateur__photo` des deux pages par `<img src="assets/img/formateur.jpg" alt="Alan">` |

Le numéro WhatsApp est réglé sur +225 07 47 93 37 48 : chaque bouton « S'inscrire » ouvre WhatsApp avec un message prêt (formule choisie et prix). Si tu vides ce numéro, les boutons mènent à la section Tarifs.

## Identité visuelle

- Ambiance « haut sombre, puis blanc » : haut de page sombre en pleine largeur avec la vidéo, puis sections blanches ; l'assistant, l'appel final et la formule recommandée restent sombres. La barre du haut passe du verre foncé au blanc quand on descend. Pas de lueur violette sur les fonds blancs. Couleurs reprises de l'affiche « Comming soon ». Mise en page inspirée du modèle « Rocket CTA » de MotionSites.
- Couleurs : néon `#B606AE` (boutons, mots clés), rose néon `#FB7AF5` (mots clés sur fond sombre), rose très pâle `#FFF5FE` (sections teintées), prune `#3A0238`, aubergine `#2D022B`, nuit `#130112` (blocs sombres), texte `#1A0719`, blanc `#FFFFFF`.
- Dans `assets/css/style.css`, la classe `.sombre` passe un bloc en version foncée : tous les textes s'y mettent en clair.
- Polices (licence SIL OFL, fichiers dans `assets/fonts/`) : Outfit pour les titres, Inter pour le texte.
- Images et vidéo générées avec Google Flow, dans `assets/img/flow/` et `assets/video/`. La vidéo du haut de page ne se charge que sur ordinateur ; sur téléphone, une image fixe la remplace.
- Le logo est le « idjô » d'Alan (fichier d'origine : `outils/logo-idjo-original.png`), vectorisé, avec « Académie » à côté en Outfit moyenne. Couleurs : idjô noir `#1A0719`, chapeau et ronds néon `#B606AE` ; sur fond sombre, idjô blanc et touches rose néon `#FB7AF5`. Icône : carré néon, idjô blanc.
- Pour refaire tous les fichiers du logo : `python3 outils/generer_logo.py Outfit[wght].ttf` (et, si le dessin d'idjô change, d'abord `python3 outils/tracer_logo_idjo.py outils/logo-idjo-original.png outils/logo-idjo-traces.json`).
- Logos dans `assets/img/` :
  - `logo.svg` / `logo.png` : version principale, pour fond clair ;
  - `logo-blanc.svg` / `logo-blanc.png` : pour fond sombre ;
  - `logo-slogan.svg` / `logo-slogan-blanc.svg` (et `.png`) : avec le slogan ;
  - `logo-symbole.svg` / `logo-symbole.png` : l'icône carrée (photo de profil, favicon) ;
  - `og-image.png` : l'image qui s'affiche quand on partage le lien.

## Voir le site sur ton ordinateur

Ouvre `index.html` dans ton navigateur, ou lance un petit serveur dans ce dossier :

```bash
python3 -m http.server 8000
```

puis va sur http://localhost:8000.
