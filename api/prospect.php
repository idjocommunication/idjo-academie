<?php
/**
 * Reçoit le formulaire d'inscription de la page d'accueil (nom, WhatsApp, e-mail).
 * Enregistre le contact (Admin > Contacts), crée le compte élève (sans accès aux vidéos tant
 * qu'il n'a pas payé), envoie un e-mail de bienvenue à l'élève et prévient l'admin.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

header('Cache-Control: no-store');
if (!est_post()) {
    json(['erreur' => 'Méthode non autorisée.'], 405);
}

// Même site seulement (le formulaire est sur la page d'accueil).
$origine = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origine !== '' && preg_replace('#^https?://#', '', $origine) !== ($_SERVER['HTTP_HOST'] ?? '')) {
    json(['erreur' => 'Requête refusée.'], 403);
}

$donnees = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($donnees)) {
    $donnees = $_POST;
}

// Champ piège invisible : rempli seulement par les robots.
if (trim((string) ($donnees['site'] ?? '')) !== '') {
    json(['ok' => true, 'prenom' => '']);
}

$nom = trim(preg_replace('/\s+/u', ' ', (string) ($donnees['nom'] ?? '')));
if (mb_strlen($nom) < 2 || mb_strlen($nom) > 80) {
    json(['erreur' => 'Écris ton nom (2 caractères au moins).', 'champ' => 'nom'], 422);
}

$whatsapp = normaliser_whatsapp((string) ($donnees['whatsapp'] ?? ''));
if ($whatsapp === null) {
    json(['erreur' => 'Ce numéro WhatsApp ne semble pas valide. Exemple : 07 47 93 37 48.', 'champ' => 'whatsapp'], 422);
}

$email = mb_strtolower(trim((string) ($donnees['email'] ?? '')));
if (!email_valide($email)) {
    json(['erreur' => 'Cette adresse e-mail ne semble pas valide. Exemple : prenom@gmail.com.', 'champ' => 'email'], 422);
}
if ($email === ADMIN_EMAIL) {
    json(['erreur' => 'Cette adresse est celle de l\'administrateur : utilise une autre adresse.', 'champ' => 'email'], 422);
}

$source = in_array($donnees['source'] ?? '', ['haut', 'bas'], true) ? $donnees['source'] : '';

if (!limite_ok('prospect', '', 6, 60)) {
    json(['erreur' => 'Trop d\'envois depuis cette connexion. Réessaie dans une heure ou écris-nous sur WhatsApp.'], 429);
}

$prenom = explode(' ', $nom)[0];
$maintenant = maintenant();

// Compte élève : créé tout de suite avec l'e-mail (l'accès aux vidéos s'ouvre au paiement).
$eleve = utilisateur_par_email($email);
if (!$eleve) {
    $eleve = creer_utilisateur($email, $nom);
    $_SESSION['comptes_crees'][] = (int) $eleve['id']; // compte créé dans ce navigateur
} elseif ($eleve['nom'] === '') {
    db()->prepare('UPDATE utilisateurs SET nom = ? WHERE id = ?')->execute([$nom, $eleve['id']]);
}
// Pour la page de paiement : les champs y sont déjà remplis.
$_SESSION['inscription'] = ['nom' => $nom, 'email' => $email, 'whatsapp' => $whatsapp];

$st = db()->prepare('SELECT id FROM prospects WHERE (whatsapp = ? OR email = ?) AND cree_le > ? ORDER BY id DESC LIMIT 1');
$st->execute([$whatsapp, $email, date('Y-m-d H:i:s', time() - 86400)]);
$existant = $st->fetchColumn();
if ($existant) {
    // Déjà reçu dans les dernières 24 h : mise à jour, sans nouveaux e-mails.
    db()->prepare('UPDATE prospects SET nom = ?, whatsapp = ?, email = ?, modifie_le = ? WHERE id = ?')
        ->execute([$nom, $whatsapp, $email, $maintenant, $existant]);
    json(['ok' => true, 'prenom' => $prenom]);
}

db()->prepare('INSERT INTO prospects (nom, whatsapp, email, source, statut, cree_le, modifie_le) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$nom, $whatsapp, $email, $source, 'nouveau', $maintenant, $maintenant]);

// Le visiteur part tout de suite vers la page de vente ; les e-mails partent juste après.
json_puis_continuer(['ok' => true, 'prenom' => $prenom]);

$lienWa = lien_whatsapp('Bonjour, je suis ' . $nom . '. Je viens de m\'inscrire sur le site d\'Idjô Académie.');
envoyer_email(
    $email,
    'Bienvenue ' . $prenom . ' : ton inscription à Idjô Académie est confirmée',
    'Merci ' . $prenom . ', ton inscription est confirmée !',
    '<p>Bienvenue chez Idjô Académie. Ton compte élève est lié à cette adresse e-mail : <strong>' . e($email) . '</strong>.</p>'
        . '<p>Découvre la formation « Apprendre à gagner de l\'argent grâce à l\'IA » (4 modules, 26 leçons vidéo) et choisis ta formule. Tes vidéos s\'ouvrent dès que ton paiement est confirmé.</p>',
    'Découvrir la formation',
    url('vente'),
    '<p style="margin:0 0 14px">Une question ? Écris-nous sur WhatsApp, on te répond vite.</p>'
        . '<p style="margin:0"><a href="' . e($lienWa) . '" style="background:#25D366;color:#fff;text-decoration:none;padding:11px 20px;border-radius:999px;font-weight:600;display:inline-block">Écrire sur WhatsApp</a></p>'
);

envoyer_email(
    ADMIN_EMAIL,
    'Nouvelle inscription : ' . $nom,
    'Nouvelle inscription sur Idjô Académie',
    '<p><strong>' . e($nom) . '</strong> vient de s\'inscrire sur l\'accueil.</p>'
        . '<p>WhatsApp : <strong>' . e(formater_whatsapp($whatsapp)) . '</strong><br>E-mail : <strong>' . e($email) . '</strong></p>'
        . '<p>Écris-lui vite : les contacts répondent mieux dans l\'heure.</p>',
    'Voir les contacts',
    url('admin/contacts.php')
);
