<?php
/** Indique à la page d'accueil si le visiteur est connecté (pour afficher « Mon espace » au lieu de « Connexion »). */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

header('Cache-Control: no-store');
$u = utilisateur();
json(['connecte' => $u !== null, 'admin' => $u !== null && est_admin($u)]);
