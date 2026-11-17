<?php

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
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
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

/** Lien vers la carte sur sa plateforme : lien explicite, sinon déduit de la référence. */
function lien_plateforme(array $carte): ?string
{
    if (!empty($carte['lien'])) {
        return $carte['lien'];
    }
    if ($carte['plateforme'] === 'postcrossing' && !empty($carte['reference'])) {
        return 'https://www.postcrossing.com/postcards/' . rawurlencode($carte['reference']);
    }
    return null;
}

function libelle_plateforme(string $p): string
{
    return ['postcrossing' => 'Postcrossing', 'cardzilla' => 'Cardzilla', 'swap' => 'Swap', 'autre' => 'Autre'][$p] ?? $p;
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
    return $base . 'photo.php?id=' . $id . ($taille === 'full' ? '&s=full' : '');
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
