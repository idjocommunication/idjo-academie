# Idjô Académie

Site vitrine de la formation vidéo **« Apprendre à gagner de l'argent grâce à l'IA »** : 4 modules, 26 leçons.

Site statique (HTML, CSS, JavaScript), sans installation ni compilation. Il se publie tel quel sur GitHub Pages, Hostinger ou n'importe quel hébergeur.

## Pages

| Fichier | Contenu |
| --- | --- |
| `index.html` | Accueil : vidéo de fond, les 4 modules, assistant de questions avec aperçu des leçons (parallaxe), onglets par module, étapes, formules, formateur, questions fréquentes |
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

Tant que le numéro WhatsApp est vide, les boutons « S'inscrire » mènent à la section Tarifs.

## Identité visuelle

- Design inspiré du modèle « Rocket CTA » de MotionSites : fond noir, panneaux en verre dépoli, halos violets discrets, boutons blancs au texte animé.
- Couleurs : noir `#000000`, violet `#6A1BF5`, violet clair `#A98BFF` (textes sur fond noir), lilas `#EDE3FF`, blanc `#FFFFFF`.
- Polices (licence SIL OFL, fichiers dans `assets/fonts/`) : Instrument Serif pour les titres, Inter pour le texte.
- Images et vidéo générées avec Google Flow, dans `assets/img/flow/` et `assets/video/`. La vidéo du haut de page ne se charge que sur ordinateur ; sur téléphone, une image fixe la remplace.
- Le logo garde ses propres formes vectorisées (dessinées avec la police Archivo).
- Logos dans `assets/img/` :
  - `logo.svg` / `logo.png` : version principale, pour fond clair ;
  - `logo-blanc.svg` / `logo-blanc.png` : pour fond noir ;
  - `logo-slogan.svg` / `logo-slogan-blanc.svg` (et `.png`) : avec le slogan ;
  - `logo-symbole.svg` / `logo-symbole.png` : le symbole seul (photo de profil, favicon).

## Voir le site sur ton ordinateur

Ouvre `index.html` dans ton navigateur, ou lance un petit serveur dans ce dossier :

```bash
python3 -m http.server 8000
```

puis va sur http://localhost:8000.
