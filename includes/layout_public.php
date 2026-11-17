<?php
// En-tête / pied des pages publiques. Usage : entete_public('carte', 'Titre'); … pied_public();

function entete_public(string $actif, string $titre = '', string $head_extra = ''): void
{
    $lang = langue_courante();
    $titre_complet = ($titre ? $titre . ' · ' : '') . t('site_titre');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
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
        <a href="swaps.php" class="<?= $actif === 'swaps' ? 'actif' : '' ?>"><i class="fa-solid fa-right-left"></i> <?= h(t('nav_swaps')) ?></a>
    </nav>
    <div class="langues">
        <a href="<?= h(url_langue('fr')) ?>" class="<?= $lang === 'fr' ? 'actif' : '' ?>">FR</a>
        <a href="<?= h(url_langue('en')) ?>" class="<?= $lang === 'en' ? 'actif' : '' ?>">EN</a>
    </div>
</header>
<main class="contenu">
    <?php
}

function pied_public(string $scripts = ''): void
{
    ?>
</main>
<div id="lightbox" class="lightbox" hidden><img alt=""><button type="button" aria-label="Fermer">&times;</button></div>
<script src="assets/lightbox.js"></script>
<?= $scripts ?>
</body>
</html>
    <?php
}
