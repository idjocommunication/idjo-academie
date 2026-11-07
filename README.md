# Idjô Académie

Site vitrine de la formation vidéo **« Apprendre à gagner de l'argent grâce à l'IA »** : 4 modules, 26 leçons.

En ligne sur **https://academie.idjôcommunication.com** (adresse technique : `academie.xn--idjcommunication-pvb.com`), hébergé chez Hostinger.

La vitrine est en HTML, CSS et JavaScript. L'espace élève et l'admin sont en PHP 8.3 avec une base SQLite privée (aucune installation, aucun mot de passe de base de données). L'ancienne adresse GitHub Pages renvoie vers le nouveau site.

## Pages

| Fichier | Contenu |
| --- | --- |
| `index.html` | Accueil : haut de page sombre avec vidéo, les 4 modules, assistant de questions avec aperçu des leçons (parallaxe), onglets par module, étapes, formules, formateur, questions fréquentes |
| `formation.html` | Page de la formation : ce que tu vas apprendre, contenu en accordéon, carte d'inscription, prérequis, description |
| `confidentialite.html` | Politique de confidentialité (exigée par Google pour la connexion Google) |
| `compte/` | Inscription gratuite, connexion (e-mail + mot de passe, lien magique par e-mail, Google), mot de passe oublié |
| `espace/` | Espace élève : mes formations, déblocage via WhatsApp, lecteur vidéo, progression, fichiers, mon compte |
| `admin/` | Admin, réservé à idjocommunication@gmail.com : formations, modules, leçons, import des vidéos, fichiers, élèves, accès, demandes, réglages |

## Espace élève et admin

- **Comptes** : un élève crée son compte gratuitement. Les formations restent payantes : il clique sur « Débloquer », choisit sa formule, et un message WhatsApp prêt part vers le +225 07 47 93 37 48. Tu reçois aussi un e-mail. Après le paiement Mobile Money, tu cliques sur « Paiement reçu : activer » dans l'admin et l'élève reçoit un e-mail.
- **Admin** : seul le compte idjocommunication@gmail.com, avec son adresse confirmée, entre dans `/admin/`. Un mot de passe posé par quelqu'un d'autre avant la confirmation est toujours effacé.
- **Vidéos** : dans l'admin, ouvre une leçon et glisse la vidéo (MP4 conseillé, jusqu'à 4 Go). Elle part en morceaux de 5 Mo et reprend toute seule si la connexion coupe. Les vidéos et fichiers sont rangés dans `stockage-academie/`, à côté de `public_html`, hors d'atteinte du web : seuls les comptes qui ont accès peuvent les lire.
- **Base de données** : fichier SQLite dans `stockage-academie/base/`. La formation existante (4 modules, 26 leçons) y est importée au premier lancement. MySQL reste possible (voir `app/config.php`).
- **Connexion Google** : projet Google Cloud « Idjo Academie » (compte idjocommunication@gmail.com). L'ID client et le code secret se collent dans Admin > Réglages.
- **Mettre le site à jour** : après un envoi sur GitHub, relance le déploiement Git du site dans hPanel (branche `main`, dossier `public_html`), ou demande-le à Claude. Les vidéos, la base et les images envoyées depuis l'admin ne sont jamais touchées.
- **Tester sur un ordinateur** : `php -S 127.0.0.1:8766` à la racine, avec un fichier `app/config.local.php` (non envoyé sur GitHub) qui met `'url' => 'http://127.0.0.1:8766'` et `'mail' => ['mode' => 'fichier']`.

## Ce que tu peux modifier toi-même

| Pour changer… | Ouvre le fichier |
| --- | --- |
| Le numéro WhatsApp des demandes de déblocage | Admin > Réglages (et `assets/js/config.js` pour la vitrine) |
| Les formations, modules, leçons, prix et vidéos | Admin > Formations |
| Un lien de paiement (Chariow, CinetPay…) | `assets/js/config.js`, ligne `lienPaiement` |
| Les prix et le contenu des 3 formules | `assets/js/config.js`, bloc `formules` |
| Les titres, résumés et durées des leçons, les images des modules | `assets/js/data.js` |
| Les réponses de l'assistant | `assets/js/main.js`, fonction `repondre` |
| La photo du formateur | ajoute `assets/img/formateur.jpg`, puis remplace le « A » dans les blocs `formateur__photo` des deux pages par `<img src="assets/img/formateur.jpg" alt="Alan">` |

Sur le site, « S'inscrire » mène à la création d'un compte gratuit, avec la formule choisie déjà cochée. Le contenu des leçons affiché sur la vitrine vient de `assets/js/data.js` ; celui de l'espace élève vient de l'admin.

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
