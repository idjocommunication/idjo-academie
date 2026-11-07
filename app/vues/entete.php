<?php
/** @var string $titre */
$u = utilisateur();
$opts = $GLOBALS['options_page'] ?? [];
$zone = $opts['zone'] ?? '';
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titre) ?> · Idjô Académie</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#FFFFFF">
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/assets/img/favicon-32.png" sizes="32x32">
  <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
  <link rel="preload" href="/assets/fonts/outfit.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/espace.css">
</head>
<body class="app <?= e($opts['classe'] ?? '') ?>">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="barre-app">
  <div class="barre-app__interieur">
    <a class="barre-app__logo" href="/" aria-label="Idjô Académie, page d'accueil">
      <img src="/assets/img/logo.svg" alt="Idjô Académie" width="135" height="34">
    </a>
    <?php if ($zone === 'admin'): ?><span class="etiquette-admin">Admin</span><?php endif; ?>
    <nav class="barre-app__liens" aria-label="Navigation du compte">
      <?php if ($u): ?>
        <a class="barre-app__accueil" href="/"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>Accueil</a>
        <a href="/espace/" <?= $zone === 'espace' ? 'aria-current="page"' : '' ?>>Mes formations</a>
        <?php if (est_admin($u)): ?><a href="/admin/" <?= $zone === 'admin' ? 'aria-current="page"' : '' ?>>Admin</a><?php endif; ?>
        <a href="/espace/compte.php" <?= $zone === 'compte' ? 'aria-current="page"' : '' ?>>Mon compte</a>
        <form method="post" action="/compte/deconnexion.php" class="barre-app__deconnexion">
          <?= champ_csrf() ?><button type="submit">Déconnexion</button>
        </form>
      <?php else: ?>
        <a class="barre-app__decouvrir" href="/">Découvrir la formation</a>
        <a href="/compte/connexion.php">Connexion</a>
        <a class="bouton bouton--petit" href="/compte/inscription.php">Créer un compte</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="contenu" class="app-contenu<?= !empty($opts['large']) ? ' app-contenu--large' : '' ?>">
<?php foreach (flashes() as [$type, $message]): ?>
  <div class="message message--<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>"><?= e($message) ?></div>
<?php endforeach; ?>
