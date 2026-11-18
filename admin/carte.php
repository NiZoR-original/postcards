<?php
// Création / modification / suppression d'une carte.
require_once __DIR__ . '/../includes/admin.php';

const PLATEFORMES_CARTE = ['postcrossing', 'cardzilla', 'swap', 'autre'];
const FORMATS_REFERENCE_CARTE = [
    'postcrossing' => ['exemple' => 'FR-123456789', 'segments' => ['[A-Z]{2}', '[0-9]{1,9}']],
    'cardzilla' => ['exemple' => 'FR-123456-AB', 'segments' => ['[A-Z]{2}', '[0-9]{1,9}', '[A-Z]{2}']],
];

function normaliser_reference_carte(string $plateforme, string $reference): ?string
{
    $segments = FORMATS_REFERENCE_CARTE[$plateforme]['segments'];
    $motif = '(' . implode(')[\s-]*(', $segments) . ')';
    if (!preg_match('/\A' . $motif . '\z/', strtoupper(trim($reference)), $groupes)) {
        return null;
    }
    return implode('-', array_slice($groupes, 1));
}

function pays_reference_carte(string $plateforme, string $sens, ?string $reference): string
{
    if ($reference === null || !isset(FORMATS_REFERENCE_CARTE[$plateforme])
        || ($sens === 'envoyee' && $plateforme !== 'cardzilla')) {
        return '';
    }
    $normalisee = normaliser_reference_carte($plateforme, $reference);
    if ($normalisee === null) {
        return '';
    }
    $parties = explode('-', $normalisee);
    $pays = $sens === 'envoyee' ? $parties[2] : $parties[0];
    return pays_valide($pays) ? $pays : '';
}

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
$carteEnregistree = $c;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    if (isset($_POST['supprimer']) && $c) {
        supprimer_photos_de('carte', $id);
        db()->prepare('DELETE FROM cartes WHERE id = ?')->execute([$id]);
        flash('Carte ' . ($c['reference'] ?: '#' . $id) . ' supprimée.');
        rediriger('index.php');
    }

    $erreurs = [];
    $plateforme = in_array($_POST['plateforme'] ?? '', PLATEFORMES_CARTE, true) ? $_POST['plateforme'] : '';
    if ($plateforme === '') {
        $erreurs[] = 'Choisissez une plateforme.';
    }
    $reference = null;
    $lienSaisi = null;
    if (isset(URL_CARTES_PLATEFORMES[$plateforme])) {
        $reference = champ('reference', 50);
        if ($reference !== null && (($c['plateforme'] ?? null) !== $plateforme || $reference !== ($c['reference'] ?? null))) {
            $normalisee = normaliser_reference_carte($plateforme, $reference);
            if ($normalisee === null) {
                $erreurs[] = 'Référence invalide. Format attendu pour ' . libelle_plateforme($plateforme)
                    . ' : ' . FORMATS_REFERENCE_CARTE[$plateforme]['exemple'] . '.';
            } else {
                $reference = $normalisee;
            }
        }
        $lienSaisi = champ('lien');
        $ancienLienDeduit = lien_deduit($plateforme, $c['reference'] ?? null);
        if ($lienSaisi === lien_deduit($plateforme, $reference)
            || (($c['plateforme'] ?? null) === $plateforme && $lienSaisi === $ancienLienDeduit
                && (empty($c['lien']) || $c['lien'] === $ancienLienDeduit))) {
            $lienSaisi = null;
        }
    } elseif (($c['plateforme'] ?? null) === $plateforme) {
        // Conserve les liens et références des anciennes fiches sur ces plateformes.
        $reference = $c['reference'];
        $lienSaisi = $c['lien'];
    }
    $distance = filter_var($_POST['distance_km'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
    $motsCles = array_filter(array_map('trim', explode(',', champ('mots_cles', 1000) ?? '')), fn($mot) => $mot !== '');
    $sens = ($_POST['sens'] ?? '') === 'envoyee' ? 'envoyee' : 'recue';
    $paysSaisi = is_string($_POST['pays_code'] ?? null) ? strtoupper(trim($_POST['pays_code'])) : '';
    $data = [
        'sens' => $sens,
        'plateforme' => $plateforme,
        'reference' => $reference,
        'lien' => $lienSaisi,
        'pays_code' => $paysSaisi !== '' ? $paysSaisi : pays_reference_carte($plateforme, $sens, $reference),
        'subdivision' => null, // renseignée après validation du pays
        'ville' => champ('ville', 120),
        'correspondant' => champ('correspondant', 120),
        'date_envoi' => champ_date('date_envoi'),
        'date_reception' => champ_date('date_reception'),
        'distance_km' => $distance === false ? null : $distance,
        'titre' => champ('titre'),
        'description' => champ('description', 10000),
        'mots_cles' => $motsCles ? implode(', ', array_unique($motsCles)) : null,
        'notes' => champ('notes', 10000),
        'publique' => !empty($_POST['publique']) ? 1 : 0,
    ];

    if (!pays_valide($data['pays_code'])) {
        $erreurs[] = 'Choisissez un pays.';
    }
    // État / province : seulement pour les pays qui en ont, et cohérent avec le pays choisi
    $sub = champ('subdivision', 6);
    $data['subdivision'] = subdivision_valide($sub, $data['pays_code']) ? $sub : null;
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
    'plateforme' => in_array($_GET['plateforme'] ?? '', PLATEFORMES_CARTE, true) ? $_GET['plateforme'] : '',
    'publique' => 1,
];
$v = fn(string $k) => h((string) ($c[$k] ?? ''));
$photos = $id ? photos_de('carte', $id, false) : [];
$avecLien = isset(URL_CARTES_PLATEFORMES[$c['plateforme']]);
$lienAutomatique = lien_deduit($c['plateforme'], $c['reference'] ?? null);
$champLienAutomatique = empty($c['lien']) || $c['lien'] === $lienAutomatique;
$formatReference = FORMATS_REFERENCE_CARTE[$c['plateforme']] ?? null;
$referenceExistante = $formatReference !== null && !empty($carteEnregistree['reference'])
    && $carteEnregistree['plateforme'] === $c['plateforme']
    && $carteEnregistree['reference'] === ($c['reference'] ?? null)
    && !preg_match('/\A' . implode('-', $formatReference['segments']) . '\z/', $c['reference']);

entete_admin($id ? 'cartes' : 'nouvelle', $id ? 'Carte ' . ($c['reference'] ?? '#' . $id) : 'Nouvelle carte');
?>
<h1><?= $id ? 'Modifier la carte ' . $v('reference') : 'Nouvelle carte' ?></h1>
<?php if (!empty($f_erreurs)): ?><div class="flash erreur"><?= nl2br(h(implode("\n", $f_erreurs))) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="formulaire" id="formulaire-carte"
    data-liens="<?= h(json_encode(URL_CARTES_PLATEFORMES, JSON_THROW_ON_ERROR)) ?>"
    data-formats="<?= h(json_encode(FORMATS_REFERENCE_CARTE, JSON_THROW_ON_ERROR)) ?>">
    <?= csrf_field() ?>
    <div class="grille-form carte-identite">
        <fieldset class="champ-sens">
            <legend>Sens</legend>
            <div class="choix-sens">
                <label><input type="radio" name="sens" value="recue" <?= $c['sens'] === 'recue' ? 'checked' : '' ?>> <i class="fa-solid fa-inbox"></i> Reçue</label>
                <label><input type="radio" name="sens" value="envoyee" <?= $c['sens'] === 'envoyee' ? 'checked' : '' ?>> <i class="fa-solid fa-paper-plane"></i> Envoyée</label>
            </div>
        </fieldset>
        <label>Plateforme
            <select name="plateforme" id="plateforme" required>
                <option value="">— Sélectionner —</option>
                <?php foreach (PLATEFORMES_CARTE as $p): ?>
                    <option value="<?= $p ?>" <?= $c['plateforme'] === $p ? 'selected' : '' ?>><?= h(libelle_plateforme($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <fieldset class="champ-reference" data-champ-lien<?= $avecLien ? '' : ' hidden' ?>>
            <legend>Référence</legend>
            <div class="reference-segments" hidden>
                <input type="text" id="reference-prefixe" aria-label="Deux premières lettres" maxlength="2"
                    pattern="[A-Z]{2}" autocapitalize="characters" spellcheck="false" placeholder="XX" disabled>
                <span aria-hidden="true">-</span>
                <input type="text" id="reference-numero" aria-label="Numéro" aria-describedby="format-reference" maxlength="9"
                    pattern="[0-9]{1,9}" inputmode="numeric" placeholder="123456789" disabled>
                <span data-reference-fin aria-hidden="true" hidden>-</span>
                <input type="text" id="reference-suffixe" data-reference-fin aria-label="Deux dernières lettres" maxlength="2"
                    pattern="[A-Z]{2}" autocapitalize="characters" spellcheck="false" placeholder="XX" hidden disabled>
            </div>
            <input type="text" name="reference" id="reference" value="<?= $v('reference') ?>" autocapitalize="characters" spellcheck="false"
                placeholder="<?= h($formatReference['exemple'] ?? '') ?>" aria-label="Référence" aria-describedby="format-reference" maxlength="50"
                <?= $referenceExistante ? 'data-existante="1"' : '' ?>
                <?= $formatReference !== null && !$referenceExistante ? 'pattern="' . h(implode('-', $formatReference['segments'])) . '"' : '' ?>
                <?= $avecLien ? '' : 'disabled' ?>>
            <small id="format-reference"><?= $formatReference ? h($formatReference['exemple']) . ' · 1 à 9 chiffres' : '' ?></small>
        </fieldset>
        <label data-champ-lien<?= $avecLien ? '' : ' hidden' ?>>Lien
            <input type="url" name="lien" value="<?= h($c['lien'] ?? $lienAutomatique) ?>" placeholder="https://…"
                maxlength="255" data-deduit="<?= $champLienAutomatique ? '1' : '0' ?>"<?= $avecLien ? '' : ' disabled' ?>>
            <small>Rempli à partir de la référence</small>
        </label>
    </div>
    <div class="grille-form carte-trajet">
        <label><span id="lib-pays"><?= $c['sens'] === 'envoyee' ? 'Pays de destination' : 'Pays d\'origine' ?></span>
            <select name="pays_code" id="pays_code" required>
                <option value="">— Choisir —</option>
                <?php foreach (liste_pays() as $code => $nom): ?>
                    <option value="<?= h($code) ?>" <?= ($c['pays_code'] ?? '') === $code ? 'selected' : '' ?>><?= h($nom) ?></option>
                <?php endforeach; ?>
            </select>
            <small id="pays-deduit" role="status"></small>
        </label>
        <label id="champ-subdivision" <?= in_array($c['pays_code'] ?? '', PAYS_AVEC_SUBDIVISIONS, true) ? '' : 'hidden' ?>>
            <span id="lib-subdivision"><?= ($c['pays_code'] ?? '') === 'FR' ? 'Département' : 'État / province' ?></span>
            <select name="subdivision">
                <option value="">— Non indiqué —</option>
                <?php foreach (PAYS_AVEC_SUBDIVISIONS as $pp): ?>
                    <optgroup label="<?= h(nom_pays($pp)) ?>" data-pays="<?= h($pp) ?>">
                        <?php foreach (liste_subdivisions($pp) as $code => $nom): ?>
                            <option value="<?= h($code) ?>" <?= ($c['subdivision'] ?? '') === $code ? 'selected' : '' ?>><?= h($nom) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ville <input type="text" name="ville" value="<?= $v('ville') ?>" maxlength="120"></label>
        <label>Correspondant <input type="text" name="correspondant" value="<?= $v('correspondant') ?>" maxlength="120" placeholder="Pseudo"></label>
    </div>
    <div class="grille-form carte-dates">
        <label>Date d'envoi <input type="date" name="date_envoi" value="<?= $v('date_envoi') ?>"></label>
        <label>Date de réception <input type="date" name="date_reception" value="<?= $v('date_reception') ?>"></label>
        <div class="calcul-distance">
            <label for="distance-km" class="distance-libelle">Distance (km)</label>
            <input type="number" id="distance-km" name="distance_km" value="<?= $v('distance_km') ?>" min="0">
            <button type="button" class="bouton secondaire" id="calculer-distance"><i class="fa-solid fa-route" aria-hidden="true"></i> Calculer</button>
            <small>À vol d’oiseau d’après les localités. <a href="https://open-meteo.com/en/docs/geocoding-api">Open-Meteo</a> / <a href="https://www.geonames.org/">GeoNames</a></small>
            <label id="choix-domicile" hidden>Localité de mon adresse <select></select></label>
            <label id="choix-correspondant" hidden>Localité du correspondant <select></select></label>
            <small id="statut-distance" role="status"></small>
        </div>
    </div>
    <div class="grille-form carte-contenu">
        <label class="large">Titre / sujet de l'illustration <input type="text" name="titre" value="<?= $v('titre') ?>" maxlength="255"></label>
        <label class="large">Description <small>(publique)</small><textarea name="description" rows="3"><?= $v('description') ?></textarea></label>
        <label class="large">Mots clés <small>Séparés par des virgules</small>
            <input type="text" name="mots_cles" value="<?= $v('mots_cles') ?>" maxlength="1000" placeholder="phare, littoral, coucher de soleil">
        </label>
        <label class="large">Notes <small>(privées : texte de la carte, adresse…)</small><textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
        <label class="large"><input type="checkbox" name="publique" value="1" <?= !empty($c['publique']) ? 'checked' : '' ?>> Carte visible sur le site public</label>
    </div>

    <?php bloc_photos($photos); ?>

    <div class="actions actions-edition">
        <button type="submit" class="bouton"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <button type="submit" name="et_nouvelle" value="1" class="bouton secondaire"><i class="fa-solid fa-plus"></i> Enregistrer et nouvelle</button>
    </div>
    <?php if ($id): ?>
    <div class="actions actions-fiche">
        <?php if (!empty($c['publique'])): ?><a href="../carte.php?id=<?= $id ?>">Voir la fiche publique</a><?php endif; ?>
        <button type="submit" name="supprimer" value="1" class="bouton danger" formnovalidate
                onclick="return confirm('Supprimer définitivement cette carte et ses photos ?')"><i class="fa-solid fa-trash"></i> Supprimer</button>
    </div>
    <?php endif; ?>
</form>

<script src="../assets/carte-admin.js"></script>
<?php
pied_admin();
