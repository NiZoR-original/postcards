<?php
// Liste des pays/territoires (ISO 3166-1 alpha-2) proposés à la saisie.
// Les noms viennent d'ICU (extension intl), dans la langue demandée.
// Postcrossing traite certains territoires comme des pays à part (RE, GP, HK, PR…) : ils sont inclus.

const CODES_PAYS = [
    'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ',
    'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ',
    'CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ',
    'DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR',
    'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY',
    'HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP',
    'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY',
    'MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ',
    'NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY',
    'QA','RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
    'TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ',
    'VA','VC','VE','VG','VI','VN','VU','WF','WS','XK','YE','YT','ZA','ZM','ZW',
];

const NOMS_PAYS_COURTS = ['HK' => 'Hong Kong', 'MO' => 'Macao'];

function pays_valide(string $code): bool
{
    return in_array($code, CODES_PAYS, true);
}

function nom_pays(string $code, string $lang = 'fr'): string
{
    if (isset(NOMS_PAYS_COURTS[$code])) {
        return NOMS_PAYS_COURTS[$code];
    }
    $n = Locale::getDisplayRegion('und-' . $code, $lang);
    return ($n === '' || $n === $code) ? $code : $n;
}

/** [code => nom] trié alphabétiquement dans la langue donnée. */
function liste_pays(string $lang = 'fr'): array
{
    $l = [];
    foreach (CODES_PAYS as $c) {
        $l[$c] = nom_pays($c, $lang);
    }
    (new Collator($lang))->asort($l);
    return $l;
}

/** Drapeau emoji à partir du code ISO. */
function drapeau(string $code): string
{
    if ($code === 'XK') {
        return '🇽🇰';
    }
    $o = '';
    foreach (str_split(strtoupper($code)) as $ch) {
        $o .= mb_chr(0x1F1E6 + ord($ch) - 65);
    }
    return $o;
}

// Pays absents du fond de carte jsVectorMap « world » (trop petits) :
// affichés en marqueurs cliquables. [lat, lng]
const COORDS_PETITS_PAYS = [
    'AD' => [42.55, 1.6], 'AG' => [17.08, -61.8], 'AI' => [18.22, -63.05], 'AS' => [-14.3, -170.7],
    'AW' => [12.52, -69.97], 'AX' => [60.2, 19.95], 'BB' => [13.17, -59.55], 'BH' => [26.07, 50.55],
    'BL' => [17.9, -62.83], 'BM' => [32.32, -64.75], 'BQ' => [12.18, -68.25], 'CK' => [-21.24, -159.78],
    'CV' => [15.12, -23.6], 'CW' => [12.17, -68.98], 'DM' => [15.42, -61.35], 'FM' => [6.92, 158.18],
    'FO' => [62.0, -6.8], 'GD' => [12.12, -61.67], 'GF' => [4.0, -53.0], 'GG' => [49.45, -2.58],
    'GI' => [36.14, -5.35], 'GP' => [16.25, -61.58], 'GU' => [13.44, 144.79], 'HK' => [22.32, 114.17],
    'IM' => [54.24, -4.55], 'JE' => [49.21, -2.13], 'KI' => [1.87, -157.36], 'KM' => [-11.65, 43.33],
    'KN' => [17.36, -62.78], 'KY' => [19.31, -81.25], 'LC' => [13.91, -60.98], 'LI' => [47.16, 9.55],
    'MC' => [43.74, 7.42], 'MF' => [18.07, -63.05], 'MH' => [7.13, 171.18], 'MO' => [22.2, 113.54],
    'MP' => [15.18, 145.75], 'MQ' => [14.64, -61.02], 'MS' => [16.74, -62.19], 'MT' => [35.94, 14.38],
    'MU' => [-20.35, 57.55], 'MV' => [3.2, 73.22], 'NF' => [-29.04, 167.95], 'NR' => [-0.53, 166.93],
    'NU' => [-19.05, -169.87], 'PF' => [-17.65, -149.43], 'PM' => [46.89, -56.32], 'PN' => [-25.07, -130.1],
    'PW' => [7.51, 134.58], 'RE' => [-21.12, 55.54], 'SC' => [-4.68, 55.49], 'SG' => [1.35, 103.82],
    'SH' => [-15.97, -5.71], 'SJ' => [78.22, 15.65], 'SM' => [43.94, 12.46], 'ST' => [0.19, 6.61],
    'SX' => [18.04, -63.07], 'TC' => [21.69, -71.8], 'TK' => [-9.2, -171.85], 'TO' => [-21.18, -175.2],
    'TV' => [-7.11, 177.65], 'VA' => [41.9, 12.45], 'VC' => [13.25, -61.2], 'VG' => [18.42, -64.64],
    'VI' => [18.34, -64.9], 'WF' => [-13.77, -177.16], 'WS' => [-13.76, -172.1], 'XK' => [42.6, 20.9],
    'YT' => [-12.83, 45.17], 'CX' => [-10.49, 105.62], 'CC' => [-12.16, 96.87], 'IO' => [-6.34, 71.88],
    'UM' => [19.28, 166.65], 'GS' => [-54.43, -36.59], 'HM' => [-53.08, 73.5], 'BV' => [-54.42, 3.36],
    'AQ' => [-75.0, 0.0],
];

// Points toujours affichés sur la carte, même sans carte reçue ni envoyée :
// micro-États souverains absents du fond, plus Hong Kong, Macao et le Kosovo.
// Les autres territoires de COORDS_PETITS_PAYS n'ont un point que s'ils ont des cartes.
const PETITS_PAYS_TOUJOURS = [
    'AD', 'AG', 'BB', 'BH', 'CV', 'DM', 'FM', 'GD', 'KI', 'KM', 'KN', 'LC', 'LI', 'MC', 'MH', 'MT',
    'MU', 'MV', 'NR', 'PW', 'SC', 'SG', 'SM', 'ST', 'TO', 'TV', 'VA', 'VC', 'WS',
    'HK', 'MO', 'XK',
];
