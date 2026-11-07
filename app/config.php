<?php
/**
 * Réglages du site (aucun secret ici : ce fichier est public sur GitHub).
 * Les clés Google se règlent dans l'admin, page « Réglages ».
 * Pour tester sur un ordinateur, un fichier config.local.php (non envoyé sur GitHub) peut remplacer ces valeurs.
 */
return [
    'url' => 'https://academie.xn--idjcommunication-pvb.com',
    'db' => [
        'type' => 'sqlite',        // fichier privé dans le dossier de stockage ; « mysql » possible avec hote, nom, utilisateur, mot_de_passe
    ],
    'mail' => [
        'expediteur' => 'noreply@academie.xn--idjcommunication-pvb.com',
        'mode' => 'mail',          // « fichier » : écrit les e-mails dans le stockage au lieu de les envoyer (tests)
    ],
    'whatsapp' => '2250747933748',
    'stockage' => null,            // vide = dossier « stockage-academie » à côté de public_html, hors d'atteinte du web
    'debug' => false,
];
