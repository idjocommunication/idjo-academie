<?php
/** Vidéo d'une leçon : seulement pour les comptes qui y ont accès. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/envoi_fichier.php';

$u = utilisateur();
$l = lecon_complete((int) ($_GET['l'] ?? 0));
if (!$l || !$l['video_fichier'] || !peut_voir_lecon($u, $l)) {
    http_response_code($u ? 403 : 401);
    exit('Accès refusé.');
}
envoyer_fichier(
    dossier_stockage('videos') . '/' . basename($l['video_fichier']),
    $l['video_type'] ?: 'video/mp4',
    slugifier($l['titre']) . '.' . pathinfo($l['video_fichier'], PATHINFO_EXTENSION),
    true
);
