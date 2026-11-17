<?php
// Traductions de l'interface publique (l'admin est en français uniquement).
// Langue : ?lang=fr|en, puis cookie, puis Accept-Language, défaut fr.

function langue_courante(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $dispo = ['fr', 'en'];
    if (isset($_GET['lang']) && in_array($_GET['lang'], $dispo, true)) {
        $lang = $_GET['lang'];
        setcookie('pc_lang', $lang, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
    } elseif (isset($_COOKIE['pc_lang']) && in_array($_COOKIE['pc_lang'], $dispo, true)) {
        $lang = $_COOKIE['pc_lang'];
    } else {
        $lang = langue_navigateur($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    }
    return $lang;
}

/**
 * Langue déduite de l'en-tête Accept-Language : 'fr' si la langue préférée du visiteur
 * est le français (fr, fr-FR, fr-CA…), 'en' pour toute autre langue ou en-tête absent.
 */
function langue_navigateur(string $accept): string
{
    $meilleure = null;
    $poidsMax = -1.0;
    foreach (explode(',', $accept) as $i => $partie) {
        $morceaux = explode(';', trim($partie));
        $tag = strtolower(trim($morceaux[0]));
        if ($tag === '' || $tag === '*') {
            continue;
        }
        $q = 1.0;
        foreach (array_slice($morceaux, 1) as $param) {
            if (preg_match('/^\s*q\s*=\s*([0-9.]+)/', $param, $m)) {
                $q = (float) $m[1];
            }
        }
        // à poids égal, l'ordre de l'en-tête départage (le premier gagne)
        if ($q > $poidsMax) {
            $poidsMax = $q;
            $meilleure = $tag;
        }
    }
    return $meilleure !== null && ($meilleure === 'fr' || str_starts_with($meilleure, 'fr-')) ? 'fr' : 'en';
}

function t(string $cle, ...$args): string
{
    static $dico = [
        'fr' => [
            'site_titre' => 'Mes cartes postales',
            'nav_carte' => 'Carte du monde',
            'nav_swaps' => 'Swaps',
            'recues' => 'Reçues',
            'envoyees' => 'Envoyées',
            'toutes' => 'Toutes',
            'stat_recues' => '%d cartes reçues',
            'stat_envoyees' => '%d cartes envoyées',
            'stat_pays' => '%d pays',
            'clic_pays' => 'Cliquez sur un pays pour voir ses cartes.',
            'aucune_carte' => 'Aucune carte pour ce pays.',
            'autres_pays' => 'Tous les pays',
            'recue_de' => 'Reçue de %s',
            'envoyee_vers' => 'Envoyée vers %s',
            'en_route' => 'En route',
            'voyage' => '%d jours de voyage',
            'envoyee_le' => 'Envoyée le %s',
            'recue_le' => 'Reçue le %s',
            'voir_sur' => 'Voir sur %s',
            'distance' => '%s km',
            'retour' => 'Retour',
            'carte_introuvable' => 'Carte introuvable.',
            'swaps_titre' => 'Cartes à échanger',
            'swaps_intro' => 'Ces cartes sont disponibles pour un échange. Contactez-moi sur le Discord de Postcrossing en indiquant le numéro de la carte.',
            'swaps_vide' => 'Aucune carte disponible pour le moment.',
            'disponible' => 'Disponible',
            'reservee' => 'Réservée',
            'filtre_tous' => 'Toutes',
            'legende' => 'Nombre de cartes',
            'sans_photo' => 'Pas de photo',
            'fermer' => 'Fermer',
            'theme' => 'Thème',
            'theme_auto' => 'Thème par défaut (celui du système)',
            'theme_clair' => 'Mode clair',
            'theme_sombre' => 'Mode sombre',
            'nav_usa' => 'USA & Canada',
            'usa_titre' => 'États-Unis et Canada',
            'stat_regions' => '%d États et provinces',
            'clic_region' => 'Cliquez sur un État ou une province pour voir ses cartes.',
            'liste_regions' => 'Tous les États et provinces',
            'voir_regions' => 'Voir par État et province',
            'sans_region' => '%d carte(s) des États-Unis ou du Canada sans État ni province indiqué.',
        ],
        'en' => [
            'site_titre' => 'My postcards',
            'nav_carte' => 'World map',
            'nav_swaps' => 'Swaps',
            'recues' => 'Received',
            'envoyees' => 'Sent',
            'toutes' => 'All',
            'stat_recues' => '%d cards received',
            'stat_envoyees' => '%d cards sent',
            'stat_pays' => '%d countries',
            'clic_pays' => 'Click a country to see its postcards.',
            'aucune_carte' => 'No postcards for this country.',
            'autres_pays' => 'All countries',
            'recue_de' => 'Received from %s',
            'envoyee_vers' => 'Sent to %s',
            'en_route' => 'Travelling',
            'voyage' => '%d days of travel',
            'envoyee_le' => 'Sent on %s',
            'recue_le' => 'Received on %s',
            'voir_sur' => 'View on %s',
            'distance' => '%s km',
            'retour' => 'Back',
            'carte_introuvable' => 'Postcard not found.',
            'swaps_titre' => 'Postcards for swap',
            'swaps_intro' => 'These postcards are available for swapping. Contact me on the Postcrossing Discord and mention the card number.',
            'swaps_vide' => 'No postcards available right now.',
            'disponible' => 'Available',
            'reservee' => 'Reserved',
            'filtre_tous' => 'All',
            'legende' => 'Number of postcards',
            'sans_photo' => 'No photo',
            'fermer' => 'Close',
            'theme' => 'Theme',
            'theme_auto' => 'Default theme (system setting)',
            'theme_clair' => 'Light mode',
            'theme_sombre' => 'Dark mode',
            'nav_usa' => 'USA & Canada',
            'usa_titre' => 'United States and Canada',
            'stat_regions' => '%d states and provinces',
            'clic_region' => 'Click a state or province to see its postcards.',
            'liste_regions' => 'All states and provinces',
            'voir_regions' => 'View by state and province',
            'sans_region' => '%d US or Canadian postcard(s) without a state or province.',
        ],
    ];
    $s = $dico[langue_courante()][$cle] ?? $dico['fr'][$cle] ?? $cle;
    return $args ? vsprintf($s, $args) : $s;
}

/** URL de la page courante dans l'autre langue (conserve les autres paramètres). */
function url_langue(string $lang): string
{
    $q = $_GET;
    $q['lang'] = $lang;
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($q);
}
