<?php
/**
 * Liens reçus par e-mail (confirmation d'adresse et lien magique).
 * Le lien ouvre d'abord une page avec un bouton : certains services de messagerie
 * ouvrent les liens automatiquement, et le jeton ne doit servir qu'à la personne.
 */
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

$type = (string) ($_REQUEST['type'] ?? '');
$jeton = (string) ($_REQUEST['jeton'] ?? '');
if (!in_array($type, ['verification', 'magique'], true)) {
    page_erreur('Lien incorrect', 'Ce lien est incomplet. Demande un nouveau lien depuis la page de connexion.');
}

if (est_post()) {
    verifier_csrf();
    $ligne = utiliser_jeton($jeton, $type);
    if (!$ligne) {
        flash('erreur', 'Ce lien a expiré ou a déjà servi. Demande un nouveau lien.');
        rediriger('compte/lien.php');
    }
    $u = utilisateur_par_email($ligne['email']);
    if ($type === 'verification') {
        if (!$u) {
            page_erreur('Compte introuvable', 'Ce compte n\'existe plus. Crée un nouveau compte.');
        }
        $u = marquer_verifie($u, true);
        flash('succes', 'Ton adresse est confirmée. Bienvenue !');
    } else {
        $u = $u ? marquer_verifie($u, false) : creer_utilisateur($ligne['email'], '', null, true);
    }
    if ($u['bloque']) {
        page_erreur('Compte suspendu', 'Ce compte est suspendu. Contacte-nous sur WhatsApp.');
    }
    apres_connexion($u);
}

entete($type === 'verification' ? 'Confirmer mon adresse' : 'Me connecter', ['classe' => 'page-auth']);
?>
<section class="carte-auth centre">
  <h1 class="titre-app"><?= $type === 'verification' ? 'Confirme ton adresse' : 'Connexion à Idjô Académie' ?></h1>
  <p><?= $type === 'verification' ? 'Un dernier clic pour activer ton compte.' : 'Clique sur le bouton pour te connecter.' ?></p>
  <form method="post">
    <?= champ_csrf() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <button class="bouton bouton--plein" type="submit"><?= $type === 'verification' ? 'Activer mon compte' : 'Me connecter' ?></button>
  </form>
</section>
<?php pied();
