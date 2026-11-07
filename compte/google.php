<?php
/** Départ vers Google pour « Continuer avec Google ». */
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

if (!google_configure()) {
    flash('erreur', 'La connexion Google n\'est pas encore disponible. Utilise ton e-mail.');
    rediriger('compte/connexion.php');
}
retenir_suite();
$etat = bin2hex(random_bytes(16));
$verificateur = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$_SESSION['google_etat'] = $etat;
$_SESSION['google_verificateur'] = $verificateur;

$params = [
    'client_id' => reglage('google_client_id'),
    'redirect_uri' => url('compte/google-retour.php'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $etat,
    'prompt' => 'select_account',
    'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verificateur, true)), '+/', '-_'), '='),
    'code_challenge_method' => 'S256',
];
rediriger('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
