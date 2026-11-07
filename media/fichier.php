<?php
/** Fichier joint à une leçon (PDF, modèle…) : seulement pour les comptes qui ont accès à la leçon. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/envoi_fichier.php';

$u = utilisateur();
$st = db()->prepare('SELECT * FROM fichiers WHERE id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$fi = $st->fetch();
$l = $fi ? lecon_complete((int) $fi['lecon_id']) : null;
if (!$fi || !$l || !peut_voir_lecon($u, $l)) {
    http_response_code($u ? 403 : 401);
    exit('Accès refusé.');
}
$extension = pathinfo($fi['fichier'], PATHINFO_EXTENSION);
$nom = $fi['nom'];
if ($extension !== '' && !str_ends_with(mb_strtolower($nom), '.' . mb_strtolower($extension))) {
    $nom .= '.' . $extension;
}
envoyer_fichier(dossier_stockage('fichiers') . '/' . basename($fi['fichier']), $fi['type'], $nom, $fi['type'] === 'application/pdf');
