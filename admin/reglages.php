<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';
require_once __DIR__ . '/../app/auth.php';

if (est_post()) {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'whatsapp') {
        $numero = preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? ''));
        if (strlen($numero) < 8) {
            flash('erreur', 'Numéro incorrect : écris-le au format international, ex. 2250700000000.');
        } else {
            definir_reglage('whatsapp', $numero);
            flash('succes', 'Numéro WhatsApp enregistré.');
        }
    } elseif ($action === 'google') {
        $id = trim((string) ($_POST['google_client_id'] ?? ''));
        $secret = trim((string) ($_POST['google_client_secret'] ?? ''));
        if ($id !== '' && !str_ends_with($id, '.apps.googleusercontent.com')) {
            flash('erreur', 'L\'identifiant client Google doit se terminer par .apps.googleusercontent.com');
            rediriger('/admin/reglages.php');
        }
        definir_reglage('google_client_id', $id);
        if ($secret !== '') {
            definir_reglage('google_client_secret', $secret);
        }
        if (isset($_POST['effacer_google'])) {
            definir_reglage('google_client_id', '');
            definir_reglage('google_client_secret', '');
        }
        flash('succes', google_configure() ? 'Connexion Google activée.' : 'Réglages Google enregistrés (connexion Google désactivée tant que les deux champs ne sont pas remplis).');
    } elseif ($action === 'test_email') {
        $ok = envoyer_email(ADMIN_EMAIL, 'Test d\'envoi Idjô Académie', 'L\'envoi d\'e-mails fonctionne', '<p>Si tu lis ce message, les e-mails de confirmation et les liens de connexion partent bien.</p>');
        flash($ok ? 'succes' : 'erreur', $ok ? 'E-mail de test envoyé à ' . ADMIN_EMAIL . '. Regarde aussi dans les spams.' : 'L\'envoi a échoué. Vérifie la configuration e-mail de l\'hébergement.');
    }
    rediriger('/admin/reglages.php');
}

$googleId = reglage('google_client_id');
$secretEnregistre = reglage('google_client_secret') !== '';
$origine = rtrim(url(), '/');

admin_entete('Réglages', 'reglages');
?>
<h1 class="titre-app">Réglages</h1>
<div class="grille-admin">
  <section class="carte-app">
    <h2 class="titre-bloc-app">WhatsApp pour les paiements</h2>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="whatsapp">
      <label class="champ"><span>Numéro au format international, sans + ni espace</span>
        <input type="text" name="whatsapp" value="<?= e(reglage('whatsapp', (string) conf('whatsapp', ''))) ?>" inputmode="numeric"></label>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">Connexion avec Google</h2>
    <p class="petit">État : <strong><?= google_configure() ? 'activée' : 'désactivée' ?></strong></p>
    <p class="petit">Dans la console Google Cloud, l'origine JavaScript autorisée est <code><?= e($origine) ?></code> et l'URI de redirection autorisée est <code><?= e(url('compte/google-retour.php')) ?></code>.</p>
    <form method="post" class="formulaire" autocomplete="off">
      <?= champ_csrf() ?><input type="hidden" name="action" value="google">
      <label class="champ"><span>ID client</span><input type="text" name="google_client_id" value="<?= e($googleId) ?>" placeholder="xxxx.apps.googleusercontent.com"></label>
      <label class="champ"><span>Code secret du client <?= $secretEnregistre ? '<small>(enregistré : laisse vide pour le garder)</small>' : '' ?></span><input type="password" name="google_client_secret" value="" autocomplete="new-password"></label>
      <?php if ($googleId !== ''): ?><label class="case"><input type="checkbox" name="effacer_google"> <span>Désactiver et effacer la connexion Google</span></label><?php endif; ?>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">E-mails</h2>
    <p class="petit">Les e-mails partent de <code><?= e((string) conf('mail.expediteur')) ?></code>. Les réponses arrivent sur <?= e(ADMIN_EMAIL) ?>.</p>
    <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="test_email"><button class="bouton bouton--verre" type="submit">M'envoyer un e-mail de test</button></form>
  </section>
</div>
<?php pied();
