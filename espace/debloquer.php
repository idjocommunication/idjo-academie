<?php
/** Enregistre la demande de déblocage, prévient l'admin, puis ouvre WhatsApp avec le message prêt. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = exiger_connexion();
if (!est_post()) {
    rediriger('/espace/');
}
verifier_csrf();
$f = formation_par_id((int) ($_POST['formation'] ?? 0));
if (!$f || !$f['publiee']) {
    page_erreur('Formation introuvable', 'Cette formation n\'est pas disponible.');
}
if (a_acces($u, (int) $f['id'])) {
    rediriger('/espace/formation.php?f=' . rawurlencode($f['slug']));
}
$formule = null;
foreach (formules_formation($f) as $fo) {
    if ($fo['id'] === ($_POST['formule'] ?? '')) {
        $formule = $fo;
    }
}
$formule = $formule ?? formules_formation($f)[0];

$st = db()->prepare("SELECT id FROM demandes WHERE utilisateur_id = ? AND formation_id = ? AND statut = 'en_attente'");
$st->execute([$u['id'], $f['id']]);
$existante = $st->fetchColumn();
if ($existante) {
    db()->prepare('UPDATE demandes SET formule = ?, cree_le = ? WHERE id = ?')->execute([$formule['nom'], maintenant(), $existante]);
} else {
    db()->prepare("INSERT INTO demandes (utilisateur_id, formation_id, formule, statut, cree_le) VALUES (?,?,?,'en_attente',?)")
        ->execute([$u['id'], $f['id'], $formule['nom'], maintenant()]);
    if (limite_ok('alerte_demande', (string) $u['id'], 3, 60)) {
        envoyer_email(
            ADMIN_EMAIL,
            'Nouvelle demande : ' . $f['titre'],
            'Nouvelle demande de déblocage',
            '<p><strong>' . e(nom_affiche($u)) . '</strong> (' . e($u['email']) . ') veut débloquer <strong>' . e($f['titre']) . '</strong>, formule ' . e($formule['nom']) . ' (' . e(prix($formule['prix'])) . ').</p><p>Après réception du paiement, active son accès dans l\'admin.</p>',
            'Ouvrir l\'admin',
            url('admin/')
        );
    }
}
unset($_SESSION['formule']);

$message = "Bonjour, je veux débloquer la formation « {$f['titre']} », formule {$formule['nom']} (" . prix($formule['prix']) . ").\n"
    . "Mon compte Idjô Académie : {$u['email']}";
rediriger(lien_whatsapp($message));
