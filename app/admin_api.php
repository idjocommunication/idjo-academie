<?php
/** Outils de l'admin utilisables sans afficher de page (API d'envoi). */
declare(strict_types=1);

/** Supprime un fichier privé sans jamais sortir du dossier prévu. */
function supprimer_fichier_prive(string $sousDossier, ?string $nom): void
{
    if ($nom) {
        $chemin = dossier_stockage($sousDossier) . '/' . basename($nom);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }
}

/** Efface les envois interrompus depuis plus de 3 jours. */
function nettoyer_envois_abandonnes(): void
{
    foreach (glob(dossier_stockage('tmp') . '/*.part') ?: [] as $f) {
        if (filemtime($f) < time() - 3 * 86400) {
            @unlink($f);
        }
    }
}

/** Taille d'un morceau d'envoi accepté par le serveur (selon upload_max_filesize et post_max_size), entre 512 Ko et 5 Mo. */
function taille_morceau(): int
{
    $octets = static function (string $valeur): int {
        $valeur = trim($valeur);
        $nombre = (float) $valeur;
        return (int) match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1073741824,
            'm' => $nombre * 1048576,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    };
    $limite = min($octets((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, ($octets((string) ini_get('post_max_size')) ?: PHP_INT_MAX) - 65536);
    return max(524288, min(5 * 1048576, $limite - 1024));
}
