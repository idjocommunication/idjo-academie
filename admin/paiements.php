<?php
/** Paiements en ligne (CinetPay) : qui a payé, combien, quelle formule. */
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';
require_once __DIR__ . '/../app/paiement.php';

const STATUTS_PAIEMENT = ['accepte' => 'Payé', 'en_attente' => 'En attente', 'refuse' => 'Échoué'];

if (est_post()) {
    verifier_csrf();
    // Revérifier un paiement resté « en attente » auprès de CinetPay
    $st = db()->prepare('SELECT * FROM paiements WHERE id = ?');
    $st->execute([(int) ($_POST['id'] ?? 0)]);
    if ($p = $st->fetch()) {
        $p = verifier_paiement($p);
        flash('info', 'État actuel : ' . (STATUTS_PAIEMENT[$p['statut']] ?? $p['statut']) . '.');
    }
    rediriger('/admin/paiements.php');
}

$total = (int) db()->query("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'accepte'")->fetchColumn();
$paiements = db()->query('SELECT * FROM paiements ORDER BY cree_le DESC LIMIT 300')->fetchAll();

admin_entete('Paiements', 'paiements');
?>
<div class="entete-admin">
  <h1 class="titre-app">Paiements <span class="petit">(<?= compter("SELECT COUNT(*) FROM paiements WHERE statut = 'accepte'") ?> ventes · <?= e(prix($total)) ?>)</span></h1>
</div>
<?php if (!cinetpay_configure()): ?>
  <p class="message message--info">Le paiement en ligne n'est pas encore activé. Colle ton ID de site et ta clé API CinetPay dans <a href="/admin/reglages.php">Réglages</a>.</p>
<?php endif; ?>
<div class="tableau-defilant">
<table class="tableau-admin">
  <thead><tr><th>Élève</th><th>Formule</th><th>Montant</th><th>Date</th><th>Statut</th><th><span class="visuellement-cache">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($paiements as $p): ?>
    <tr>
      <td><a href="/admin/eleve.php?id=<?= (int) $p['utilisateur_id'] ?>"><strong><?= e($p['nom']) ?></strong></a><br><span class="petit"><?= e($p['email']) ?> · <?= e(formater_whatsapp($p['whatsapp'])) ?></span></td>
      <td><?= e($p['formule']) ?></td>
      <td><?= e(prix((int) $p['montant'])) ?></td>
      <td class="petit"><?= e(date('d/m/Y H:i', strtotime($p['cree_le']))) ?><br><?= e($p['transaction_id']) ?></td>
      <td><span class="badge-app <?= $p['statut'] === 'accepte' ? 'badge-app--ok' : ($p['statut'] === 'refuse' ? 'badge-app--manque' : 'badge-app--attente') ?>"><?= e(STATUTS_PAIEMENT[$p['statut']] ?? $p['statut']) ?></span><?= $p['moyen'] !== '' ? '<br><span class="petit">' . e($p['moyen']) . '</span>' : '' ?></td>
      <td class="actions-ligne">
        <?php if ($p['statut'] === 'en_attente' && cinetpay_configure()): ?>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="lien-bouton" type="submit">Revérifier</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$paiements): ?><tr><td colspan="6" class="petit">Aucun paiement pour l'instant.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php pied();
