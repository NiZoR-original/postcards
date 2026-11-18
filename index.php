<?php
// Carte du monde publique.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/page_carte.php';

$lang = langue_courante();

// Comptage public par pays et par sens
$stats = [];
foreach (db()->query("SELECT pays_code, sens, COUNT(*) n FROM cartes
    WHERE publique = 1 AND NOT (" . CARTE_EN_ROUTE_SQL . ") GROUP BY pays_code, sens") as $r) {
    $stats[$r['pays_code']] ??= ['recue' => 0, 'envoyee' => 0, 'nom' => nom_pays($r['pays_code'], $lang), 'drapeau' => drapeau($r['pays_code'])];
    $stats[$r['pays_code']][$r['sens']] = (int) $r['n'];
}

page_carte([
    'actif' => 'carte',
    'titre' => t('nav_carte'),
    'fond' => 'world',
    'script_fond' => 'https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/maps/world.js',
    'stats' => $stats,
    // Petits pays absents du fond : toujours pour les micro-États, sinon seulement s'ils ont des cartes
    'coords' => array_intersect_key(COORDS_PETITS_PAYS, $stats + array_flip(PETITS_PAYS_TOUJOURS)),
    'stat_zones' => 'stat_pays',
    'clic' => 'clic_pays',
    'titre_liste' => 'autres_pays',
    'choisir' => 'atlas_choisir_pays',
]);
