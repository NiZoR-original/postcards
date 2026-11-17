<?php
// En-tête / pied des pages publiques. Usage : entete_public('carte', 'Titre'); … pied_public();

function entete_public(string $actif, string $titre = '', string $head_extra = ''): void
{
    $lang = langue_courante();
    $titre_complet = ($titre ? $titre . ' · ' : '') . t('site_titre');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titre_complet) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <?= $head_extra ?>
</head>
<body class="public">
<header class="topbar">
    <a class="brand" href="index.php"><i class="fa-solid fa-envelope-open-text"></i> <?= h(t('site_titre')) ?></a>
    <nav>
        <a href="index.php" class="<?= $actif === 'carte' ? 'actif' : '' ?>"><i class="fa-solid fa-earth-europe"></i> <?= h(t('nav_carte')) ?></a>
        <a href="usa-canada.php" class="<?= $actif === 'usa' ? 'actif' : '' ?>"><i class="fa-solid fa-flag-usa"></i> <?= h(t('nav_usa')) ?></a>
        <a href="swaps.php" class="<?= $actif === 'swaps' ? 'actif' : '' ?>"><i class="fa-solid fa-right-left"></i> <?= h(t('nav_swaps')) ?></a>
    </nav>
    <?php if ($profils = liens_profils()): ?>
    <div class="profils">
        <?php foreach ($profils as $p => $url): ?>
            <a href="<?= h($url) ?>" target="_blank" rel="noopener me" title="<?= h(t('profil_' . $p) . ($p === 'discord' && parametre('pseudo_discord') ? ' (@' . parametre('pseudo_discord') . ')' : '')) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?= h(libelle_plateforme($p)) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="langues">
        <a href="<?= h(url_langue('fr')) ?>" class="<?= $lang === 'fr' ? 'actif' : '' ?>">FR</a>
        <a href="<?= h(url_langue('en')) ?>" class="<?= $lang === 'en' ? 'actif' : '' ?>">EN</a>
    </div>
    <?= selecteur_theme(['groupe' => t('theme'), 'auto' => t('theme_auto'), 'light' => t('theme_clair'), 'dark' => t('theme_sombre')]) ?>
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
</footer>
<?php if (!isset($_COOKIE['pc_cookies'])): ?>
<div id="bandeau-cookies" class="bandeau-cookies" role="dialog" aria-live="polite" aria-label="<?= h(t('cookies_titre')) ?>" hidden>
    <p><i class="fa-solid fa-cookie-bite"></i> <?= h(t('cookies_texte')) ?>
        <a href="confidentialite.php"><?= h(t('cookies_plus')) ?></a></p>
    <button type="button" class="bouton" data-cookies-ok><?= h(t('cookies_ok')) ?></button>
</div>
<script src="assets/cookies.js"></script>
<?php endif; ?>
<div id="lightbox" class="lightbox" hidden><img alt=""><button type="button" aria-label="Fermer">&times;</button></div>
<script src="assets/lightbox.js"></script>
<script src="assets/theme.js"></script>
<?php // l'administrateur n'est pas compté (le script ne sert alors qu'au bouton d'opposition de la page Confidentialité) ?>
<script src="assets/stats.js" data-api="<?= est_admin() ? '' : 'api/visite.php' ?>"></script>
<?= $scripts ?>
</body>
</html>
    <?php
}
