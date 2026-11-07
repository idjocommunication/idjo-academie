<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

$l = lecon_complete((int) ($_GET['id'] ?? $_POST['lecon'] ?? 0));
if (!$l) {
    page_erreur('Leçon introuvable', 'Cette leçon n\'existe pas.');
}
$lid = (int) $l['id'];
$retour = '/admin/lecon.php?id=' . $lid;

if (est_post()) {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'enregistrer') {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        $minutes = (float) str_replace(',', '.', (string) ($_POST['duree'] ?? '0'));
        db()->prepare('UPDATE lecons SET titre = ?, resume = ?, duree_sec = ?, apercu_gratuit = ? WHERE id = ?')->execute([
            mb_substr($titre !== '' ? $titre : $l['titre'], 0, 200),
            trim((string) ($_POST['resume'] ?? '')),
            max(0, (int) round($minutes * 60)),
            isset($_POST['apercu_gratuit']) ? 1 : 0,
            $lid,
        ]);
        flash('succes', 'Leçon enregistrée.');
    } elseif ($action === 'supprimer_video') {
        supprimer_fichier_prive('videos', $l['video_fichier']);
        db()->prepare('UPDATE lecons SET video_fichier = NULL, video_taille = NULL, video_type = NULL WHERE id = ?')->execute([$lid]);
        flash('succes', 'Vidéo supprimée.');
    } elseif ($action === 'supprimer_fichier') {
        $st = db()->prepare('SELECT * FROM fichiers WHERE id = ? AND lecon_id = ?');
        $st->execute([(int) ($_POST['fichier'] ?? 0), $lid]);
        if ($fi = $st->fetch()) {
            supprimer_fichier_prive('fichiers', $fi['fichier']);
            db()->prepare('DELETE FROM fichiers WHERE id = ?')->execute([$fi['id']]);
            flash('succes', 'Fichier supprimé.');
        }
    } elseif ($action === 'renommer_fichier') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom !== '') {
            db()->prepare('UPDATE fichiers SET nom = ? WHERE id = ? AND lecon_id = ?')->execute([mb_substr($nom, 0, 200), (int) $_POST['fichier'], $lid]);
        }
    }
    rediriger($retour);
}

$st = db()->prepare('SELECT * FROM fichiers WHERE lecon_id = ? ORDER BY id');
$st->execute([$lid]);
$fichiers = $st->fetchAll();
$f = formation_par_id((int) $l['formation_id']);

admin_entete($l['titre'], 'formations', ['/assets/js/admin.js']);
?>
<nav class="fil-app"><a href="/admin/formations.php">Formations</a> <span aria-hidden="true">/</span> <a href="/admin/formation.php?id=<?= (int) $f['id'] ?>#module-<?= (int) $l['module_id'] ?>"><?= e($f['titre']) ?></a> <span aria-hidden="true">/</span> <span><?= e($l['module_titre']) ?></span></nav>
<div class="entete-admin">
  <h1 class="titre-app"><?= e($l['titre']) ?></h1>
  <a class="bouton bouton--verre bouton--petit" href="/espace/lecon.php?id=<?= $lid ?>" target="_blank" rel="noopener">Voir comme un élève</a>
</div>

<div class="grille-admin">
  <section class="carte-app">
    <h2 class="titre-bloc-app">Vidéo de la leçon</h2>
    <?php if ($l['video_fichier']): ?>
      <video class="apercu-video" controls preload="metadata" src="/media/video.php?l=<?= $lid ?>"></video>
      <p class="petit"><?= e(taille_texte((int) $l['video_taille'])) ?> · <?= e($l['video_type']) ?><?= $l['duree_sec'] ? ' · ' . e(duree_texte((int) $l['duree_sec'])) : '' ?></p>
      <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer_video">
        <button class="lien-bouton lien-bouton--danger" type="submit" data-confirmer="Supprimer cette vidéo ?">Supprimer la vidéo</button></form>
    <?php endif; ?>
    <div class="zone-envoi" data-envoi="video" data-lecon="<?= $lid ?>" data-morceau="<?= taille_morceau() ?>" data-csrf="<?= e(jeton_csrf()) ?>">
      <input type="file" id="fichier-video" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov,.m4v" class="visuellement-cache">
      <label for="fichier-video" class="zone-envoi__cible">
        <strong><?= $l['video_fichier'] ? 'Remplacer la vidéo' : 'Importer la vidéo' ?></strong>
        <span>Glisse ta vidéo ici ou clique pour la choisir · MP4 conseillé, jusqu'à 4 Go</span>
      </label>
      <div class="zone-envoi__progression" hidden>
        <div class="barre-progression"><span></span></div>
        <p class="petit zone-envoi__etat" aria-live="polite"></p>
        <button type="button" class="lien-bouton zone-envoi__annuler">Annuler</button>
      </div>
    </div>
    <p class="petit">Garde cette page ouverte pendant l'envoi. Si la connexion coupe, choisis à nouveau le même fichier : l'envoi reprend où il s'était arrêté.</p>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">Informations</h2>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
      <label class="champ"><span>Titre</span><input type="text" name="titre" value="<?= e($l['titre']) ?>" maxlength="200" required></label>
      <label class="champ"><span>Résumé (affiché sous la vidéo)</span><textarea name="resume" rows="5"><?= e($l['resume']) ?></textarea></label>
      <label class="champ"><span>Durée en minutes <small>(remplie toute seule à l'import de la vidéo)</small></span><input type="text" name="duree" value="<?= e(rtrim(rtrim(number_format(((int) $l['duree_sec']) / 60, 1, '.', ''), '0'), '.')) ?>" inputmode="decimal"></label>
      <label class="case"><input type="checkbox" name="apercu_gratuit" <?= $l['apercu_gratuit'] ? 'checked' : '' ?>> <span>Aperçu gratuit (visible par tous les comptes, même sans paiement)</span></label>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">Fichiers à télécharger</h2>
    <?php if ($fichiers): ?>
    <ul class="fichiers-admin">
      <?php foreach ($fichiers as $fi): ?>
      <li>
        <form method="post" class="formulaire formulaire--ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="renommer_fichier"><input type="hidden" name="fichier" value="<?= (int) $fi['id'] ?>">
          <input type="text" name="nom" value="<?= e($fi['nom']) ?>" maxlength="200" aria-label="Nom du fichier">
          <button class="lien-bouton" type="submit">Renommer</button>
        </form>
        <span class="petit"><?= e(taille_texte((int) $fi['taille'])) ?></span>
        <a class="lien-bouton" href="/media/fichier.php?id=<?= (int) $fi['id'] ?>">Ouvrir</a>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer_fichier"><input type="hidden" name="fichier" value="<?= (int) $fi['id'] ?>">
          <button class="lien-bouton lien-bouton--danger" type="submit" data-confirmer="Supprimer ce fichier ?">Supprimer</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="petit">Aucun fichier pour l'instant.</p><?php endif; ?>
    <div class="zone-envoi zone-envoi--petite" data-envoi="fichier" data-lecon="<?= $lid ?>" data-morceau="<?= taille_morceau() ?>" data-csrf="<?= e(jeton_csrf()) ?>">
      <input type="file" id="fichier-joint" accept=".pdf,.docx,.xlsx,.pptx,.zip,.png,.jpg,.jpeg,.txt" class="visuellement-cache">
      <label for="fichier-joint" class="zone-envoi__cible">
        <strong>Ajouter un fichier</strong>
        <span>PDF, Word, Excel, PowerPoint, ZIP ou image · jusqu'à 200 Mo</span>
      </label>
      <div class="zone-envoi__progression" hidden>
        <div class="barre-progression"><span></span></div>
        <p class="petit zone-envoi__etat" aria-live="polite"></p>
        <button type="button" class="lien-bouton zone-envoi__annuler">Annuler</button>
      </div>
    </div>
  </section>
</div>
<?php pied();
