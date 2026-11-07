<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

if (!est_post()) {
    rediriger('compte/connexion.php');
}
verifier_csrf();
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
if (email_valide($email) && limite_ok('renvoi', $email, 4, 60)) {
    $u = utilisateur_par_email($email);
    if ($u && $u['email_verifie_le'] === null && $u['mot_de_passe'] !== null) {
        envoyer_verification($email);
    }
    flash('info', 'Si un compte attend une confirmation avec cette adresse, un nouveau lien vient d\'être envoyé.');
} else {
    flash('erreur', 'Trop de demandes. Réessaie dans une heure, ou demande un lien de connexion.');
}
rediriger('compte/connexion.php?email=' . rawurlencode($email));
