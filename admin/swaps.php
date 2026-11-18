<?php
// Liste des cartes à échanger.
require_once __DIR__ . '/../includes/admin.php';

$etat = in_array($_GET['etat'] ?? '', ['disponible', 'reservee', 'echangee'], true) ? $_GET['etat'] : '';
$st = db()->prepare("SELECT s.*,
        (SELECT p.id FROM photos p WHERE p.swap_id = s.id ORDER BY p.publique DESC, p.ordre, p.id LIMIT 1) AS photo
    FROM swaps s " . ($etat ? 'WHERE s.etat = ?' : '') . "
    ORDER BY FIELD(s.etat, 'disponible', 'reservee', 'echangee'), s.id DESC");
$st->execute($etat ? [$etat] : []);
$swaps = $st->fetchAll();

$libelles = ['disponible' => 'Disponible', 'reservee' => 'Réservée', 'echangee' => 'Échangée'];
entete_admin('swaps', 'Swaps');
?>
<h1>Swaps</h1>
<p class="liens-swaps-admin">
    <a class="bouton" href="swap.php"><i class="fa-solid fa-plus"></i> Nouvelle carte à échanger</a>
    <a href="../swaps.php">Page publique à partager sur Discord</a>
</p>
<nav class="onglets">
    <a href="swaps.php" class="<?= $etat === '' ? 'actif' : '' ?>">Toutes</a>
    <?php foreach ($libelles as $k => $l): ?>
        <a href="?etat=<?= $k ?>" class="<?= $etat === $k ? 'actif' : '' ?>"><?= h($l) ?></a>
    <?php endforeach; ?>
</nav>
<table class="table-cartes table-inventaire" aria-label="Cartes à échanger">
    <thead><tr><th></th><th>#</th><th>Titre</th><th>Thème</th><th>État</th><th>Réservée pour</th></tr></thead>
    <tbody>
    <?php foreach ($swaps as $s): ?>
        <tr class="etat-<?= h($s['etat']) ?>">
            <td class="mini"><?php if ($s['photo']): ?><img src="<?= h(url_photo((int) $s['photo'], 'thumb', '../')) ?>" alt="" loading="lazy"><?php endif; ?></td>
            <td data-libelle="Numéro"><?= (int) $s['id'] ?></td>
            <td class="cellule-principale"><a href="swap.php?id=<?= (int) $s['id'] ?>"><strong><?= h($s['titre']) ?></strong></a></td>
            <td data-libelle="Thème"><?= h($s['theme']) ?></td>
            <td data-libelle="État"><span class="pastille <?= h($s['etat']) ?>"><?= h($libelles[$s['etat']]) ?></span></td>
            <td data-libelle="Réservée pour"><?= h($s['reserve_pour']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$swaps): ?><tr><td colspan="6" class="vide">Aucune carte.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php
pied_admin();
