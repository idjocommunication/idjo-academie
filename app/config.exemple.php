<?php
/**
 * Modèle de configuration. Sur le serveur, une copie nommée config.local.php
 * contient les vraies valeurs (elle n'est jamais envoyée sur GitHub).
 */
return [
    'url' => 'https://academie.xn--idjcommunication-pvb.com',
    'db' => [
        'hote' => '127.0.0.1',
        'port' => 3306,
        'nom' => '',
        'utilisateur' => '',
        'mot_de_passe' => '',
    ],
    'mail' => [
        'expediteur' => 'noreply@academie.xn--idjcommunication-pvb.com',
        'mode' => 'mail',          // « fichier » pour écrire les e-mails dans stockage/mails au lieu de les envoyer
    ],
    'whatsapp' => '2250747933748',
    'stockage' => null,            // dossier privé des vidéos ; vide = à côté de public_html
    'debug' => false,
];
