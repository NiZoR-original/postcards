<?php
// Installation / réparation de l'application. Relançable sans risque :
// - crée ou met à jour db_config.php (identifiants MySQL)
// - crée la base si elle n'existe pas, puis applique les migrations en attente (sql/migrations/)
// - crée le compte admin (ou réinitialise son mot de passe)
// - vérifie les dossiers d'upload
//
// Usage : php scripts/installer.php
if (PHP_SAPI !== 'cli') {
    exit;
}

$racine = dirname(__DIR__);
$fichierConfig = $racine . '/db_config.php';

function demander(string $question, ?string $defaut = null, bool $cache = false): string
{
    echo $question . ($defaut !== null && $defaut !== '' ? " [$defaut]" : '') . ' : ';
    if ($cache) {
        system('stty -echo');
    }
    $r = trim((string) fgets(STDIN));
    if ($cache) {
        system('stty echo');
        echo "\n";
    }
    return $r === '' ? (string) $defaut : $r;
}

function oui(string $question, bool $defaut = true): bool
{
    $r = strtolower(demander($question . ($defaut ? ' (O/n)' : ' (o/N)')));
    return $r === '' ? $defaut : in_array($r, ['o', 'oui', 'y', 'yes'], true);
}

function etape(string $titre): void
{
    echo "\n\033[1m== $titre\033[0m\n";
}

function ok(string $m): void { echo "  \033[32m✔\033[0m $m\n"; }
function attention(string $m): void { echo "  \033[33m!\033[0m $m\n"; }
function echec(string $m): never { fwrite(STDERR, "  \033[31m✘\033[0m $m\n"); exit(1); }

foreach (['pdo_mysql', 'intl', 'gd', 'fileinfo', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        echec("Extension PHP manquante : $ext");
    }
}

// ---------------------------------------------------------------- 1. Configuration
etape('Connexion MySQL');
$cfg = ['DB_HOST' => '127.0.0.1', 'DB_NAME' => 'postcards', 'DB_USER' => get_current_user(), 'DB_PASS' => ''];
$existe = is_file($fichierConfig);
if ($existe) {
    require $fichierConfig;
    foreach (array_keys($cfg) as $k) {
        if (defined($k)) {
            $cfg[$k] = constant($k);
        }
    }
    ok('db_config.php existant trouvé (base « ' . $cfg['DB_NAME'] . ' »)');
}

$reconfigurer = !$existe || oui('Modifier les identifiants ?', false);
while (true) {
    if ($reconfigurer) {
        $cfg['DB_HOST'] = demander('Hôte MySQL', $cfg['DB_HOST']);
        $cfg['DB_NAME'] = demander('Nom de la base', $cfg['DB_NAME']);
        $cfg['DB_USER'] = demander('Utilisateur MySQL', $cfg['DB_USER']);
        $mdp = demander('Mot de passe MySQL' . ($cfg['DB_PASS'] !== '' ? ' (Entrée = inchangé)' : ''), null, true);
        if ($mdp !== '') {
            $cfg['DB_PASS'] = $mdp;
        }
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cfg['DB_NAME'])) {
        attention('Nom de base invalide (lettres, chiffres, _ uniquement).');
        $reconfigurer = true;
        continue;
    }
    try {
        $pdo = new PDO("mysql:host={$cfg['DB_HOST']};charset=utf8mb4", $cfg['DB_USER'], $cfg['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        ok('Connexion réussie (MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn() . ')');
        break;
    } catch (PDOException $e) {
        attention('Connexion impossible : ' . $e->getMessage());
        if (!oui('Réessayer ?')) {
            exit(1);
        }
        $reconfigurer = true;
    }
}

if ($reconfigurer) {
    $contenu = "<?php\n// Généré par scripts/installer.php — NE PAS VERSIONNER\n";
    foreach ($cfg as $k => $v) {
        $contenu .= "define('$k', " . var_export($v, true) . ");\n";
    }
    if (file_put_contents($fichierConfig, $contenu) === false) {
        echec("Écriture impossible de $fichierConfig");
    }
    chmod($fichierConfig, 0640);
    ok('db_config.php écrit');
}

// ---------------------------------------------------------------- 2. Base et tables
etape('Base de données');
$base = $cfg['DB_NAME'];
$baseExiste = (bool) $pdo->query('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($base))->fetchColumn();
if ($baseExiste) {
    ok("La base « $base » existe déjà");
} else {
    try {
        $pdo->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        ok("Base « $base » créée");
    } catch (PDOException $e) {
        echec("Impossible de créer la base « $base » : " . $e->getMessage()
            . "\n    Crée-la avec un compte qui en a le droit, puis relance le script.");
    }
}
$pdo->exec("USE `$base`");

require_once $racine . '/includes/migrations.php';
try {
    $n = migrer($pdo, fn($m) => print("    $m\n"));
    ok($n ? "$n migration(s) appliquée(s)" : 'Schéma à jour');
} catch (Throwable $e) {
    echec($e->getMessage());
}

// ---------------------------------------------------------------- 3. Compte admin
etape('Compte administrateur');
$comptes = $pdo->query('SELECT nom_utilisateur FROM utilisateurs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
if ($comptes) {
    ok('Compte(s) existant(s) : ' . implode(', ', $comptes));
}
if (!$comptes || oui('Créer un compte ou changer un mot de passe ?', false)) {
    $nom = demander('Identifiant', $comptes[0] ?? get_current_user());
    while (true) {
        $mdp = demander('Mot de passe (10 caractères min.)', null, true);
        if (mb_strlen($mdp) < 10) {
            attention('Trop court.');
            continue;
        }
        if ($mdp !== demander('Confirmation', null, true)) {
            attention('Les deux saisies diffèrent.');
            continue;
        }
        break;
    }
    $pdo->prepare('INSERT INTO utilisateurs (nom_utilisateur, password_hash) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
        ->execute([$nom, password_hash($mdp, PASSWORD_DEFAULT)]);
    ok("Compte « $nom » enregistré");
}

// ---------------------------------------------------------------- 4. Dossiers d'upload
etape('Dossiers des photos');
$utilisateurWeb = 'apache';
foreach (['uploads/full', 'uploads/thumbs'] as $d) {
    $chemin = "$racine/$d";
    if (!is_dir($chemin) && !mkdir($chemin, 0755, true)) {
        echec("Création impossible de $d");
    }
    // ACL pour que PHP-FPM (utilisateur apache) puisse écrire
    $acl = shell_exec('getfacl -p ' . escapeshellarg($chemin) . ' 2>/dev/null') ?? '';
    if (!str_contains($acl, "user:$utilisateurWeb:rwx")) {
        exec('setfacl -m u:' . $utilisateurWeb . ':rwx -d -m u:' . $utilisateurWeb . ':rwx -d -m u:' . get_current_user() . ':rwx '
            . escapeshellarg($chemin) . ' 2>&1', $out, $code);
        $code === 0 ? ok("$d : droits d'écriture donnés à $utilisateurWeb") : attention("$d : setfacl a échoué — " . implode(' ', $out));
    } else {
        ok("$d : $utilisateurWeb peut écrire");
    }
    // Contexte SELinux
    $ctx = trim((string) shell_exec('stat -c %C ' . escapeshellarg($chemin) . ' 2>/dev/null'));
    if ($ctx !== '' && !str_contains($ctx, 'httpd_sys_rw_content_t')) {
        attention("$d : contexte SELinux « $ctx ». Pour autoriser l'écriture :\n"
            . "    sudo semanage fcontext -a -t httpd_sys_rw_content_t '$racine/uploads(/.*)?' && sudo restorecon -Rv $racine/uploads");
    }
}

etape('Terminé');
echo "  Site public : /postcards/\n  Admin       : /postcards/admin/\n";
