<?php
// Socle des pages admin/ : contrôle d'accès, mise en page, bloc photos partagé (cartes et swaps).
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/migrations.php';

if (!est_admin()) {
    $_SESSION = [];
    rediriger('login.php');
}
$_SESSION['pc_last'] = time();

function entete_admin(string $actif, string $titre): void
{
    $f = flash();
    ?>
<!DOCTYPE html>
<html lang="fr"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= h($titre) ?> · Admin cartes postales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin">
<header class="topbar">
    <a class="brand" href="index.php"><i class="fa-solid fa-lock"></i> Admin</a>
    <nav>
        <a href="index.php" class="<?= $actif === 'cartes' ? 'actif' : '' ?>"><i class="fa-solid fa-envelope"></i> Cartes</a>
        <a href="carte.php" class="<?= $actif === 'nouvelle' ? 'actif' : '' ?>"><i class="fa-solid fa-plus"></i> Nouvelle carte</a>
        <a href="swaps.php" class="<?= $actif === 'swaps' ? 'actif' : '' ?>"><i class="fa-solid fa-right-left"></i> Swaps</a>
        <a href="statistiques.php" class="<?= $actif === 'statistiques' ? 'actif' : '' ?>"><i class="fa-solid fa-chart-column"></i> Statistiques</a>
        <a href="parametres.php" class="<?= $actif === 'parametres' ? 'actif' : '' ?>"><i class="fa-solid fa-gear"></i> Paramètres</a>
        <a href="migrations.php" class="<?= $actif === 'migrations' ? 'actif' : '' ?>"><i class="fa-solid fa-database"></i> Migrations</a>
        <a href="../index.php" target="_blank"><i class="fa-solid fa-earth-europe"></i> Site public</a>
    </nav>
    <?= selecteur_theme(['groupe' => 'Thème', 'auto' => 'Thème par défaut (celui du système)', 'light' => 'Mode clair', 'dark' => 'Mode sombre']) ?>
    <a href="logout.php" class="deconnexion"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
</header>
<main class="contenu">
    <?php if ($attente = migrations_en_attente(db())): ?>
        <?php if ($actif !== 'migrations'): ?>
        <div class="flash erreur"><i class="fa-solid fa-database"></i> <?= count($attente) ?> migration(s) en attente
            après une mise à jour : <a href="migrations.php">les appliquer</a>.</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($f): ?><div class="flash <?= h($f['type']) ?>"><?= nl2br(h($f['msg'])) ?></div><?php endif; ?>
    <?php
}

function pied_admin(): void
{
    ?>
</main>
<div id="lightbox" class="lightbox" hidden><img alt=""><button type="button" aria-label="Fermer">&times;</button></div>
<script src="../assets/lightbox.js"></script>
<script src="../assets/theme.js"></script>
</body>
</html>
    <?php
}

/** Bloc de gestion des photos existantes + champ d'envoi (dans un <form enctype="multipart/form-data">). */
function bloc_photos(array $photos, bool $publique_par_defaut = true): void
{
    ?>
    <fieldset class="photos-admin">
        <legend>Photos</legend>
        <?php if ($photos): ?>
        <div class="photos-liste">
            <?php foreach ($photos as $p): $pid = (int) $p['id']; ?>
            <div class="photo-item <?= $p['publique'] ? '' : 'privee' ?>">
                <a href="<?= h(url_photo($pid, 'full', '../')) ?>" data-lightbox><img src="<?= h(url_photo($pid, 'thumb', '../')) ?>" alt=""></a>
                <input type="text" name="photos[<?= $pid ?>][legende]" value="<?= h($p['legende']) ?>" placeholder="Légende (recto, verso…)">
                <div class="photo-options">
                    <label title="Ordre d'affichage">#<input type="number" name="photos[<?= $pid ?>][ordre]" value="<?= (int) $p['ordre'] ?>"></label>
                    <label><input type="checkbox" name="photos[<?= $pid ?>][publique]" value="1" <?= $p['publique'] ? 'checked' : '' ?>> Publique</label>
                    <label class="danger"><input type="checkbox" name="photos[<?= $pid ?>][supprimer]" value="1"> Supprimer</label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="aide">La première photo publique (ordre le plus petit) sert de vignette sur la carte du monde.</p>
        <?php endif; ?>
        <div class="ajout-photos">
            <label>Ajouter des photos (JPEG, PNG, WebP — plusieurs possibles)
                <input type="file" name="nouvelles_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
            <label><input type="checkbox" name="nouvelles_publiques" value="1" <?= $publique_par_defaut ? 'checked' : '' ?>> Nouvelles photos publiques</label>
        </div>
    </fieldset>
    <?php
}

/**
 * Applique les modifications du bloc photos et enregistre les nouvelles.
 * @return string[] erreurs
 */
function traiter_bloc_photos(string $type, int $id): array
{
    $col = $type === 'swap' ? 'swap_id' : 'carte_id';
    $existantes = [];
    foreach (photos_de($type, $id, false) as $p) {
        $existantes[(int) $p['id']] = $p;
    }

    $maj = db()->prepare("UPDATE photos SET legende = ?, publique = ?, ordre = ? WHERE id = ? AND $col = ?");
    $del = db()->prepare("DELETE FROM photos WHERE id = ? AND $col = ?");
    foreach ((array) ($_POST['photos'] ?? []) as $pid => $v) {
        $pid = (int) $pid;
        if (!isset($existantes[$pid])) {
            continue; // n'appartient pas à cet objet
        }
        if (!empty($v['supprimer'])) {
            $del->execute([$pid, $id]);
            supprimer_fichiers_photo($existantes[$pid]['fichier']);
            continue;
        }
        $legende = trim($v['legende'] ?? '');
        $maj->execute([$legende !== '' ? $legende : null, !empty($v['publique']) ? 1 : 0, (int) ($v['ordre'] ?? 0), $pid, $id]);
    }

    return enregistrer_photos($type, $id, fichiers_envoyes('nouvelles_photos'), !empty($_POST['nouvelles_publiques']));
}

/** Chaîne nettoyée ou null si vide. */
function champ(string $nom, int $max = 255): ?string
{
    $v = trim((string) ($_POST[$nom] ?? ''));
    return $v === '' ? null : mb_substr($v, 0, $max);
}

function champ_date(string $nom): ?string
{
    $v = trim((string) ($_POST[$nom] ?? ''));
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}
