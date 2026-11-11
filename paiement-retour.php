<?php
/** Page où CinetPay renvoie l'acheteur après le paiement. */
declare(strict_types=1);
require __DIR__ . '/app/paiement.php';

$transaction = (string) ($_GET['transaction'] ?? $_POST['transaction_id'] ?? $_POST['cpm_trans_id'] ?? '');
$p = preg_match('/^[A-Z0-9]{8,40}$/', $transaction) ? paiement_par_transaction($transaction) : null;
if (!$p) {
    page_erreur('Paiement introuvable', 'Nous ne retrouvons pas ce paiement. Si tu as été débité, écris-nous sur WhatsApp avec la référence reçue par SMS.');
}
$p = verifier_paiement($p);
$f = formation_par_id((int) $p['formation_id']);
$prenom = explode(' ', trim($p['nom']))[0];

// Le navigateur qui a lancé le paiement est connecté automatiquement, seulement si le compte vient d'être créé pour cet achat.
$ceNavigateur = in_array($p['transaction_id'], $_SESSION['paiements'] ?? [], true);
$creeIci = (int) $p['compte_cree'] === 1 || in_array((int) $p['utilisateur_id'], $_SESSION['comptes_crees'] ?? [], true);
$connecte = utilisateur();
if ($p['statut'] === 'accepte' && $ceNavigateur && $creeIci && !$connecte) {
    $eleve = utilisateur_par_email($p['email']);
    if ($eleve && !$eleve['bloque'] && mb_strtolower($eleve['email']) !== ADMIN_EMAIL) {
        connecter($eleve);
        $connecte = $eleve;
    }
}

entete('Paiement', ['classe' => 'page-paiement']);
?>
<section class="carte-app paiement__fin centre">
<?php if ($p['statut'] === 'accepte'): ?>
  <p class="paiement__icone paiement__icone--ok" aria-hidden="true">✓</p>
  <h1 class="titre-app">Bienvenue dans la formation, <?= e($prenom) ?> !</h1>
  <p>Ton paiement de <strong><?= e(prix((int) $p['montant'])) ?></strong> est confirmé. Ton accès à « <?= e($f['titre'] ?? 'la formation') ?> » est ouvert.</p>
  <?php if ($connecte && (int) $connecte['id'] === (int) $p['utilisateur_id']): ?>
    <p><a class="bouton bouton--plein" href="/espace/formation.php?f=<?= e(rawurlencode($f['slug'] ?? '')) ?>">Commencer la formation</a></p>
  <?php else: ?>
    <p>Nous t'avons envoyé un e-mail à <strong><?= e($p['email']) ?></strong> avec un bouton pour entrer dans tes cours. Pense à regarder dans les spams.</p>
    <p><a class="bouton" href="/compte/lien.php">Recevoir un nouveau lien de connexion</a></p>
  <?php endif; ?>
<?php elseif ($p['statut'] === 'refuse'): ?>
  <p class="paiement__icone paiement__icone--non" aria-hidden="true">!</p>
  <h1 class="titre-app">Le paiement n'a pas abouti</h1>
  <p>Aucun montant n'a été validé. Tu peux réessayer, ou nous écrire sur WhatsApp pour payer autrement.</p>
  <p><a class="bouton" href="/paiement.php?formule=<?= e(rawurlencode(slugifier($p['formule']))) ?>">Réessayer</a></p>
  <p class="petit"><a href="<?= e(lien_whatsapp('Bonjour, mon paiement pour Idjô Académie n\'a pas abouti (référence ' . $p['transaction_id'] . ').')) ?>" target="_blank" rel="noopener">Écrire sur WhatsApp</a></p>
<?php else: ?>
  <meta http-equiv="refresh" content="6">
  <p class="paiement__icone" aria-hidden="true">…</p>
  <h1 class="titre-app">Paiement en cours de confirmation</h1>
  <p>Valide le paiement sur ton téléphone si on te le demande. Cette page se met à jour toute seule.</p>
  <p class="petit">Référence : <?= e($p['transaction_id']) ?> · <a href="<?= e(lien_whatsapp('Bonjour, mon paiement Idjô Académie est en attente (référence ' . $p['transaction_id'] . ').')) ?>" target="_blank" rel="noopener">Un souci ? Écris-nous</a></p>
<?php endif; ?>
</section>
<?php pied();
