<?php
/**
 * Envoi d'e-mails par un serveur SMTP authentifié (ex. Gmail avec un mot de passe d'application).
 * Petit client sans dépendance : connexion SSL (port 465) ou STARTTLS (port 587), AUTH LOGIN.
 */
declare(strict_types=1);

final class EchecSmtp extends RuntimeException
{
}

function smtp_envoyer(array $r, string $a, string $sujet, string $html): void
{
    $hote = (string) $r['hote'];
    $port = (int) $r['port'];
    $ssl = $port === 465;
    $verifier = (bool) conf('mail.verifier_certificat', true);   // à garder vrai ; faux seulement pour les tests locaux
    $contexte = stream_context_create(['ssl' => ['verify_peer' => $verifier, 'verify_peer_name' => $verifier, 'allow_self_signed' => !$verifier, 'peer_name' => $hote]]);
    $flux = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $hote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $contexte);
    if (!$flux) {
        throw new EchecSmtp("Connexion impossible à $hote:$port ($errstr)");
    }
    stream_set_timeout($flux, 20);

    $lire = static function () use ($flux): array {
        $texte = '';
        while (($ligne = fgets($flux, 1024)) !== false) {
            $texte .= $ligne;
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }
        return [(int) substr($texte, 0, 3), trim($texte)];
    };
    $commande = static function (string $ligne, array $attendus, string $etape) use ($flux, $lire): string {
        fwrite($flux, $ligne . "\r\n");
        [$code, $reponse] = $lire();
        if (!in_array($code, $attendus, true)) {
            throw new EchecSmtp("$etape refusé : $reponse");
        }
        return $reponse;
    };

    try {
        [$code, $accueil] = $lire();
        if ($code !== 220) {
            throw new EchecSmtp("Accueil inattendu : $accueil");
        }
        $nomMachine = parse_url((string) conf('url'), PHP_URL_HOST) ?: 'localhost';
        $commande("EHLO $nomMachine", [250], 'EHLO');
        if (!$ssl) {
            $commande('STARTTLS', [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new EchecSmtp('Chiffrement TLS impossible');
            }
            $commande("EHLO $nomMachine", [250], 'EHLO');
        }
        $commande('AUTH LOGIN', [334], 'AUTH');
        $commande(base64_encode((string) $r['utilisateur']), [334], 'Identifiant');
        $commande(base64_encode((string) $r['mot_de_passe']), [235], 'Mot de passe d\'application');

        $expediteur = (string) $r['expediteur'];
        $commande("MAIL FROM:<$expediteur>", [250], 'MAIL FROM');
        $commande("RCPT TO:<$a>", [250, 251], 'Destinataire');
        $commande('DATA', [354], 'DATA');

        $domaine = substr(strrchr($expediteur, '@') ?: '@localhost', 1);
        $entetes = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mb_encode_mimeheader('Idjô Académie', 'UTF-8') . " <$expediteur>",
            "To: <$a>",
            'Reply-To: ' . ADMIN_EMAIL,
            'Subject: ' . mb_encode_mimeheader($sujet, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domaine>",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $message = implode("\r\n", $entetes) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
        fwrite($flux, $message . "\r\n.\r\n");
        [$code, $reponse] = $lire();
        if ($code !== 250) {
            throw new EchecSmtp("Message refusé : $reponse");
        }
        fwrite($flux, "QUIT\r\n");
    } finally {
        fclose($flux);
    }
}
