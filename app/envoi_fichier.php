<?php
/** Envoie un fichier privé au navigateur, avec prise en charge des plages (avance rapide dans la vidéo). */
declare(strict_types=1);

function envoyer_fichier(string $chemin, string $type, string $nomTelechargement, bool $enLigne): never
{
    if (!is_file($chemin) || !is_readable($chemin)) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    @set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $taille = filesize($chemin);
    $debut = 0;
    $fin = $taille - 1;

    header('Content-Type: ' . $type);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($enLigne ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($nomTelechargement));

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $debut = max(0, $taille - (int) $m[2]);
        } else {
            $debut = (int) $m[1];
            $fin = $m[2] !== '' ? min((int) $m[2], $taille - 1) : $fin;
        }
        if ($debut > $fin || $debut >= $taille) {
            http_response_code(416);
            header("Content-Range: bytes */$taille");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $debut-$fin/$taille");
    }
    $longueur = $fin - $debut + 1;
    header('Content-Length: ' . $longueur);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    session_write_close();
    $f = fopen($chemin, 'rb');
    fseek($f, $debut);
    $reste = $longueur;
    while ($reste > 0 && !feof($f) && connection_status() === CONNECTION_NORMAL) {
        $morceau = fread($f, (int) min(1048576, $reste));
        if ($morceau === false) {
            break;
        }
        echo $morceau;
        flush();
        $reste -= strlen($morceau);
    }
    fclose($f);
    exit;
}
