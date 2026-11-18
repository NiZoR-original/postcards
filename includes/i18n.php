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
            'navigation' => 'Navigation principale',
            'nav_monde_court' => 'Monde',
            'nav_france_court' => 'France',
            'menu_options' => 'Options',
            'photos' => 'Photos',
            'photo_precedente' => 'Photo précédente',
            'photo_suivante' => 'Photo suivante',
            'atlas_titre' => 'Atlas postal',
            'atlas_sens' => 'Sens des échanges',
            'atlas_choisir_pays' => 'Pays ou territoire',
            'atlas_choisir_region' => 'État, province ou territoire',
            'atlas_selection_vide' => 'Choisir sur la carte ou dans la liste',
            'atlas_attente' => 'Les cartes de la zone sélectionnée apparaîtront ici.',
            'recues' => 'Reçues',
            'envoyees' => 'Envoyées',
            'toutes' => 'Toutes',
            'stat_recues' => '%d cartes reçues',
            'stat_envoyees' => '%d cartes envoyées',
            'stat_pays' => '%d pays',
            'clic_pays' => 'Cliquez sur un pays pour voir ses cartes.',
            'aucune_carte' => 'Aucune carte pour cette zone.',
            'carte_chargement' => 'Chargement des cartes…',
            'carte_erreur_chargement' => 'Impossible de charger les cartes.',
            'autres_pays' => 'Tous les pays',
            'recue_de' => 'Reçue de %s',
            'envoyee_vers' => 'Envoyée vers %s',
            'en_route' => 'En route',
            'nav_en_route' => 'En route',
            'en_route_vide' => 'Aucune carte en route pour le moment.',
            'carte_en_route' => '1 carte en route',
            'cartes_en_route' => '%d cartes en route',
            'date_arrivee' => 'Date d’arrivée',
            'enregistrer_arrivee' => 'Enregistrer l’arrivée',
            'arrivee_enregistree' => 'Date d’arrivée enregistrée. La carte est maintenant classée dans « Envoyées ».',
            'arrivee_date_invalide' => 'Choisis une date valide, au plus tôt à la date d’envoi et au plus tard aujourd’hui.',
            'arrivee_deja_traitee' => 'La carte a changé. Vérifie sa date de réception dans l’administration.',
            'arrivee_acces' => 'Connecte-toi à l’administration pour enregistrer l’arrivée.',
            'retour_en_route' => 'Retour aux cartes en route',
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
            'confidentialite' => 'Confidentialité et mentions légales',
            'connexion' => 'Connexion',
            'administration' => 'Administration',
            'cookies_titre' => 'Information sur les cookies',
            'cookies_texte' => 'Ce site n\'utilise que des cookies nécessaires à son fonctionnement (session, langue et thème choisis) et mesure son audience de façon anonyme (pays, date et durée de visite, sans adresse IP). Aucun suivi, aucune publicité.',
            'cookies_plus' => 'En savoir plus',
            'cookies_ok' => 'J\'ai compris',
            'profil_postcrossing' => 'Mon profil Postcrossing',
            'profil_cardzilla' => 'Mon profil Cardzilla',
            'profil_discord' => 'Mon profil Discord',
            'swaps_pseudo' => 'Mon pseudo Discord :',
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
            'nav_france' => 'France et outre-mer',
            'atlas_choisir_france' => 'Département ou territoire',
            'stat_zones_france' => '%d départements et territoires',
            'clic_france' => 'Cliquez sur un département ou un territoire pour voir ses cartes.',
            'liste_france' => 'Départements et territoires avec des cartes',
            'voir_france' => 'Voir la carte France et outre-mer',
            'sans_region_france' => '%d carte(s) enregistrée(s) sous France sans département indiqué.',
            'sources_fond' => 'Fond de carte',
        ],
        'en' => [
            'site_titre' => 'My postcards',
            'nav_carte' => 'World map',
            'nav_swaps' => 'Swaps',
            'navigation' => 'Main navigation',
            'nav_monde_court' => 'World',
            'nav_france_court' => 'France',
            'menu_options' => 'Options',
            'photos' => 'Photos',
            'photo_precedente' => 'Previous photo',
            'photo_suivante' => 'Next photo',
            'atlas_titre' => 'Postcard atlas',
            'atlas_sens' => 'Postcard direction',
            'atlas_choisir_pays' => 'Country or territory',
            'atlas_choisir_region' => 'State, province or territory',
            'atlas_selection_vide' => 'Choose on the map or from the list',
            'atlas_attente' => 'Postcards from the selected area will appear here.',
            'recues' => 'Received',
            'envoyees' => 'Sent',
            'toutes' => 'All',
            'stat_recues' => '%d cards received',
            'stat_envoyees' => '%d cards sent',
            'stat_pays' => '%d countries',
            'clic_pays' => 'Click a country to see its postcards.',
            'aucune_carte' => 'No postcards for this area.',
            'carte_chargement' => 'Loading postcards…',
            'carte_erreur_chargement' => 'Could not load the postcards.',
            'autres_pays' => 'All countries',
            'recue_de' => 'Received from %s',
            'envoyee_vers' => 'Sent to %s',
            'en_route' => 'Travelling',
            'nav_en_route' => 'In transit',
            'en_route_vide' => 'No postcards in transit right now.',
            'carte_en_route' => '1 postcard in transit',
            'cartes_en_route' => '%d postcards in transit',
            'date_arrivee' => 'Arrival date',
            'enregistrer_arrivee' => 'Save arrival date',
            'arrivee_enregistree' => 'Arrival date saved. The postcard is now listed under “Sent”.',
            'arrivee_date_invalide' => 'Choose a valid date from the day the postcard was sent through today.',
            'arrivee_deja_traitee' => 'The postcard has changed. Check its arrival date in the administration area.',
            'arrivee_acces' => 'Log in to the administration area to save the arrival date.',
            'retour_en_route' => 'Back to postcards in transit',
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
            'confidentialite' => 'Privacy and legal notice',
            'connexion' => 'Log in',
            'administration' => 'Administration',
            'cookies_titre' => 'About cookies',
            'cookies_texte' => 'This site only uses cookies needed for it to work (session, chosen language and theme) and measures its audience anonymously (country, date and length of visit, no IP address). No tracking, no advertising.',
            'cookies_plus' => 'Learn more',
            'cookies_ok' => 'Got it',
            'profil_postcrossing' => 'My Postcrossing profile',
            'profil_cardzilla' => 'My Cardzilla profile',
            'profil_discord' => 'My Discord profile',
            'swaps_pseudo' => 'My Discord username:',
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
            'nav_france' => 'France and overseas',
            'atlas_choisir_france' => 'Department or territory',
            'stat_zones_france' => '%d departments and territories',
            'clic_france' => 'Click a department or territory to see its postcards.',
            'liste_france' => 'Departments and territories with postcards',
            'voir_france' => 'View the France and overseas map',
            'sans_region_france' => '%d postcard(s) filed under France without a department.',
            'sources_fond' => 'Map sources',
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
