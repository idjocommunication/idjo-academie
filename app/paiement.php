<?php
/**
 * Paiement en ligne avec CinetPay (Wave, Orange Money, MTN, Moov, carte).
 * Doc : https://docs.cinetpay.com/api/1.0-fr/checkout/initialisation
 * L'ID de site et la clé API sont collés par l'admin dans Admin > Réglages (jamais dans le code).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function cinetpay_configure(): bool
{
    return reglage('cinetpay_site_id') !== '' && reglage('cinetpay_apikey') !== '';
}

/** Appel JSON à l'API CinetPay. Renvoie la réponse décodée, ou lève une exception. */
function cinetpay_appel(string $chemin, array $donnees): array
{
    $base = rtrim((string) conf('cinetpay.url', 'https://api-checkout.cinetpay.com'), '/');
    $donnees += ['apikey' => reglage('cinetpay_apikey'), 'site_id' => reglage('cinetpay_site_id')];
    $ch = curl_init($base . $chemin);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($donnees, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_USERAGENT => 'IdjoAcademie/1.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $corps = curl_exec($ch);
    $erreur = curl_error($ch);
    curl_close($ch);
    if ($corps === false) {
        throw new RuntimeException('CinetPay injoignable : ' . $erreur);
    }
    $reponse = json_decode((string) $corps, true);
    if (!is_array($reponse)) {
        throw new RuntimeException('Réponse CinetPay illisible.');
    }
    return $reponse;
}

/** Nouveau numéro de transaction, unique et sans caractère spécial. */
function nouvelle_transaction(): string
{
    return 'IDJO' . date('ymdHis') . strtoupper(bin2hex(random_bytes(4)));
}

function paiement_par_transaction(string $transaction): ?array
{
    $st = db()->prepare('SELECT * FROM paiements WHERE transaction_id = ?');
    $st->execute([$transaction]);
    return $st->fetch() ?: null;
}

/** Crée le guichet de paiement CinetPay et renvoie son adresse. */
function cinetpay_guichet(array $p, array $formation): string
{
    $parties = explode(' ', trim($p['nom']), 2);
    $reponse = cinetpay_appel('/v2/payment', [
        'transaction_id' => $p['transaction_id'],
        'amount' => (int) $p['montant'],
        'currency' => 'XOF',
        'description' => 'Formation Idjo Academie - formule ' . preg_replace('/[#\/$_&]/', ' ', $p['formule']),
        'notify_url' => url('api/cinetpay-notification.php'),
        'return_url' => url('paiement-retour.php?transaction=' . rawurlencode($p['transaction_id'])),
        'channels' => 'ALL',
        'lang' => 'FR',
        'metadata' => (string) $p['id'],
        'customer_id' => (string) $p['utilisateur_id'],
        'customer_name' => $parties[0],
        'customer_surname' => $parties[1] ?? $parties[0],
        'customer_email' => $p['email'],
        'customer_phone_number' => ltrim($p['whatsapp'], '+'),
        'customer_address' => 'Abidjan',
        'customer_city' => 'Abidjan',
        'customer_country' => 'CI',
        'customer_state' => 'CI',
        'customer_zip_code' => '00225',
    ]);
    $adresse = (string) ($reponse['data']['payment_url'] ?? '');
    if (($reponse['code'] ?? '') !== '201' || !str_starts_with($adresse, 'https://')) {
        throw new RuntimeException('CinetPay a refusé la création du paiement (' . ($reponse['code'] ?? '?') . ' ' . ($reponse['message'] ?? '') . ').');
    }
    return $adresse;
}

/**
 * Demande à CinetPay l'état réel d'un paiement et met la base à jour.
 * Débloque la formation quand le paiement est accepté (une seule fois, même si on est appelé plusieurs fois).
 * Renvoie le paiement à jour.
 */
function verifier_paiement(array $p): array
{
    if ($p['statut'] === 'accepte' || !cinetpay_configure()) {
        return $p;
    }
    try {
        $reponse = cinetpay_appel('/v2/payment/check', ['transaction_id' => $p['transaction_id']]);
    } catch (Throwable $e) {
        return $p;
    }
    $data = $reponse['data'] ?? [];
    $statut = (string) ($data['status'] ?? '');
    if ($statut === 'ACCEPTED' && (int) ($data['amount'] ?? 0) >= (int) $p['montant'] && ($data['currency'] ?? 'XOF') === 'XOF') {
        confirmer_paiement($p, (string) ($data['payment_method'] ?? ''));
    } elseif ($statut === 'REFUSED' || ($reponse['code'] ?? '') === '627') {
        db()->prepare("UPDATE paiements SET statut = 'refuse', modifie_le = ? WHERE id = ? AND statut = 'en_attente'")->execute([maintenant(), $p['id']]);
    }
    return paiement_par_transaction($p['transaction_id']) ?? $p;
}

/** Marque le paiement accepté, débloque la formation et envoie les e-mails (une seule fois). */
function confirmer_paiement(array $p, string $moyen): void
{
    $st = db()->prepare("UPDATE paiements SET statut = 'accepte', moyen = ?, modifie_le = ? WHERE id = ? AND statut <> 'accepte'");
    $st->execute([$moyen, maintenant(), $p['id']]);
    if ($st->rowCount() === 0) {
        return; // déjà traité
    }
    accorder_acces((int) $p['utilisateur_id'], (int) $p['formation_id'], $p['formule'], 'Paiement en ligne ' . $p['transaction_id'], false);
    db()->prepare("UPDATE prospects SET statut = 'inscrit', modifie_le = ? WHERE whatsapp = ?")->execute([maintenant(), $p['whatsapp']]);

    $f = formation_par_id((int) $p['formation_id']);
    $titre = $f ? $f['titre'] : 'ta formation';
    $prenom = explode(' ', trim($p['nom']))[0];
    // Lien de connexion valable 7 jours, pour entrer dans les cours sans mot de passe.
    $jeton = creer_jeton($p['email'], 'magique', 7 * 24 * 60);
    envoyer_email(
        $p['email'],
        'Ton accès à Idjô Académie est prêt',
        'Bienvenue ' . $prenom . ' !',
        '<p>Ton paiement de <strong>' . e(prix((int) $p['montant'])) . '</strong> est confirmé. Ton accès à <strong>' . e($titre) . '</strong> (formule ' . e($p['formule']) . ') est ouvert.</p>'
            . '<p>Clique sur le bouton pour entrer dans tes cours. Ce lien est valable 7 jours ; ensuite, connecte-toi avec ton adresse e-mail.</p>',
        'Entrer dans ma formation',
        url('compte/verifier.php?type=magique&jeton=' . $jeton)
    );
    envoyer_email(
        ADMIN_EMAIL,
        'Nouvelle vente : ' . $p['nom'] . ' (' . prix((int) $p['montant']) . ')',
        'Nouvelle vente en ligne',
        '<p><strong>' . e($p['nom']) . '</strong> (' . e($p['email']) . ', WhatsApp ' . e(formater_whatsapp($p['whatsapp'])) . ') a payé <strong>' . e(prix((int) $p['montant'])) . '</strong>, formule ' . e($p['formule']) . ($moyen !== '' ? ', par ' . e($moyen) : '') . '.</p><p>Son accès est déjà activé.</p>',
        'Voir les paiements',
        url('admin/paiements.php')
    );
}
