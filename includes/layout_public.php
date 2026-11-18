<?php
// En-tête / pied des pages publiques. Usage : entete_public('carte', 'Titre'); … pied_public();

function entete_public(string $actif, string $titre = '', string $head_extra = ''): void
{
    $lang = langue_courante();
    $titre_complet = ($titre ? $titre . ' · ' : '') . t('site_titre');
    $navigation = [
        'carte' => ['index.php', 'fa-earth-europe', 'nav_carte', 'nav_monde_court'],
        'usa' => ['usa-canada.php', 'fa-flag-usa', 'nav_usa', 'nav_usa'],
        'france' => ['france.php', 'fa-map', 'nav_france', 'nav_france_court'],
        'en_route' => ['en-route.php', 'fa-truck-fast', 'nav_en_route', 'nav_en_route'],
        'swaps' => ['swaps.php', 'fa-right-left', 'nav_swaps', 'nav_swaps'],
    ];
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#151b28">
    <title><?= h($titre_complet) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= h(url_asset('assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(url_asset('assets/public.css')) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icones/atlas-180.png">
    <link rel="icon" href="assets/icones/atlas-192.png" type="image/png">
    <?= $head_extra ?>
</head>
<body class="public">
<header class="topbar">
    <a class="brand" href="index.php"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> <?= h(t('site_titre')) ?></a>
    <button type="button" class="menu-mobile" aria-expanded="false" aria-controls="options-publiques">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i> <?= h(t('menu_options')) ?>
    </button>
    <nav class="nav-publique" aria-label="<?= h(t('navigation')) ?>">
        <?php foreach ($navigation as $cle => [$url, $icone, $libelle, $court]): ?>
            <a href="<?= h($url) ?>" aria-label="<?= h(t($libelle)) ?>"<?= $actif === $cle ? ' class="actif" aria-current="page"' : '' ?>>
                <i class="fa-solid <?= h($icone) ?>" aria-hidden="true"></i>
                <span class="nav-long"><?= h(t($libelle)) ?></span>
                <span class="nav-court"><?= h(t($court)) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="options-publiques" id="options-publiques">
        <?php if ($profils = liens_profils()): ?>
        <div class="profils">
            <?php foreach ($profils as $p => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener me" title="<?= h(t('profil_' . $p) . ($p === 'discord' && parametre('pseudo_discord') ? ' (@' . parametre('pseudo_discord') . ')' : '')) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?= h(libelle_plateforme($p)) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="langues">
            <a href="<?= h(url_langue('fr')) ?>" hreflang="fr" aria-label="Français" class="<?= $lang === 'fr' ? 'actif' : '' ?>">FR</a>
            <a href="<?= h(url_langue('en')) ?>" hreflang="en" aria-label="English" class="<?= $lang === 'en' ? 'actif' : '' ?>">EN</a>
        </div>
        <?= selecteur_theme(['groupe' => t('theme'), 'auto' => t('theme_auto'), 'light' => t('theme_clair'), 'dark' => t('theme_sombre')]) ?>
    </div>
</header>
<main class="contenu">
    <?php
}

function pied_public(string $scripts = ''): void
{
    ?>
</main>
<footer class="pied-site">
    <a href="confidentialite.php"><?= h(t('confidentialite')) ?></a>
    <span aria-hidden="true">·</span>
    <a href="admin/"><?= h(t(est_admin() ? 'administration' : 'connexion')) ?></a>
</footer>
<?php if (!isset($_COOKIE['pc_cookies'])): ?>
<div id="bandeau-cookies" class="bandeau-cookies" role="dialog" aria-live="polite" aria-label="<?= h(t('cookies_titre')) ?>" hidden>
    <p><i class="fa-solid fa-cookie-bite"></i> <?= h(t('cookies_texte')) ?>
        <a href="confidentialite.php"><?= h(t('cookies_plus')) ?></a></p>
    <button type="button" class="bouton" data-cookies-ok><?= h(t('cookies_ok')) ?></button>
</div>
<script src="assets/cookies.js"></script>
<?php endif; ?>
<?php fenetre_photos(t('photos'), t('fermer'), t('photo_precedente'), t('photo_suivante')); ?>
<script src="<?= h(url_asset('assets/lightbox.js')) ?>"></script>
<script src="assets/theme.js"></script>
<script src="assets/mobile.js"></script>
<?php // l'administrateur n'est pas compté (le script ne sert alors qu'au bouton d'opposition de la page Confidentialité) ?>
<script src="assets/stats.js" data-api="<?= est_admin() ? '' : 'api/visite.php' ?>"></script>
<?= $scripts ?>
</body>
</html>
    <?php
}
