<?php

// Métadonnées dérivées de libaddressinput (Apache 2.0), voir LICENCE-formats-adresse.txt.
const CHAMPS_ADRESSE = [
    'prenom' => ['N', 'Prénom', 'given-name', 80],
    'nom' => ['N', 'Nom', 'family-name', 80],
    'ligne1' => ['A', 'Ligne d’adresse 1', 'address-line1', 180],
    'ligne2' => ['A', 'Ligne d’adresse 2', 'address-line2', 180],
    'code_postal' => ['Z', 'Code postal', 'postal-code', 24],
    'ville' => ['C', 'Ville', 'address-level2', 120],
    'region' => ['S', 'Région / province', 'address-level1', 120],
    'quartier' => ['D', 'Quartier / localité', 'address-level3', 120],
];

function formats_adresse(): array
{
    static $formats = null;
    return $formats ??= json_decode(file_get_contents(__DIR__ . '/formats-adresse.json'), true, 512, JSON_THROW_ON_ERROR);
}

function format_adresse(string $pays): array
{
    $formats = formats_adresse();
    $format = $formats[$pays] ?? $formats['ZZ'];
    if (!str_contains($format['ordre'], 'C')) {
        // Une localité facultative reste utilisable pour le calcul même si l’adresse postale n’en demande pas.
        $format['ordre'] .= 'C';
        $format['ville'] = 'calcul';
    }
    return $format;
}

function adresse_personnelle(): array
{
    $enregistree = json_decode(parametre('adresse_personnelle') ?? '{}', true);
    return (is_array($enregistree) ? $enregistree : []) + ['pays' => 'FR'];
}

function libelle_champ_adresse(string $cle, array $format): string
{
    return match ($format[$cle === 'code_postal' ? 'postal' : $cle] ?? '') {
        'state' => 'État', 'province' => 'Province', 'county' => 'Comté', 'emirate' => 'Émirat',
        'prefecture' => 'Préfecture', 'oblast' => 'Oblast', 'department' => 'Département',
        'parish' => 'Paroisse', 'district' => 'District', 'island' => 'Île',
        'zip' => 'Code ZIP', 'eircode' => 'Eircode', 'pin' => 'Code PIN',
        'post_town' => 'Ville postale', 'townland' => 'Localité',
        'calcul' => 'Localité pour le calcul de distance',
        default => CHAMPS_ADRESSE[$cle][1],
    };
}
