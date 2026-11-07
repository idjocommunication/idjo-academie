<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = exiger_connexion();
$erreurs = [];

if (est_post()) {
    verifier_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'nom') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '' || mb_strlen($nom) > 120) {
            $erreurs['nom'] = 'Écris ton nom (120 caractères au plus).';
        } else {
            db()->prepare('UPDATE utilisateurs SET nom = ? WHERE id = ?')->execute([$nom, $u['id']]);
            flash('succes', 'Ton nom est enregistré.');
            rediriger('/espace/compte.php');
        }
    } elseif ($action === 'mot_de_passe') {
        $actuel = (string) ($_POST['actuel'] ?? '');
        $nouveau = (string) ($_POST['nouveau'] ?? '');
        if ($u['mot_de_passe'] !== null && !password_verify($actuel, $u['mot_de_passe'])) {
            $erreurs['actuel'] = 'Ton mot de passe actuel est incorrect.';
        } elseif (mb_strlen($nouveau) < 8) {
            $erreurs['nouveau'] = 'Choisis un mot de passe d\'au moins 8 caractères.';
        } elseif ($nouveau !== (string) ($_POST['confirmation'] ?? '')) {
            $erreurs['nouveau'] = 'Les deux mots de passe ne sont pas identiques.';
        } else {
            db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')->execute([password_hash($nouveau, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('succes', 'Ton mot de passe est enregistré.');
            rediriger('/espace/compte.php');
        }
    }
}

entete('Mon compte', ['zone' => 'compte']);
?>
<section class="tete-app">
  <h1 class="titre-app">Mon compte</h1>
  <p class="chapeau-app"><?= e($u['email']) ?><?= $u['google_sub'] ? ' · relié à Google' : '' ?></p>
</section>

<div class="grille-deux">
  <section class="carte-app">
    <h2 class="titre-bloc-app">Mon nom</h2>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="nom">
      <label class="champ"><span>Nom affiché</span>
        <input type="text" name="nom" value="<?= e($u['nom']) ?>" maxlength="120" required autocomplete="name">
        <?php if (isset($erreurs['nom'])): ?><small class="champ__erreur"><?= e($erreurs['nom']) ?></small><?php endif; ?>
      </label>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app"><?= $u['mot_de_passe'] === null ? 'Choisir un mot de passe' : 'Changer de mot de passe' ?></h2>
    <?php if ($u['mot_de_passe'] === null): ?><p class="petit">Tu te connectes avec Google ou un lien par e-mail. Tu peux aussi choisir un mot de passe.</p><?php endif; ?>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="mot_de_passe">
      <?php if ($u['mot_de_passe'] !== null): ?>
      <label class="champ"><span>Mot de passe actuel</span>
        <input type="password" name="actuel" autocomplete="current-password" required>
        <?php if (isset($erreurs['actuel'])): ?><small class="champ__erreur"><?= e($erreurs['actuel']) ?></small><?php endif; ?>
      </label>
      <?php endif; ?>
      <label class="champ"><span>Nouveau mot de passe <small>(8 caractères minimum)</small></span>
        <input type="password" name="nouveau" autocomplete="new-password" required minlength="8">
        <?php if (isset($erreurs['nouveau'])): ?><small class="champ__erreur"><?= e($erreurs['nouveau']) ?></small><?php endif; ?>
      </label>
      <label class="champ"><span>Confirme le mot de passe</span>
        <input type="password" name="confirmation" autocomplete="new-password" required minlength="8">
      </label>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>
</div>
<?php pied();
