<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

if (est_post()) {
    verifier_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'creer') {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        if ($titre === '') {
            flash('erreur', 'Donne un titre à la formation.');
            rediriger('/admin/formations.php');
        }
        $slug = slugifier($titre);
        $base = $slug;
        $n = 2;
        while (formation_par_slug($slug)) {
            $slug = $base . '-' . $n++;
        }
        $ordre = (int) db()->query('SELECT COALESCE(MAX(ordre), 0) + 1 FROM formations')->fetchColumn();
        db()->prepare('INSERT INTO formations (slug, titre, prix_fcfa, publiee, ordre, cree_le, modifie_le) VALUES (?,?,0,0,?,?,?)')
            ->execute([$slug, $titre, $ordre, maintenant(), maintenant()]);
        flash('succes', 'Formation créée. Ajoute ses modules et ses leçons, puis publie-la.');
        rediriger('/admin/formation.php?id=' . db()->lastInsertId());
    }
    if ($action === 'monter' || $action === 'descendre') {
        deplacer('formations', (int) $_POST['id'], $action === 'monter' ? -1 : 1, '');
    }
    rediriger('/admin/formations.php');
}

$formations = db()->query('SELECT f.*,
    (SELECT COUNT(*) FROM modules m WHERE m.formation_id = f.id) AS nb_modules,
    (SELECT COUNT(*) FROM lecons l JOIN modules m ON m.id = l.module_id WHERE m.formation_id = f.id) AS nb_lecons,
    (SELECT COUNT(*) FROM lecons l JOIN modules m ON m.id = l.module_id WHERE m.formation_id = f.id AND l.video_fichier IS NOT NULL) AS nb_videos,
    (SELECT COUNT(*) FROM acces a WHERE a.formation_id = f.id AND a.actif = 1) AS nb_eleves
    FROM formations f ORDER BY ordre, id')->fetchAll();

admin_entete('Formations', 'formations');
?>
<h1 class="titre-app">Formations</h1>
<div class="tableau-defilant">
<table class="tableau-admin">
  <thead><tr><th>Formation</th><th>Contenu</th><th>Vidéos</th><th>Élèves</th><th>État</th><th><span class="visuellement-cache">Ordre</span></th></tr></thead>
  <tbody>
  <?php foreach ($formations as $f): ?>
    <tr>
      <td><a href="/admin/formation.php?id=<?= (int) $f['id'] ?>"><strong><?= e($f['titre']) ?></strong></a><br><span class="petit">À partir de <?= e(prix((int) $f['prix_fcfa'])) ?></span></td>
      <td class="petit"><?= (int) $f['nb_modules'] ?> modules · <?= (int) $f['nb_lecons'] ?> leçons</td>
      <td class="petit"><?= (int) $f['nb_videos'] ?> / <?= (int) $f['nb_lecons'] ?></td>
      <td class="petit"><?= (int) $f['nb_eleves'] ?></td>
      <td><span class="badge-app <?= $f['publiee'] ? 'badge-app--ok' : '' ?>"><?= $f['publiee'] ? 'Publiée' : 'Brouillon' ?></span></td>
      <td class="actions-ligne">
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="bouton-icone" name="action" value="monter" title="Monter" aria-label="Monter">↑</button><button class="bouton-icone" name="action" value="descendre" title="Descendre" aria-label="Descendre">↓</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<section class="carte-app">
  <h2 class="titre-bloc-app">Nouvelle formation</h2>
  <form method="post" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
    <label class="champ"><span>Titre</span><input type="text" name="titre" required maxlength="200" placeholder="Ex. : Créer sa boutique en ligne avec l'IA"></label>
    <button class="bouton" type="submit">Créer</button>
  </form>
</section>
<?php pied();
