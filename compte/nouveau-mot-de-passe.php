<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

$jeton = (string) ($_REQUEST['jeton'] ?? '');
$erreur = '';

if (est_post()) {
    verifier_csrf();
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    if (mb_strlen($mdp) < 8) {
        $erreur = 'Choisis un mot de passe d\'au moins 8 caractères.';
    } elseif ($mdp !== (string) ($_POST['confirmation'] ?? '')) {
        $erreur = 'Les deux mots de passe ne sont pas identiques.';
    } else {
        $ligne = utiliser_jeton($jeton, 'reinitialisation');
        $u = $ligne ? utilisateur_par_email($ligne['email']) : null;
        if (!$u) {
            flash('erreur', 'Ce lien a expiré ou a déjà servi. Demande un nouveau lien.');
            rediriger('compte/oubli.php');
        }
        // Le lien a été reçu par e-mail : l'adresse est donc confirmée.
        db()->prepare('UPDATE utilisateurs SET mot_de_passe = ?, email_verifie_le = COALESCE(email_verifie_le, ?) WHERE id = ?')
            ->execute([password_hash($mdp, PASSWORD_DEFAULT), maintenant(), $u['id']]);
        flash('succes', 'Ton nouveau mot de passe est enregistré.');
        apres_connexion(utilisateur_par_email($u['email']));
    }
}

entete('Nouveau mot de passe', ['classe' => 'page-auth']);
carte_auth_debut('Choisis un nouveau mot de passe');
if ($erreur): ?><div class="message message--erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>
  <form method="post" class="formulaire">
    <?= champ_csrf() ?>
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <label class="champ">
      <span>Nouveau mot de passe <small>(8 caractères minimum)</small></span>
      <input type="password" name="mot_de_passe" autocomplete="new-password" required minlength="8">
    </label>
    <label class="champ">
      <span>Confirme le mot de passe</span>
      <input type="password" name="confirmation" autocomplete="new-password" required minlength="8">
    </label>
    <button class="bouton bouton--plein" type="submit">Enregistrer</button>
  </form>
</section>
<?php pied();
