<?php
// Statistiques de visite anonymes (table visites, alimentée par api/visite.php).
require_once __DIR__ . '/../includes/admin.php';

const PERIODES = ['30j' => '30 derniers jours', '12m' => '12 derniers mois', 'tout' => 'Tout (25 mois max.)'];
$periode = isset(PERIODES[$_GET['periode'] ?? '']) ? $_GET['periode'] : '30j';

try {
    db()->query('SELECT 1 FROM visites LIMIT 1');
    $table_ok = true;
} catch (PDOException) {
    $table_ok = false;
}

/** Durée lisible : 45 s, 3 min 12 s, 1 h 05 min. */
function duree_lisible(float $s): string
{
    $s = (int) round($s);
    if ($s < 60) return $s . ' s';
    if ($s < 3600) return intdiv($s, 60) . ' min ' . sprintf('%02d', $s % 60) . ' s';
    return intdiv($s, 3600) . ' h ' . sprintf('%02d', intdiv($s % 3600, 60)) . ' min';
}

if ($table_ok) {
    $depuis = match ($periode) {
        '30j' => (new DateTime('today'))->modify('-29 days'),
        '12m' => (new DateTime('first day of this month'))->setTime(0, 0)->modify('-11 months'),
        'tout' => (new DateTime('first day of this month'))->setTime(0, 0)->modify('-24 months'),
    };
    $par_jour = $periode === '30j';
    $where = 'debut >= ?';
    $param = [$depuis->format('Y-m-d H:i:s')];

    $st = db()->prepare("SELECT COUNT(*) visites, COALESCE(SUM(pages), 0) pages, COALESCE(AVG(duree), 0) duree,
                                COUNT(DISTINCT pays) nb_pays FROM visites WHERE $where");
    $st->execute($param);
    $total = $st->fetch();

    // Durée médiane (moins sensible que la moyenne aux onglets restés ouverts longtemps)
    $st = db()->prepare("SELECT duree FROM visites WHERE $where ORDER BY duree");
    $st->execute($param);
    $durees = $st->fetchAll(PDO::FETCH_COLUMN);
    $mediane = $durees ? (float) $durees[intdiv(count($durees), 2)] : 0;

    // Visites par jour ou par mois, périodes vides comprises
    $format = $par_jour ? '%Y-%m-%d' : '%Y-%m';
    $st = db()->prepare("SELECT DATE_FORMAT(debut, '$format') k, COUNT(*) n FROM visites WHERE $where GROUP BY k");
    $st->execute($param);
    $compte = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $serie = [];
    $fin = new DateTime('today');
    for ($d = clone $depuis; $d <= $fin; $d->modify($par_jour ? '+1 day' : '+1 month')) {
        $k = $d->format($par_jour ? 'Y-m-d' : 'Y-m');
        $serie[$k] = (int) ($compte[$k] ?? 0);
    }
    $max = max(1, ...array_values($serie));
    $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $libelle = function (string $k) use ($par_jour, $mois): string {
        $d = new DateTime($par_jour ? $k : $k . '-01');
        return $par_jour ? $d->format('j') . ' ' . $mois[(int) $d->format('n')] : $mois[(int) $d->format('n')] . ' ' . $d->format('Y');
    };

    // Par pays
    $st = db()->prepare("SELECT pays, COUNT(*) n, AVG(duree) duree, SUM(pages) pages FROM visites WHERE $where
                         GROUP BY pays ORDER BY n DESC, pays");
    $st->execute($param);
    $pays = $st->fetchAll();

    // Répartition des durées
    $tranches = ['moins de 10 s' => [0, 9], '10 à 30 s' => [10, 29], '30 s à 1 min' => [30, 59],
                 '1 à 3 min' => [60, 179], '3 à 10 min' => [180, 599], 'plus de 10 min' => [600, PHP_INT_MAX]];
    $repartition = array_fill_keys(array_keys($tranches), 0);
    foreach ($durees as $s) {
        foreach ($tranches as $nom => [$a, $b]) {
            if ($s >= $a && $s <= $b) { $repartition[$nom]++; break; }
        }
    }
}

entete_admin('statistiques', 'Statistiques');
?>
<h1>Statistiques de visite</h1>
<?php if (!$table_ok): ?>
    <p class="aide">La table des visites n'existe pas encore : <a href="migrations.php">applique d'abord les migrations</a>.</p>
<?php else: ?>
<p class="aide">Mesure anonyme : pays (déduit du fuseau horaire du visiteur), date, pages vues et temps passé, sans adresse IP.
    Tes propres visites, connecté à l'admin, ne sont pas comptées. Données supprimées au bout de 25 mois.</p>

<nav class="filtres-periode">
    <?php foreach (PERIODES as $k => $l): ?>
        <a href="?periode=<?= $k ?>" class="<?= $k === $periode ? 'actif' : '' ?>"><?= h($l) ?></a>
    <?php endforeach; ?>
</nav>

<section class="tuiles-stats">
    <div><span><?= number_format((int) $total['visites'], 0, ',', ' ') ?></span>visites</div>
    <div><span><?= number_format((int) $total['pages'], 0, ',', ' ') ?></span>pages vues</div>
    <div><span><?= duree_lisible($mediane) ?></span>durée médiane</div>
    <div><span><?= duree_lisible((float) $total['duree']) ?></span>durée moyenne</div>
    <div><span><?= (int) $total['nb_pays'] ?></span>pays</div>
</section>

<?php if ((int) $total['visites'] === 0): ?>
    <p class="vide">Aucune visite enregistrée sur cette période.</p>
<?php else: ?>
<h2>Visites par <?= $par_jour ? 'jour' : 'mois' ?></h2>
<figure class="histo" aria-label="Visites par <?= $par_jour ? 'jour' : 'mois' ?>">
    <div class="histo-barres" style="--nb: <?= count($serie) ?>">
        <?php foreach ($serie as $k => $n): ?>
            <div class="histo-col" title="<?= h($libelle($k)) ?> : <?= $n ?> visite<?= $n > 1 ? 's' : '' ?>">
                <?php if ($n): ?><span class="histo-val"><?= $n ?></span><?php endif; ?>
                <i style="height: <?= round($n / $max * 100, 1) ?>%"></i>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="histo-axe">
        <span><?= h($libelle(array_key_first($serie))) ?></span>
        <span><?= h($libelle(array_key_last($serie))) ?></span>
    </div>
</figure>

<div class="stats-colonnes">
    <section>
        <h2>Par pays</h2>
        <table class="table-cartes">
            <thead><tr><th>Pays</th><th>Visites</th><th>Part</th><th>Durée moy.</th></tr></thead>
            <tbody>
            <?php foreach ($pays as $p): ?>
                <tr>
                    <td><?= $p['pays'] ? drapeau($p['pays']) . ' ' . h(nom_pays($p['pays'], 'fr')) : '<em>Inconnu</em>' ?></td>
                    <td><?= (int) $p['n'] ?></td>
                    <td><?= round($p['n'] / $total['visites'] * 100) ?> %</td>
                    <td><?= duree_lisible((float) $p['duree']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section>
        <h2>Temps passé</h2>
        <table class="table-cartes">
            <thead><tr><th>Durée de la visite</th><th>Visites</th><th>Part</th></tr></thead>
            <tbody>
            <?php foreach ($repartition as $nom => $n): ?>
                <tr><td><?= h($nom) ?></td><td><?= $n ?></td><td><?= round($n / $total['visites'] * 100) ?> %</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<?php endif; ?>
<?php endif; ?>
<?php pied_admin(); ?>
