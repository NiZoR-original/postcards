<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';

$lang = langue_courante();

// Comptage public par pays et par sens
$rows = db()->query("SELECT pays_code, sens, COUNT(*) n FROM cartes WHERE publique = 1 GROUP BY pays_code, sens")->fetchAll();
$stats = [];
$total = ['recue' => 0, 'envoyee' => 0];
foreach ($rows as $r) {
    $stats[$r['pays_code']] ??= ['recue' => 0, 'envoyee' => 0, 'nom' => nom_pays($r['pays_code'], $lang)];
    $stats[$r['pays_code']][$r['sens']] = (int) $r['n'];
    $total[$r['sens']] += (int) $r['n'];
}
uasort($stats, fn($a, $b) => ($b['recue'] + $b['envoyee']) <=> ($a['recue'] + $a['envoyee']) ?: strcmp($a['nom'], $b['nom']));

$coords = array_intersect_key(COORDS_PETITS_PAYS, $stats);

$i18n_js = [
    'recues' => t('recues'), 'envoyees' => t('envoyees'),
    'aucune' => t('aucune_carte'), 'sansPhoto' => t('sans_photo'),
    'enRoute' => t('en_route'),
];

entete_public('carte', '', '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.css">');
?>
<section class="stats">
    <span><i class="fa-solid fa-inbox"></i> <?= h(t('stat_recues', $total['recue'])) ?></span>
    <span><i class="fa-solid fa-paper-plane"></i> <?= h(t('stat_envoyees', $total['envoyee'])) ?></span>
    <span><i class="fa-solid fa-flag"></i> <?= h(t('stat_pays', count($stats))) ?></span>
</section>

<div class="mode-switch" role="tablist">
    <button type="button" data-mode="recue" class="actif"><?= h(t('recues')) ?></button>
    <button type="button" data-mode="envoyee"><?= h(t('envoyees')) ?></button>
    <button type="button" data-mode="tous"><?= h(t('toutes')) ?></button>
</div>

<div class="carte-layout">
    <div class="carte-monde">
        <div id="map"></div>
        <div id="legende" class="legende" aria-label="<?= h(t('legende')) ?>"></div>
    </div>
    <aside id="panneau" class="panneau">
        <p class="aide"><i class="fa-solid fa-hand-pointer"></i> <?= h(t('clic_pays')) ?></p>
    </aside>
</div>

<section class="liste-pays">
    <h2><?= h(t('autres_pays')) ?></h2>
    <ul>
        <?php foreach ($stats as $code => $s): ?>
            <li><button type="button" data-pays="<?= h($code) ?>">
                <?= drapeau($code) ?> <?= h($s['nom']) ?>
                <small><?= $s['recue'] ?> <i class="fa-solid fa-inbox"></i> · <?= $s['envoyee'] ?> <i class="fa-solid fa-paper-plane"></i></small>
            </button></li>
        <?php endforeach; ?>
    </ul>
</section>

<?php
pied_public('
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/maps/world.js"></script>
<script>
window.PC = ' . json_encode([
    'stats' => (object) $stats,
    'coords' => (object) $coords,
    'lang' => $lang,
    't' => $i18n_js,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';
</script>
<script src="assets/map.js"></script>');
