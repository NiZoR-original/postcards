<?php
// Création / modification / suppression d'une carte.
require_once __DIR__ . '/../includes/admin.php';

$id = (int) ($_GET['id'] ?? 0);
$c = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM cartes WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) {
        flash('Carte introuvable.', 'erreur');
        rediriger('index.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    if (isset($_POST['supprimer']) && $c) {
        supprimer_photos_de('carte', $id);
        db()->prepare('DELETE FROM cartes WHERE id = ?')->execute([$id]);
        flash('Carte ' . ($c['reference'] ?: '#' . $id) . ' supprimée.');
        rediriger('index.php');
    }

    $data = [
        'sens' => ($_POST['sens'] ?? '') === 'envoyee' ? 'envoyee' : 'recue',
        'plateforme' => in_array($_POST['plateforme'] ?? '', ['postcrossing', 'cardzilla', 'swap', 'autre'], true) ? $_POST['plateforme'] : 'autre',
        'reference' => champ('reference', 50),
        'lien' => champ('lien'),
        'pays_code' => strtoupper((string) ($_POST['pays_code'] ?? '')),
        'ville' => champ('ville', 120),
        'correspondant' => champ('correspondant', 120),
        'date_envoi' => champ_date('date_envoi'),
        'date_reception' => champ_date('date_reception'),
        'distance_km' => ($d = (int) ($_POST['distance_km'] ?? 0)) > 0 ? $d : null,
        'titre' => champ('titre'),
        'description' => champ('description', 10000),
        'notes' => champ('notes', 10000),
        'publique' => !empty($_POST['publique']) ? 1 : 0,
    ];

    $erreurs = [];
    if (!pays_valide($data['pays_code'])) {
        $erreurs[] = 'Choisissez un pays.';
    }
    if ($data['lien'] && !preg_match('#^https?://#i', $data['lien'])) {
        $erreurs[] = 'Le lien doit commencer par http:// ou https://.';
    }
    if ($data['reference'] !== null) {
        $st = db()->prepare('SELECT id FROM cartes WHERE plateforme = ? AND reference = ? AND id <> ?');
        $st->execute([$data['plateforme'], $data['reference'], $id]);
        if ($autre = $st->fetchColumn()) {
            $erreurs[] = "La référence {$data['reference']} existe déjà (carte #$autre).";
        }
    }

    if (!$erreurs) {
        $cols = array_keys($data);
        if ($c) {
            $sql = 'UPDATE cartes SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ' WHERE id = ?';
            db()->prepare($sql)->execute([...array_values($data), $id]);
        } else {
            $sql = 'INSERT INTO cartes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            db()->prepare($sql)->execute(array_values($data));
            $id = (int) db()->lastInsertId();
        }
        $erreurs_photos = traiter_bloc_photos('carte', $id);
        if ($erreurs_photos) {
            flash("Carte enregistrée, mais certaines photos ont échoué :\n" . implode("\n", $erreurs_photos), 'erreur');
        } else {
            flash('Carte enregistrée.');
        }
        rediriger(isset($_POST['et_nouvelle']) ? 'carte.php?' . http_build_query(['sens' => $data['sens'], 'plateforme' => $data['plateforme']]) : 'carte.php?id=' . $id);
    }

    // Réaffiche le formulaire avec la saisie
    $c = array_merge($c ?? [], $data);
    $f_erreurs = $erreurs;
}

// Valeurs par défaut d'une nouvelle carte (préremplies depuis « Enregistrer et nouvelle »)
$c ??= [
    'sens' => ($_GET['sens'] ?? '') === 'envoyee' ? 'envoyee' : 'recue',
    'plateforme' => in_array($_GET['plateforme'] ?? '', ['postcrossing', 'cardzilla', 'swap', 'autre'], true) ? $_GET['plateforme'] : 'postcrossing',
    'publique' => 1,
];
$v = fn(string $k) => h((string) ($c[$k] ?? ''));
$photos = $id ? photos_de('carte', $id, false) : [];

entete_admin($id ? 'cartes' : 'nouvelle', $id ? 'Carte ' . ($c['reference'] ?? '#' . $id) : 'Nouvelle carte');
?>
<h1><?= $id ? 'Modifier la carte ' . $v('reference') : 'Nouvelle carte' ?></h1>
<?php if (!empty($f_erreurs)): ?><div class="flash erreur"><?= nl2br(h(implode("\n", $f_erreurs))) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="formulaire">
    <?= csrf_field() ?>
    <div class="grille-form">
        <fieldset class="choix-sens">
            <legend>Sens</legend>
            <label><input type="radio" name="sens" value="recue" <?= $c['sens'] === 'recue' ? 'checked' : '' ?>> <i class="fa-solid fa-inbox"></i> Reçue</label>
            <label><input type="radio" name="sens" value="envoyee" <?= $c['sens'] === 'envoyee' ? 'checked' : '' ?>> <i class="fa-solid fa-paper-plane"></i> Envoyée</label>
        </fieldset>
        <label>Plateforme
            <select name="plateforme" id="plateforme">
                <?php foreach (['postcrossing', 'cardzilla', 'swap', 'autre'] as $p): ?>
                    <option value="<?= $p ?>" <?= $c['plateforme'] === $p ? 'selected' : '' ?>><?= h(libelle_plateforme($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Référence <input type="text" name="reference" id="reference" value="<?= $v('reference') ?>" placeholder="FR-1234567" maxlength="50"></label>
        <label>Lien <small>(facultatif pour Postcrossing : déduit de la référence)</small>
            <input type="url" name="lien" value="<?= $v('lien') ?>" placeholder="https://…">
        </label>
        <label><span id="lib-pays"><?= $c['sens'] === 'envoyee' ? 'Pays de destination' : 'Pays d\'origine' ?></span>
            <select name="pays_code" required>
                <option value="">— Choisir —</option>
                <?php foreach (liste_pays() as $code => $nom): ?>
                    <option value="<?= h($code) ?>" <?= ($c['pays_code'] ?? '') === $code ? 'selected' : '' ?>><?= h($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ville <input type="text" name="ville" value="<?= $v('ville') ?>" maxlength="120"></label>
        <label>Correspondant <small>(pseudo)</small> <input type="text" name="correspondant" value="<?= $v('correspondant') ?>" maxlength="120"></label>
        <label>Distance (km) <input type="number" name="distance_km" value="<?= $v('distance_km') ?>" min="0"></label>
        <label>Date d'envoi <input type="date" name="date_envoi" value="<?= $v('date_envoi') ?>"></label>
        <label>Date de réception <input type="date" name="date_reception" value="<?= $v('date_reception') ?>"></label>
        <label class="large">Titre / sujet de l'illustration <input type="text" name="titre" value="<?= $v('titre') ?>" maxlength="255"></label>
        <label class="large">Description <small>(publique)</small><textarea name="description" rows="3"><?= $v('description') ?></textarea></label>
        <label class="large">Notes <small>(privées : texte de la carte, adresse…)</small><textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
        <label class="large"><input type="checkbox" name="publique" value="1" <?= !empty($c['publique']) ? 'checked' : '' ?>> Carte visible sur le site public</label>
    </div>

    <?php bloc_photos($photos); ?>

    <div class="actions">
        <button type="submit" class="bouton"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <button type="submit" name="et_nouvelle" value="1" class="bouton secondaire"><i class="fa-solid fa-plus"></i> Enregistrer et nouvelle</button>
        <?php if ($id && !empty($c['publique'])): ?><a href="../carte.php?id=<?= $id ?>" target="_blank">Voir la fiche publique</a><?php endif; ?>
        <?php if ($id): ?>
            <button type="submit" name="supprimer" value="1" class="bouton danger" formnovalidate
                    onclick="return confirm('Supprimer définitivement cette carte et ses photos ?')"><i class="fa-solid fa-trash"></i> Supprimer</button>
        <?php endif; ?>
    </div>
</form>

<script>
// Libellé du pays selon le sens ; référence Postcrossing en majuscules
document.querySelectorAll('input[name=sens]').forEach(r => r.addEventListener('change', () => {
  document.getElementById('lib-pays').textContent = r.value === 'envoyee' ? 'Pays de destination' : "Pays d'origine";
}));
document.getElementById('reference').addEventListener('blur', e => {
  if (document.getElementById('plateforme').value === 'postcrossing') e.target.value = e.target.value.trim().toUpperCase();
});
</script>
<?php
pied_admin();
