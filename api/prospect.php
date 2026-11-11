<?php
/**
 * Reçoit le formulaire « Nom + WhatsApp » de la page d'accueil.
 * Enregistre le contact (visible dans Admin > Contacts) et prévient l'admin par e-mail.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

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

$source = in_array($donnees['source'] ?? '', ['haut', 'bas'], true) ? $donnees['source'] : '';

if (!limite_ok('prospect', '', 6, 60)) {
    json(['erreur' => 'Trop d\'envois depuis cette connexion. Réessaie dans une heure ou écris-nous sur WhatsApp.'], 429);
}

$prenom = explode(' ', $nom)[0];
$maintenant = maintenant();
$st = db()->prepare('SELECT id FROM prospects WHERE whatsapp = ? AND cree_le > ? ORDER BY id DESC LIMIT 1');
$st->execute([$whatsapp, date('Y-m-d H:i:s', time() - 86400)]);
$existant = $st->fetchColumn();
if ($existant) {
    // Déjà reçu dans les dernières 24 h : on met juste le nom à jour, sans nouvelle alerte.
    db()->prepare('UPDATE prospects SET nom = ?, modifie_le = ? WHERE id = ?')->execute([$nom, $maintenant, $existant]);
    json(['ok' => true, 'prenom' => $prenom]);
}

db()->prepare('INSERT INTO prospects (nom, whatsapp, source, statut, cree_le, modifie_le) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$nom, $whatsapp, $source, 'nouveau', $maintenant, $maintenant]);

envoyer_email(
    ADMIN_EMAIL,
    'Nouveau contact : ' . $nom,
    'Nouveau contact sur Idjô Académie',
    '<p><strong>' . e($nom) . '</strong> a laissé son numéro WhatsApp : <strong>' . e(formater_whatsapp($whatsapp)) . '</strong>.</p>'
        . '<p>Écris-lui vite : les contacts répondent mieux dans l\'heure.</p>',
    'Voir les contacts',
    url('admin/contacts.php')
);

json(['ok' => true, 'prenom' => $prenom]);
