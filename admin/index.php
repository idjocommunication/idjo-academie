<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

if (est_post()) {
    verifier_csrf();
    $demande = (int) ($_POST['demande'] ?? 0);
    $st = db()->prepare('SELECT * FROM demandes WHERE id = ?');
    $st->execute([$demande]);
    $d = $st->fetch();
    if ($d && ($_POST['action'] ?? '') === 'activer') {
        accorder_acces((int) $d['utilisateur_id'], (int) $d['formation_id'], $d['formule'], 'Demande du ' . date('d/m/Y', strtotime($d['cree_le'])));
        flash('succes', 'Accès activé. L\'élève a reçu un e-mail.');
    } elseif ($d && ($_POST['action'] ?? '') === 'ignorer') {
        db()->prepare("UPDATE demandes SET statut = 'ignoree' WHERE id = ?")->execute([$demande]);
        flash('info', 'Demande retirée de la liste.');
    }
    rediriger('/admin/');
}

$stats = [
    'Élèves inscrits' => compter('SELECT COUNT(*) FROM utilisateurs WHERE email <> ?', [ADMIN_EMAIL]),
    'Accès actifs' => compter('SELECT COUNT(*) FROM acces WHERE actif = 1'),
    'Demandes en attente' => compter("SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente'"),
    'Contacts à rappeler' => compter("SELECT COUNT(*) FROM prospects WHERE statut = 'nouveau'"),
    'Leçons sans vidéo' => compter('SELECT COUNT(*) FROM lecons WHERE video_fichier IS NULL'),
];
$demandes = db()->query("SELECT d.*, u.nom, u.email, f.titre AS formation_titre FROM demandes d
    JOIN utilisateurs u ON u.id = d.utilisateur_id JOIN formations f ON f.id = d.formation_id
    WHERE d.statut = 'en_attente' ORDER BY d.cree_le DESC")->fetchAll();
$recents = db()->query('SELECT * FROM utilisateurs ORDER BY cree_le DESC LIMIT 8')->fetchAll();

admin_entete('Tableau de bord', 'tableau');
?>
<h1 class="titre-app">Tableau de bord</h1>
<ul class="stats-admin">
  <?php foreach ($stats as $nom => $valeur): ?>
    <li class="carte-app"><strong><?= $valeur ?></strong><span><?= e($nom) ?></span></li>
  <?php endforeach; ?>
</ul>

<section class="carte-app">
  <h2 class="titre-bloc-app">Demandes de déblocage</h2>
  <?php if (!$demandes): ?>
    <p class="petit">Aucune demande en attente. Quand un élève clique sur « Débloquer », sa demande arrive ici et tu reçois un e-mail.</p>
  <?php else: ?>
  <div class="tableau-defilant">
  <table class="tableau-admin">
    <thead><tr><th>Élève</th><th>Formation</th><th>Formule</th><th>Date</th><th><span class="visuellement-cache">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($demandes as $d): ?>
      <tr>
        <td><a href="/admin/eleve.php?id=<?= (int) $d['utilisateur_id'] ?>"><?= e($d['nom'] ?: $d['email']) ?></a><br><span class="petit"><?= e($d['email']) ?></span></td>
        <td><?= e($d['formation_titre']) ?></td>
        <td><?= e($d['formule']) ?></td>
        <td class="petit"><?= e(date('d/m/Y H:i', strtotime($d['cree_le']))) ?></td>
        <td class="actions-ligne">
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="demande" value="<?= (int) $d['id'] ?>"><input type="hidden" name="action" value="activer"><button class="bouton bouton--petit" type="submit">Paiement reçu : activer</button></form>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="demande" value="<?= (int) $d['id'] ?>"><input type="hidden" name="action" value="ignorer"><button class="lien-bouton" type="submit">Ignorer</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section class="carte-app">
  <h2 class="titre-bloc-app">Derniers inscrits</h2>
  <ul class="liste-simple">
    <?php foreach ($recents as $r): ?>
      <li><a href="/admin/eleve.php?id=<?= (int) $r['id'] ?>"><?= e($r['nom'] ?: $r['email']) ?></a> <span class="petit"><?= e($r['email']) ?> · <?= e(date('d/m/Y', strtotime($r['cree_le']))) ?><?= $r['email_verifie_le'] ? '' : ' · e-mail non confirmé' ?></span></li>
    <?php endforeach; ?>
  </ul>
  <p><a href="/admin/eleves.php">Voir tous les élèves</a></p>
</section>
<?php pied();
