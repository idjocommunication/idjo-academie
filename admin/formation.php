<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

$f = formation_par_id((int) ($_GET['id'] ?? $_POST['formation'] ?? 0));
if (!$f) {
    page_erreur('Formation introuvable', 'Cette formation n\'existe pas.');
}
$fid = (int) $f['id'];
$retour = '/admin/formation.php?id=' . $fid;

/** Le module appartient-il à cette formation ? */
function module_de(int $moduleId, int $fid): ?array
{
    $st = db()->prepare('SELECT * FROM modules WHERE id = ? AND formation_id = ?');
    $st->execute([$moduleId, $fid]);
    return $st->fetch() ?: null;
}

function lecon_de(int $leconId, int $fid): ?array
{
    $l = lecon_complete($leconId);
    return $l && (int) $l['formation_id'] === $fid ? $l : null;
}

/** Supprime les vidéos et fichiers d'une liste de leçons (avant suppression en base). */
function nettoyer_lecons(array $leconIds): void
{
    if (!$leconIds) {
        return;
    }
    $place = implode(',', array_fill(0, count($leconIds), '?'));
    $st = db()->prepare("SELECT video_fichier FROM lecons WHERE id IN ($place)");
    $st->execute($leconIds);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $v) {
        supprimer_fichier_prive('videos', $v);
    }
    $st = db()->prepare("SELECT fichier FROM fichiers WHERE lecon_id IN ($place)");
    $st->execute($leconIds);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $fi) {
        supprimer_fichier_prive('fichiers', $fi);
    }
}

function ids_lecons_formation(int $fid): array
{
    $st = db()->prepare('SELECT l.id FROM lecons l JOIN modules m ON m.id = l.module_id WHERE m.formation_id = ?');
    $st->execute([$fid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

if (est_post()) {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'enregistrer') {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        $slug = slugifier((string) ($_POST['slug'] ?? '') ?: $titre);
        $autre = formation_par_slug($slug);
        if ($titre === '') {
            flash('erreur', 'Le titre est obligatoire.');
            rediriger($retour);
        }
        if ($autre && (int) $autre['id'] !== $fid) {
            flash('erreur', 'Cette adresse (slug) est déjà prise par une autre formation.');
            rediriger($retour);
        }
        $formules = [];
        foreach ((array) ($_POST['formule_nom'] ?? []) as $i => $nom) {
            $nom = trim((string) $nom);
            $prixFormule = (int) preg_replace('/\D/', '', (string) ($_POST['formule_prix'][$i] ?? ''));
            if ($nom !== '') {
                $formules[] = ['id' => slugifier($nom), 'nom' => $nom, 'prix' => $prixFormule];
            }
        }
        $prixBase = $formules ? min(array_column($formules, 'prix')) : (int) preg_replace('/\D/', '', (string) ($_POST['prix'] ?? '0'));
        $image = $f['image'];
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $infos = @getimagesize($_FILES['image']['tmp_name']);
            $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$infos || !isset($types[$infos['mime']]) || $_FILES['image']['size'] > 8 * 1048576) {
                flash('erreur', 'Image refusée : utilise un JPG, PNG ou WebP de moins de 8 Mo.');
                rediriger($retour);
            }
            $dossier = RACINE . '/televersements';
            if (!is_dir($dossier)) {
                mkdir($dossier, 0755, true);
            }
            $nomImage = 'formation-' . $fid . '-' . bin2hex(random_bytes(4)) . '.' . $types[$infos['mime']];
            move_uploaded_file($_FILES['image']['tmp_name'], $dossier . '/' . $nomImage);
            if (str_starts_with((string) $f['image'], 'televersements/')) {
                @unlink(RACINE . '/' . basename(dirname($f['image'])) . '/' . basename($f['image']));
            }
            $image = 'televersements/' . $nomImage;
        }
        db()->prepare('UPDATE formations SET titre = ?, slug = ?, sous_titre = ?, description = ?, prix_fcfa = ?, formules = ?, image = ?, publiee = ?, modifie_le = ? WHERE id = ?')
            ->execute([
                mb_substr($titre, 0, 200), $slug, mb_substr(trim((string) ($_POST['sous_titre'] ?? '')), 0, 300),
                trim((string) ($_POST['description'] ?? '')), $prixBase,
                json_encode($formules, JSON_UNESCAPED_UNICODE), $image, isset($_POST['publiee']) ? 1 : 0, maintenant(), $fid,
            ]);
        flash('succes', 'Formation enregistrée.');
    } elseif ($action === 'supprimer_formation') {
        if (($_POST['confirmation'] ?? '') !== 'SUPPRIMER') {
            flash('erreur', 'Écris SUPPRIMER en majuscules pour confirmer.');
            rediriger($retour);
        }
        nettoyer_lecons(ids_lecons_formation($fid));
        db()->prepare('DELETE FROM formations WHERE id = ?')->execute([$fid]);
        flash('succes', 'Formation supprimée.');
        rediriger('/admin/formations.php');
    } elseif ($action === 'ajouter_module') {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        if ($titre !== '') {
            db()->prepare('INSERT INTO modules (formation_id, titre, description, ordre) VALUES (?,?,?,?)')
                ->execute([$fid, mb_substr($titre, 0, 200), '', prochain_ordre('modules', 'formation_id', $fid)]);
            flash('succes', 'Module ajouté.');
        }
    } elseif ($action === 'modifier_module' && ($m = module_de((int) $_POST['module'], $fid))) {
        db()->prepare('UPDATE modules SET titre = ?, description = ? WHERE id = ?')
            ->execute([mb_substr(trim((string) $_POST['titre']), 0, 200) ?: $m['titre'], trim((string) ($_POST['description'] ?? '')), $m['id']]);
        flash('succes', 'Module enregistré.');
    } elseif (in_array($action, ['monter_module', 'descendre_module'], true) && ($m = module_de((int) $_POST['module'], $fid))) {
        deplacer('modules', (int) $m['id'], $action === 'monter_module' ? -1 : 1, 'formation_id');
    } elseif ($action === 'supprimer_module' && ($m = module_de((int) $_POST['module'], $fid))) {
        $st = db()->prepare('SELECT id FROM lecons WHERE module_id = ?');
        $st->execute([$m['id']]);
        nettoyer_lecons(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        db()->prepare('DELETE FROM modules WHERE id = ?')->execute([$m['id']]);
        flash('succes', 'Module supprimé.');
    } elseif ($action === 'ajouter_lecon' && ($m = module_de((int) $_POST['module'], $fid))) {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        if ($titre !== '') {
            db()->prepare('INSERT INTO lecons (module_id, titre, resume, ordre, cree_le) VALUES (?,?,?,?,?)')
                ->execute([$m['id'], mb_substr($titre, 0, 200), '', prochain_ordre('lecons', 'module_id', (int) $m['id']), maintenant()]);
            flash('succes', 'Leçon ajoutée. Clique sur « Modifier » pour importer sa vidéo.');
            $retour .= '#module-' . $m['id'];
        }
    } elseif (in_array($action, ['monter_lecon', 'descendre_lecon'], true) && ($l = lecon_de((int) $_POST['lecon'], $fid))) {
        deplacer('lecons', (int) $l['id'], $action === 'monter_lecon' ? -1 : 1, 'module_id');
        $retour .= '#module-' . $l['module_id'];
    } elseif ($action === 'supprimer_lecon' && ($l = lecon_de((int) $_POST['lecon'], $fid))) {
        nettoyer_lecons([(int) $l['id']]);
        db()->prepare('DELETE FROM lecons WHERE id = ?')->execute([$l['id']]);
        flash('succes', 'Leçon supprimée.');
        $retour .= '#module-' . $l['module_id'];
    }
    rediriger($retour);
}

$modules = structure_formation($fid);
$formules = json_decode((string) $f['formules'], true) ?: [];
while (count($formules) < 3) {
    $formules[] = ['nom' => '', 'prix' => ''];
}

admin_entete($f['titre'], 'formations', ['/assets/js/admin.js']);
?>
<nav class="fil-app"><a href="/admin/formations.php">Formations</a> <span aria-hidden="true">/</span> <span><?= e($f['titre']) ?></span></nav>
<div class="entete-admin">
  <h1 class="titre-app"><?= e($f['titre']) ?></h1>
  <a class="bouton bouton--verre bouton--petit" href="/espace/formation.php?f=<?= e($f['slug']) ?>" target="_blank" rel="noopener">Voir comme un élève</a>
</div>

<div class="grille-admin">
  <section class="carte-app">
    <h2 class="titre-bloc-app">Informations</h2>
    <form method="post" enctype="multipart/form-data" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer"><input type="hidden" name="formation" value="<?= $fid ?>">
      <label class="champ"><span>Titre</span><input type="text" name="titre" value="<?= e($f['titre']) ?>" required maxlength="200"></label>
      <label class="champ"><span>Phrase d'accroche</span><input type="text" name="sous_titre" value="<?= e($f['sous_titre']) ?>" maxlength="300"></label>
      <label class="champ"><span>Description</span><textarea name="description" rows="4"><?= e($f['description']) ?></textarea></label>
      <label class="champ"><span>Adresse de la page <small>(lettres, chiffres et tirets)</small></span><input type="text" name="slug" value="<?= e($f['slug']) ?>" maxlength="120"></label>
      <fieldset class="champ">
        <legend>Formules et prix (FCFA)</legend>
        <p class="petit">Laisse vide les lignes inutiles. Sans formule, le prix ci-dessous s'applique.</p>
        <?php foreach ($formules as $fo): ?>
        <div class="ligne-formule">
          <input type="text" name="formule_nom[]" value="<?= e($fo['nom']) ?>" placeholder="Nom (ex. Autonome)" aria-label="Nom de la formule">
          <input type="text" name="formule_prix[]" value="<?= e((string) $fo['prix']) ?>" inputmode="numeric" placeholder="Prix" aria-label="Prix de la formule">
        </div>
        <?php endforeach; ?>
        <label class="champ"><span>Prix unique (si aucune formule)</span><input type="text" name="prix" value="<?= (int) $f['prix_fcfa'] ?>" inputmode="numeric"></label>
      </fieldset>
      <div class="champ">
        <span>Image</span>
        <?php if ($f['image']): ?><img class="apercu-image" src="/<?= e(ltrim($f['image'], '/')) ?>" alt=""><?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
      </div>
      <label class="case"><input type="checkbox" name="publiee" <?= $f['publiee'] ? 'checked' : '' ?>> <span>Publiée (visible par les élèves)</span></label>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="contenu-admin">
    <h2 class="titre-bloc-app">Modules et leçons</h2>
    <?php foreach ($modules as $i => $m): ?>
    <article class="carte-app module-admin" id="module-<?= (int) $m['id'] ?>">
      <div class="module-admin__tete">
        <span class="module-app__num">Module <?= $i + 1 ?></span>
        <form method="post" class="actions-ligne"><?= champ_csrf() ?><input type="hidden" name="module" value="<?= (int) $m['id'] ?>">
          <button class="bouton-icone" name="action" value="monter_module" aria-label="Monter le module" title="Monter">↑</button>
          <button class="bouton-icone" name="action" value="descendre_module" aria-label="Descendre le module" title="Descendre">↓</button>
          <button class="bouton-icone bouton-icone--danger" name="action" value="supprimer_module" data-confirmer="Supprimer ce module, ses leçons et leurs vidéos ?" aria-label="Supprimer le module" title="Supprimer">✕</button>
        </form>
      </div>
      <form method="post" class="formulaire">
        <?= champ_csrf() ?><input type="hidden" name="action" value="modifier_module"><input type="hidden" name="module" value="<?= (int) $m['id'] ?>">
        <input class="champ-titre" type="text" name="titre" value="<?= e($m['titre']) ?>" maxlength="200" aria-label="Titre du module">
        <textarea name="description" rows="2" placeholder="Description courte (facultatif)" aria-label="Description du module"><?= e($m['description']) ?></textarea>
        <button class="lien-bouton" type="submit">Enregistrer le module</button>
      </form>
      <ol class="lecons-admin">
        <?php foreach ($m['lecons'] as $j => $l): ?>
        <li>
          <span class="lecons-admin__num"><?= ($i + 1) . '.' . ($j + 1) ?></span>
          <a href="/admin/lecon.php?id=<?= (int) $l['id'] ?>" class="lecons-admin__titre"><?= e($l['titre']) ?></a>
          <span class="badge-app <?= $l['video_fichier'] ? 'badge-app--ok' : 'badge-app--manque' ?>"><?= $l['video_fichier'] ? 'Vidéo' : 'Sans vidéo' ?></span>
          <?php if ($l['nb_fichiers']): ?><span class="badge-app"><?= (int) $l['nb_fichiers'] ?> fichier<?= $l['nb_fichiers'] > 1 ? 's' : '' ?></span><?php endif; ?>
          <?php if ($l['apercu_gratuit']): ?><span class="badge-app">Aperçu</span><?php endif; ?>
          <form method="post" class="actions-ligne"><?= champ_csrf() ?><input type="hidden" name="lecon" value="<?= (int) $l['id'] ?>">
            <a class="bouton bouton--petit bouton--verre" href="/admin/lecon.php?id=<?= (int) $l['id'] ?>">Modifier</a>
            <button class="bouton-icone" name="action" value="monter_lecon" aria-label="Monter la leçon" title="Monter">↑</button>
            <button class="bouton-icone" name="action" value="descendre_lecon" aria-label="Descendre la leçon" title="Descendre">↓</button>
            <button class="bouton-icone bouton-icone--danger" name="action" value="supprimer_lecon" data-confirmer="Supprimer cette leçon et sa vidéo ?" aria-label="Supprimer la leçon" title="Supprimer">✕</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ol>
      <form method="post" class="formulaire formulaire--ligne">
        <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter_lecon"><input type="hidden" name="module" value="<?= (int) $m['id'] ?>">
        <input type="text" name="titre" placeholder="Titre de la nouvelle leçon" required maxlength="200" aria-label="Titre de la nouvelle leçon">
        <button class="bouton bouton--petit" type="submit">Ajouter une leçon</button>
      </form>
    </article>
    <?php endforeach; ?>

    <section class="carte-app">
      <form method="post" class="formulaire formulaire--ligne">
        <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter_module">
        <input type="text" name="titre" placeholder="Titre du nouveau module" required maxlength="200" aria-label="Titre du nouveau module">
        <button class="bouton" type="submit">Ajouter un module</button>
      </form>
    </section>

    <details class="carte-app zone-danger">
      <summary>Supprimer cette formation</summary>
      <form method="post" class="formulaire">
        <?= champ_csrf() ?><input type="hidden" name="action" value="supprimer_formation">
        <p class="petit">Tous les modules, leçons, vidéos, fichiers et accès des élèves seront supprimés. Écris SUPPRIMER pour confirmer.</p>
        <input type="text" name="confirmation" autocomplete="off" aria-label="Confirmation">
        <button class="bouton bouton--danger" type="submit">Supprimer définitivement</button>
      </form>
    </details>
  </section>
</div>
<?php pied();
