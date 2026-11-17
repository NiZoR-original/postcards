<?php
// Carte publique des États des USA et des provinces / territoires du Canada.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/page_carte.php';

$lang = langue_courante();

$stats = [];
$sansRegion = 0;
// Zones : États / provinces (code ISO 3166-2 dans cartes.subdivision) et territoires américains (pays à part)
$enListe = "'" . implode("','", array_merge(PAYS_AVEC_SUBDIVISIONS, TERRITOIRES_US)) . "'";
$st = db()->query("SELECT pays_code, subdivision, sens, COUNT(*) n FROM cartes
    WHERE publique = 1 AND pays_code IN ($enListe) GROUP BY pays_code, subdivision, sens");
foreach ($st as $r) {
    if (in_array($r['pays_code'], TERRITOIRES_US, true)) {
        $code = $r['pays_code'];
        $nom = nom_pays($code, $lang);
    } elseif ($r['subdivision'] !== null && isset(SUBDIVISIONS[$r['subdivision']])) {
        $code = $r['subdivision'];
        $nom = nom_subdivision($code, $lang);
    } else {
        $sansRegion += (int) $r['n'];
        continue;
    }
    $stats[$code] ??= ['recue' => 0, 'envoyee' => 0, 'nom' => $nom, 'drapeau' => drapeau(substr($code, 0, 2))];
    $stats[$code][$r['sens']] = (int) $r['n'];
}

$noms = liste_subdivisions(null, $lang);
foreach (TERRITOIRES_US as $t) {
    $noms[$t] = nom_pays($t, $lang);
}

// Points des régions trop petites : coordonnées en pixels du fond, lues dans le fichier généré
$coords = [];
$fond = file_get_contents(__DIR__ . '/assets/fonds/usa-canada.js');
if (preg_match('/"centres":(\{[^}]*\})/', $fond, $m)) {
    $centres = json_decode($m[1], true) ?: [];
    $coords = array_intersect_key($centres, array_flip(ZONES_EN_POINT_USA));
}

page_carte([
    'actif' => 'usa',
    'titre' => t('usa_titre'),
    'fond' => 'usa_canada',
    'script_fond' => 'assets/fonds/usa-canada.js',
    'stats' => $stats,
    'coords' => $coords,
    'noms' => $noms,
    'stat_zones' => 'stat_regions',
    'clic' => 'clic_region',
    'titre_liste' => 'liste_regions',
    'note' => $sansRegion ? t('sans_region', $sansRegion) : null,
]);
