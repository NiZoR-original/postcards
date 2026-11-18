<?php
// Géocodage privé demandé explicitement depuis le formulaire d’une carte.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/adresse.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function reponse_distance(array $donnees, int $statut = 200): never
{
    http_response_code($statut);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reponse_distance(['erreur' => 'Utilise le formulaire de la carte.'], 405);
}
if (!est_admin()) {
    reponse_distance(['erreur' => 'Reconnecte-toi à l’administration pour calculer la distance.'], 403);
}
csrf_verifier();
$_SESSION['pc_last'] = time();
session_write_close();

function lieux_geocodes(string $terme, string $pays): array
{
    $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
        'name' => $terme, 'countryCode' => $pays, 'count' => 12, 'language' => 'fr',
    ]);
    $requete = curl_init($url);
    curl_setopt_array($requete, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FAILONERROR => true,
    ]);
    $contenu = curl_exec($requete);
    if (!is_string($contenu)) {
        throw new RuntimeException('Géocodage indisponible.');
    }
    $resultats = json_decode($contenu, true, 512, JSON_THROW_ON_ERROR);
    $lieux = [];
    foreach ($resultats['results'] ?? [] as $lieu) {
        if (($lieu['country_code'] ?? '') !== $pays || !isset($lieu['name'], $lieu['latitude'], $lieu['longitude'])) {
            continue;
        }
        $parties = array_unique(array_filter([$lieu['name'], $lieu['admin2'] ?? '', $lieu['admin1'] ?? '']));
        $lieux[] = [
            'nom' => $lieu['name'],
            'libelle' => implode(' · ', $parties),
            'latitude' => (float) $lieu['latitude'],
            'longitude' => (float) $lieu['longitude'],
        ];
    }
    return $lieux;
}

function lieux_prioritaires(array $lieux, string $ville): array
{
    $comparateur = new Collator('fr');
    $comparateur->setStrength(Collator::PRIMARY);
    $exacts = array_filter($lieux, fn($lieu) => $comparateur->compare($lieu['nom'], $ville) === 0);
    return array_values($exacts ?: $lieux);
}

$pays = is_string($_POST['pays'] ?? null) ? strtoupper($_POST['pays']) : '';
$ville = is_string($_POST['ville'] ?? null) ? trim($_POST['ville']) : '';
if (!pays_valide($pays) || $ville === '' || mb_strlen($ville) > 120) {
    reponse_distance(['erreur' => 'Indique le pays et la ville de ton correspondant.'], 422);
}
$adresse = adresse_personnelle();
$paysMaison = $adresse['pays'];
$villeMaison = $adresse['ville'] ?? '';
$postalMaison = $adresse['code_postal'] ?? '';
if ($villeMaison === '' && $postalMaison === '') {
    reponse_distance(['erreur' => 'Renseigne ta ville ou ton code postal dans Paramètres → Mon adresse.'], 422);
}
if (!function_exists('curl_init')) {
    reponse_distance(['erreur' => 'Le calcul de distance est indisponible sur ce serveur.'], 503);
}

try {
    $domicile = $postalMaison !== '' ? lieux_geocodes($postalMaison, $paysMaison) : [];
    if (!$domicile && $villeMaison !== '') {
        $domicile = lieux_geocodes($villeMaison, $paysMaison);
    }
    $subdivision = $_POST['subdivision'] ?? null;
    $recherche = in_array($pays, PAYS_USA_CANADA, true) && is_string($subdivision) && subdivision_valide($subdivision, $pays)
        ? $ville . ', ' . substr($subdivision, 3)
        : $ville;
    $correspondant = lieux_geocodes($recherche, $pays);
    if (!$domicile || !$correspondant) {
        $erreur = !$domicile
            ? 'Ton adresse n’a pas pu être localisée. Vérifie la ville et le code postal dans les paramètres.'
            : 'La ville du correspondant est introuvable. Vérifie le pays, la ville et la subdivision.';
        reponse_distance(['erreur' => $erreur], 422);
    }
    reponse_distance([
        'domicile' => lieux_prioritaires($domicile, $villeMaison),
        'correspondant' => lieux_prioritaires($correspondant, $ville),
    ]);
} catch (RuntimeException | JsonException $e) {
    reponse_distance(['erreur' => 'Le service de géocodage est indisponible. Tu peux saisir la distance manuellement.'], 502);
}
