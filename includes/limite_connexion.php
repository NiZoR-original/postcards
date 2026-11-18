<?php

/**
 * Réserve une tentative dans une fenêtre de 15 minutes, partagée entre sessions.
 * Renvoie le nombre de secondes à attendre, ou zéro si la tentative est admise.
 */
function reserver_tentative_connexion(string $adresse): int
{
    $dossier = UPLOAD_DIR . '/thumbs';
    $etat = $dossier . '/.connexion.json';
    $verrou = fopen($dossier . '/.connexion.lock', 'c');
    if ($verrou === false) {
        throw new RuntimeException('Stockage des tentatives indisponible.');
    }
    try {
        if (!flock($verrou, LOCK_EX)) {
            throw new RuntimeException('Verrou des tentatives indisponible.');
        }
        // Un nombre fixe de compartiments borne la taille du fichier partagé.
        $ip = inet_pton($adresse);
        $compartiment = substr(hash('sha256', $ip === false ? '' : $ip), 0, 4);
        $maintenant = time();
        $tentatives = is_file($etat)
            ? json_decode(file_get_contents($etat), true, 512, JSON_THROW_ON_ERROR)
            : [];
        $tentatives = array_filter($tentatives, fn($entree) => $entree['fin'] > $maintenant);
        $entree = $tentatives[$compartiment] ?? ['fin' => $maintenant + 900, 'nombre' => 0];
        if ($entree['nombre'] >= 10) {
            return $entree['fin'] - $maintenant;
        }
        $entree['nombre']++;
        $tentatives[$compartiment] = $entree;
        $contenu = json_encode($tentatives, JSON_THROW_ON_ERROR);
        $temporaire = tempnam($dossier, '.connexion-');
        if ($temporaire === false) {
            throw new RuntimeException('Stockage des tentatives indisponible.');
        }
        try {
            if (file_put_contents($temporaire, $contenu) !== strlen($contenu) || !rename($temporaire, $etat)) {
                throw new RuntimeException('Écriture des tentatives impossible.');
            }
        } finally {
            if (is_file($temporaire)) {
                unlink($temporaire);
            }
        }
        return 0;
    } finally {
        fclose($verrou);
    }
}
