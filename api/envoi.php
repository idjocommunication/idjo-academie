<?php
/**
 * Import de vidéos et de fichiers par morceaux (admin seulement).
 * Le navigateur envoie le fichier en morceaux (5 Mo au plus, selon les limites du serveur) ; si la connexion coupe, l'envoi reprend au bon endroit.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/admin_api.php';

$u = utilisateur();
if (!est_admin($u)) {
    json(['erreur' => 'Accès réservé à l\'administrateur.'], 403);
}
if (!est_post()) {
    json(['erreur' => 'Méthode non autorisée.'], 405);
}
verifier_csrf();

$cible = (string) ($_POST['cible'] ?? '');
$regles = [
    'video' => ['max' => 4 * 1073741824, 'extensions' => ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime']],
    'fichier' => ['max' => 200 * 1048576, 'extensions' => [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'txt' => 'text/plain',
    ]],
];
if (!isset($regles[$cible])) {
    json(['erreur' => 'Type d\'envoi inconnu.'], 400);
}
$regle = $regles[$cible];
$l = lecon_complete((int) ($_POST['lecon'] ?? 0));
if (!$l) {
    json(['erreur' => 'Leçon introuvable.'], 404);
}
$envoi = (string) ($_POST['envoi'] ?? '');
if (!preg_match('/^[a-f0-9]{32,64}$/', $envoi)) {
    json(['erreur' => 'Identifiant d\'envoi incorrect.'], 400);
}
$nom = trim(str_replace(["\0", '/', '\\'], '', (string) ($_POST['nom'] ?? 'fichier')));
$extension = mb_strtolower(pathinfo($nom, PATHINFO_EXTENSION));
if (!isset($regle['extensions'][$extension])) {
    json(['erreur' => 'Format refusé (.' . $extension . '). Formats acceptés : ' . implode(', ', array_keys($regle['extensions'])) . '.'], 415);
}
$taille = (int) ($_POST['taille'] ?? 0);
if ($taille <= 0 || $taille > $regle['max']) {
    json(['erreur' => 'Fichier trop lourd (maximum ' . taille_texte($regle['max']) . ').'], 413);
}

$temporaire = dossier_stockage('tmp') . '/' . $envoi . '.part';
clearstatcache(true, $temporaire);
$recu = is_file($temporaire) ? (int) filesize($temporaire) : 0;

if (($_POST['action'] ?? '') === 'etat') {
    json(['recu' => $recu]);
}

$debut = (int) ($_POST['debut'] ?? -1);
if ($debut !== $recu) {
    json(['erreur' => 'decalage', 'recu' => $recu], 409);
}
$morceau = $_FILES['morceau'] ?? null;
if (!$morceau || $morceau['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($morceau['tmp_name'])) {
    $code = $morceau['error'] ?? 0;
    json(['erreur' => in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'Le serveur refuse des morceaux de cette taille. Recharge la page et réessaie.'
        : 'Morceau non reçu (code ' . $code . '). Réessaie.'], 400);
}
if ($morceau['size'] > 16 * 1048576 || $recu + $morceau['size'] > $taille) {
    json(['erreur' => 'Morceau trop gros.'], 400);
}
$sortie = fopen($temporaire, 'ab');
$entree = fopen($morceau['tmp_name'], 'rb');
stream_copy_to_stream($entree, $sortie);
fclose($entree);
fclose($sortie);
clearstatcache(true, $temporaire);
$recu = (int) filesize($temporaire);

if ($recu < $taille) {
    json(['recu' => $recu]);
}

// Fichier complet : contrôle du contenu, puis rangement.
$detecte = (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporaire);
$suspect = preg_match('/php|html|javascript|x-sh|x-executable|x-msdownload/i', $detecte);
if ($suspect || ($cible === 'video' && !str_starts_with($detecte, 'video/') && $detecte !== 'application/octet-stream')) {
    @unlink($temporaire);
    json(['erreur' => 'Ce fichier ne ressemble pas à une ' . ($cible === 'video' ? 'vidéo' : 'pièce jointe') . ' valide (' . $detecte . ').'], 415);
}
$nomFinal = $l['id'] . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
$dossier = dossier_stockage($cible === 'video' ? 'videos' : 'fichiers');
if (!rename($temporaire, $dossier . '/' . $nomFinal)) {
    json(['erreur' => 'Impossible d\'enregistrer le fichier sur le serveur.'], 500);
}
nettoyer_envois_abandonnes();

if ($cible === 'video') {
    supprimer_fichier_prive('videos', $l['video_fichier']);
    $duree = (int) round((float) ($_POST['duree'] ?? 0));
    db()->prepare('UPDATE lecons SET video_fichier = ?, video_taille = ?, video_type = ?, duree_sec = IF(? > 0, ?, duree_sec) WHERE id = ?')
        ->execute([$nomFinal, $taille, $regle['extensions'][$extension], $duree, $duree, $l['id']]);
} else {
    $nomAffiche = trim(pathinfo($nom, PATHINFO_FILENAME)) ?: 'Fichier';
    db()->prepare('INSERT INTO fichiers (lecon_id, nom, fichier, taille, type, cree_le) VALUES (?,?,?,?,?,?)')
        ->execute([$l['id'], mb_substr($nomAffiche, 0, 200), $nomFinal, $taille, $regle['extensions'][$extension], maintenant()]);
}
json(['recu' => $recu, 'termine' => true]);
