<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

$email = mb_strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));
$erreur = '';
$envoye = false;

if (est_post()) {
    verifier_csrf();
    if (!email_valide($email)) {
        $erreur = 'Cette adresse e-mail ne semble pas correcte.';
    } elseif (!limite_ok('oubli', $email, 4, 60)) {
        $erreur = 'Trop de demandes. Réessaie dans une heure.';
    } else {
        $u = utilisateur_par_email($email);
        if ($u && !$u['bloque']) {
            envoyer_reinitialisation($email);
        }
        $envoye = true;
    }
}

entete('Mot de passe oublié', ['classe' => 'page-auth']);
if ($envoye): ?>
  <section class="carte-auth centre">
    <div class="pictogramme" aria-hidden="true">✉</div>
    <h1 class="titre-app">Regarde tes e-mails</h1>
    <p>Si un compte existe avec <strong><?= e($email) ?></strong>, tu vas recevoir un lien pour choisir un nouveau mot de passe. Il est valable 1 heure.</p>
    <p><a href="/compte/connexion.php">Retour à la connexion</a></p>
  </section>
<?php else:
  carte_auth_debut('Mot de passe oublié', 'Entre ton adresse : tu reçois un lien pour choisir un nouveau mot de passe.');
  if ($erreur): ?><div class="message message--erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>
  <form method="post" class="formulaire">
    <?= champ_csrf() ?>
    <label class="champ">
      <span>Adresse e-mail</span>
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required inputmode="email">
    </label>
    <button class="bouton bouton--plein" type="submit">Recevoir le lien</button>
  </form>
  <p class="carte-auth__autre"><a href="/compte/connexion.php">Retour à la connexion</a></p>
</section>
<?php endif;
pied();
