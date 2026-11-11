</main>
<footer class="pied-app">
  <div class="pied-app__interieur">
    <span>© <?= date('Y') ?> Idjô Académie, une initiative d'Idjô Communication · <a href="/confidentialite.html">Confidentialité</a></span>
    <a href="<?= e(lien_whatsapp('Bonjour, j\'ai une question sur Idjô Académie.')) ?>" target="_blank" rel="noopener">Besoin d'aide ? WhatsApp</a>
  </div>
</footer>
<?php foreach (($GLOBALS['options_page']['scripts'] ?? []) as $script): ?>
<script src="<?= e(actif($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
