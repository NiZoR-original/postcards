<?php
// Gabarit commun des pages de carte publiques (monde : index.php, USA & Canada : usa-canada.php).
// Le comportement est dans assets/map.js, piloté par window.PC.

/**
 * @param array{
 *   actif: string,            onglet du menu
 *   titre: string,            nom de la carte affiché au-dessus du titre
 *   fond: string,             nom du fond jsVectorMap ('world', 'usa_canada')
 *   script_fond: string,      URL du fichier du fond
 *   stats: array,             [code => ['recue'=>int, 'envoyee'=>int, 'nom'=>string, 'drapeau'=>string]]
 *   coords: array,            [code => [lat, lng]] (fond projeté) ou [x, y] (fond en pixels) des points
 *   noms?: array,             [code => nom] des zones du fond (tooltips des zones sans carte) ; sinon noms ICU
 *   stat_zones: string,       clé i18n du compteur de zones ('stat_pays', 'stat_regions')
 *   clic: string,             clé i18n de l'aide au clic
 *   titre_liste: string,      clé i18n du titre de la liste sous la carte
 *   choisir: string,          clé i18n du sélecteur de zone
 *   note?: string|null,       remarque affichée sous la carte
 *   source?: array{nom: string, url: string}, attribution du fond cartographique
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
    $zones = $cfg['noms'] ?? liste_pays($lang);
    (new Collator($lang))->asort($zones);

    entete_public($cfg['actif'], $cfg['titre'], '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.css">');
    ?>
<header class="atlas-entete">
    <div>
        <p class="atlas-zone"><?= h($cfg['titre']) ?></p>
        <h1><?= h(t('atlas_titre')) ?></h1>
    </div>
    <p class="stats">
        <span><i class="fa-solid fa-inbox" aria-hidden="true"></i> <?= h(t('stat_recues', $total['recue'])) ?></span>
        <span><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <?= h(t('stat_envoyees', $total['envoyee'])) ?></span>
        <span><i class="fa-solid fa-flag" aria-hidden="true"></i> <?= h(t($cfg['stat_zones'], count($stats))) ?></span>
    </p>
</header>

<div class="carte-layout">
    <div class="carte-monde">
        <div class="mode-switch" role="group" aria-label="<?= h(t('atlas_sens')) ?>">
            <button type="button" data-mode="recue" class="actif" aria-pressed="true"><?= h(t('recues')) ?></button>
            <button type="button" data-mode="envoyee" aria-pressed="false"><?= h(t('envoyees')) ?></button>
            <button type="button" data-mode="tous" aria-pressed="false"><?= h(t('toutes')) ?></button>
        </div>
        <div id="map"></div>
        <div class="pied-carte">
            <div id="legende" class="legende" aria-label="<?= h(t('legende')) ?>"></div>
            <p class="aide-clic"><i class="fa-solid fa-hand-pointer"></i> <?= h(t($cfg['clic'])) ?></p>
        </div>
    </div>
    <aside class="atlas-cartes">
        <label for="atlas-selection"><?= h(t($cfg['choisir'])) ?></label>
        <select id="atlas-selection">
            <option value=""><?= h(t('atlas_selection_vide')) ?></option>
            <?php foreach ($zones as $code => $nom): ?>
                <option value="<?= h($code) ?>"><?= h($nom) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="atlas-attente aide" id="atlas-attente"><?= h(t('atlas_attente')) ?></p>
        <div id="panneau" class="panneau" aria-live="polite" hidden></div>
    </aside>
</div>
<?php if (!empty($cfg['note'])): ?><p class="aide note-carte"><i class="fa-solid fa-circle-info"></i> <?= h($cfg['note']) ?></p><?php endif; ?>
<?php if (!empty($cfg['source'])): ?>
<p class="aide note-carte"><?= h(t('sources_fond')) ?> · <a href="<?= h($cfg['source']['url']) ?>"><?= h($cfg['source']['nom']) ?></a></p>
<?php endif; ?>

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
        'noms' => (object) ($cfg['noms'] ?? NOMS_PAYS_COURTS),
        'lang' => $lang,
        't' => [
            'aucune' => t('aucune_carte'), 'sansPhoto' => t('sans_photo'),
            'enRoute' => t('en_route'), 'fermer' => t('fermer'),
            'chargement' => t('carte_chargement'), 'erreurChargement' => t('carte_erreur_chargement'),
        ],
    ];
    pied_public('
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.js"></script>
<script src="assets/fonds/capture.js"></script>
<script src="' . h($cfg['script_fond']) . '"></script>
<script>window.PC = ' . json_encode($pc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>
<script src="assets/map.js"></script>');
}

/**
 * Les subdivisions utilisent le code de la carte et les territoires leur code pays.
 * Les cartes des pays principaux sans subdivision sont comptées dans la note.
 */
function page_carte_regionale(array $cfg, array $pays, array $territoires, string $note, array $zonesEnPoint = []): void
{
    $lang = langue_courante();
    $noms = [];
    foreach ($pays as $code) {
        $noms += liste_subdivisions($code, $lang);
    }
    foreach ($territoires as $code) {
        $noms[$code] = nom_pays($code, $lang);
    }

    $codesPays = array_merge($pays, $territoires);
    $parametres = implode(',', array_fill(0, count($codesPays), '?'));
    $st = db()->prepare("SELECT pays_code, subdivision, sens, COUNT(*) n FROM cartes
        WHERE publique = 1 AND NOT (" . CARTE_EN_ROUTE_SQL . ")
          AND pays_code IN ($parametres) GROUP BY pays_code, subdivision, sens");
    $st->execute($codesPays);
    $stats = [];
    $sansRegion = 0;
    foreach ($st as $ligne) {
        $paysCarte = $ligne['pays_code'];
        $code = in_array($paysCarte, $territoires, true) ? $paysCarte : $ligne['subdivision'];
        $nombre = (int) $ligne['n'];
        if ($code === null || !isset($noms[$code])
            || (in_array($paysCarte, $pays, true) && !subdivision_valide($code, $paysCarte))) {
            $sansRegion += $nombre;
            continue;
        }
        $stats[$code] ??= ['recue' => 0, 'envoyee' => 0, 'nom' => $noms[$code], 'drapeau' => drapeau($paysCarte)];
        $stats[$code][$ligne['sens']] += $nombre;
    }

    $coords = [];
    if ($zonesEnPoint) {
        $fond = file_get_contents(APP_ROOT . '/' . $cfg['script_fond']);
        if (preg_match('/"centres":(\{[^}]*\})/', $fond, $m)) {
            $coords = array_intersect_key(json_decode($m[1], true) ?: [], array_flip($zonesEnPoint));
        }
    }

    page_carte($cfg + [
        'stats' => $stats,
        'coords' => $coords,
        'noms' => $noms,
        'note' => $sansRegion ? t($note, $sansRegion) : null,
    ]);
}
