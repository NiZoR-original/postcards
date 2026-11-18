<?php
// Paramètres du site : profils Postcrossing / Cardzilla / Discord affichés dans la barre de menu publique,
// pseudo Discord affiché sur la page des swaps.
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/adresse.php';

const PARAMETRES = [
    'profil_postcrossing' => ['Nom d\'utilisateur Postcrossing', 'https://www.postcrossing.com/user/'],
    'profil_cardzilla' => ['Nom d\'utilisateur Cardzilla', 'https://cardzilla.eu/users/'],
    'profil_discord' => ['Identifiant Discord', 'https://discord.com/users/'],
    'pseudo_discord' => ['Pseudo Discord', 'sans le @, ex. jean_dupont'],
    'email_contact' => ['Adresse de contact', 'e-mail affiché sur la page Confidentialité'],
];

try {
    db()->query('SELECT 1 FROM parametres LIMIT 1');
    $table_ok = true;
} catch (PDOException) {
    $table_ok = false;
}

if ($table_ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $erreurs = [];
    $st = db()->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    if (($_POST['section'] ?? '') === 'adresse') {
        $paysAdresse = champ('adresse_pays', 2);
        if ($paysAdresse === null || !pays_valide($paysAdresse)) {
            flash('Choisis le pays de ton adresse.', 'erreur');
        } else {
            $adresse = ['pays' => $paysAdresse];
            $format = format_adresse($paysAdresse);
            foreach (CHAMPS_ADRESSE as $cle => [$type, , , $max]) {
                if (str_contains($format['ordre'], $type)) {
                    $adresse[$cle] = champ('adresse_' . $cle, $max);
                }
            }
            $st->execute(['adresse_personnelle', json_encode($adresse, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            flash('Adresse enregistrée.');
        }
        rediriger('parametres.php#adresse');
    }

    foreach (PARAMETRES as $cle => [$libelle]) {
        // Adresse de contact : e-mail, aucune autre transformation
        if ($cle === 'email_contact') {
            $v = champ($cle, 254);
            if ($v !== null && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = "$libelle : adresse e-mail invalide.";
                continue;
            }
            $st->execute([$cle, $v]);
            continue;
        }
        $v = champ($cle, 64);
        // On accepte aussi l'URL complète du profil collée telle quelle : on n'en garde que le nom
        if ($v !== null && preg_match('#/users?/([^/?\#]+)#', $v, $m)) {
            $v = rawurldecode($m[1]);
        }
        // Pseudo Discord : minuscules, chiffres, « _ » et « . », 2 à 32 caractères ; le @ éventuel est retiré
        if ($v !== null && $cle === 'pseudo_discord') {
            $v = mb_strtolower(ltrim($v, '@'));
            if (!preg_match('/^[a-z0-9_.]{2,32}$/', $v)) {
                $erreurs[] = "$libelle : 2 à 32 caractères parmi lettres, chiffres, « _ » et « . ».";
                continue;
            }
        }
        // Discord : identifiant numérique (Paramètres > Avancés > Mode développeur, puis clic droit sur son profil > Copier l'identifiant)
        if ($v !== null && $cle === 'profil_discord' && !preg_match('/^\d{15,21}$/', $v)) {
            $erreurs[] = "$libelle : l'identifiant Discord est un nombre de 17 à 20 chiffres (pas le pseudo).";
            continue;
        }
        if ($v !== null && !preg_match('/^[\p{L}\p{N}_.\-]+$/u', $v)) {
            $erreurs[] = "$libelle : caractères non autorisés (lettres, chiffres, « _ », « . » et « - » seulement).";
            continue;
        }
        $st->execute([$cle, $v]);
    }
    $erreurs ? flash(implode("\n", $erreurs), 'erreur') : flash('Paramètres enregistrés.');
    rediriger('parametres.php');
}

entete_admin('parametres', 'Paramètres');
?>
<h1>Paramètres</h1>
<?php if (!$table_ok): ?>
    <p class="aide">La table des paramètres n'existe pas encore : <a href="migrations.php">applique d'abord les migrations</a>.</p>
<?php else: ?>
<p class="aide">Les profils renseignés apparaissent en lien dans la barre de menu du site public. Laisser vide pour masquer le lien.<br>
    Discord : il faut l'identifiant numérique, pas le pseudo (Paramètres Discord → Avancés → activer le Mode développeur,
    puis clic droit sur ton profil → « Copier l'identifiant utilisateur »).<br>
    Le pseudo Discord est affiché sur la page des swaps (et dans l'infobulle du lien Discord), pour qu'on te retrouve
    sur le serveur Postcrossing si le lien ne s'ouvre pas.<br>
    L'adresse de contact est affichée sur la page « Confidentialité et mentions légales » (demandes de retrait d'une photo, droits RGPD).</p>
<form method="post" class="formulaire">
    <?= csrf_field() ?>
    <div class="grille-form">
        <?php foreach (PARAMETRES as $cle => [$libelle, $prefixe]): ?>
            <label><?= h($libelle) ?> <small>(<?= h($prefixe) ?>…)</small>
                <input name="<?= $cle ?>" value="<?= h(parametre($cle) ?? '') ?>" maxlength="254" autocomplete="off" type="<?= $cle === 'email_contact' ? 'email' : 'text' ?>">
            </label>
        <?php endforeach; ?>
    </div>
    <?php if ($liens = liens_profils()): ?>
        <p class="aide">Vérifier :
            <?php foreach ($liens as $p => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h(libelle_plateforme($p)) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <div class="actions">
        <button type="submit" class="bouton"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
    </div>
</form>
<?php
$adresse = adresse_personnelle();
$format = format_adresse($adresse['pays']);
$champs = CHAMPS_ADRESSE;
$ordre = array_flip(str_split($format['ordre']));
uasort($champs, fn($a, $b) => ($ordre[$a[0]] ?? PHP_INT_MAX) <=> ($ordre[$b[0]] ?? PHP_INT_MAX));
$paysAdresse = liste_pays();
$formats = [];
foreach ($paysAdresse as $code => $nom) {
    $gabarit = format_adresse($code);
    $formats[$code] = ['ordre' => $gabarit['ordre'], 'libelles' => []];
    foreach (CHAMPS_ADRESSE as $cle => [, $libelle]) {
        $libelleLocal = libelle_champ_adresse($cle, $gabarit);
        if ($libelle !== $libelleLocal) {
            $formats[$code]['libelles'][$cle] = $libelleLocal;
        }
    }
}
?>
<section id="adresse" class="section-adresse">
    <h2>Mon adresse</h2>
    <p class="aide">Adresse privée. Le calcul utilise seulement le pays, le code postal et la ville.</p>
    <form method="post" class="formulaire" data-formats-adresse="<?= h(json_encode($formats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="adresse">
        <div class="grille-form adresse-grille">
            <label class="large">Pays
                <select name="adresse_pays" autocomplete="country" required>
                    <?php foreach ($paysAdresse as $code => $nom): ?>
                        <option value="<?= h($code) ?>" <?= $adresse['pays'] === $code ? 'selected' : '' ?>><?= h($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php foreach ($champs as $cle => [$type, $libelle, $auto, $max]): $visible = isset($ordre[$type]); ?>
                <label data-adresse-champ="<?= h($type) ?>" data-adresse-cle="<?= h($cle) ?>" data-libelle="<?= h($libelle) ?>"
                        <?= $type === 'A' ? 'class="large"' : '' ?><?= $visible ? '' : ' hidden' ?>>
                    <span><?= h(libelle_champ_adresse($cle, $format)) ?></span>
                    <input name="adresse_<?= h($cle) ?>" value="<?= h($adresse[$cle] ?? '') ?>" maxlength="<?= $max ?>"
                        autocomplete="<?= h($auto) ?>"<?= $visible ? '' : ' disabled' ?>>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="actions"><button class="bouton" type="submit">Enregistrer mon adresse</button></div>
    </form>
</section>
<script src="../assets/parametres-adresse.js"></script>
<?php endif; ?>
<?php pied_admin(); ?>
