<?php
// Liste des cartes avec filtres.
require_once __DIR__ . '/../includes/admin.php';

$f = [
    'sens' => in_array($_GET['sens'] ?? '', ['recue', 'envoyee'], true) ? $_GET['sens'] : '',
    'plateforme' => in_array($_GET['plateforme'] ?? '', ['postcrossing', 'cardzilla', 'swap', 'autre'], true) ? $_GET['plateforme'] : '',
    'pays' => pays_valide($_GET['pays'] ?? '') ? $_GET['pays'] : '',
    'q' => trim($_GET['q'] ?? ''),
    'en_route' => !empty($_GET['en_route']),
];

$where = [];
$params = [];
if ($f['sens']) { $where[] = 'c.sens = ?'; $params[] = $f['sens']; }
if ($f['plateforme']) { $where[] = 'c.plateforme = ?'; $params[] = $f['plateforme']; }
if ($f['pays']) { $where[] = 'c.pays_code = ?'; $params[] = $f['pays']; }
if ($f['en_route']) { $where[] = "c.sens = 'envoyee' AND c.date_reception IS NULL"; }
if ($f['q'] !== '') {
    $where[] = '(c.reference LIKE ? OR c.titre LIKE ? OR c.correspondant LIKE ? OR c.ville LIKE ?)';
    array_push($params, ...array_fill(0, 4, '%' . $f['q'] . '%'));
}

$parPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = db()->prepare("SELECT COUNT(*) FROM cartes c $sqlWhere");
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = db()->prepare("SELECT c.*,
        (SELECT p.id FROM photos p WHERE p.carte_id = c.id ORDER BY p.publique DESC, p.ordre, p.id LIMIT 1) AS photo,
        (SELECT COUNT(*) FROM photos p WHERE p.carte_id = c.id) AS nb_photos
    FROM cartes c $sqlWhere
    ORDER BY COALESCE(c.date_reception, c.date_envoi, c.created_at) DESC, c.id DESC
    LIMIT $parPage OFFSET " . (($page - 1) * $parPage));
$st->execute($params);
$cartes = $st->fetchAll();

$compteurs = db()->query("SELECT
    SUM(sens = 'recue') recues, SUM(sens = 'envoyee') envoyees,
    SUM(sens = 'envoyee' AND date_reception IS NULL) en_route,
    COUNT(DISTINCT pays_code) pays FROM cartes")->fetch();

$pays = liste_pays();
entete_admin('cartes', 'Cartes');
?>
<section class="stats">
    <span><i class="fa-solid fa-inbox"></i> <?= (int) $compteurs['recues'] ?> reçues</span>
    <span><i class="fa-solid fa-paper-plane"></i> <?= (int) $compteurs['envoyees'] ?> envoyées</span>
    <span><a href="?en_route=1"><i class="fa-solid fa-truck-fast"></i> <?= (int) $compteurs['en_route'] ?> en route</a></span>
    <span><i class="fa-solid fa-flag"></i> <?= (int) $compteurs['pays'] ?> pays</span>
</section>

<form class="filtres-admin" method="get">
    <input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Référence, titre, pseudo, ville…">
    <select name="sens">
        <option value="">Reçues et envoyées</option>
        <option value="recue" <?= $f['sens'] === 'recue' ? 'selected' : '' ?>>Reçues</option>
        <option value="envoyee" <?= $f['sens'] === 'envoyee' ? 'selected' : '' ?>>Envoyées</option>
    </select>
    <select name="plateforme">
        <option value="">Toutes plateformes</option>
        <?php foreach (['postcrossing', 'cardzilla', 'swap', 'autre'] as $p): ?>
            <option value="<?= $p ?>" <?= $f['plateforme'] === $p ? 'selected' : '' ?>><?= h(libelle_plateforme($p)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="pays">
        <option value="">Tous pays</option>
        <?php foreach ($pays as $code => $nom): ?>
            <option value="<?= h($code) ?>" <?= $f['pays'] === $code ? 'selected' : '' ?>><?= h($nom) ?></option>
        <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="en_route" value="1" <?= $f['en_route'] ? 'checked' : '' ?>> En route</label>
    <button class="bouton" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
    <a href="index.php">Réinitialiser</a>
</form>

<p class="aide"><?= $total ?> carte(s)</p>

<table class="table-cartes">
    <thead><tr><th></th><th>Sens</th><th>Référence</th><th>Pays</th><th>Correspondant</th><th>Envoi</th><th>Réception</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cartes as $c): ?>
        <tr class="<?= $c['publique'] ? '' : 'privee' ?>">
            <td class="mini"><?php if ($c['photo']): ?><img src="<?= h(url_photo((int) $c['photo'], 'thumb', '../')) ?>" alt="" loading="lazy"><?php endif; ?></td>
            <td><i class="fa-solid <?= $c['sens'] === 'recue' ? 'fa-inbox' : 'fa-paper-plane' ?>" title="<?= $c['sens'] === 'recue' ? 'Reçue' : 'Envoyée' ?>"></i></td>
            <td>
                <a href="carte.php?id=<?= (int) $c['id'] ?>"><strong><?= h($c['reference'] ?: '(sans réf.)') ?></strong></a>
                <small><?= h(libelle_plateforme($c['plateforme'])) ?><?= $c['titre'] ? ' · ' . h($c['titre']) : '' ?></small>
            </td>
            <td><?= drapeau($c['pays_code']) ?> <?= h($pays[$c['pays_code']] ?? $c['pays_code']) ?>
                <?php if ($c['subdivision']): ?><small><?= h(nom_subdivision($c['subdivision'])) ?></small><?php endif; ?></td>
            <td><?= h($c['correspondant']) ?></td>
            <td><?= h(date_lisible($c['date_envoi'])) ?></td>
            <td><?= $c['date_reception'] ? h(date_lisible($c['date_reception'])) : ($c['sens'] === 'envoyee' ? '<em>en route</em>' : '') ?></td>
            <td class="icones">
                <?php if (!$c['publique']): ?><i class="fa-solid fa-eye-slash" title="Privée"></i><?php endif; ?>
                <?php if ($c['nb_photos']): ?><span title="Photos"><i class="fa-regular fa-images"></i> <?= (int) $c['nb_photos'] ?></span><?php endif; ?>
                <?php if ($l = lien_plateforme($c)): ?><a href="<?= h($l) ?>" target="_blank" rel="noopener" title="Voir sur la plateforme"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$cartes): ?><tr><td colspan="8" class="vide">Aucune carte. <a href="carte.php">Enregistrer la première</a></td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($total > $parPage): ?>
<nav class="pagination">
    <?php for ($i = 1, $n = (int) ceil($total / $parPage); $i <= $n; $i++): ?>
        <a href="?<?= h(http_build_query(array_merge($_GET, ['page' => $i]))) ?>" class="<?= $i === $page ? 'actif' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php
pied_admin();
