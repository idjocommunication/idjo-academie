<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = exiger_connexion();
$f = formation_par_slug((string) ($_GET['f'] ?? ''));
if (!$f || (!$f['publiee'] && !est_admin($u))) {
    page_erreur('Formation introuvable', 'Cette formation n\'existe pas ou n\'est pas encore publiée.');
}
$fid = (int) $f['id'];
$acces = a_acces($u, $fid);
$modules = structure_formation($fid);
$progr = progressions((int) $u['id'], $fid);
[$faites, $total, $pct] = progression_formation((int) $u['id'], $fid);
$dureeTotale = 0;
foreach ($modules as $m) {
    foreach ($m['lecons'] as $l) {
        $dureeTotale += (int) $l['duree_sec'];
    }
}
$formules = formules_formation($f);
$formuleChoisie = $_SESSION['formule'] ?? ($formules[0]['id'] ?? '');
$st = db()->prepare("SELECT * FROM demandes WHERE utilisateur_id = ? AND formation_id = ? AND statut = 'en_attente' ORDER BY id DESC LIMIT 1");
$st->execute([$u['id'], $fid]);
$demande = $st->fetch();

/** Première leçon non terminée (ou la première). */
$suivante = null;
foreach ($modules as $m) {
    foreach ($m['lecons'] as $l) {
        if ($suivante === null && empty($progr[$l['id']]['terminee_le'])) {
            $suivante = $l;
        }
    }
}
$suivante = $suivante ?? ($modules[0]['lecons'][0] ?? null);

entete($f['titre'], ['zone' => 'espace']);
?>
<nav class="fil-app" aria-label="Fil d'Ariane"><a href="/espace/">Mes formations</a> <span aria-hidden="true">/</span> <span><?= e($f['titre']) ?></span></nav>

<section class="tete-formation-app">
  <div class="tete-formation-app__texte">
    <h1 class="titre-app"><?= e($f['titre']) ?></h1>
    <?php if ($f['sous_titre']): ?><p class="chapeau-app"><?= e($f['sous_titre']) ?></p><?php endif; ?>
    <p class="petit"><?= count($modules) ?> modules · <?= $total ?> leçons<?= $dureeTotale ? ' · ' . e(duree_texte($dureeTotale)) : '' ?></p>
    <?php if ($acces): ?>
      <div class="barre-progression" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progression"><span style="width:<?= $pct ?>%"></span></div>
      <p class="petit"><?= $faites ?> sur <?= $total ?> leçons terminées · <?= $pct ?> %</p>
      <?php if ($suivante): ?><a class="bouton" href="/espace/lecon.php?id=<?= (int) $suivante['id'] ?>"><?= $faites ? 'Continuer' : 'Commencer la formation' ?></a><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($f['image']): ?><img class="tete-formation-app__image" src="/<?= e(ltrim($f['image'], '/')) ?>" alt=""><?php endif; ?>
</section>

<?php if (!$acces): ?>
<section class="carte-app debloquer" id="debloquer">
  <h2 class="titre-bloc-app">Débloquer la formation</h2>
  <?php if ($demande): ?>
    <p class="pastille-attente">Ta demande (formule <?= e($demande['formule']) ?>) est bien enregistrée. Ton accès sera activé dès réception de ton paiement Mobile Money.</p>
  <?php endif; ?>
  <p>Choisis ta formule, puis envoie ta demande sur WhatsApp. Tu paies par Mobile Money, puis ton accès est activé sur ce compte.</p>
  <form method="post" action="/espace/debloquer.php" class="formulaire">
    <?= champ_csrf() ?>
    <input type="hidden" name="formation" value="<?= $fid ?>">
    <fieldset class="choix-formules">
      <legend class="visuellement-cache">Formule</legend>
      <?php foreach ($formules as $i => $fo): ?>
        <label class="choix-formule-app">
          <input type="radio" name="formule" value="<?= e($fo['id']) ?>" <?= ($fo['id'] === $formuleChoisie || (!in_array($formuleChoisie, array_column($formules, 'id'), true) && $i === 0)) ? 'checked' : '' ?>>
          <span><strong><?= e($fo['nom']) ?></strong><em><?= e(prix($fo['prix'])) ?></em></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <button class="bouton" type="submit">Envoyer ma demande sur WhatsApp</button>
  </form>
  <p class="petit">Le détail des formules est sur la <a href="/formation.html">page de la formation</a>.</p>
</section>
<?php endif; ?>

<section class="bloc-app">
  <h2 class="titre-bloc-app">Programme</h2>
  <div class="programme-app">
    <?php foreach ($modules as $i => $m): ?>
    <details class="module-app" <?= $i === 0 ? 'open' : '' ?>>
      <summary>
        <span class="module-app__num">Module <?= $i + 1 ?></span>
        <strong><?= e($m['titre']) ?></strong>
        <span class="petit"><?= count($m['lecons']) ?> leçons</span>
      </summary>
      <ol class="lecons-app">
        <?php foreach ($m['lecons'] as $j => $l):
          $visible = $acces || $l['apercu_gratuit'];
          $finie = !empty($progr[$l['id']]['terminee_le']);
          $etat = $finie ? 'finie' : ($visible ? 'ouverte' : 'verrou'); ?>
        <li class="lecon-app lecon-app--<?= $etat ?>">
          <span class="lecon-app__etat" aria-hidden="true"><?= $finie ? '✓' : ($visible ? '▶' : '🔒') ?></span>
          <?php if ($visible): ?>
            <a href="/espace/lecon.php?id=<?= (int) $l['id'] ?>"><?= ($i + 1) . '.' . ($j + 1) ?> <?= e($l['titre']) ?></a>
          <?php else: ?>
            <span><?= ($i + 1) . '.' . ($j + 1) ?> <?= e($l['titre']) ?></span>
          <?php endif; ?>
          <span class="lecon-app__infos">
            <?php if ($l['apercu_gratuit'] && !$acces): ?><span class="badge-app">Aperçu gratuit</span><?php endif; ?>
            <?php if ($l['duree_sec']): ?><span class="petit"><?= e(duree_texte((int) $l['duree_sec'])) ?></span><?php endif; ?>
            <span class="visuellement-cache"><?= $finie ? 'terminée' : ($visible ? 'disponible' : 'verrouillée') ?></span>
          </span>
        </li>
        <?php endforeach; ?>
      </ol>
    </details>
    <?php endforeach; ?>
  </div>
</section>
<?php pied();
