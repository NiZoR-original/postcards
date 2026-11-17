<?php
// Gabarit commun des pages de carte publiques (monde : index.php, USA & Canada : usa-canada.php).
// Le comportement est dans assets/map.js, piloté par window.PC.

/**
 * @param array{
 *   actif: string,            onglet du menu
 *   titre: string,            titre de la page ('' = titre du site)
 *   fond: string,             nom du fond jsVectorMap ('world', 'usa_canada')
 *   script_fond: string,      URL du fichier du fond
 *   stats: array,             [code => ['recue'=>int, 'envoyee'=>int, 'nom'=>string, 'drapeau'=>string]]
 *   coords: array,            [code => [lat, lng]] (fond projeté) ou [x, y] (fond en pixels) des points
 *   noms?: array,             [code => nom] des zones du fond (tooltips des zones sans carte) ; sinon noms ICU
 *   stat_zones: string,       clé i18n du compteur de zones ('stat_pays', 'stat_regions')
 *   clic: string,             clé i18n de l'aide au clic
 *   titre_liste: string,      clé i18n du titre de la liste sous la carte
 *   note?: string|null,       remarque affichée sous la carte
 * } $cfg
 */
function page_carte(array $cfg): void
{
    $lang = langue_courante();
    $stats = $cfg['stats'];
    uasort($stats, fn($a, $b) => ($b['recue'] + $b['envoyee']) <=> ($a['recue'] + $a['envoyee'])
        ?: (new Collator($lang))->compare($a['nom'], $b['nom']));
    $total = ['recue' => 0, 'envoyee' => 0];
    foreach ($stats as $s) {
        $total['recue'] += $s['recue'];
        $total['envoyee'] += $s['envoyee'];
    }

    entete_public($cfg['actif'], $cfg['titre'], '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.css">');
    ?>
<?php if ($cfg['titre']): ?><h1><?= h($cfg['titre']) ?></h1><?php endif; ?>
<section class="stats">
    <span><i class="fa-solid fa-inbox"></i> <?= h(t('stat_recues', $total['recue'])) ?></span>
    <span><i class="fa-solid fa-paper-plane"></i> <?= h(t('stat_envoyees', $total['envoyee'])) ?></span>
    <span><i class="fa-solid fa-flag"></i> <?= h(t($cfg['stat_zones'], count($stats))) ?></span>
</section>

<div class="mode-switch" role="tablist">
    <button type="button" data-mode="recue" class="actif"><?= h(t('recues')) ?></button>
    <button type="button" data-mode="envoyee"><?= h(t('envoyees')) ?></button>
    <button type="button" data-mode="tous"><?= h(t('toutes')) ?></button>
</div>

<div class="carte-layout" id="carte-layout">
    <div class="carte-monde">
        <div id="map"></div>
        <div class="pied-carte">
            <div id="legende" class="legende" aria-label="<?= h(t('legende')) ?>"></div>
            <p class="aide-clic"><i class="fa-solid fa-hand-pointer"></i> <?= h(t($cfg['clic'])) ?></p>
        </div>
    </div>
    <aside id="panneau" class="panneau" hidden></aside>
</div>
<?php if (!empty($cfg['note'])): ?><p class="aide note-carte"><i class="fa-solid fa-circle-info"></i> <?= h($cfg['note']) ?></p><?php endif; ?>

<?php if ($stats): ?>
<section class="liste-pays">
    <h2><?= h(t($cfg['titre_liste'])) ?></h2>
    <ul>
        <?php foreach ($stats as $code => $s): ?>
            <li><button type="button" data-pays="<?= h($code) ?>">
                <span><?= $s['drapeau'] ?> <?= h($s['nom']) ?></span>
                <small><?= $s['recue'] ?> <i class="fa-solid fa-inbox"></i> · <?= $s['envoyee'] ?> <i class="fa-solid fa-paper-plane"></i></small>
            </button></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

    <?php
    $pc = [
        'fond' => $cfg['fond'],
        'stats' => (object) $stats,
        'coords' => (object) $cfg['coords'],
        'noms' => (object) ($cfg['noms'] ?? []),
        'lang' => $lang,
        't' => [
            'aucune' => t('aucune_carte'), 'sansPhoto' => t('sans_photo'),
            'enRoute' => t('en_route'), 'fermer' => t('fermer'),
        ],
    ];
    pied_public('
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.js"></script>
<script src="assets/fonds/capture.js"></script>
<script src="' . h($cfg['script_fond']) . '"></script>
<script>window.PC = ' . json_encode($pc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>
<script src="assets/map.js"></script>');
}
