<?php
/**
 * Page de paiement : nom, e-mail, WhatsApp et formule, puis deux façons de payer :
 * - « Payer maintenant » : paiement en ligne avec CinetPay, l'accès s'ouvre dès que CinetPay confirme ;
 * - « Finaliser sur WhatsApp » : la demande arrive dans l'admin, Alan active l'accès après le paiement.
 * Tant que CinetPay n'est pas configuré (Admin > Réglages), seul le bouton WhatsApp s'affiche.
 */
declare(strict_types=1);
require __DIR__ . '/app/paiement.php';
require_once __DIR__ . '/app/auth.php';

$slug = (string) ($_GET['f'] ?? $_POST['f'] ?? '');
$f = $slug !== '' ? formation_par_slug($slug) : null;
if (!$f) {
    $f = db()->query('SELECT * FROM formations WHERE publiee = 1 ORDER BY ordre, id LIMIT 1')->fetch() ?: null;
}
if (!$f || !$f['publiee']) {
    page_erreur('Formation introuvable', 'Cette formation n\'est pas disponible pour le moment.');
}
$formules = formules_formation($f);
$ids = array_column($formules, 'id');
$choix = (string) ($_POST['formule'] ?? $_GET['formule'] ?? 'accompagne');
if (!in_array($choix, $ids, true)) {
    $choix = in_array('accompagne', $ids, true) ? 'accompagne' : $ids[0];
}
$u = utilisateur();
$inscrit = $_SESSION['inscription'] ?? []; // infos laissées sur la page d'accueil
$valeurs = [
    'nom' => (string) ($_POST['nom'] ?? ($inscrit['nom'] ?? ($u['nom'] ?? ''))),
    'email' => (string) ($_POST['email'] ?? ($inscrit['email'] ?? ($u['email'] ?? ''))),
    'whatsapp' => (string) ($_POST['whatsapp'] ?? (isset($inscrit['whatsapp']) ? formater_whatsapp($inscrit['whatsapp']) : '')),
];
$enLigne = cinetpay_configure();
$moyen = $enLigne && ($_POST['moyen'] ?? 'en_ligne') !== 'whatsapp' ? 'en_ligne' : 'whatsapp';
$erreurs = [];
$attenteManuelle = null; // demande enregistrée quand le paiement en ligne n'est pas encore activé
$dejaAcces = false;

if (est_post()) {
    verifier_csrf();
    $nom = trim(preg_replace('/\s+/u', ' ', $valeurs['nom']));
    $email = mb_strtolower(trim($valeurs['email']));
    $whatsapp = normaliser_whatsapp($valeurs['whatsapp']);
    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 80) {
        $erreurs['nom'] = 'Écris ton nom et ton prénom.';
    }
    if (!email_valide($email)) {
        $erreurs['email'] = 'Cette adresse e-mail ne semble pas valide.';
    } elseif ($email === ADMIN_EMAIL) {
        $erreurs['email'] = 'Cette adresse est celle de l\'administrateur : utilise l\'adresse de l\'élève.';
    }
    if ($whatsapp === null) {
        $erreurs['whatsapp'] = 'Ce numéro ne semble pas valide. Exemple : 07 47 93 37 48.';
    }
    if (!$erreurs && !limite_ok('paiement', '', 10, 60)) {
        $erreurs['general'] = 'Trop de tentatives depuis cette connexion. Réessaie dans une heure ou écris-nous sur WhatsApp.';
    }
    if (!$erreurs) {
        $eleve = utilisateur_par_email($email);
        // Compte créé dans ce navigateur, ici ou sur la page d'accueil.
        $compteCree = $eleve && in_array((int) $eleve['id'], $_SESSION['comptes_crees'] ?? [], true);
        if (!$eleve) {
            $eleve = creer_utilisateur($email, $nom);
            $compteCree = true;
            $_SESSION['comptes_crees'][] = (int) $eleve['id']; // compte créé dans ce navigateur
        } elseif ($eleve['nom'] === '') {
            db()->prepare('UPDATE utilisateurs SET nom = ? WHERE id = ?')->execute([$nom, $eleve['id']]);
        }
        if ($eleve['bloque']) {
            $erreurs['general'] = 'Ce compte est suspendu. Contacte-nous sur WhatsApp.';
        } elseif (a_acces($eleve, (int) $f['id'])) {
            $dejaAcces = true;
        } else {
            $formule = $formules[array_search($choix, $ids, true)];

            if ($moyen === 'en_ligne') {
                $transaction = nouvelle_transaction();
                db()->prepare('INSERT INTO paiements (transaction_id, utilisateur_id, formation_id, formule, montant, nom, email, whatsapp, statut, compte_cree, cree_le, modifie_le) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$transaction, $eleve['id'], $f['id'], $formule['nom'], $formule['prix'], $nom, $email, $whatsapp, 'en_attente', $compteCree ? 1 : 0, maintenant(), maintenant()]);
                $_SESSION['paiements'][] = $transaction; // pour reconnaître l'acheteur au retour
                $p = paiement_par_transaction($transaction);
                try {
                    rediriger(cinetpay_guichet($p, $f));
                } catch (Throwable $e) {
                    error_log('[paiement] ' . $e->getMessage());
                    $erreurs['general'] = 'Le guichet de paiement ne répond pas pour le moment. Réessaie dans quelques minutes, ou écris-nous sur WhatsApp pour payer par Mobile Money.';
                }
            } else {
                // Finaliser sur WhatsApp (choix de l'élève, ou paiement en ligne pas encore activé).
                $st = db()->prepare("SELECT id FROM demandes WHERE utilisateur_id = ? AND formation_id = ? AND statut = 'en_attente'");
                $st->execute([$eleve['id'], $f['id']]);
                if (!$st->fetchColumn()) {
                    db()->prepare("INSERT INTO demandes (utilisateur_id, formation_id, formule, statut, cree_le) VALUES (?,?,?,'en_attente',?)")
                        ->execute([$eleve['id'], $f['id'], $formule['nom'], maintenant()]);
                    envoyer_email(
                        ADMIN_EMAIL,
                        'Nouvelle demande : ' . $nom,
                        'Nouvelle demande d\'inscription',
                        '<p><strong>' . e($nom) . '</strong> (' . e($email) . ', WhatsApp ' . e(formater_whatsapp($whatsapp)) . ') veut la formule ' . e($formule['nom']) . ' (' . e(prix($formule['prix'])) . ').</p><p>' . ($enLigne ? 'Il a choisi de finaliser sur WhatsApp' : 'Le paiement en ligne n\'est pas encore activé') . ' : envoie-lui les infos de paiement, puis active son accès dans l\'admin.</p>',
                        'Ouvrir l\'admin',
                        url('admin/')
                    );
                }
                $attenteManuelle = ['formule' => $formule, 'nom' => $nom, 'email' => $email];
            }
        }
    }
}

entete('Paiement', ['classe' => 'page-paiement']);
?>
<div class="paiement">
  <?php if ($dejaAcces): ?>
    <section class="carte-app paiement__fin">
      <h1 class="titre-app">Tu as déjà accès à cette formation</h1>
      <p>L'adresse <strong><?= e($valeurs['email']) ?></strong> a déjà accès à « <?= e($f['titre']) ?> ». Connecte-toi pour retrouver tes cours.</p>
      <p><a class="bouton" href="/compte/connexion.php">Me connecter</a></p>
    </section>
  <?php elseif ($attenteManuelle): $fo = $attenteManuelle['formule']; ?>
    <section class="carte-app paiement__fin">
      <h1 class="titre-app">Plus qu'une étape, <?= e(explode(' ', $attenteManuelle['nom'])[0]) ?> !</h1>
      <p>Ta demande pour la formule <strong><?= e($fo['nom']) ?></strong> (<?= e(prix($fo['prix'])) ?>) est enregistrée. Envoie-nous le message sur WhatsApp : on te donne les détails du paiement Mobile Money, puis ton accès s'ouvre sur ton compte <?= e($attenteManuelle['email']) ?>.</p>
      <p><a class="bouton" href="<?= e(lien_whatsapp("Bonjour, je veux prendre la formation « {$f['titre']} », formule {$fo['nom']} (" . prix($fo['prix']) . ").\nMon e-mail : {$attenteManuelle['email']}")) ?>" target="_blank" rel="noopener">Finaliser sur WhatsApp</a></p>
      <?php if ($enLigne): ?><p class="petit">Tu préfères payer tout de suite ? <a href="/paiement.php?formule=<?= e(rawurlencode($fo['id'])) ?>">Payer en ligne</a> (Wave, Orange Money, MTN, Moov, carte).</p><?php endif; ?>
    </section>
  <?php else: ?>
    <section class="carte-app paiement__formulaire">
      <p class="paiement__etape">Dernière étape</p>
      <h1 class="titre-app">Finalise ton inscription</h1>
      <p class="paiement__intro"><?= $enLigne ? 'Remplis tes informations, choisis ta formule, puis paie en ligne ou finalise sur WhatsApp. Ton accès s\'ouvre dès que le paiement est confirmé.' : 'Remplis tes informations et choisis ta formule, puis finalise sur WhatsApp : on t\'envoie les infos de paiement et ton accès s\'ouvre dès que le paiement est reçu.' ?></p>
      <?php if (!empty($erreurs['general'])): ?><p class="message message--erreur" role="alert"><?= e($erreurs['general']) ?></p><?php endif; ?>
      <form method="post" class="formulaire" novalidate>
        <?= champ_csrf() ?>
        <input type="hidden" name="f" value="<?= e($f['slug']) ?>">
        <label class="champ"><span>Nom et prénom</span>
          <input type="text" name="nom" value="<?= e($valeurs['nom']) ?>" autocomplete="name" required maxlength="80" <?= isset($erreurs['nom']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($erreurs['nom'])): ?><small class="champ__erreur"><?= e($erreurs['nom']) ?></small><?php endif; ?></label>
        <label class="champ"><span>Adresse e-mail <small>(tes accès arrivent ici)</small></span>
          <input type="email" name="email" value="<?= e($valeurs['email']) ?>" autocomplete="email" required <?= isset($erreurs['email']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($erreurs['email'])): ?><small class="champ__erreur"><?= e($erreurs['email']) ?></small><?php endif; ?></label>
        <label class="champ"><span>Numéro WhatsApp</span>
          <input type="tel" name="whatsapp" value="<?= e($valeurs['whatsapp']) ?>" autocomplete="tel" inputmode="tel" placeholder="Ex. : 07 47 93 37 48" required <?= isset($erreurs['whatsapp']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($erreurs['whatsapp'])): ?><small class="champ__erreur"><?= e($erreurs['whatsapp']) ?></small><?php endif; ?></label>
        <fieldset class="champ">
          <legend>Ta formule</legend>
          <div class="choix-formules">
            <?php foreach ($formules as $fo): ?>
              <label class="choix-formule-app">
                <input type="radio" name="formule" value="<?= e($fo['id']) ?>" data-prix="<?= e(prix($fo['prix'])) ?>" data-nom="<?= e($fo['nom']) ?>" <?= $fo['id'] === $choix ? 'checked' : '' ?>>
                <span><strong><?= e($fo['nom']) ?></strong><em><?= e(prix($fo['prix'])) ?></em></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php $actuelle = $formules[array_search($choix, $ids, true)]; ?>
        <?php if ($enLigne): ?>
          <button class="bouton bouton--plein paiement__payer" type="submit" name="moyen" value="en_ligne">Payer maintenant <span id="montant-bouton"><?= e(prix($actuelle['prix'])) ?></span></button>
          <p class="paiement__securite">Paiement sécurisé par CinetPay : Wave, Orange Money, MTN, Moov ou carte bancaire.</p>
          <p class="paiement__ou"><span>ou</span></p>
        <?php endif; ?>
        <button class="bouton bouton--plein <?= $enLigne ? 'bouton--verre' : '' ?> paiement__wa" type="submit" name="moyen" value="whatsapp"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.4.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3Z"/></svg>Finaliser sur WhatsApp</button>
        <p class="paiement__securite">Tu reçois les infos de paiement Mobile Money sur WhatsApp, puis on active ton accès. En continuant, tu acceptes notre <a href="/confidentialite.html">politique de confidentialité</a>.</p>
      </form>
    </section>
    <aside class="carte-app paiement__recap" aria-label="Récapitulatif">
      <img src="/assets/img/modules/module-3.webp" alt="" width="960" height="600">
      <h2 class="titre-bloc-app"><?= e($f['titre']) ?></h2>
      <ul class="paiement__inclus">
        <li>4 modules et 26 leçons vidéo</li>
        <li>Un livrable concret par module</li>
        <li>Sur téléphone et ordinateur</li>
        <li>Accès dès la confirmation du paiement</li>
      </ul>
      <p class="paiement__total"><span>Formule <strong id="formule-recap"><?= e($actuelle['nom']) ?></strong></span><strong id="montant-recap"><?= e(prix($actuelle['prix'])) ?></strong></p>
      <p class="petit">Une question avant de payer ? <a href="<?= e(lien_whatsapp('Bonjour, j\'ai une question avant de prendre la formation Idjô Académie.')) ?>" target="_blank" rel="noopener">Écris-nous sur WhatsApp</a>.</p>
    </aside>
  <?php endif; ?>
</div>
<script>
  // Met à jour le montant affiché quand on change de formule.
  document.querySelectorAll('.paiement input[name="formule"]').forEach(function (r) {
    r.addEventListener('change', function () {
      ['montant-bouton', 'montant-recap'].forEach(function (id) { var n = document.getElementById(id); if (n) n.textContent = r.dataset.prix; });
      var nom = document.getElementById('formule-recap'); if (nom) nom.textContent = r.dataset.nom;
    });
  });
</script>
<?php pied();
