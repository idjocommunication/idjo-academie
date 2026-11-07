<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$u = exiger_connexion();
$l = lecon_complete((int) ($_GET['id'] ?? 0));
if (!$l) {
    page_erreur('Leçon introuvable', 'Cette leçon n\'existe pas.');
}
$f = formation_par_id((int) $l['formation_id']);
if (!peut_voir_lecon($u, $l)) {
    flash('info', 'Cette leçon fait partie de la formation complète. Débloque-la pour y accéder.');
    rediriger('/espace/formation.php?f=' . rawurlencode($f['slug']) . '#debloquer');
}
$acces = a_acces($u, (int) $f['id']);
$modules = structure_formation((int) $f['id']);
$progr = progressions((int) $u['id'], (int) $f['id']);
$maProgression = $progr[$l['id']] ?? null;

// Leçons visibles dans l'ordre, pour « précédente » et « suivante ».
$ordre = [];
$numeros = [];
foreach ($modules as $i => $m) {
    foreach ($m['lecons'] as $j => $x) {
        $numeros[$x['id']] = ($i + 1) . '.' . ($j + 1);
        if ($acces || $x['apercu_gratuit']) {
            $ordre[] = $x;
        }
    }
}
$pos = array_search((int) $l['id'], array_map('intval', array_column($ordre, 'id')), true);
$precedente = $pos !== false && $pos > 0 ? $ordre[$pos - 1] : null;
$suivanteL = $pos !== false && $pos < count($ordre) - 1 ? $ordre[$pos + 1] : null;

$st = db()->prepare('SELECT * FROM fichiers WHERE lecon_id = ? ORDER BY id');
$st->execute([$l['id']]);
$fichiers = $st->fetchAll();

entete($l['titre'], ['zone' => 'espace', 'large' => true, 'scripts' => ['/assets/js/espace.js']]);
?>
<nav class="fil-app" aria-label="Fil d'Ariane"><a href="/espace/">Mes formations</a> <span aria-hidden="true">/</span> <a href="/espace/formation.php?f=<?= e($f['slug']) ?>"><?= e($f['titre']) ?></a></nav>

<div class="lecture">
  <div class="lecture__principal">
    <div class="lecteur">
      <?php if ($l['video_fichier']): ?>
        <video id="video-lecon" controls playsinline preload="metadata" controlslist="nodownload" oncontextmenu="return false"
          src="/media/video.php?l=<?= (int) $l['id'] ?>"
          data-lecon="<?= (int) $l['id'] ?>" data-position="<?= (int) ($maProgression['position_sec'] ?? 0) ?>"
          data-terminee="<?= !empty($maProgression['terminee_le']) ? '1' : '0' ?>" data-csrf="<?= e(jeton_csrf()) ?>"></video>
      <?php else: ?>
        <div class="lecteur__vide">
          <p><strong>La vidéo de cette leçon arrive bientôt.</strong></p>
          <p class="petit">Tu seras prévenu dès qu'elle est en ligne.</p>
        </div>
      <?php endif; ?>
    </div>

    <div class="lecture__entete">
      <div>
        <p class="surtitre">Module <?= e(explode('.', $numeros[$l['id']])[0]) ?> · <?= e($l['module_titre']) ?></p>
        <h1 class="titre-app"><?= e($numeros[$l['id']] . ' ' . $l['titre']) ?></h1>
      </div>
      <form method="post" action="/api/progression.php" class="js-terminer">
        <?= champ_csrf() ?>
        <input type="hidden" name="lecon" value="<?= (int) $l['id'] ?>">
        <input type="hidden" name="terminee" value="<?= empty($maProgression['terminee_le']) ? '1' : '0' ?>">
        <button class="bouton <?= empty($maProgression['terminee_le']) ? '' : 'bouton--verre' ?>" type="submit" data-fait="<?= empty($maProgression['terminee_le']) ? '0' : '1' ?>">
          <?= empty($maProgression['terminee_le']) ? 'Marquer comme terminée' : '✓ Terminée' ?>
        </button>
      </form>
    </div>

    <?php if ($l['resume']): ?><p class="resume-lecon"><?= nl2br(e($l['resume'])) ?></p><?php endif; ?>

    <?php if ($fichiers): ?>
    <section class="fichiers-lecon">
      <h2 class="titre-bloc-app">Fichiers de la leçon</h2>
      <ul>
        <?php foreach ($fichiers as $fi): ?>
        <li><a href="/media/fichier.php?id=<?= (int) $fi['id'] ?>"><span aria-hidden="true">📄</span> <?= e($fi['nom']) ?></a> <span class="petit"><?= e(taille_texte((int) $fi['taille'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <div class="navigation-lecons">
      <?php if ($precedente): ?><a class="bouton bouton--verre" href="/espace/lecon.php?id=<?= (int) $precedente['id'] ?>">← Leçon précédente</a><?php else: ?><span></span><?php endif; ?>
      <?php if ($suivanteL): ?><a class="bouton" href="/espace/lecon.php?id=<?= (int) $suivanteL['id'] ?>">Leçon suivante →</a><?php elseif (!$acces): ?><a class="bouton" href="/espace/formation.php?f=<?= e($f['slug']) ?>#debloquer">Débloquer la suite</a><?php endif; ?>
    </div>
  </div>

  <aside class="lecture__sommaire" aria-label="Sommaire">
    <?php foreach ($modules as $i => $m): ?>
      <details <?= (int) $m['id'] === (int) $l['module_id'] ? 'open' : '' ?>>
        <summary><span class="petit">Module <?= $i + 1 ?></span> <?= e($m['titre']) ?></summary>
        <ol>
          <?php foreach ($m['lecons'] as $x):
            $visible = $acces || $x['apercu_gratuit'];
            $finie = !empty($progr[$x['id']]['terminee_le']); ?>
          <li class="<?= (int) $x['id'] === (int) $l['id'] ? 'en-cours' : '' ?> <?= $finie ? 'finie' : '' ?>">
            <span aria-hidden="true"><?= $finie ? '✓' : ($visible ? '▶' : '🔒') ?></span>
            <?php if ($visible): ?><a href="/espace/lecon.php?id=<?= (int) $x['id'] ?>" <?= (int) $x['id'] === (int) $l['id'] ? 'aria-current="page"' : '' ?>><?= e($numeros[$x['id']] . ' ' . $x['titre']) ?></a>
            <?php else: ?><span><?= e($numeros[$x['id']] . ' ' . $x['titre']) ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </details>
    <?php endforeach; ?>
  </aside>
</div>
<?php pied();
