<?php

const CARTE_EN_ROUTE_SQL = "sens = 'envoyee' AND date_reception IS NULL";

function carte_en_route(array $carte): bool
{
    return $carte['sens'] === 'envoyee' && $carte['date_reception'] === null;
}

function date_iso(mixed $valeur): ?string
{
    if (!is_string($valeur) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $valeur)) {
        return null;
    }
    [$annee, $mois, $jour] = array_map('intval', explode('-', $valeur));
    return $annee >= 1000 && checkdate($mois, $jour, $annee) ? $valeur : null;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function est_admin(): bool
{
    return !empty($_SESSION['pc_admin'])
        && ($_SESSION['pc_last'] ?? 0) > time() - ADMIN_SESSION_TIMEOUT;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verifier(): void
{
    $attendu = $_SESSION['csrf'] ?? null;
    $recu = $_POST['csrf'] ?? null;
    if (!is_string($attendu) || !is_string($recu) || !hash_equals($attendu, $recu)) {
        http_response_code(400);
        exit('Jeton CSRF invalide — rechargez la page.');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function rediriger(string $url): never
{
    header('Location: ' . $url);
    exit;
}

const URL_CARTES_PLATEFORMES = [
    'postcrossing' => 'https://www.postcrossing.com/postcards/',
    'cardzilla' => 'https://cardzilla.eu/postcards/',
];

function lien_deduit(string $plateforme, ?string $reference): ?string
{
    return isset(URL_CARTES_PLATEFORMES[$plateforme]) && $reference !== null && $reference !== ''
        ? URL_CARTES_PLATEFORMES[$plateforme] . rawurlencode($reference)
        : null;
}

/** Lien vers la carte sur sa plateforme : lien explicite, sinon déduit de la référence. */
function lien_plateforme(array $carte): ?string
{
    if (!empty($carte['lien'])) {
        return $carte['lien'];
    }
    return lien_deduit($carte['plateforme'], $carte['reference'] ?? null);
}

function libelle_plateforme(string $p): string
{
    return ['postcrossing' => 'Postcrossing', 'cardzilla' => 'Cardzilla', 'discord' => 'Discord', 'swap' => 'Swap', 'autre' => 'Autre'][$p] ?? $p;
}

/** Durée de voyage en jours, ou null. */
function jours_voyage(array $carte): ?int
{
    if (empty($carte['date_envoi']) || empty($carte['date_reception'])) {
        return null;
    }
    return (int) (new DateTime($carte['date_envoi']))->diff(new DateTime($carte['date_reception']))->format('%r%a');
}

function date_lisible(?string $d, string $lang = 'fr'): string
{
    if (!$d) {
        return '';
    }
    $fmt = new IntlDateFormatter($lang === 'en' ? 'en_GB' : 'fr_FR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    return $fmt->format(new DateTime($d));
}

/**
 * Photos d'un objet. $publiques_seulement filtre pour l'affichage public.
 * @param 'carte'|'swap' $type
 */
function photos_de(string $type, int $id, bool $publiques_seulement): array
{
    $col = $type === 'swap' ? 'swap_id' : 'carte_id';
    $sql = "SELECT * FROM photos WHERE $col = ?" . ($publiques_seulement ? ' AND publique = 1' : '') . ' ORDER BY ordre, id';
    $st = db()->prepare($sql);
    $st->execute([$id]);
    return $st->fetchAll();
}

function url_photo(int $id, string $taille = 'thumb', string $base = ''): string
{
    return $base . 'photo.php?v=2&id=' . $id . ($taille === 'full' ? '&s=full' : '');
}

function url_asset(string $chemin, string $base = ''): string
{
    $version = filemtime(dirname(__DIR__) . '/' . $chemin);
    return $base . $chemin . '?v=' . $version;
}

function fenetre_photos(string $titre, string $fermer, string $precedente, string $suivante): void
{
    ?>
<dialog id="lightbox" class="lightbox" aria-label="<?= h($titre) ?>" hidden>
    <img alt="">
    <button type="button" class="lightbox-fermer" aria-label="<?= h($fermer) ?>" autofocus>&times;</button>
    <div class="lightbox-commandes" hidden>
        <button type="button" data-photo-pas="-1" aria-label="<?= h($precedente) ?>">‹</button>
        <span aria-live="polite"></span>
        <button type="button" data-photo-pas="1" aria-label="<?= h($suivante) ?>">›</button>
    </div>
</dialog>
    <?php
}

/** Thème choisi par le visiteur : 'light', 'dark' ou 'auto' (suit le système). Cookie posé par assets/theme.js. */
function theme_courant(): string
{
    $t = $_COOKIE['pc_theme'] ?? 'auto';
    return in_array($t, ['light', 'dark'], true) ? $t : 'auto';
}

/** Attribut data-theme à poser sur <html> (vide si le thème suit le système). */
function attr_theme(): string
{
    $t = theme_courant();
    return $t === 'auto' ? '' : ' data-theme="' . $t . '"';
}

/**
 * Sélecteur de thème (système / clair / sombre). $libelles : ['auto' => …, 'light' => …, 'dark' => …, 'groupe' => …].
 * Nécessite assets/theme.js.
 */
function selecteur_theme(array $libelles): string
{
    $icones = ['auto' => 'fa-circle-half-stroke', 'light' => 'fa-sun', 'dark' => 'fa-moon'];
    $actuel = theme_courant();
    $html = '<div class="themes" role="group" aria-label="' . h($libelles['groupe']) . '">';
    foreach ($icones as $t => $icone) {
        $actif = $t === $actuel;
        $html .= '<button type="button" data-theme-choix="' . $t . '" class="' . ($actif ? 'actif' : '') . '"'
            . ' aria-pressed="' . ($actif ? 'true' : 'false') . '" title="' . h($libelles[$t]) . '" aria-label="' . h($libelles[$t]) . '">'
            . '<i class="fa-solid ' . $icone . '"></i></button>';
    }
    return $html . '</div>';
}

/**
 * Paramètre du site (table parametres, Admin > Paramètres), null s'il est vide ou absent.
 * Tolère l'absence de la table tant que la migration 0003 n'est pas appliquée.
 */
function parametre(string $cle): ?string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query('SELECT cle, valeur FROM parametres')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException) {
            $cache = [];
        }
    }
    $v = $cache[$cle] ?? null;
    return $v === null || $v === '' ? null : $v;
}

/** Liens vers mes profils Postcrossing / Cardzilla / Discord, selon les noms d'utilisateur saisis dans les paramètres. */
function liens_profils(): array
{
    $liens = [];
    if ($u = parametre('profil_postcrossing')) {
        $liens['postcrossing'] = 'https://www.postcrossing.com/user/' . rawurlencode($u);
    }
    if ($u = parametre('profil_cardzilla')) {
        $liens['cardzilla'] = 'https://cardzilla.eu/users/' . rawurlencode($u);
    }
    if ($u = parametre('profil_discord')) {
        $liens['discord'] = 'https://discord.com/users/' . rawurlencode($u);
    }
    return $liens;
}

/**
 * Pays (ISO alpha-2) d'un fuseau horaire IANA envoyé par le navigateur, null si inconnu (UTC, Etc/…).
 * Sert aux statistiques de visite : le pays n'est jamais déduit de l'adresse IP.
 */
function pays_du_fuseau(string $tz): ?string
{
    // Anciens noms encore renvoyés par certains systèmes (PHP ne leur associe pas de pays)
    $alias = [
        'Europe/Kiev' => 'UA', 'Asia/Calcutta' => 'IN', 'Asia/Saigon' => 'VN', 'Asia/Katmandu' => 'NP',
        'Asia/Rangoon' => 'MM', 'America/Montreal' => 'CA', 'America/Buenos_Aires' => 'AR', 'Atlantic/Faeroe' => 'FO',
        'Asia/Istanbul' => 'TR', 'Europe/Belfast' => 'GB', 'Asia/Dacca' => 'BD', 'Asia/Ulan_Bator' => 'MN',
        'Pacific/Samoa' => 'AS', 'America/Godthab' => 'GL', 'Australia/ACT' => 'AU', 'Australia/NSW' => 'AU',
        'Asia/Chongqing' => 'CN', 'Asia/Harbin' => 'CN', 'America/Indianapolis' => 'US', 'US/Eastern' => 'US',
        'US/Central' => 'US', 'US/Mountain' => 'US', 'US/Pacific' => 'US', 'Canada/Eastern' => 'CA',
    ];
    if (isset($alias[$tz])) {
        return $alias[$tz];
    }
    if (!preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+\-]+){1,2}$#', $tz)) {
        return null;
    }
    try {
        $code = (new DateTimeZone($tz))->getLocation()['country_code'] ?? '??';
    } catch (Exception) {
        return null;
    }
    return pays_valide($code) ? $code : null;
}

function purger_visites(PDO $pdo): void
{
    $pdo->exec('DELETE FROM visites WHERE debut < NOW() - INTERVAL 25 MONTH');
}
