<?php
// Création / modification / suppression d'une carte à échanger.
require_once __DIR__ . '/../includes/admin.php';

$id = (int) ($_GET['id'] ?? 0);
$s = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM swaps WHERE id = ?');
    $st->execute([$id]);
    $s = $st->fetch();
    if (!$s) {
        flash('Swap introuvable.', 'erreur');
        rediriger('swaps.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    if (isset($_POST['supprimer']) && $s) {
        supprimer_photos_de('swap', $id);
        db()->prepare('DELETE FROM swaps WHERE id = ?')->execute([$id]);
        flash('Swap #' . $id . ' supprimé.');
        rediriger('swaps.php');
    }

    $data = [
        'titre' => champ('titre'),
        'theme' => champ('theme', 120),
        'description' => champ('description', 10000),
        'etat' => in_array($_POST['etat'] ?? '', ['disponible', 'reservee', 'echangee'], true) ? $_POST['etat'] : 'disponible',
        'reserve_pour' => champ('reserve_pour', 120),
        'notes' => champ('notes', 10000),
    ];

    if ($data['titre'] === null) {
        $f_erreurs = ['Le titre est obligatoire.'];
        $s = array_merge($s ?? [], $data);
    } else {
        $cols = array_keys($data);
        if ($s) {
            db()->prepare('UPDATE swaps SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ' WHERE id = ?')
                ->execute([...array_values($data), $id]);
        } else {
            db()->prepare('INSERT INTO swaps (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
                ->execute(array_values($data));
            $id = (int) db()->lastInsertId();
        }
        $erreurs_photos = traiter_bloc_photos('swap', $id);
        flash($erreurs_photos ? "Enregistré, mais certaines photos ont échoué :\n" . implode("\n", $erreurs_photos) : 'Swap enregistré.', $erreurs_photos ? 'erreur' : 'ok');
        rediriger(isset($_POST['et_nouvelle']) ? 'swap.php' : 'swap.php?id=' . $id);
    }
}

$s ??= ['etat' => 'disponible'];
$v = fn(string $k) => h((string) ($s[$k] ?? ''));
$photos = $id ? photos_de('swap', $id, false) : [];
$themes = db()->query('SELECT DISTINCT theme FROM swaps WHERE theme IS NOT NULL ORDER BY theme')->fetchAll(PDO::FETCH_COLUMN);

entete_admin('swaps', $id ? 'Swap #' . $id : 'Nouveau swap');
?>
<h1><?= $id ? 'Swap #' . $id : 'Nouvelle carte à échanger' ?></h1>
<?php if (!empty($f_erreurs)): ?><div class="flash erreur"><?= h(implode("\n", $f_erreurs)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="formulaire">
    <?= csrf_field() ?>
    <div class="grille-form">
        <label class="large">Titre <input type="text" name="titre" value="<?= $v('titre') ?>" required maxlength="255"></label>
        <label>Thème <input type="text" name="theme" value="<?= $v('theme') ?>" list="themes" maxlength="120" placeholder="Paris, phares, chats…">
            <datalist id="themes"><?php foreach ($themes as $t): ?><option value="<?= h($t) ?>"><?php endforeach; ?></datalist>
        </label>
        <label>État
            <select name="etat">
                <?php foreach (['disponible' => 'Disponible', 'reservee' => 'Réservée', 'echangee' => 'Échangée (masquée du public)'] as $k => $l): ?>
                    <option value="<?= $k ?>" <?= $s['etat'] === $k ? 'selected' : '' ?>><?= h($l) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Réservée pour <small>(pseudo Discord, privé)</small> <input type="text" name="reserve_pour" value="<?= $v('reserve_pour') ?>" maxlength="120"></label>
        <label class="large">Description <small>(publique)</small><textarea name="description" rows="3"><?= $v('description') ?></textarea></label>
        <label class="large">Notes <small>(privées)</small><textarea name="notes" rows="2"><?= $v('notes') ?></textarea></label>
    </div>

    <?php bloc_photos($photos); ?>

    <div class="actions actions-edition">
        <button type="submit" class="bouton"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <button type="submit" name="et_nouvelle" value="1" class="bouton secondaire"><i class="fa-solid fa-plus"></i> Enregistrer et nouvelle</button>
    </div>
    <?php if ($id): ?>
    <div class="actions actions-fiche">
        <button type="submit" name="supprimer" value="1" class="bouton danger" formnovalidate
                onclick="return confirm('Supprimer définitivement ce swap et ses photos ?')"><i class="fa-solid fa-trash"></i> Supprimer</button>
    </div>
    <?php endif; ?>
</form>
<?php
pied_admin();
