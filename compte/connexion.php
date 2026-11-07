<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

if (utilisateur()) {
    rediriger(est_admin() ? '/admin/' : '/espace/');
}
$suite = retenir_suite();
$email = mb_strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));
$erreur = '';
$nonVerifie = false;

if (est_post()) {
    verifier_csrf();
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    if (!limite_ok('connexion', $email, 10, 15)) {
        $erreur = 'Trop d\'essais. Attends 15 minutes, ou connecte-toi avec un lien reçu par e-mail.';
    } else {
        $u = $email !== '' ? utilisateur_par_email($email) : null;
        if (!$u || $u['bloque']) {
            $erreur = 'Adresse e-mail ou mot de passe incorrect.';
        } elseif ($u['mot_de_passe'] === null) {
            $erreur = 'Ce compte n\'a pas de mot de passe. Connecte-toi avec Google ou avec un lien reçu par e-mail, puis choisis un mot de passe dans « Mon compte ».';
        } elseif (!password_verify($mdp, $u['mot_de_passe'])) {
            $erreur = 'Adresse e-mail ou mot de passe incorrect.';
        } elseif ($u['email_verifie_le'] === null) {
            $nonVerifie = true;
        } else {
            if (password_needs_rehash($u['mot_de_passe'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')->execute([password_hash($mdp, PASSWORD_DEFAULT), $u['id']]);
            }
            apres_connexion($u, $suite ?: null);
        }
    }
}

entete('Connexion', ['classe' => 'page-auth']);
carte_auth_debut('Content de te revoir', 'Connecte-toi pour retrouver tes formations.');
echo bouton_google($suite);
if ($erreur): ?><div class="message message--erreur" role="alert"><?= e($erreur) ?></div><?php endif;
if ($nonVerifie): ?>
  <div class="message message--info" role="status">
    Ton adresse n'est pas encore confirmée. Clique sur le lien reçu par e-mail.
    <form method="post" action="/compte/renvoyer.php"><?= champ_csrf() ?><input type="hidden" name="email" value="<?= e($email) ?>"><button class="lien-bouton" type="submit">Renvoyer le lien</button></form>
  </div>
<?php endif; ?>
  <form method="post" class="formulaire">
    <?= champ_csrf() ?>
    <input type="hidden" name="suite" value="<?= e($suite) ?>">
    <label class="champ">
      <span>Adresse e-mail</span>
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required inputmode="email">
    </label>
    <label class="champ">
      <span>Mot de passe</span>
      <input type="password" name="mot_de_passe" autocomplete="current-password" required>
    </label>
    <p class="champ__aide"><a href="/compte/oubli.php">Mot de passe oublié ?</a></p>
    <button class="bouton bouton--plein" type="submit">Se connecter</button>
  </form>
  <a class="bouton bouton--verre bouton--plein" href="/compte/lien.php<?= $email !== '' ? '?email=' . rawurlencode($email) : '' ?>">Recevoir un lien de connexion par e-mail</a>
  <p class="carte-auth__autre">Pas encore de compte ? <a href="/compte/inscription.php">Créer un compte gratuit</a></p>
</section>
<?php pied();
