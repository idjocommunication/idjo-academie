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
    } elseif ($action === 'smtp') {
        $hote = trim((string) ($_POST['smtp_hote'] ?? ''));
        $utilisateur = mb_strtolower(trim((string) ($_POST['smtp_utilisateur'] ?? '')));
        $motDePasse = str_replace(' ', '', (string) ($_POST['smtp_mot_de_passe'] ?? ''));
        if (isset($_POST['effacer_smtp'])) {
            foreach (['smtp_hote', 'smtp_port', 'smtp_utilisateur', 'smtp_mot_de_passe', 'smtp_expediteur'] as $cle) {
                definir_reglage($cle, '');
            }
            flash('info', 'Envoi par SMTP désactivé : les e-mails partent à nouveau par l\'hébergement.');
            rediriger('/admin/reglages.php');
        }
        if ($hote === '' || !filter_var($utilisateur, FILTER_VALIDATE_EMAIL)) {
            flash('erreur', 'Remplis le serveur et l\'adresse e-mail.');
            rediriger('/admin/reglages.php');
        }
        definir_reglage('smtp_hote', $hote);
        definir_reglage('smtp_port', (string) max(1, (int) ($_POST['smtp_port'] ?? 465)));
        definir_reglage('smtp_utilisateur', $utilisateur);
        definir_reglage('smtp_expediteur', $utilisateur);
        if ($motDePasse !== '') {
            definir_reglage('smtp_mot_de_passe', $motDePasse);
        }
        $ok = envoyer_email(ADMIN_EMAIL, 'Test d\'envoi Idjô Académie', 'L\'envoi d\'e-mails fonctionne', '<p>Les e-mails de confirmation et les liens de connexion partent maintenant depuis ' . e($utilisateur) . '.</p>');
        flash($ok ? 'succes' : 'erreur', $ok ? 'Réglages enregistrés et e-mail de test envoyé à ' . ADMIN_EMAIL . '.' : 'Réglages enregistrés, mais l\'envoi a échoué : ' . reglage('dernier_echec_mail'));
    } elseif ($action === 'cinetpay') {
        if (isset($_POST['effacer_cinetpay'])) {
            definir_reglage('cinetpay_site_id', '');
            definir_reglage('cinetpay_apikey', '');
            flash('info', 'Paiement en ligne désactivé : les inscriptions repassent par WhatsApp.');
            rediriger('/admin/reglages.php');
        }
        $site = preg_replace('/\s+/', '', (string) ($_POST['cinetpay_site_id'] ?? ''));
        $cle = trim((string) ($_POST['cinetpay_apikey'] ?? ''));
        if (!preg_match('/^\d{3,12}$/', $site)) {
            flash('erreur', 'L\'ID de site CinetPay est un nombre (ex. 123456).');
            rediriger('/admin/reglages.php');
        }
        definir_reglage('cinetpay_site_id', $site);
        if ($cle !== '') {
            definir_reglage('cinetpay_apikey', $cle);
        }
        flash('succes', reglage('cinetpay_apikey') !== '' ? 'Paiement en ligne CinetPay activé.' : 'ID de site enregistré. Ajoute la clé API pour activer le paiement en ligne.');
    } elseif ($action === 'test_email') {
        $ok = envoyer_email(ADMIN_EMAIL, 'Test d\'envoi Idjô Académie', 'L\'envoi d\'e-mails fonctionne', '<p>Si tu lis ce message, les e-mails de confirmation et les liens de connexion partent bien.</p>');
        flash($ok ? 'succes' : 'erreur', $ok ? 'E-mail de test envoyé à ' . ADMIN_EMAIL . '. Regarde aussi dans les spams.' : 'L\'envoi a échoué. Vérifie la configuration e-mail de l\'hébergement.');
    }
    rediriger('/admin/reglages.php');
}

$googleId = reglage('google_client_id');
$google = google_identifiants();
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
    <h2 class="titre-bloc-app">Paiement en ligne (CinetPay)</h2>
    <?php $cinetpayActif = reglage('cinetpay_site_id') !== '' && reglage('cinetpay_apikey') !== ''; ?>
    <p class="petit"><?= $cinetpayActif
        ? 'Activé : les élèves paient sur la page de paiement (Wave, Orange Money, MTN, Moov, carte) et leur accès s\'ouvre tout seul.'
        : 'Pas encore activé : la page de paiement envoie les élèves sur WhatsApp. Colle ici l\'ID de site et la clé API de ton compte marchand CinetPay (rubrique Intégrations / API).' ?></p>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="cinetpay">
      <label class="champ"><span>ID de site (site_id)</span><input type="text" name="cinetpay_site_id" value="<?= e(reglage('cinetpay_site_id')) ?>" inputmode="numeric" autocomplete="off"></label>
      <label class="champ"><span>Clé API (apikey) <?= reglage('cinetpay_apikey') !== '' ? '<small>(enregistrée : laisse vide pour la garder)</small>' : '' ?></span><input type="password" name="cinetpay_apikey" autocomplete="new-password"></label>
      <?php if ($cinetpayActif): ?><label class="case"><input type="checkbox" name="effacer_cinetpay"> <span>Désactiver le paiement en ligne</span></label><?php endif; ?>
      <button class="bouton" type="submit">Enregistrer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">Connexion avec Google</h2>
    <p class="petit">État : <strong><?= google_configure() ? 'activée' : 'désactivée' ?></strong><?= $google['source'] === 'fichier' ? ' (clés lues dans le fichier JSON déposé dans le dossier app)' : '' ?></p>
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
    <h2 class="titre-bloc-app">Envoi des e-mails</h2>
    <?php if (reglage('smtp_hote') !== ''): ?>
      <p class="petit">Les e-mails partent de <strong><?= e(reglage('smtp_expediteur')) ?></strong> par <?= e(reglage('smtp_hote')) ?>.</p>
    <?php else: ?>
      <p class="petit">Les e-mails partent de l'hébergement (<code><?= e((string) conf('mail.expediteur')) ?></code>). Gmail les refuse souvent : pour qu'ils arrivent, envoie-les depuis ton Gmail avec un mot de passe d'application.</p>
    <?php endif; ?>
    <?php if (reglage('dernier_echec_mail') !== ''): ?><p class="petit">Dernier échec : <?= e(reglage('dernier_echec_mail')) ?></p><?php endif; ?>
    <form method="post" class="formulaire" autocomplete="off">
      <?= champ_csrf() ?><input type="hidden" name="action" value="smtp">
      <label class="champ"><span>Adresse qui envoie</span><input type="email" name="smtp_utilisateur" value="<?= e(reglage('smtp_utilisateur') ?: ADMIN_EMAIL) ?>"></label>
      <label class="champ"><span>Mot de passe d'application Google (16 lettres) <?= reglage('smtp_mot_de_passe') !== '' ? '<small>(enregistré : laisse vide pour le garder)</small>' : '' ?></span><input type="password" name="smtp_mot_de_passe" autocomplete="new-password"></label>
      <details class="petit"><summary>Serveur (déjà réglé pour Gmail)</summary>
        <div class="ligne-formule" style="margin-top:8px">
          <input type="text" name="smtp_hote" value="<?= e(reglage('smtp_hote') ?: 'smtp.gmail.com') ?>" aria-label="Serveur SMTP">
          <input type="text" name="smtp_port" value="<?= e(reglage('smtp_port') ?: '465') ?>" inputmode="numeric" aria-label="Port">
        </div>
      </details>
      <?php if (reglage('smtp_hote') !== ''): ?><label class="case"><input type="checkbox" name="effacer_smtp"> <span>Désactiver et revenir à l'envoi par l'hébergement</span></label><?php endif; ?>
      <button class="bouton" type="submit">Enregistrer et m'envoyer un test</button>
    </form>
    <p class="petit">Pour créer le mot de passe d'application : <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> (la validation en deux étapes doit être activée), nomme-le « Idjô Académie », puis colle les 16 lettres ici.</p>
    <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="test_email"><button class="lien-bouton" type="submit">M'envoyer un e-mail de test</button></form>
  </section>
</div>
<?php pied();
