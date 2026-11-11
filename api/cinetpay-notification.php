<?php
/**
 * Adresse appelée par CinetPay après chaque paiement (notify_url).
 * La notification ne contient pas le résultat : on demande toujours le vrai état à CinetPay
 * (verifier_paiement), puis on débloque la formation si le paiement est accepté.
 * CinetPay peut appeler plusieurs fois : le traitement ne se fait qu'une fois.
 */
declare(strict_types=1);
require __DIR__ . '/../app/paiement.php';

header('Content-Type: text/plain; charset=utf-8');
if (!est_post()) {
    echo 'OK'; // CinetPay vérifie seulement que l'adresse répond
    exit;
}
$transaction = (string) ($_POST['cpm_trans_id'] ?? '');
$site = (string) ($_POST['cpm_site_id'] ?? '');
if ($transaction === '' || !preg_match('/^[A-Z0-9]{8,40}$/', $transaction) || ($site !== '' && $site !== reglage('cinetpay_site_id'))) {
    http_response_code(200);
    echo 'IGNORE';
    exit;
}
$p = paiement_par_transaction($transaction);
if ($p) {
    verifier_paiement($p);
}
echo 'OK';
