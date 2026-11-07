<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (est_post()) {
    verifier_csrf();
    deconnecter();
    flash('info', 'Tu es déconnecté. À bientôt !');
}
rediriger('compte/connexion.php');
