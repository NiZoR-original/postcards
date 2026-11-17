<?php
// Catalogue public des cartes à échanger (lien à partager sur le Discord Postcrossing).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';

$swaps = db()->query("SELECT id, titre, description, theme, etat FROM swaps
    WHERE etat IN ('disponible','reservee')
    ORDER BY etat = 'reservee', theme, id DESC")->fetchAll();

$themes = array_values(array_unique(array_filter(array_column($swaps, 'theme'))));
sort($themes);

entete_public('swaps', t('swaps_titre'));
?>
<h1><?= h(t('swaps_titre')) ?></h1>
<p class="intro"><?= h(t('swaps_intro')) ?></p>
<?php if ($pseudo = parametre('pseudo_discord')):
    $profil = liens_profils()['discord'] ?? null; ?>
<p class="intro contact-discord"><i class="fa-brands fa-discord"></i> <?= h(t('swaps_pseudo')) ?>
    <?php if ($profil): ?><a href="<?= h($profil) ?>" target="_blank" rel="noopener me"><strong>@<?= h($pseudo) ?></strong></a>
    <?php else: ?><strong>@<?= h($pseudo) ?></strong><?php endif; ?></p>
<?php endif; ?>

<?php if ($themes): ?>
<div class="filtres" role="toolbar">
    <button type="button" class="actif" data-theme=""><?= h(t('filtre_tous')) ?></button>
    <?php foreach ($themes as $th): ?>
        <button type="button" data-theme="<?= h($th) ?>"><?= h($th) ?></button>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$swaps): ?>
    <p class="vide"><?= h(t('swaps_vide')) ?></p>
<?php else: ?>
<div class="grille-swaps">
    <?php foreach ($swaps as $s):
        $photos = photos_de('swap', (int) $s['id'], true); ?>
        <article class="swap <?= h($s['etat']) ?>" data-theme="<?= h($s['theme'] ?? '') ?>">
            <div class="swap-photos">
                <?php foreach ($photos as $i => $p): ?>
                    <a href="<?= h(url_photo((int) $p['id'], 'full')) ?>" data-lightbox<?= $i ? ' hidden' : '' ?>>
                        <img src="<?= h(url_photo((int) $p['id'])) ?>" alt="<?= h($p['legende'] ?: $s['titre']) ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
                <?php if (count($photos) > 1): ?><span class="nb-photos"><i class="fa-regular fa-images"></i> <?= count($photos) ?></span><?php endif; ?>
                <?php if (!$photos): ?><div class="sans-photo"><i class="fa-regular fa-image"></i></div><?php endif; ?>
            </div>
            <div class="swap-infos">
                <span class="numero">#<?= (int) $s['id'] ?></span>
                <span class="etat"><?= h(t($s['etat'])) ?></span>
                <h3><?= h($s['titre']) ?></h3>
                <?php if ($s['theme']): ?><p class="theme"><i class="fa-solid fa-tag"></i> <?= h($s['theme']) ?></p><?php endif; ?>
                <?php if ($s['description']): ?><p><?= nl2br(h($s['description'])) ?></p><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
pied_public(<<<'JS'
<script>
document.querySelectorAll('.filtres button').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.filtres button').forEach(x => x.classList.toggle('actif', x === b));
  document.querySelectorAll('.swap').forEach(s => { s.hidden = b.dataset.theme !== '' && s.dataset.theme !== b.dataset.theme; });
}));
</script>
JS);
