<?php
/** Outils communs aux pages de connexion et d'inscription. */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Identifiants Google : ceux de Admin > Réglages, sinon le fichier JSON téléchargé depuis Google Cloud
 * (client_secret_….json) déposé dans le dossier app/ du site (dossier fermé au web).
 */
function google_identifiants(): array
{
    if (reglage('google_client_id') !== '' && reglage('google_client_secret') !== '') {
        return ['id' => reglage('google_client_id'), 'secret' => reglage('google_client_secret'), 'source' => 'reglages'];
    }
    foreach (array_merge(glob(__DIR__ . '/client_secret*.json') ?: [], glob(__DIR__ . '/google-client*.json') ?: []) as $fichier) {
        $donnees = json_decode((string) @file_get_contents($fichier), true);
        $web = $donnees['web'] ?? null;
        if (!empty($web['client_id']) && !empty($web['client_secret'])) {
            return ['id' => (string) $web['client_id'], 'secret' => (string) $web['client_secret'], 'source' => 'fichier'];
        }
    }
    return ['id' => '', 'secret' => '', 'source' => ''];
}

function google_configure(): bool
{
    $g = google_identifiants();
    return $g['id'] !== '' && $g['secret'] !== '';
}

function bouton_google(string $suite = ''): string
{
    if (!google_configure()) {
        return '';
    }
    $lien = '/compte/google.php' . ($suite !== '' ? '?suite=' . rawurlencode($suite) : '');
    return '<a class="bouton-google" href="' . e($lien) . '">'
        . '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'
        . '<span>Continuer avec Google</span></a>'
        . '<div class="separateur"><span>ou</span></div>';
}

function envoyer_verification(string $email): void
{
    $jeton = creer_jeton($email, 'verification', 60 * 24);
    $lien = url('compte/verifier.php?type=verification&jeton=' . $jeton);
    envoyer_email(
        $email,
        'Confirme ton adresse e-mail',
        'Bienvenue sur Idjô Académie',
        '<p>Il reste une étape : confirme ton adresse pour activer ton compte gratuit.</p><p>Ce lien est valable 24 heures.</p>',
        'Confirmer mon adresse',
        $lien
    );
}

function envoyer_lien_magique(string $email): void
{
    $jeton = creer_jeton($email, 'magique', 20);
    $lien = url('compte/verifier.php?type=magique&jeton=' . $jeton);
    envoyer_email(
        $email,
        'Ton lien de connexion',
        'Ton lien de connexion',
        '<p>Clique sur le bouton pour te connecter à Idjô Académie, sans mot de passe. Si tu n\'as pas encore de compte, il sera créé gratuitement.</p><p>Ce lien est valable 20 minutes et ne sert qu\'une fois.</p>',
        'Me connecter',
        $lien
    );
}

function envoyer_reinitialisation(string $email): void
{
    $jeton = creer_jeton($email, 'reinitialisation', 60);
    $lien = url('compte/nouveau-mot-de-passe.php?jeton=' . $jeton);
    envoyer_email(
        $email,
        'Choisis un nouveau mot de passe',
        'Nouveau mot de passe',
        '<p>Tu as demandé à changer ton mot de passe. Clique sur le bouton pour en choisir un nouveau.</p><p>Ce lien est valable 1 heure.</p>',
        'Choisir un nouveau mot de passe',
        $lien
    );
}

/** Après une connexion réussie : vers la page demandée, l'admin ou l'espace élève. */
function apres_connexion(array $u, ?string $suite = null): never
{
    connecter($u);
    $suite = $suite ?? ($_SESSION['suite'] ?? null);
    unset($_SESSION['suite']);
    rediriger(chemin_suite($suite, est_admin($u) ? '/admin/' : '/espace/'));
}

function email_valide(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190;
}

/** Retient la page à afficher après connexion, et la formule choisie sur la vitrine. */
function retenir_suite(): string
{
    $suite = chemin_suite($_GET['suite'] ?? $_POST['suite'] ?? null, '');
    if ($suite !== '') {
        $_SESSION['suite'] = $suite;
    }
    if (!empty($_GET['formule']) && isset(formules()[$_GET['formule']])) {
        $_SESSION['formule'] = $_GET['formule'];
    }
    return $suite;
}

function carte_auth_debut(string $titre, string $sousTitre = ''): void
{
    echo '<section class="carte-auth"><h1 class="titre-app">' . e($titre) . '</h1>';
    if ($sousTitre !== '') {
        echo '<p class="carte-auth__sous">' . $sousTitre . '</p>';
    }
}
