<?php
// Application des migrations depuis le navigateur (hébergement sans SSH).
require_once __DIR__ . '/../includes/admin.php';

$journal = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    try {
        $n = migrer(db(), function ($m) use (&$journal) { $journal[] = $m; });
        flash($n ? "$n migration(s) appliquée(s)." : 'La base était déjà à jour.');
    } catch (Throwable $e) {
        flash($e->getMessage() . "\nLes migrations précédentes restent appliquées ; corriger puis relancer.", 'erreur');
    }
    rediriger('migrations.php');
}

$faites = migrations_appliquees(db());
$dispo = migrations_disponibles();
$attente = array_values(array_diff($dispo, array_keys($faites)));
$orphelines = array_diff(array_keys($faites), $dispo);

entete_admin('migrations', 'Migrations');
?>
<h1>Migrations du schéma</h1>
<p class="aide">Après une mise à jour des fichiers du site, applique ici les migrations en attente.
    Pense à sauvegarder la base (export phpMyAdmin) avant une migration importante.</p>

<?php if ($attente): ?>
<form method="post" class="actions">
    <?= csrf_field() ?>
    <button type="submit" class="bouton"><i class="fa-solid fa-database"></i> Appliquer <?= count($attente) ?> migration(s)</button>
</form>
<?php endif; ?>

<table class="table-cartes">
    <thead><tr><th>Fichier</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($dispo as $f): ?>
        <tr>
            <td><code><?= h($f) ?></code></td>
            <td><?= isset($faites[$f])
                ? '<span class="pastille disponible">Appliquée</span> <small>' . h($faites[$f]) . '</small>'
                : '<span class="pastille">En attente</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($orphelines as $f): ?>
        <tr><td><code><?= h($f) ?></code></td><td><span class="pastille">Appliquée, fichier absent</span></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
pied_admin();
