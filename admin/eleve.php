<?php
declare(strict_types=1);
require __DIR__ . '/../app/admin.php';

$st = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$st->execute([(int) ($_GET['id'] ?? $_POST['eleve'] ?? 0)]);
$x = $st->fetch();
if (!$x) {
    page_erreur('Élève introuvable', 'Ce compte n\'existe pas.');
}
$xid = (int) $x['id'];
$retour = '/admin/eleve.php?id=' . $xid;
$estCompteAdmin = mb_strtolower($x['email']) === ADMIN_EMAIL;

if (est_post()) {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $formationId = (int) ($_POST['formation'] ?? 0);
    if ($action === 'accorder' && formation_par_id($formationId)) {
        accorder_acces($xid, $formationId, trim((string) ($_POST['formule'] ?? '')), mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255));
        flash('succes', 'Accès activé. L\'élève a reçu un e-mail.');
    } elseif ($action === 'retirer') {
        db()->prepare('UPDATE acces SET actif = 0 WHERE utilisateur_id = ? AND formation_id = ?')->execute([$xid, $formationId]);
        flash('info', 'Accès retiré.');
    } elseif ($action === 'confirmer_email') {
        db()->prepare('UPDATE utilisateurs SET email_verifie_le = COALESCE(email_verifie_le, ?) WHERE id = ?')->execute([maintenant(), $xid]);
        flash('succes', 'Adresse marquée comme confirmée.');
    } elseif ($action === 'bloquer' && !$estCompteAdmin) {
        db()->prepare('UPDATE utilisateurs SET bloque = 1 - bloque WHERE id = ?')->execute([$xid]);
        flash('info', $x['bloque'] ? 'Compte débloqué.' : 'Compte bloqué : l\'élève ne peut plus se connecter.');
    } elseif ($action === 'ignorer_demande') {
        db()->prepare("UPDATE demandes SET statut = 'ignoree' WHERE id = ? AND utilisateur_id = ?")->execute([(int) $_POST['demande'], $xid]);
    }
    rediriger($retour);
}

$formations = db()->query('SELECT * FROM formations ORDER BY ordre, id')->fetchAll();
$st = db()->prepare('SELECT a.*, f.titre, f.slug FROM acces a JOIN formations f ON f.id = a.formation_id WHERE a.utilisateur_id = ? ORDER BY a.accorde_le DESC');
$st->execute([$xid]);
$acces = $st->fetchAll();
$st = db()->prepare("SELECT d.*, f.titre FROM demandes d JOIN formations f ON f.id = d.formation_id WHERE d.utilisateur_id = ? ORDER BY d.cree_le DESC");
$st->execute([$xid]);
$demandes = $st->fetchAll();

admin_entete(nom_affiche($x), 'eleves', ['/assets/js/admin.js']);
?>
<nav class="fil-app"><a href="/admin/eleves.php">Élèves</a> <span aria-hidden="true">/</span> <span><?= e(nom_affiche($x)) ?></span></nav>
<div class="entete-admin">
  <div>
    <h1 class="titre-app"><?= e($x['nom'] ?: nom_affiche($x)) ?></h1>
    <p class="petit"><?= e($x['email']) ?> · inscrit le <?= e(date('d/m/Y', strtotime($x['cree_le']))) ?>
      <?= $x['google_sub'] ? ' · Google' : '' ?><?= $x['mot_de_passe'] ? ' · mot de passe' : '' ?>
      <?= $x['derniere_connexion'] ? ' · vu le ' . e(date('d/m/Y H:i', strtotime($x['derniere_connexion']))) : '' ?></p>
  </div>
</div>

<div class="grille-admin">
  <section class="carte-app">
    <h2 class="titre-bloc-app">Formations</h2>
    <?php if ($acces): ?>
    <ul class="liste-acces">
      <?php foreach ($acces as $a): [$faites, $total, $pct] = progression_formation($xid, (int) $a['formation_id']); ?>
      <li>
        <div>
          <strong><?= e($a['titre']) ?></strong>
          <span class="badge-app <?= $a['actif'] ? 'badge-app--ok' : 'badge-app--manque' ?>"><?= $a['actif'] ? 'Actif' : 'Retiré' ?></span>
          <p class="petit"><?= $a['formule'] ? 'Formule ' . e($a['formule']) . ' · ' : '' ?>depuis le <?= e(date('d/m/Y', strtotime($a['accorde_le']))) ?><?= $a['note'] ? ' · ' . e($a['note']) : '' ?></p>
          <div class="barre-progression"><span style="width:<?= $pct ?>%"></span></div>
          <p class="petit"><?= $faites ?> / <?= $total ?> leçons terminées (<?= $pct ?> %)</p>
        </div>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="eleve" value="<?= $xid ?>"><input type="hidden" name="formation" value="<?= (int) $a['formation_id'] ?>">
          <?php if ($a['actif']): ?>
            <button class="lien-bouton lien-bouton--danger" name="action" value="retirer" data-confirmer="Retirer l'accès à cette formation ?">Retirer l'accès</button>
          <?php else: ?>
            <input type="hidden" name="formule" value="<?= e($a['formule']) ?>"><button class="lien-bouton" name="action" value="accorder">Réactiver</button>
          <?php endif; ?>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="petit">Aucune formation débloquée.</p><?php endif; ?>

    <h3 class="titre-sous-bloc">Débloquer une formation</h3>
    <form method="post" class="formulaire">
      <?= champ_csrf() ?><input type="hidden" name="action" value="accorder"><input type="hidden" name="eleve" value="<?= $xid ?>">
      <label class="champ"><span>Formation</span>
        <select name="formation" required>
          <?php foreach ($formations as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['titre']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="champ"><span>Formule</span>
        <select name="formule">
          <option value="">—</option>
          <?php $noms = []; foreach ($formations as $f) { foreach (formules_formation($f) as $fo) { $noms[$fo['nom']] = true; } }
          foreach (array_keys($noms) as $nom): ?><option><?= e($nom) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="champ"><span>Note <small>(ex. paiement Wave du 08/11)</small></span><input type="text" name="note" maxlength="255"></label>
      <button class="bouton" type="submit">Débloquer</button>
    </form>
  </section>

  <section class="carte-app">
    <h2 class="titre-bloc-app">Demandes</h2>
    <?php if ($demandes): ?>
    <ul class="liste-simple">
      <?php foreach ($demandes as $d): ?>
      <li><?= e($d['titre']) ?> · <?= e($d['formule']) ?> · <span class="petit"><?= e(date('d/m/Y H:i', strtotime($d['cree_le']))) ?></span>
        <span class="badge-app <?= $d['statut'] === 'en_attente' ? 'badge-app--attente' : '' ?>"><?= e(['en_attente' => 'En attente', 'acceptee' => 'Acceptée', 'ignoree' => 'Ignorée'][$d['statut']] ?? $d['statut']) ?></span>
        <?php if ($d['statut'] === 'en_attente'): ?>
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="eleve" value="<?= $xid ?>"><input type="hidden" name="formation" value="<?= (int) $d['formation_id'] ?>"><input type="hidden" name="formule" value="<?= e($d['formule']) ?>">
            <button class="lien-bouton" name="action" value="accorder">Activer</button></form>
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="eleve" value="<?= $xid ?>"><input type="hidden" name="demande" value="<?= (int) $d['id'] ?>">
            <button class="lien-bouton" name="action" value="ignorer_demande">Ignorer</button></form>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="petit">Aucune demande.</p><?php endif; ?>

    <h3 class="titre-sous-bloc">Compte</h3>
    <form method="post" class="actions-colonne"><?= champ_csrf() ?><input type="hidden" name="eleve" value="<?= $xid ?>">
      <?php if (!$x['email_verifie_le']): ?><button class="lien-bouton" name="action" value="confirmer_email">Marquer l'adresse comme confirmée</button><?php endif; ?>
      <?php if (!$estCompteAdmin): ?>
        <button class="lien-bouton <?= $x['bloque'] ? '' : 'lien-bouton--danger' ?>" name="action" value="bloquer" data-confirmer="<?= $x['bloque'] ? 'Débloquer ce compte ?' : 'Bloquer ce compte ? L\'élève ne pourra plus se connecter.' ?>"><?= $x['bloque'] ? 'Débloquer le compte' : 'Bloquer le compte' ?></button>
      <?php endif; ?>
    </form>
  </section>
</div>
<?php pied();
