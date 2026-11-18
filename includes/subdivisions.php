<?php
// États des USA (+ DC) et provinces / territoires du Canada (ISO 3166-2).
// Les départements métropolitains utilisent les codes COG de l’Insee préfixés par FR-.

require_once __DIR__ . '/departements_fr.php';

const SUBDIVISIONS = [
    'CA-AB' => ['Alberta', 'Alberta'],
    'CA-BC' => ['Colombie-Britannique', 'British Columbia'],
    'CA-MB' => ['Manitoba', 'Manitoba'],
    'CA-NB' => ['Nouveau-Brunswick', 'New Brunswick'],
    'CA-NL' => ['Terre-Neuve-et-Labrador', 'Newfoundland and Labrador'],
    'CA-NS' => ['Nouvelle-Écosse', 'Nova Scotia'],
    'CA-NT' => ['Territoires du Nord-Ouest', 'Northwest Territories'],
    'CA-NU' => ['Nunavut', 'Nunavut'],
    'CA-ON' => ['Ontario', 'Ontario'],
    'CA-PE' => ['Île-du-Prince-Édouard', 'Prince Edward Island'],
    'CA-QC' => ['Québec', 'Quebec'],
    'CA-SK' => ['Saskatchewan', 'Saskatchewan'],
    'CA-YT' => ['Yukon', 'Yukon'],
    'US-AK' => ['Alaska', 'Alaska'],
    'US-AL' => ['Alabama', 'Alabama'],
    'US-AR' => ['Arkansas', 'Arkansas'],
    'US-AZ' => ['Arizona', 'Arizona'],
    'US-CA' => ['Californie', 'California'],
    'US-CO' => ['Colorado', 'Colorado'],
    'US-CT' => ['Connecticut', 'Connecticut'],
    'US-DC' => ['District de Columbia', 'District of Columbia'],
    'US-DE' => ['Delaware', 'Delaware'],
    'US-FL' => ['Floride', 'Florida'],
    'US-GA' => ['Géorgie', 'Georgia'],
    'US-HI' => ['Hawaï', 'Hawaii'],
    'US-IA' => ['Iowa', 'Iowa'],
    'US-ID' => ['Idaho', 'Idaho'],
    'US-IL' => ['Illinois', 'Illinois'],
    'US-IN' => ['Indiana', 'Indiana'],
    'US-KS' => ['Kansas', 'Kansas'],
    'US-KY' => ['Kentucky', 'Kentucky'],
    'US-LA' => ['Louisiane', 'Louisiana'],
    'US-MA' => ['Massachusetts', 'Massachusetts'],
    'US-MD' => ['Maryland', 'Maryland'],
    'US-ME' => ['Maine', 'Maine'],
    'US-MI' => ['Michigan', 'Michigan'],
    'US-MN' => ['Minnesota', 'Minnesota'],
    'US-MO' => ['Missouri', 'Missouri'],
    'US-MS' => ['Mississippi', 'Mississippi'],
    'US-MT' => ['Montana', 'Montana'],
    'US-NC' => ['Caroline du Nord', 'North Carolina'],
    'US-ND' => ['Dakota du Nord', 'North Dakota'],
    'US-NE' => ['Nebraska', 'Nebraska'],
    'US-NH' => ['New Hampshire', 'New Hampshire'],
    'US-NJ' => ['New Jersey', 'New Jersey'],
    'US-NM' => ['Nouveau-Mexique', 'New Mexico'],
    'US-NV' => ['Nevada', 'Nevada'],
    'US-NY' => ['New York', 'New York'],
    'US-OH' => ['Ohio', 'Ohio'],
    'US-OK' => ['Oklahoma', 'Oklahoma'],
    'US-OR' => ['Oregon', 'Oregon'],
    'US-PA' => ['Pennsylvanie', 'Pennsylvania'],
    'US-RI' => ['Rhode Island', 'Rhode Island'],
    'US-SC' => ['Caroline du Sud', 'South Carolina'],
    'US-SD' => ['Dakota du Sud', 'South Dakota'],
    'US-TN' => ['Tennessee', 'Tennessee'],
    'US-TX' => ['Texas', 'Texas'],
    'US-UT' => ['Utah', 'Utah'],
    'US-VA' => ['Virginie', 'Virginia'],
    'US-VT' => ['Vermont', 'Vermont'],
    'US-WA' => ['Washington', 'Washington'],
    'US-WI' => ['Wisconsin', 'Wisconsin'],
    'US-WV' => ['Virginie-Occidentale', 'West Virginia'],
    'US-WY' => ['Wyoming', 'Wyoming'],
];

const PAYS_USA_CANADA = ['US', 'CA'];
const PAYS_AVEC_SUBDIVISIONS = [...PAYS_USA_CANADA, 'FR'];

/**
 * Territoires américains affichés en encarts sur la carte USA & Canada. Ce sont des pays à part
 * (comme sur Postcrossing) : leurs cartes sont repérées par cartes.pays_code, pas par subdivision.
 */
const TERRITOIRES_US = ['PR', 'VI', 'GU', 'MP', 'AS'];

// DROM, COM et Nouvelle-Calédonie : codes pays/territoire déjà utilisés dans cartes.pays_code.
const OUTRE_MER_FR = ['GP', 'MQ', 'GF', 'RE', 'YT', 'PM', 'BL', 'MF', 'WF', 'PF', 'NC'];

// Zones de la carte USA & Canada trop petites pour être cliquées : affichées aussi en point
const ZONES_EN_POINT_USA = ['US-DC', 'VI', 'GU', 'MP', 'AS'];

function subdivision_valide(?string $code, string $pays): bool
{
    return $code !== null
        && (isset(SUBDIVISIONS[$code]) || isset(DEPARTEMENTS_FR[$code]))
        && str_starts_with($code, $pays . '-');
}

function nom_subdivision(string $code, string $lang = 'fr'): string
{
    return SUBDIVISIONS[$code][$lang === 'en' ? 1 : 0] ?? DEPARTEMENTS_FR[$code] ?? $code;
}

/** [code => nom] des subdivisions d'un pays (ou de tous), triées par nom. */
function liste_subdivisions(?string $pays = null, string $lang = 'fr'): array
{
    $l = [];
    foreach (array_keys(SUBDIVISIONS + DEPARTEMENTS_FR) as $c) {
        if ($pays === null || str_starts_with($c, $pays . '-')) {
            $l[$c] = nom_subdivision($c, $lang);
        }
    }
    (new Collator($lang))->asort($l);
    return $l;
}
