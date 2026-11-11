<?php
/**
 * Idjô Académie : chargement commun (configuration, session, base de données, outils).
 * Chaque page PHP commence par : require __DIR__ . '/../app/bootstrap.php';
 */
declare(strict_types=1);

/** Seul compte autorisé à entrer dans l'admin (e-mail vérifié obligatoire). */
const ADMIN_EMAIL = 'idjocommunication@gmail.com';
const RACINE = __DIR__ . '/..';
const VERSION_SCHEMA = '4';

date_default_timezone_set('Africa/Abidjan');
mb_internal_encoding('UTF-8');

$GLOBALS['CONFIG'] = require __DIR__ . '/config.php';
if (is_file(__DIR__ . '/config.local.php')) {
    $GLOBALS['CONFIG'] = array_replace_recursive($GLOBALS['CONFIG'], require __DIR__ . '/config.local.php');
}

/** Lit une valeur de configuration, ex. conf('db.nom'). */
function conf(string $cle, $defaut = null)
{
    $valeur = $GLOBALS['CONFIG'];
    foreach (explode('.', $cle) as $morceau) {
        if (!is_array($valeur) || !array_key_exists($morceau, $valeur)) {
            return $defaut;
        }
        $valeur = $valeur[$morceau];
    }
    return $valeur;
}

if (conf('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/* ---------- Stockage privé (vidéos, PDF, sessions) hors du dossier public si possible ---------- */
function dossier_stockage(string $sous = ''): string
{
    static $base = null;
    if ($base === null) {
        $base = conf('stockage') ?: dirname(realpath(RACINE) ?: RACINE) . '/stockage-academie';
        if (!is_dir($base) && !@mkdir($base, 0750, true)) {
            $base = RACINE . '/stockage';
            if (!is_dir($base)) {
                mkdir($base, 0750, true);
            }
        }
        if (!is_file($base . '/.htaccess')) {
            @file_put_contents($base . '/.htaccess', "Require all denied\n");
        }
    }
    $chemin = $sous === '' ? $base : $base . '/' . trim($sous, '/');
    if (!is_dir($chemin)) {
        mkdir($chemin, 0750, true);
    }
    return $chemin;
}

/* ---------- Session ---------- */
function est_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || str_starts_with((string) conf('url', ''), 'https://');
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $duree = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string) $duree);
    ini_set('session.use_strict_mode', '1');
    session_save_path(dossier_stockage('sessions'));
    session_name('idjo_session');
    session_set_cookie_params([
        'lifetime' => $duree,
        'path' => '/',
        'secure' => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Base de données (SQLite par défaut, MySQL possible) ---------- */
function pilote(): string
{
    return conf('db.type', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (pilote() === 'mysql') {
            $c = conf('db');
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['hote'] ?? '127.0.0.1', $c['port'] ?? 3306, $c['nom']),
                $c['utilisateur'],
                $c['mot_de_passe'],
                $options
            );
        } else {
            $pdo = new PDO('sqlite:' . dossier_stockage('base') . '/academie.sqlite', null, null, $options);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 8000');
        }
        installer_si_besoin($pdo);
    }
    return $pdo;
}

function installer_si_besoin(PDO $pdo): void
{
    try {
        $v = $pdo->query("SELECT valeur FROM reglages WHERE cle = 'version_schema'")->fetchColumn();
        if ($v === VERSION_SCHEMA) {
            return;
        }
    } catch (PDOException $e) {
        // Base vide : on la crée.
    }
    $sql = file_get_contents(__DIR__ . (pilote() === 'mysql' ? '/schema.sql' : '/schema-sqlite.sql'));
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $requete) {
        $pdo->exec($requete);
    }
    // Colonnes ajoutées après coup (les tables déjà créées ne les ont pas encore).
    ajouter_colonne($pdo, 'prospects', 'email', pilote() === 'mysql' ? "VARCHAR(190) NOT NULL DEFAULT '' AFTER whatsapp" : "TEXT NOT NULL DEFAULT ''");
    $pdo->prepare("REPLACE INTO reglages (cle, valeur) VALUES ('version_schema', ?)")->execute([VERSION_SCHEMA]);
    if ((int) $pdo->query('SELECT COUNT(*) FROM formations')->fetchColumn() === 0) {
        importer_formation_initiale($pdo);
    }
}

function ajouter_colonne(PDO $pdo, string $table, string $colonne, string $definition): void
{
    $colonnes = pilote() === 'mysql'
        ? $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN)
        : array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    if (!in_array($colonne, $colonnes, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $colonne $definition");
    }
}

/** Importe la formation « Gagner de l'argent grâce à l'IA » (4 modules, 26 leçons) sans vidéo. */
function importer_formation_initiale(PDO $pdo): void
{
    $f = json_decode((string) file_get_contents(__DIR__ . '/donnees/formation-ia.json'), true);
    if (!$f) {
        return;
    }
    $maintenant = maintenant();
    $pdo->prepare('INSERT INTO formations (slug, titre, sous_titre, description, prix_fcfa, formules, image, publiee, ordre, cree_le, modifie_le) VALUES (?,?,?,?,?,?,?,1,1,?,?)')
        ->execute([$f['slug'], $f['titre'], $f['sous_titre'], $f['description'] ?? '', $f['prix_fcfa'], json_encode($f['formules'] ?? [], JSON_UNESCAPED_UNICODE), $f['image'], $maintenant, $maintenant]);
    $fid = (int) $pdo->lastInsertId();
    foreach ($f['modules'] as $i => $m) {
        $pdo->prepare('INSERT INTO modules (formation_id, titre, description, ordre) VALUES (?,?,?,?)')
            ->execute([$fid, $m['titre'], $m['description'], $i + 1]);
        $mid = (int) $pdo->lastInsertId();
        foreach ($m['lecons'] as $j => $l) {
            $pdo->prepare('INSERT INTO lecons (module_id, titre, resume, duree_sec, apercu_gratuit, ordre, cree_le) VALUES (?,?,?,?,?,?,?)')
                ->execute([$mid, $l['titre'], $l['resume'], $l['duree_sec'], $l['apercu_gratuit'] ? 1 : 0, $j + 1, $maintenant]);
        }
    }
}

/**
 * Ajoute une ligne ou la met à jour si elle existe déjà (fonctionne avec SQLite et MySQL).
 * $cles : colonnes qui identifient la ligne ; $valeurs : autres colonnes.
 */
function enregistrer_ligne(string $table, array $cles, array $valeurs): void
{
    $where = implode(' AND ', array_map(fn ($c) => "$c = ?", array_keys($cles)));
    $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE $where");
    $st->execute(array_values($cles));
    if ((int) $st->fetchColumn() > 0) {
        $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($valeurs)));
        db()->prepare("UPDATE $table SET $set WHERE $where")->execute([...array_values($valeurs), ...array_values($cles)]);
    } else {
        $tout = $cles + $valeurs;
        $colonnes = implode(', ', array_keys($tout));
        $places = implode(', ', array_fill(0, count($tout), '?'));
        db()->prepare("INSERT INTO $table ($colonnes) VALUES ($places)")->execute(array_values($tout));
    }
}

function maintenant(): string
{
    return date('Y-m-d H:i:s');
}

function reglage(string $cle, string $defaut = ''): string
{
    $st = db()->prepare('SELECT valeur FROM reglages WHERE cle = ?');
    $st->execute([$cle]);
    $v = $st->fetchColumn();
    return $v === false || $v === null ? $defaut : (string) $v;
}

function definir_reglage(string $cle, string $valeur): void
{
    db()->prepare('REPLACE INTO reglages (cle, valeur) VALUES (?, ?)')->execute([$cle, $valeur]);
}

/* ---------- Outils d'affichage et de navigation ---------- */
function e($texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $chemin = ''): string
{
    return rtrim((string) conf('url'), '/') . '/' . ltrim($chemin, '/');
}

function rediriger(string $chemin): never
{
    header('Location: ' . (preg_match('#^https?://#', $chemin) ? $chemin : url($chemin)));
    exit;
}

/** Garde seulement un chemin interne (évite les redirections vers un autre site). */
function chemin_suite(?string $suite, string $defaut): string
{
    $suite = (string) $suite;
    if ($suite === '' || $suite[0] !== '/' || str_starts_with($suite, '//') || str_contains($suite, '\\')) {
        return $defaut;
    }
    return $suite;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array
{
    $liste = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $liste;
}

function prix(int $fcfa): string
{
    return number_format($fcfa, 0, ',', "\u{202F}") . "\u{00A0}FCFA";
}

function duree_texte(int $secondes): string
{
    if ($secondes > 0 && $secondes < 60) {
        return $secondes . ' s';
    }
    $min = (int) round($secondes / 60);
    if ($min < 60) {
        return $min . ' min';
    }
    return intdiv($min, 60) . ' h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
}

function taille_texte(int $octets): string
{
    if ($octets >= 1073741824) {
        return number_format($octets / 1073741824, 1, ',', ' ') . ' Go';
    }
    if ($octets >= 1048576) {
        return number_format($octets / 1048576, 0, ',', ' ') . ' Mo';
    }
    return number_format(max(1, $octets / 1024), 0, ',', ' ') . ' Ko';
}

function slugifier(string $texte): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte) ?: $texte;
    $t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t));
    return trim($t, '-') ?: 'formation';
}

/** Met un numéro WhatsApp au format +225XXXXXXXXXX. Renvoie null s'il n'est pas valide. */
function normaliser_whatsapp(string $brut): ?string
{
    $n = preg_replace('/[\s.\-()\/]/', '', $brut);
    if (str_starts_with($n, '00')) {
        $n = '+' . substr($n, 2);
    }
    if (preg_match('/^0\d{9}$/', $n)) {          // numéro ivoirien à 10 chiffres
        $n = '+225' . $n;
    } elseif (preg_match('/^225\d{10}$/', $n)) {
        $n = '+' . $n;
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $n) ? $n : null;
}

/** Affiche +2250747933748 comme +225 07 47 93 37 48. */
function formater_whatsapp(string $n): string
{
    if (preg_match('/^\+225(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $n, $m)) {
        return '+225 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4] . ' ' . $m[5];
    }
    return $n;
}

/** Lien pour écrire à un numéro donné sur WhatsApp. */
function lien_whatsapp_vers(string $numero, string $message): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', $numero) . '?text=' . rawurlencode($message);
}

function lien_whatsapp(string $message): string
{
    $numero = preg_replace('/\D/', '', reglage('whatsapp', (string) conf('whatsapp', '')));
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($message);
}

function ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ---------- Protection des formulaires ---------- */
function jeton_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">';
}

function verifier_csrf(): void
{
    $recu = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if ($recu === '' || !hash_equals(jeton_csrf(), $recu)) {
        http_response_code(419);
        exit('La page a expiré. Reviens en arrière, actualise la page et réessaie.');
    }
}

function est_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Limite les essais répétés (connexion, envoi d'e-mails). Renvoie false si la limite est atteinte. */
function limite_ok(string $action, string $cle, int $max, int $minutes): bool
{
    $cle = substr($action . '|' . ip() . '|' . mb_strtolower($cle), 0, 200);
    $depuis = date('Y-m-d H:i:s', time() - $minutes * 60);
    $st = db()->prepare('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND cree_le > ?');
    $st->execute([$cle, $depuis]);
    if ((int) $st->fetchColumn() >= $max) {
        return false;
    }
    db()->prepare('INSERT INTO tentatives (cle, cree_le) VALUES (?, ?)')->execute([$cle, maintenant()]);
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM tentatives WHERE cree_le < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
    return true;
}

/* ---------- Comptes ---------- */
function utilisateur(bool $recharger = false): ?array
{
    static $u = false;
    if ($u === false || $recharger) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
            $st->execute([$_SESSION['uid']]);
            $ligne = $st->fetch();
            if ($ligne && !$ligne['bloque']) {
                $u = $ligne;
            } else {
                unset($_SESSION['uid']);
            }
        }
    }
    return $u;
}

function est_admin(?array $u = null): bool
{
    $u = $u ?? utilisateur();
    return $u !== null && mb_strtolower($u['email']) === ADMIN_EMAIL && $u['email_verifie_le'] !== null;
}

function connecter(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    db()->prepare('UPDATE utilisateurs SET derniere_connexion = ? WHERE id = ?')->execute([maintenant(), $u['id']]);
    utilisateur(true);
}

function deconnecter(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function exiger_connexion(): array
{
    $u = utilisateur();
    if (!$u) {
        rediriger('compte/connexion.php?suite=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/espace/'));
    }
    return $u;
}

function exiger_admin(): array
{
    $u = exiger_connexion();
    if (!est_admin($u)) {
        http_response_code(403);
        page_erreur('Accès réservé', "Cette partie du site est réservée à l'administrateur.");
    }
    return $u;
}

function utilisateur_par_email(string $email): ?array
{
    $st = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    return $st->fetch() ?: null;
}

function creer_utilisateur(string $email, string $nom = '', ?string $motDePasse = null, bool $verifie = false): array
{
    db()->prepare('INSERT INTO utilisateurs (email, nom, mot_de_passe, email_verifie_le, cree_le) VALUES (?,?,?,?,?)')->execute([
        mb_strtolower(trim($email)),
        trim($nom),
        $motDePasse === null ? null : password_hash($motDePasse, PASSWORD_DEFAULT),
        $verifie ? maintenant() : null,
        maintenant(),
    ]);
    return utilisateur_par_email($email);
}

/**
 * Marque l'adresse comme vérifiée.
 * $garderMotDePasse : vrai seulement pour le lien de confirmation envoyé après une inscription avec mot de passe.
 * Avec un lien magique ou Google, un mot de passe posé avant la vérification (peut-être par quelqu'un d'autre) est effacé.
 * Pour le compte admin, ce mot de passe est toujours effacé : Alan en choisit un nouveau depuis « Mon compte ».
 */
function marquer_verifie(array $u, bool $garderMotDePasse = false): array
{
    if ($u['email_verifie_le'] === null) {
        $garder = $garderMotDePasse && mb_strtolower($u['email']) !== ADMIN_EMAIL;
        db()->prepare($garder
            ? 'UPDATE utilisateurs SET email_verifie_le = ? WHERE id = ?'
            : 'UPDATE utilisateurs SET email_verifie_le = ?, mot_de_passe = NULL WHERE id = ?')
            ->execute([maintenant(), $u['id']]);
        $u = utilisateur_par_email($u['email']);
    }
    return $u;
}

function nom_affiche(array $u): string
{
    return $u['nom'] !== '' ? $u['nom'] : explode('@', $u['email'])[0];
}

/* ---------- Jetons envoyés par e-mail ---------- */
function creer_jeton(string $email, string $type, int $minutes): string
{
    $jeton = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO jetons (email, type, empreinte, expire_le, cree_le) VALUES (?,?,?,?,?)')->execute([
        mb_strtolower(trim($email)), $type, hash('sha256', $jeton), date('Y-m-d H:i:s', time() + $minutes * 60), maintenant(),
    ]);
    return $jeton;
}

function utiliser_jeton(string $jeton, string $type): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM jetons WHERE empreinte = ? AND type = ? AND utilise_le IS NULL AND expire_le > ?');
    $st->execute([hash('sha256', $jeton), $type, maintenant()]);
    $ligne = $st->fetch();
    if (!$ligne) {
        return null;
    }
    db()->prepare('UPDATE jetons SET utilise_le = ? WHERE id = ?')->execute([maintenant(), $ligne['id']]);
    return $ligne;
}

/* ---------- E-mails ---------- */
function envoyer_email(string $a, string $sujet, string $titre, string $texteHtml, ?string $bouton = null, ?string $lien = null, string $apresHtml = ''): bool
{
    $boutonHtml = $bouton && $lien
        ? '<p style="margin:28px 0"><a href="' . e($lien) . '" style="background:#B606AE;color:#fff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:600;display:inline-block">' . e($bouton) . '</a></p>'
          . '<p style="color:#7D6D7B;font-size:13px">Si le bouton ne marche pas, copie ce lien dans ton navigateur :<br><span style="word-break:break-all">' . e($lien) . '</span></p>'
        : '';
    $html = '<!doctype html><html><body style="margin:0;background:#F6F2F6;font-family:Arial,Helvetica,sans-serif;color:#1A0719">'
        . '<div style="max-width:560px;margin:0 auto;padding:32px 20px">'
        . '<p style="font-weight:700;font-size:18px;margin:0 0 20px">Idjô <span style="color:#B606AE">Académie</span></p>'
        . '<div style="background:#fff;border-radius:18px;padding:28px">'
        . '<h1 style="font-size:22px;margin:0 0 14px">' . e($titre) . '</h1>'
        . '<div style="font-size:15px;line-height:1.6;color:#3B2B39">' . $texteHtml . '</div>' . $boutonHtml
        . ($apresHtml !== '' ? '<div style="font-size:15px;line-height:1.6;color:#3B2B39;border-top:1px solid #EFE6EE;margin-top:22px;padding-top:18px">' . $apresHtml . '</div>' : '')
        . '</div>'
        . '<p style="color:#7D6D7B;font-size:12px;margin-top:18px">Tu reçois cet e-mail parce que quelqu\'un a utilisé ton adresse sur ' . e(url()) . '. Si ce n\'est pas toi, ignore-le.</p>'
        . '</div></body></html>';

    if (conf('mail.mode') === 'fichier' && reglage('smtp_hote') === '') {
        $f = dossier_stockage('mails') . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html';
        file_put_contents($f, "A: $a\nSujet: $sujet\nLien: " . ($lien ?? '') . "\n\n" . $html);
        return true;
    }
    if (reglage('smtp_hote') !== '') {
        require_once __DIR__ . '/smtp.php';
        try {
            smtp_envoyer([
                'hote' => reglage('smtp_hote'),
                'port' => (int) reglage('smtp_port', '465'),
                'utilisateur' => reglage('smtp_utilisateur'),
                'mot_de_passe' => reglage('smtp_mot_de_passe'),
                'expediteur' => reglage('smtp_expediteur') ?: reglage('smtp_utilisateur'),
            ], $a, $sujet, $html);
            return true;
        } catch (Throwable $erreur) {
            noter_echec_email($a, $sujet, 'SMTP : ' . $erreur->getMessage());
            return false;
        }
    }
    $expediteur = (string) conf('mail.expediteur', 'noreply@localhost');
    $entetes = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader('Idjô Académie', 'UTF-8') . ' <' . $expediteur . '>',
        'Reply-To: ' . ADMIN_EMAIL,
    ]);
    $ok = @mail($a, mb_encode_mimeheader($sujet, 'UTF-8'), $html, $entetes, '-f' . $expediteur);
    if (!$ok) {
        noter_echec_email($a, $sujet, 'mail() a renvoyé une erreur');
    }
    return $ok;
}

/** Garde une trace des envois ratés (affichée dans Admin > Réglages). */
function noter_echec_email(string $a, string $sujet, string $raison): void
{
    $ligne = date('d/m/Y H:i') . " · $a · $sujet · $raison";
    @file_put_contents(dossier_stockage('journaux') . '/mail.log', $ligne . "\n", FILE_APPEND);
    try {
        definir_reglage('dernier_echec_mail', $ligne);
    } catch (Throwable $e) {
        // La trace dans le fichier suffit.
    }
}

/* ---------- Formations ---------- */
function formation_par_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM formations WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function formation_par_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM formations WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Modules de la formation, chacun avec la liste de ses leçons (ordre respecté). */
function structure_formation(int $formationId): array
{
    $st = db()->prepare('SELECT * FROM modules WHERE formation_id = ? ORDER BY ordre, id');
    $st->execute([$formationId]);
    $modules = $st->fetchAll();
    if (!$modules) {
        return [];
    }
    $ids = array_column($modules, 'id');
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT l.*, (SELECT COUNT(*) FROM fichiers f WHERE f.lecon_id = l.id) AS nb_fichiers FROM lecons l WHERE module_id IN ($place) ORDER BY ordre, id");
    $st->execute($ids);
    $parModule = [];
    foreach ($st->fetchAll() as $l) {
        $parModule[$l['module_id']][] = $l;
    }
    foreach ($modules as &$m) {
        $m['lecons'] = $parModule[$m['id']] ?? [];
    }
    return $modules;
}

function a_acces(?array $u, int $formationId): bool
{
    if (!$u) {
        return false;
    }
    if (est_admin($u)) {
        return true;
    }
    $st = db()->prepare('SELECT 1 FROM acces WHERE utilisateur_id = ? AND formation_id = ? AND actif = 1');
    $st->execute([$u['id'], $formationId]);
    return (bool) $st->fetchColumn();
}

/** Leçon avec son module et sa formation. */
function lecon_complete(int $leconId): ?array
{
    $st = db()->prepare('SELECT l.*, m.formation_id, m.titre AS module_titre, m.ordre AS module_ordre, f.slug AS formation_slug, f.titre AS formation_titre
        FROM lecons l JOIN modules m ON m.id = l.module_id JOIN formations f ON f.id = m.formation_id WHERE l.id = ?');
    $st->execute([$leconId]);
    return $st->fetch() ?: null;
}

/** Peut regarder la leçon : accès à la formation, ou leçon d'aperçu gratuite pour un compte connecté. */
function peut_voir_lecon(?array $u, array $lecon): bool
{
    return $u !== null && ($lecon['apercu_gratuit'] || a_acces($u, (int) $lecon['formation_id']));
}

/** [leçons terminées, total, pourcentage] */
function progression_formation(int $uid, int $formationId): array
{
    $st = db()->prepare('SELECT COUNT(*) AS total, SUM(p.terminee_le IS NOT NULL) AS faites
        FROM lecons l JOIN modules m ON m.id = l.module_id
        LEFT JOIN progression p ON p.lecon_id = l.id AND p.utilisateur_id = ?
        WHERE m.formation_id = ?');
    $st->execute([$uid, $formationId]);
    $r = $st->fetch();
    $total = (int) $r['total'];
    $faites = (int) $r['faites'];
    return [$faites, $total, $total ? (int) round($faites * 100 / $total) : 0];
}

/** Progression de chaque leçon pour un élève : [lecon_id => ligne] */
function progressions(int $uid, int $formationId): array
{
    $st = db()->prepare('SELECT p.* FROM progression p JOIN lecons l ON l.id = p.lecon_id JOIN modules m ON m.id = l.module_id WHERE p.utilisateur_id = ? AND m.formation_id = ?');
    $st->execute([$uid, $formationId]);
    $res = [];
    foreach ($st->fetchAll() as $p) {
        $res[$p['lecon_id']] = $p;
    }
    return $res;
}

/** Formules générales (pour les liens de la vitrine). */
function formules(): array
{
    return [
        'autonome' => 'Autonome',
        'accompagne' => 'Accompagné',
        'vip' => 'VIP',
    ];
}

/** Formules d'une formation : [['id'=>..., 'nom'=>..., 'prix'=>...]]. Sans formule : une seule offre au prix de base. */
function formules_formation(array $f): array
{
    $liste = json_decode((string) ($f['formules'] ?? ''), true);
    $propres = [];
    foreach (is_array($liste) ? $liste : [] as $x) {
        if (!empty($x['nom']) && isset($x['prix'])) {
            $propres[] = ['id' => (string) ($x['id'] ?? slugifier($x['nom'])), 'nom' => (string) $x['nom'], 'prix' => (int) $x['prix']];
        }
    }
    return $propres ?: [['id' => 'standard', 'nom' => 'Accès complet', 'prix' => (int) $f['prix_fcfa']]];
}

/** Active l'accès d'un élève à une formation. Prévient l'élève par e-mail sauf si $email est faux. */
function accorder_acces(int $uid, int $formationId, string $formule, string $note = '', bool $email = true): void
{
    enregistrer_ligne('acces', ['utilisateur_id' => $uid, 'formation_id' => $formationId], [
        'formule' => $formule,
        'actif' => 1,
        'note' => $note,
        'accorde_le' => maintenant(),
    ]);
    db()->prepare("UPDATE demandes SET statut = 'acceptee' WHERE utilisateur_id = ? AND formation_id = ? AND statut = 'en_attente'")
        ->execute([$uid, $formationId]);
    $st = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $st->execute([$uid]);
    $eleve = $st->fetch();
    $f = formation_par_id($formationId);
    if ($email && $eleve && $f) {
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

/* ---------- Pages ---------- */
function entete(string $titre, array $options = []): void
{
    $GLOBALS['options_page'] = $options;
    require __DIR__ . '/vues/entete.php';
}

function pied(): void
{
    require __DIR__ . '/vues/pied.php';
}

function page_erreur(string $titre, string $message): never
{
    entete($titre);
    echo '<section class="carte-app centre"><h1 class="titre-app">' . e($titre) . '</h1><p>' . e($message) . '</p>'
        . '<p><a class="bouton" href="/espace/">Retour à mon espace</a></p></section>';
    pied();
    exit;
}

function json(array $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envoie la réponse JSON tout de suite, puis laisse le script continuer (envoi des e-mails)
 * sans faire attendre le visiteur.
 */
function json_puis_continuer(array $donnees): void
{
    $corps = json_encode($donnees, JSON_UNESCAPED_UNICODE);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($corps));
    echo $corps;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ignore_user_abort(true);
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
}

/** Adresse d'un fichier CSS ou JS avec sa date de modification : le navigateur reprend la nouvelle version après chaque mise à jour. */
function actif(string $chemin): string
{
    $fichier = dirname(__DIR__) . '/' . ltrim($chemin, '/');
    return $chemin . (is_file($fichier) ? '?v=' . filemtime($fichier) : '');
}
