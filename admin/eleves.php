<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

$q = trim((string) ($_GET['q'] ?? ''));
$parPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE u.email LIKE ? OR u.nom LIKE ?';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$total = compter("SELECT COUNT(*) FROM utilisateurs u $where", $params);
$st = db()->prepare("SELECT u.*,
    (SELECT GROUP_CONCAT(f.titre SEPARATOR ' · ') FROM acces a JOIN formations f ON f.id = a.formation_id WHERE a.utilisateur_id = u.id AND a.actif = 1) AS formations,
    (SELECT COUNT(*) FROM demandes d WHERE d.utilisateur_id = u.id AND d.statut = 'en_attente') AS nb_demandes
    FROM utilisateurs u $where ORDER BY u.cree_le DESC LIMIT $parPage OFFSET " . (($page - 1) * $parPage));
$st->execute($params);
$eleves = $st->fetchAll();

admin_entete('Élèves', 'eleves');
?>
<div class="entete-admin">
  <h1 class="titre-app">Élèves <span class="petit">(<?= $total ?>)</span></h1>
  <form method="get" class="formulaire formulaire--ligne recherche-admin" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom ou e-mail" aria-label="Rechercher un élève">
    <button class="bouton bouton--petit" type="submit">Rechercher</button>
  </form>
</div>
<div class="tableau-defilant">
<table class="tableau-admin">
  <thead><tr><th>Élève</th><th>Formations débloquées</th><th>Inscrit le</th><th>Dernière connexion</th></tr></thead>
  <tbody>
  <?php foreach ($eleves as $x): ?>
    <tr>
      <td>
        <a href="/admin/eleve.php?id=<?= (int) $x['id'] ?>"><strong><?= e($x['nom'] ?: '(sans nom)') ?></strong></a><br>
        <span class="petit"><?= e($x['email']) ?></span>
        <?php if (!$x['email_verifie_le']): ?><span class="badge-app badge-app--manque">Non confirmé</span><?php endif; ?>
        <?php if ($x['bloque']): ?><span class="badge-app badge-app--manque">Bloqué</span><?php endif; ?>
        <?php if ($x['nb_demandes']): ?><span class="badge-app badge-app--attente">Demande en attente</span><?php endif; ?>
      </td>
      <td class="petit"><?= e($x['formations'] ?: '—') ?></td>
      <td class="petit"><?= e(date('d/m/Y', strtotime($x['cree_le']))) ?></td>
      <td class="petit"><?= $x['derniere_connexion'] ? e(date('d/m/Y H:i', strtotime($x['derniere_connexion']))) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$eleves): ?><tr><td colspan="4" class="petit">Aucun élève trouvé.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($total > $parPage): ?>
<nav class="pagination" aria-label="Pages">
  <?php for ($p = 1; $p <= (int) ceil($total / $parPage); $p++): ?>
    <a href="?q=<?= rawurlencode($q) ?>&amp;page=<?= $p ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php pied();
