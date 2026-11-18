<?php
// Carte publique des départements métropolitains et de l’outre-mer français.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/page_carte.php';

page_carte_regionale([
    'actif' => 'france',
    'titre' => t('nav_france'),
    'fond' => 'france',
    'script_fond' => 'assets/fonds/france.js',
    'stat_zones' => 'stat_zones_france',
    'clic' => 'clic_france',
    'titre_liste' => 'liste_france',
    'choisir' => 'atlas_choisir_france',
    'source' => [
        'nom' => 'data.gouv.fr · Contours administratifs 2026 (ODbL)',
        'url' => 'https://www.data.gouv.fr/datasets/contours-administratifs',
    ],
], ['FR'], OUTRE_MER_FR, 'sans_region_france');
