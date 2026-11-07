<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = exiger_connexion();
$admin = est_admin($u);

$formations = db()->query('SELECT * FROM formations WHERE publiee = 1 ORDER BY ordre, id')->fetchAll();
$mes = [];
$autres = [];
foreach ($formations as $f) {
    if (a_acces($u, (int) $f['id'])) {
        $f['progression'] = progression_formation((int) $u['id'], (int) $f['id']);
        $mes[] = $f;
    } else {
        $autres[] = $f;
    }
}
$st = db()->prepare("SELECT formation_id FROM demandes WHERE utilisateur_id = ? AND statut = 'en_attente'");
$st->execute([$u['id']]);
$demandees = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

/** Dernière leçon ouverte, pour « Reprendre ». */
function lecon_a_reprendre(int $uid, int $formationId): ?int
{
    $st = db()->prepare('SELECT p.lecon_id FROM progression p JOIN lecons l ON l.id = p.lecon_id JOIN modules m ON m.id = l.module_id
        WHERE p.utilisateur_id = ? AND m.formation_id = ? ORDER BY p.modifie_le DESC LIMIT 1');
    $st->execute([$uid, $formationId]);
    $id = $st->fetchColumn();
    return $id ? (int) $id : null;
}

entete('Mes formations', ['zone' => 'espace']);
?>
<section class="tete-app">
  <p class="surtitre">Ton espace</p>
  <h1 class="titre-app">Bonjour <?= e(nom_affiche($u)) ?> 👋</h1>
  <p class="chapeau-app"><?= $mes ? 'Reprends là où tu t\'es arrêté.' : 'Ton compte est prêt. Débloque une formation pour commencer, ou regarde les leçons d\'aperçu gratuites.' ?></p>
</section>

<?php if ($mes): ?>
<section class="bloc-app">
  <h2 class="titre-bloc-app">Mes formations</h2>
  <ul class="grille-formations">
    <?php foreach ($mes as $f): [$faites, $total, $pct] = $f['progression']; $reprise = lecon_a_reprendre((int) $u['id'], (int) $f['id']); ?>
    <li class="carte-formation">
      <a class="carte-formation__image" href="/espace/formation.php?f=<?= e($f['slug']) ?>"><?php if ($f['image']): ?><img src="/<?= e(ltrim($f['image'], '/')) ?>" alt="" loading="lazy"><?php endif; ?></a>
      <div class="carte-formation__corps">
        <h3><a href="/espace/formation.php?f=<?= e($f['slug']) ?>"><?= e($f['titre']) ?></a></h3>
        <div class="barre-progression" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progression"><span style="width:<?= $pct ?>%"></span></div>
        <p class="petit"><?= $faites ?> leçon<?= $faites > 1 ? 's' : '' ?> terminée<?= $faites > 1 ? 's' : '' ?> sur <?= $total ?> · <?= $pct ?> %</p>
        <a class="bouton" href="<?= $reprise ? '/espace/lecon.php?id=' . $reprise : '/espace/formation.php?f=' . e($f['slug']) ?>"><?= $reprise ? 'Reprendre' : 'Commencer' ?></a>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($autres): ?>
<section class="bloc-app">
  <h2 class="titre-bloc-app"><?= $mes ? 'Autres formations' : 'Formations disponibles' ?></h2>
  <ul class="grille-formations">
    <?php foreach ($autres as $f): ?>
    <li class="carte-formation carte-formation--verrou">
      <a class="carte-formation__image" href="/espace/formation.php?f=<?= e($f['slug']) ?>"><?php if ($f['image']): ?><img src="/<?= e(ltrim($f['image'], '/')) ?>" alt="" loading="lazy"><?php endif; ?><span class="cadenas" aria-hidden="true">🔒</span></a>
      <div class="carte-formation__corps">
        <h3><a href="/espace/formation.php?f=<?= e($f['slug']) ?>"><?= e($f['titre']) ?></a></h3>
        <?php if ($f['sous_titre']): ?><p class="petit"><?= e($f['sous_titre']) ?></p><?php endif; ?>
        <p class="prix-app">À partir de <?= e(prix((int) $f['prix_fcfa'])) ?></p>
        <?php if (isset($demandees[$f['id']])): ?>
          <p class="pastille-attente">Demande envoyée : accès activé dès réception du paiement</p>
        <?php endif; ?>
        <div class="actions-app">
          <a class="bouton" href="/espace/formation.php?f=<?= e($f['slug']) ?>#debloquer">Débloquer</a>
          <a class="bouton bouton--verre" href="/espace/formation.php?f=<?= e($f['slug']) ?>">Voir le programme</a>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!$formations): ?>
  <section class="carte-app centre"><p>Aucune formation n'est encore publiée. Reviens bientôt !</p></section>
<?php endif; ?>
<?php pied();
