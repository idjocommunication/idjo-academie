<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

if (utilisateur()) {
    rediriger('/espace/');
}
$suite = retenir_suite();
$erreurs = [];
$valeurs = ['nom' => '', 'email' => ''];
$envoye = null;

if (est_post()) {
    verifier_csrf();
    $valeurs['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $valeurs['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');

    if ($valeurs['nom'] === '' || mb_strlen($valeurs['nom']) > 120) {
        $erreurs['nom'] = 'Écris ton nom (120 caractères au plus).';
    }
    if (!email_valide($valeurs['email'])) {
        $erreurs['email'] = 'Cette adresse e-mail ne semble pas correcte.';
    }
    if (mb_strlen($mdp) < 8) {
        $erreurs['mot_de_passe'] = 'Choisis un mot de passe d\'au moins 8 caractères.';
    }
    if (!$erreurs && !limite_ok('inscription', '', 8, 60)) {
        $erreurs['general'] = 'Trop d\'essais depuis cet appareil. Réessaie dans une heure.';
    }
    if (!$erreurs) {
        $u = utilisateur_par_email($valeurs['email']);
        if ($u && $u['email_verifie_le'] !== null) {
            flash('info', 'Un compte existe déjà avec cette adresse. Connecte-toi, ou demande un lien de connexion par e-mail.');
            rediriger('compte/connexion.php?email=' . rawurlencode($valeurs['email']));
        }
        if ($u) {
            // Compte jamais confirmé : on remplace le nom et le mot de passe en attente.
            db()->prepare('UPDATE utilisateurs SET nom = ?, mot_de_passe = ? WHERE id = ?')
                ->execute([$valeurs['nom'], password_hash($mdp, PASSWORD_DEFAULT), $u['id']]);
        } else {
            creer_utilisateur($valeurs['email'], $valeurs['nom'], $mdp);
        }
        envoyer_verification($valeurs['email']);
        $envoye = $valeurs['email'];
    }
}

entete('Créer un compte', ['classe' => 'page-auth']);
if ($envoye): ?>
  <section class="carte-auth centre">
    <div class="pictogramme" aria-hidden="true">✉</div>
    <h1 class="titre-app">Vérifie ta boîte mail</h1>
    <p>Nous avons envoyé un lien de confirmation à <strong><?= e($envoye) ?></strong>. Clique dessus pour activer ton compte.</p>
    <p class="petit">Rien reçu après quelques minutes ? Regarde dans les spams ou les « Promotions ».</p>
    <form method="post" action="/compte/renvoyer.php"><?= champ_csrf() ?>
      <input type="hidden" name="email" value="<?= e($envoye) ?>">
      <button class="lien-bouton" type="submit">Renvoyer le lien</button>
    </form>
  </section>
<?php else:
  carte_auth_debut('Crée ton compte gratuit', 'Accède à ton espace, aux leçons d\'aperçu, puis débloque les formations quand tu es prêt.');
  echo bouton_google($suite);
  if (!empty($erreurs['general'])): ?><div class="message message--erreur" role="alert"><?= e($erreurs['general']) ?></div><?php endif; ?>
  <form method="post" class="formulaire" novalidate>
    <?= champ_csrf() ?>
    <input type="hidden" name="suite" value="<?= e($suite) ?>">
    <label class="champ">
      <span>Ton nom</span>
      <input type="text" name="nom" value="<?= e($valeurs['nom']) ?>" autocomplete="name" required maxlength="120">
      <?php if (isset($erreurs['nom'])): ?><small class="champ__erreur"><?= e($erreurs['nom']) ?></small><?php endif; ?>
    </label>
    <label class="champ">
      <span>Adresse e-mail</span>
      <input type="email" name="email" value="<?= e($valeurs['email']) ?>" autocomplete="email" required inputmode="email">
      <?php if (isset($erreurs['email'])): ?><small class="champ__erreur"><?= e($erreurs['email']) ?></small><?php endif; ?>
    </label>
    <label class="champ">
      <span>Mot de passe <small>(8 caractères minimum)</small></span>
      <input type="password" name="mot_de_passe" autocomplete="new-password" required minlength="8">
      <?php if (isset($erreurs['mot_de_passe'])): ?><small class="champ__erreur"><?= e($erreurs['mot_de_passe']) ?></small><?php endif; ?>
    </label>
    <button class="bouton bouton--plein" type="submit">Créer mon compte</button>
  </form>
  <p class="carte-auth__autre"><a href="/compte/lien.php">Recevoir plutôt un lien de connexion par e-mail</a></p>
  <p class="carte-auth__autre">Déjà un compte ? <a href="/compte/connexion.php">Se connecter</a></p>
  </section>
<?php endif;
pied();
