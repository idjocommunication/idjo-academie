<?php
/** Retour de Google : vérifie la réponse, puis connecte ou crée le compte. */
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

function echec_google(string $message): never
{
    flash('erreur', $message);
    rediriger('compte/connexion.php');
}

function requete_google(string $url, ?array $post = null, ?string $jeton = null): ?array
{
    $ch = curl_init($url);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15];
    if ($post !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    if ($jeton !== null) {
        $options[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $jeton];
    }
    curl_setopt_array($ch, $options);
    $reponse = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $donnees = is_string($reponse) ? json_decode($reponse, true) : null;
    return $code === 200 && is_array($donnees) ? $donnees : null;
}

if (!google_configure()) {
    echec_google('La connexion Google n\'est pas encore disponible.');
}
if (isset($_GET['error'])) {
    echec_google('Connexion Google annulée.');
}
$etat = (string) ($_GET['state'] ?? '');
if ($etat === '' || empty($_SESSION['google_etat']) || !hash_equals($_SESSION['google_etat'], $etat)) {
    echec_google('La connexion Google a expiré. Réessaie.');
}
$verificateur = (string) ($_SESSION['google_verificateur'] ?? '');
unset($_SESSION['google_etat'], $_SESSION['google_verificateur']);

$jetons = requete_google('https://oauth2.googleapis.com/token', [
    'code' => (string) ($_GET['code'] ?? ''),
    'client_id' => reglage('google_client_id'),
    'client_secret' => reglage('google_client_secret'),
    'redirect_uri' => url('compte/google-retour.php'),
    'grant_type' => 'authorization_code',
    'code_verifier' => $verificateur,
]);
if (!$jetons || empty($jetons['access_token'])) {
    echec_google('Google n\'a pas validé la connexion. Réessaie.');
}
$profil = requete_google('https://openidconnect.googleapis.com/v1/userinfo', null, $jetons['access_token']);
if (!$profil || empty($profil['sub']) || empty($profil['email']) || empty($profil['email_verified'])) {
    echec_google('Ton compte Google n\'a pas d\'adresse e-mail confirmée.');
}

$email = mb_strtolower($profil['email']);
$st = db()->prepare('SELECT * FROM utilisateurs WHERE google_sub = ?');
$st->execute([$profil['sub']]);
$u = $st->fetch() ?: utilisateur_par_email($email);

if ($u) {
    if ($u['google_sub'] === null) {
        db()->prepare('UPDATE utilisateurs SET google_sub = ? WHERE id = ?')->execute([$profil['sub'], $u['id']]);
    }
    if ($u['nom'] === '' && !empty($profil['name'])) {
        db()->prepare('UPDATE utilisateurs SET nom = ? WHERE id = ?')->execute([mb_substr($profil['name'], 0, 120), $u['id']]);
    }
    $u = marquer_verifie(utilisateur_par_email($u['email']), false);
} else {
    $u = creer_utilisateur($email, mb_substr((string) ($profil['name'] ?? ''), 0, 120), null, true);
    db()->prepare('UPDATE utilisateurs SET google_sub = ? WHERE id = ?')->execute([$profil['sub'], $u['id']]);
}
if ($u['bloque']) {
    echec_google('Ce compte est suspendu. Contacte-nous sur WhatsApp.');
}
apres_connexion($u);
