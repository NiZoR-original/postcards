<?php
// Installeur web. Étapes déduites de l'état du serveur, dans l'ordre :
//   1. vérifications (PHP, extensions, droits d'écriture)
//   2. connexion MySQL → écrit db_config.php (ou affiche son contenu à déposer en FTP)
//   3. migrations du schéma
//   4. création du premier compte admin → connexion et redirection vers l'admin
// Verrouillé dès qu'un compte admin existe : les mises à jour de schéma se font ensuite dans admin/migrations.php.
// Par sécurité, il ne modifie jamais un db_config.php existant.

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/migrations.php';

$fichierConfig = __DIR__ . '/db_config.php';
$erreurs = [];
$messages = [];
$configAAfficher = null; // contenu de db_config.php si on n'a pas pu l'écrire
header('Cache-Control: no-store');

// ---------------------------------------------------------------- 1. Vérifications
$verifs = [];
$verifs[] = ['PHP ≥ 8.1 (actuel : ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1', '>='), true];
foreach (['pdo_mysql', 'intl', 'gd', 'fileinfo', 'mbstring'] as $ext) {
    $verifs[] = ["Extension $ext", extension_loaded($ext), true];
}
$verifs[] = ['Extension exif (redressement des photos de téléphone)', extension_loaded('exif'), false];
foreach (['uploads/full', 'uploads/thumbs'] as $d) {
    $chemin = __DIR__ . '/' . $d;
    if (!is_dir($chemin)) {
        @mkdir($chemin, 0755, true);
    }
    $verifs[] = ["Dossier $d inscriptible", is_dir($chemin) && is_writable($chemin), true];
}
$verifs[] = ['Taille max. d\'un envoi : ' . ini_get('upload_max_filesize') . ' (POST : ' . ini_get('post_max_size') . ')', true, false];
$bloquant = array_filter($verifs, fn($v) => $v[2] && !$v[1]);

// ---------------------------------------------------------------- État courant
$pdo = null;
$erreurConnexion = null;
if (is_file($fichierConfig)) {
    require_once $fichierConfig;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        $erreurConnexion = $e->getMessage();
    }
}

function compter_admins(?PDO $pdo): int
{
    if (!$pdo) {
        return 0;
    }
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
            return 0; // table pas encore créée
        }
        throw $e;
    }
}

// Déjà installé : plus rien à faire ici
if ($pdo && compter_admins($pdo) > 0) {
    $etape = 'termine';
    unset($_SESSION['pc_install']);
} else {
    $fichierCle = __DIR__ . '/install_key.php';
    $cle = is_file($fichierCle) ? require $fichierCle : null;
    if (!is_string($cle) || !preg_match('/\A[a-f0-9]{64}\z/', $cle)) {
        http_response_code(503);
        $etape = 'cle_absente';
    } else {
        $empreinte = hash('sha256', $cle);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'autoriser') {
            csrf_verifier();
            $saisie = $_POST['cle'] ?? null;
            if (is_string($saisie) && hash_equals($cle, trim($saisie))) {
                session_regenerate_id(true);
                $_SESSION['pc_install'] = $empreinte;
                rediriger('install.php');
            }
            $erreurs[] = 'Clé d’installation invalide.';
        }
        if (!hash_equals($empreinte, $_SESSION['pc_install'] ?? '')) {
            http_response_code(403);
            $etape = 'autoriser';
        }
    }
}

// ---------------------------------------------------------------- Traitement des formulaires
if (!isset($etape) && $_SERVER['REQUEST_METHOD'] === 'POST' && !$bloquant) {
    csrf_verifier();

    // Étape 2 : identifiants MySQL
    if (($_POST['action'] ?? '') === 'config' && !is_file($fichierConfig)) {
        $cfg = [
            'DB_HOST' => trim($_POST['host'] ?? '') ?: 'localhost',
            'DB_NAME' => trim($_POST['nom'] ?? ''),
            'DB_USER' => trim($_POST['user'] ?? ''),
            'DB_PASS' => (string) ($_POST['pass'] ?? ''),
        ];
        if (!preg_match('/^[A-Za-z0-9_$-]+$/', $cfg['DB_NAME'])) {
            $erreurs[] = 'Nom de base invalide.';
        } else {
            try {
                // Connexion sans base pour pouvoir la créer si besoin (souvent interdit en mutualisé)
                $test = new PDO('mysql:host=' . $cfg['DB_HOST'] . ';charset=utf8mb4', $cfg['DB_USER'], $cfg['DB_PASS'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $st = $test->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
                $st->execute([$cfg['DB_NAME']]);
                if (!$st->fetchColumn()) {
                    try {
                        $test->exec('CREATE DATABASE `' . $cfg['DB_NAME'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                        $messages[] = 'Base « ' . $cfg['DB_NAME'] . ' » créée.';
                    } catch (PDOException $e) {
                        throw new RuntimeException('La base « ' . $cfg['DB_NAME'] . ' » n\'existe pas et ce compte ne peut pas la créer. '
                            . 'Crée-la depuis le panneau de ton hébergeur, puis réessaie.');
                    }
                }
                $test->exec('USE `' . $cfg['DB_NAME'] . '`');

                $contenu = "<?php\n// Généré par install.php le " . date('Y-m-d H:i') . " — NE PAS VERSIONNER\n";
                foreach ($cfg as $k => $v) {
                    $contenu .= "define('$k', " . var_export($v, true) . ");\n";
                }
                $fichierTemp = __DIR__ . '/pc_config_' . bin2hex(random_bytes(16)) . '.php';
                $temporaire = @fopen($fichierTemp, 'x');
                $publie = false;
                if ($temporaire !== false) {
                    try {
                        $publie = chmod($fichierTemp, 0640)
                            && fwrite($temporaire, $contenu) === strlen($contenu)
                            && fflush($temporaire)
                            && @link($fichierTemp, $fichierConfig);
                    } finally {
                        fclose($temporaire);
                        unlink($fichierTemp);
                    }
                }
                if ($publie || is_file($fichierConfig)) {
                    rediriger('install.php');
                }
                $configAAfficher = $contenu;
            } catch (PDOException $e) {
                $erreurs[] = 'Connexion MySQL impossible : ' . $e->getMessage();
            } catch (RuntimeException $e) {
                $erreurs[] = $e->getMessage();
            }
        }
    }

    // Étape 4 : compte admin (uniquement tant qu'aucun n'existe)
    if (($_POST['action'] ?? '') === 'admin' && $pdo && compter_admins($pdo) === 0 && !migrations_en_attente($pdo)) {
        $nom = trim($_POST['nom_utilisateur'] ?? '');
        $mdp = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[\p{L}\p{N}._-]{2,50}$/u', $nom)) {
            $erreurs[] = 'Identifiant : 2 à 50 caractères (lettres, chiffres, . _ -).';
        } elseif (mb_strlen($mdp) < 10) {
            $erreurs[] = 'Le mot de passe doit faire au moins 10 caractères.';
        } elseif ($mdp !== ($_POST['password2'] ?? '')) {
            $erreurs[] = 'Les deux mots de passe diffèrent.';
        } else {
            $verrou = 'pc_install_' . substr(hash('sha256', DB_NAME), 0, 40);
            $st = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $st->execute([$verrou]);
            if (!(int) $st->fetchColumn()) {
                http_response_code(503);
                exit('Une installation est en cours. Réessaie dans quelques secondes.');
            }
            try {
                $id = null;
                if (compter_admins($pdo) === 0) {
                    $pdo->prepare('INSERT INTO utilisateurs (nom_utilisateur, password_hash, derniere_connexion) VALUES (?, ?, NOW())')
                        ->execute([$nom, password_hash($mdp, PASSWORD_DEFAULT)]);
                    $id = (int) $pdo->lastInsertId();
                }
            } finally {
                $st = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $st->execute([$verrou]);
            }
            if ($id === null) {
                rediriger('install.php');
            }
            session_regenerate_id(true);
            $_SESSION = ['pc_admin' => $id, 'pc_user' => $nom, 'pc_last' => time()];
            flash('Installation terminée. Bienvenue !');
            rediriger('admin/index.php');
        }
    }
}

// ---------------------------------------------------------------- Étape à afficher
$journalMigrations = [];
if (!isset($etape)) {
    if ($bloquant) {
        $etape = 'verifs';
    } elseif (!is_file($fichierConfig)) {
        $etape = 'config';
    } elseif (!$pdo) {
        $etape = 'config_cassee';
    } else {
        // Étape 3 : migrations, appliquées automatiquement pendant l'installation
        try {
            migrer($pdo, function ($m) use (&$journalMigrations) { $journalMigrations[] = $m; });
            $etape = 'admin';
        } catch (Throwable $e) {
            $erreurs[] = $e->getMessage();
            $etape = 'migrations';
        }
    }
}

$numeros = ['cle_absente' => 0, 'autoriser' => 0, 'verifs' => 1, 'config' => 2, 'config_cassee' => 2, 'migrations' => 3, 'admin' => 4, 'termine' => 5];
?>
<!DOCTYPE html>
<html lang="fr"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Installation · Cartes postales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-card installeur">
    <h1><i class="fa-solid fa-envelope-open-text"></i> Installation</h1>

    <ol class="etapes">
        <?php foreach (['Vérifications', 'Base de données', 'Tables', 'Compte admin'] as $i => $lib): $n = $i + 1; ?>
            <li class="<?= $n < $numeros[$etape] ? 'fait' : ($n === $numeros[$etape] ? 'courant' : '') ?>"><?= h($lib) ?></li>
        <?php endforeach; ?>
    </ol>

    <?php foreach ($messages as $m): ?><div class="flash"><?= h($m) ?></div><?php endforeach; ?>
    <?php foreach ($erreurs as $m): ?><div class="flash erreur"><?= h($m) ?></div><?php endforeach; ?>

    <?php if ($etape === 'cle_absente'): ?>
        <p>L’installation web est verrouillée. Dépose un fichier <code>install_key.php</code> à la racine selon la documentation.</p>
    <?php elseif ($etape === 'autoriser'): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="autoriser">
            <label>Clé d’installation <input type="password" name="cle" required autocomplete="off"></label>
            <button type="submit" class="bouton">Ouvrir l’installation</button>
        </form>
    <?php elseif ($etape === 'termine'): ?>
        <p><i class="fa-solid fa-circle-check" style="color:var(--ok)"></i> L'application est déjà installée. Cette page est désormais verrouillée.</p>
        <p class="aide">Les mises à jour du schéma se font depuis l'admin, menu « Migrations ».</p>
        <a class="bouton" href="admin/index.php">Aller à l'admin</a>

    <?php elseif ($etape === 'verifs'): ?>
        <p>Corrige les points en rouge puis recharge la page.</p>
    <?php endif; ?>

    <?php if (in_array($etape, ['verifs', 'config'], true)): ?>
        <ul class="verifs">
            <?php foreach ($verifs as [$lib, $ok, $requis]): ?>
                <li class="<?= $ok ? 'ok' : ($requis ? 'ko' : 'avert') ?>">
                    <i class="fa-solid <?= $ok ? 'fa-check' : ($requis ? 'fa-xmark' : 'fa-triangle-exclamation') ?>"></i> <?= h($lib) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($bloquant && array_filter($bloquant, fn($v) => str_starts_with($v[0], 'Dossier'))): ?>
            <p class="aide">Avec ton client FTP, donne les droits d'écriture (755 ou 775 selon l'hébergeur) aux dossiers <code>uploads/full</code> et <code>uploads/thumbs</code>.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($etape === 'config'): ?>
        <?php if ($configAAfficher !== null): ?>
            <div class="flash erreur">Connexion réussie, mais impossible d'écrire <code>db_config.php</code> (droits du dossier).
                Crée ce fichier à la racine de l'application avec ce contenu (via FTP), puis recharge la page :</div>
            <textarea class="config-a-copier" rows="8" readonly onclick="this.select()"><?= h($configAAfficher) ?></textarea>
            <a class="bouton" href="install.php">J'ai déposé le fichier</a>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="config">
            <p class="aide">Identifiants fournis par ton hébergeur (souvent dans le panneau « Bases de données »).</p>
            <label>Serveur MySQL <input type="text" name="host" value="<?= h($_POST['host'] ?? 'localhost') ?>" required></label>
            <label>Nom de la base <input type="text" name="nom" value="<?= h($_POST['nom'] ?? 'postcards') ?>" required></label>
            <label>Utilisateur <input type="text" name="user" value="<?= h($_POST['user'] ?? '') ?>" required autocomplete="off"></label>
            <label>Mot de passe <input type="password" name="pass" autocomplete="new-password"></label>
            <button type="submit" class="bouton">Tester et enregistrer</button>
        </form>
        <?php endif; ?>

    <?php elseif ($etape === 'config_cassee'): ?>
        <div class="flash erreur">Un fichier <code>db_config.php</code> existe mais la connexion échoue :<br><?= h($erreurConnexion) ?></div>
        <p class="aide">Par sécurité, l'installeur ne modifie jamais un <code>db_config.php</code> existant. Corrige-le via FTP,
            ou supprime-le pour recommencer cette étape, puis recharge la page.</p>
        <a class="bouton" href="install.php">Recharger</a>

    <?php elseif ($etape === 'migrations'): ?>
        <p>La création des tables a échoué. Corrige le problème indiqué puis recharge la page : les migrations déjà passées ne seront pas rejouées.</p>
        <a class="bouton" href="install.php">Réessayer</a>

    <?php elseif ($etape === 'admin'): ?>
        <?php if ($journalMigrations): ?>
            <div class="flash">Tables créées (<?= h(implode(', ', array_map('trim', $journalMigrations))) ?>).</div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin">
            <p class="aide">Dernière étape : le compte qui donnera accès à l'admin.</p>
            <label>Identifiant <input type="text" name="nom_utilisateur" value="<?= h($_POST['nom_utilisateur'] ?? '') ?>" required autocomplete="username"></label>
            <label>Mot de passe <small>(10 caractères min.)</small> <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <label>Confirmation <input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
            <button type="submit" class="bouton">Créer le compte et terminer</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
