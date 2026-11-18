<?php
// Carte publique des États des USA et des provinces / territoires du Canada.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/page_carte.php';

page_carte_regionale([
    'actif' => 'usa',
    'titre' => t('usa_titre'),
    'fond' => 'usa_canada',
    'script_fond' => 'assets/fonds/usa-canada.js',
    'stat_zones' => 'stat_regions',
    'clic' => 'clic_region',
    'titre_liste' => 'liste_regions',
    'choisir' => 'atlas_choisir_region',
], PAYS_USA_CANADA, TERRITOIRES_US, 'sans_region', ZONES_EN_POINT_USA);
