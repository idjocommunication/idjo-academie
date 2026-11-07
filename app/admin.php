<?php
/** Outils communs aux pages de l'admin (réservées à idjocommunication@gmail.com). */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin_api.php';

$GLOBALS['admin'] = exiger_admin();

function admin_entete(string $titre, string $actif, array $scripts = []): void
{
    entete($titre, ['zone' => 'admin', 'large' => true, 'classe' => 'page-admin', 'scripts' => $scripts]);
    $liens = [
        'tableau' => ['/admin/', 'Tableau de bord'],
        'formations' => ['/admin/formations.php', 'Formations'],
        'eleves' => ['/admin/eleves.php', 'Élèves'],
        'reglages' => ['/admin/reglages.php', 'Réglages'],
    ];
    echo '<nav class="sous-nav-admin" aria-label="Admin">';
    foreach ($liens as $cle => [$href, $texte]) {
        echo '<a href="' . $href . '"' . ($cle === $actif ? ' aria-current="page"' : '') . '>' . $texte . '</a>';
    }
    echo '</nav>';
}

/** Déplace un élément d'un cran vers le haut (-1) ou le bas (+1) parmi ses voisins. */
function deplacer(string $table, int $id, int $sens, string $colonneParent): void
{
    $tables = ['modules' => true, 'lecons' => true, 'formations' => true];
    if (!isset($tables[$table]) || !in_array($colonneParent, ['formation_id', 'module_id', ''], true)) {
        return;
    }
    $st = db()->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    $ligne = $st->fetch();
    if (!$ligne) {
        return;
    }
    $condition = $colonneParent !== '' ? "$colonneParent = " . (int) $ligne[$colonneParent] : '1 = 1';
    $freres = db()->query("SELECT id FROM $table WHERE $condition ORDER BY ordre, id")->fetchAll(PDO::FETCH_COLUMN);
    $freres = array_map('intval', $freres);
    $pos = array_search($id, $freres, true);
    $cible = $pos + $sens;
    if ($pos === false || $cible < 0 || $cible >= count($freres)) {
        return;
    }
    [$freres[$pos], $freres[$cible]] = [$freres[$cible], $freres[$pos]];
    $maj = db()->prepare("UPDATE $table SET ordre = ? WHERE id = ?");
    foreach ($freres as $i => $fid) {
        $maj->execute([$i + 1, $fid]);
    }
}

function prochain_ordre(string $table, string $colonneParent, int $parentId): int
{
    $st = db()->prepare("SELECT COALESCE(MAX(ordre), 0) + 1 FROM $table WHERE $colonneParent = ?");
    $st->execute([$parentId]);
    return (int) $st->fetchColumn();
}

function accorder_acces(int $uid, int $formationId, string $formule, string $note = ''): void
{
    db()->prepare('INSERT INTO acces (utilisateur_id, formation_id, formule, actif, note, accorde_le) VALUES (?,?,?,1,?,?)
        ON DUPLICATE KEY UPDATE actif = 1, formule = VALUES(formule), note = VALUES(note), accorde_le = VALUES(accorde_le)')
        ->execute([$uid, $formationId, $formule, $note, maintenant()]);
    db()->prepare("UPDATE demandes SET statut = 'acceptee' WHERE utilisateur_id = ? AND formation_id = ? AND statut = 'en_attente'")
        ->execute([$uid, $formationId]);
    $st = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $st->execute([$uid]);
    $eleve = $st->fetch();
    $f = formation_par_id($formationId);
    if ($eleve && $f) {
        envoyer_email(
            $eleve['email'],
            'Ta formation est débloquée',
            'C\'est parti, ' . nom_affiche($eleve) . ' !',
            '<p>Ton accès à <strong>' . e($f['titre']) . '</strong> est activé' . ($formule !== '' ? ' (formule ' . e($formule) . ')' : '') . '. Toutes les leçons sont disponibles dans ton espace.</p>',
            'Commencer la formation',
            url('espace/formation.php?f=' . rawurlencode($f['slug']))
        );
    }
}

function compter(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
}
