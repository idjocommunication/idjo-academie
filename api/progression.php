<?php
/** Enregistre la position dans la vidéo et l'état « terminée » d'une leçon. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = utilisateur();
$enJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
if (!$u) {
    $enJson ? json(['erreur' => 'connexion'], 401) : rediriger('compte/connexion.php');
}
if (!est_post()) {
    json(['erreur' => 'methode'], 405);
}
verifier_csrf();
$l = lecon_complete((int) ($_POST['lecon'] ?? 0));
if (!$l || !peut_voir_lecon($u, $l)) {
    $enJson ? json(['erreur' => 'acces'], 403) : rediriger('/espace/');
}

$position = isset($_POST['position']) ? max(0, (int) $_POST['position']) : null;
$terminee = $_POST['terminee'] ?? null;   // '1', '0' ou absent

$st = db()->prepare('SELECT * FROM progression WHERE utilisateur_id = ? AND lecon_id = ?');
$st->execute([$u['id'], $l['id']]);
$actuelle = $st->fetch();

$nouvellePosition = $position ?? (int) ($actuelle['position_sec'] ?? 0);
$nouvelleFin = $actuelle['terminee_le'] ?? null;
if ($terminee === '1') {
    $nouvelleFin = $nouvelleFin ?? maintenant();
} elseif ($terminee === '0') {
    $nouvelleFin = null;
}

db()->prepare('INSERT INTO progression (utilisateur_id, lecon_id, position_sec, terminee_le, modifie_le) VALUES (?,?,?,?,?)
    ON DUPLICATE KEY UPDATE position_sec = VALUES(position_sec), terminee_le = VALUES(terminee_le), modifie_le = VALUES(modifie_le)')
    ->execute([$u['id'], $l['id'], $nouvellePosition, $nouvelleFin, maintenant()]);

if ($enJson) {
    [$faites, $total, $pct] = progression_formation((int) $u['id'], (int) $l['formation_id']);
    json(['ok' => true, 'terminee' => $nouvelleFin !== null, 'pourcentage' => $pct]);
}
rediriger('/espace/lecon.php?id=' . (int) $l['id']);
