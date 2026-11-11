<?php
/** Contacts laissés sur la page d'accueil (nom + WhatsApp), avec leur suivi. */
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

const STATUTS_CONTACT = [
    'nouveau' => 'À contacter',
    'contacte' => 'Contacté',
    'inscrit' => 'Inscrit',
    'pas_interesse' => 'Pas intéressé',
];

if (est_post()) {
    verifier_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'statut' && isset(STATUTS_CONTACT[$_POST['statut'] ?? ''])) {
        db()->prepare('UPDATE prospects SET statut = ?, modifie_le = ? WHERE id = ?')->execute([$_POST['statut'], maintenant(), $id]);
        flash('succes', 'Statut mis à jour.');
    } elseif ($action === 'supprimer') {
        db()->prepare('DELETE FROM prospects WHERE id = ?')->execute([$id]);
        flash('info', 'Contact supprimé.');
    }
    rediriger('/admin/contacts.php' . (isset($_GET['statut']) ? '?statut=' . rawurlencode((string) $_GET['statut']) : ''));
}

$filtre = isset(STATUTS_CONTACT[$_GET['statut'] ?? '']) ? (string) $_GET['statut'] : '';
$comptes = [];
foreach (array_keys(STATUTS_CONTACT) as $s) {
    $comptes[$s] = compter('SELECT COUNT(*) FROM prospects WHERE statut = ?', [$s]);
}
$st = db()->prepare('SELECT * FROM prospects' . ($filtre ? ' WHERE statut = ?' : '') . ' ORDER BY cree_le DESC LIMIT 300');
$st->execute($filtre ? [$filtre] : []);
$contacts = $st->fetchAll();

admin_entete('Contacts', 'contacts');
?>
<div class="entete-admin">
  <h1 class="titre-app">Contacts <span class="petit">(<?= array_sum($comptes) ?>)</span></h1>
</div>
<p class="petit">Les personnes qui ont laissé leur nom et leur numéro WhatsApp sur la page d'accueil. Tu reçois un e-mail à chaque nouveau contact.</p>

<nav class="filtres-admin" aria-label="Filtrer par statut">
  <a href="/admin/contacts.php" <?= $filtre === '' ? 'aria-current="page"' : '' ?>>Tous</a>
  <?php foreach (STATUTS_CONTACT as $s => $libelle): ?>
    <a href="?statut=<?= $s ?>" <?= $filtre === $s ? 'aria-current="page"' : '' ?>><?= e($libelle) ?> (<?= $comptes[$s] ?>)</a>
  <?php endforeach; ?>
</nav>

<div class="tableau-defilant">
<table class="tableau-admin">
  <thead><tr><th>Contact</th><th>WhatsApp</th><th>Reçu le</th><th>Statut</th><th><span class="visuellement-cache">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($contacts as $c):
      $prenom = explode(' ', $c['nom'])[0];
      $message = "Bonjour $prenom, c'est l'équipe Idjô Académie. Tu as laissé ton numéro sur notre site pour la formation « Apprendre à gagner de l'argent grâce à l'IA ». Je peux te présenter les formules ?";
  ?>
    <tr>
      <td><strong><?= e($c['nom']) ?></strong><?php if ($c['statut'] === 'nouveau'): ?> <span class="badge-app badge-app--attente">Nouveau</span><?php endif; ?></td>
      <td><a class="bouton bouton--petit" href="<?= e(lien_whatsapp_vers($c['whatsapp'], $message)) ?>" target="_blank" rel="noopener"><?= e(formater_whatsapp($c['whatsapp'])) ?></a></td>
      <td class="petit"><?= e(date('d/m/Y H:i', strtotime($c['cree_le']))) ?></td>
      <td>
        <form method="post" class="formulaire formulaire--ligne">
          <?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="statut">
          <select name="statut" aria-label="Statut de <?= e($c['nom']) ?>" onchange="this.form.submit()">
            <?php foreach (STATUTS_CONTACT as $s => $libelle): ?>
              <option value="<?= $s ?>" <?= $c['statut'] === $s ? 'selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="lien-bouton" type="submit">Enregistrer</button></noscript>
        </form>
      </td>
      <td class="actions-ligne">
        <form method="post" onsubmit="return confirm('Supprimer ce contact ?')"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="supprimer"><button class="lien-bouton" type="submit">Supprimer</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$contacts): ?><tr><td colspan="5" class="petit">Aucun contact pour l'instant.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php pied();
