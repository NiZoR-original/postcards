<?php
// Fiche publique d'une carte.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';

$lang = langue_courante();
$st = db()->prepare('SELECT * FROM cartes WHERE id = ? AND publique = 1');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$c = $st->fetch();

if (!$c) {
    http_response_code(404);
    entete_public('carte');
    echo '<p class="vide">' . h(t('carte_introuvable')) . '</p><p><a href="index.php">&larr; ' . h(t('retour')) . '</a></p>';
    pied_public();
    exit;
}

$photos = photos_de('carte', (int) $c['id'], true);
$pays = drapeau($c['pays_code']) . ' ' . nom_pays($c['pays_code'], $lang)
    . ($c['subdivision'] ? ' — ' . nom_subdivision($c['subdivision'], $lang) : '')
    . ($c['ville'] ? ' — ' . $c['ville'] : '');
$lien = lien_plateforme($c);
$jours = jours_voyage($c);

entete_public('carte', $c['reference'] ?: ($c['titre'] ?: ''));
?>
<p><a href="index.php">&larr; <?= h(t('retour')) ?></a></p>
<article class="fiche">
    <div class="galerie">
        <?php foreach ($photos as $p): ?>
            <a href="<?= h(url_photo((int) $p['id'], 'full')) ?>" data-lightbox>
                <img src="<?= h(url_photo((int) $p['id'])) ?>" width="<?= (int) $p['largeur'] ?>" height="<?= (int) $p['hauteur'] ?>"
                     alt="<?= h($p['legende'] ?: $c['titre']) ?>" loading="lazy">
            </a>
        <?php endforeach; ?>
        <?php if (!$photos): ?><div class="sans-photo grand"><i class="fa-regular fa-image"></i><span><?= h(t('sans_photo')) ?></span></div><?php endif; ?>
    </div>
    <div class="infos">
        <p class="badge <?= h($c['sens']) ?>">
            <i class="fa-solid <?= $c['sens'] === 'recue' ? 'fa-inbox' : 'fa-paper-plane' ?>"></i>
            <?= h(t($c['sens'] === 'recue' ? 'recue_de' : 'envoyee_vers', $pays)) ?>
        </p>
        <?php if ($c['reference']): ?><h1><?= h($c['reference']) ?></h1><?php endif; ?>
        <?php if ($c['titre']): ?><h2><?= h($c['titre']) ?></h2><?php endif; ?>
        <ul class="details">
            <?php if ($c['date_envoi']): ?><li><i class="fa-regular fa-calendar"></i> <?= h(t('envoyee_le', date_lisible($c['date_envoi'], $lang))) ?></li><?php endif; ?>
            <?php if ($c['date_reception']): ?><li><i class="fa-regular fa-calendar-check"></i> <?= h(t('recue_le', date_lisible($c['date_reception'], $lang))) ?></li><?php endif; ?>
            <?php if ($jours !== null): ?><li><i class="fa-solid fa-route"></i> <?= h(t('voyage', $jours)) ?></li><?php endif; ?>
            <?php if ($c['sens'] === 'envoyee' && !$c['date_reception']): ?><li><i class="fa-solid fa-truck-fast"></i> <?= h(t('en_route')) ?></li><?php endif; ?>
            <?php if ($c['distance_km']): ?><li><i class="fa-solid fa-ruler"></i> <?= h(t('distance', number_format((int) $c['distance_km'], 0, ',', ' '))) ?></li><?php endif; ?>
        </ul>
        <?php if ($c['description']): ?><div class="description"><?= nl2br(h($c['description'])) ?></div><?php endif; ?>
        <?php if ($lien): ?>
            <p><a class="bouton" href="<?= h($lien) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?= h(t('voir_sur', libelle_plateforme($c['plateforme']))) ?></a></p>
        <?php endif; ?>
    </div>
</article>
<?php
pied_public();
