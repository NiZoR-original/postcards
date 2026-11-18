<?php
// Galerie publique des cartes expédiées dont la date d’arrivée reste à renseigner.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';

$lang = langue_courante();
$cartes = db()->query("SELECT c.id, c.titre, c.reference, c.pays_code, c.date_envoi,
           p.id AS photo, p.legende
    FROM cartes c
    LEFT JOIN photos p ON p.id = (
        SELECT p2.id FROM photos p2 WHERE p2.carte_id = c.id AND p2.publique = 1
        ORDER BY p2.ordre, p2.id LIMIT 1
    )
    WHERE c.publique = 1 AND " . CARTE_EN_ROUTE_SQL . "
    ORDER BY COALESCE(c.date_envoi, DATE(c.created_at)) DESC, c.id DESC")->fetchAll();
$nombre = count($cartes);
$message = est_admin() ? flash() : null;

entete_public('en_route', t('nav_en_route'));
?>
<header class="atlas-entete">
    <h1><?= h(t('nav_en_route')) ?></h1>
    <?php if ($nombre): ?><p class="aide"><?= h(t($nombre === 1 ? 'carte_en_route' : 'cartes_en_route', $nombre)) ?></p><?php endif; ?>
</header>
<?php if ($message): ?>
<p class="flash <?= h($message['type']) ?>" role="status"><?= h($message['msg']) ?></p>
<?php endif; ?>
<?php if (!$cartes): ?>
<p class="vide"><?= h(t('en_route_vide')) ?></p>
<?php else: ?>
<div class="grille-en-route">
    <?php foreach ($cartes as $carte):
        $id = (int) $carte['id'];
        $titre = $carte['titre'] ?: ($carte['reference'] ?: '#' . $id); ?>
    <a class="carte-en-route" href="carte.php?id=<?= $id ?>">
        <div class="visuel-en-route">
        <?php if ($carte['photo']): ?>
            <img src="<?= h(url_photo((int) $carte['photo'])) ?>" alt="<?= h($carte['legende']) ?>" loading="lazy">
        <?php else: ?>
            <div class="sans-photo"><i class="fa-regular fa-image" aria-hidden="true"></i><span><?= h(t('sans_photo')) ?></span></div>
        <?php endif; ?>
        </div>
        <div class="infos-en-route">
            <h2><?= h($titre) ?></h2>
            <?php if ($carte['reference'] && $carte['reference'] !== $titre): ?><p><?= h($carte['reference']) ?></p><?php endif; ?>
            <p><?= drapeau($carte['pays_code']) ?> <?= h(nom_pays($carte['pays_code'], $lang)) ?></p>
            <?php if ($carte['date_envoi']): ?><p><?= h(t('envoyee_le', date_lisible($carte['date_envoi'], $lang))) ?></p><?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php pied_public(); ?>
