<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

if (utilisateur()) {
    rediriger('/espace/');
}
retenir_suite();
$email = mb_strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));
$erreur = '';
$envoye = false;

if (est_post()) {
    verifier_csrf();
    if (!email_valide($email)) {
        $erreur = 'Cette adresse e-mail ne semble pas correcte.';
    } elseif (!limite_ok('lien', $email, 5, 60)) {
        $erreur = 'Trop de liens demandés. Réessaie dans une heure.';
    } else {
        $u = utilisateur_par_email($email);
        if (!$u || !$u['bloque']) {
            envoyer_lien_magique($email);
        }
        $envoye = true;
    }
}

entete('Lien de connexion', ['classe' => 'page-auth']);
if ($envoye): ?>
  <section class="carte-auth centre">
    <div class="pictogramme" aria-hidden="true">✉</div>
    <h1 class="titre-app">Regarde tes e-mails</h1>
    <p>Si l'adresse <strong><?= e($email) ?></strong> est correcte, tu vas recevoir un lien pour te connecter. Il est valable 20 minutes.</p>
    <p class="petit">Rien reçu ? Regarde dans les spams ou les « Promotions ».</p>
    <p><a href="/compte/lien.php?email=<?= rawurlencode($email) ?>">Renvoyer un lien</a></p>
  </section>
<?php else:
  carte_auth_debut('Connexion sans mot de passe', 'Entre ton adresse e-mail : tu reçois un lien qui te connecte en un clic. Pas encore de compte ? Il sera créé gratuitement.');
  if ($erreur): ?><div class="message message--erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>
  <form method="post" class="formulaire">
    <?= champ_csrf() ?>
    <label class="champ">
      <span>Adresse e-mail</span>
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required inputmode="email">
    </label>
    <button class="bouton bouton--plein" type="submit">Recevoir mon lien</button>
  </form>
  <p class="carte-auth__autre"><a href="/compte/connexion.php">Se connecter avec un mot de passe</a></p>
</section>
<?php endif;
pied();
