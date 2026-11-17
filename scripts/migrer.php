<?php
// Gestion des migrations du schéma.
//
//   php scripts/migrer.php                 applique les migrations en attente
//   php scripts/migrer.php statut          liste les migrations et leur état
//   php scripts/migrer.php creer <nom> [php]   crée sql/migrations/NNNN_<nom>.sql (ou .php)
if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../includes/migrations.php';

$cmd = $argv[1] ?? 'appliquer';

if ($cmd === 'creer') {
    $nom = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $argv[2] ?? ''), '_'));
    if ($nom === '') {
        fwrite(STDERR, "Usage : php scripts/migrer.php creer <nom> [php]\n");
        exit(1);
    }
    $dispo = migrations_disponibles();
    $num = $dispo ? (int) substr(end($dispo), 0, 4) + 1 : 1;
    $ext = ($argv[3] ?? '') === 'php' ? 'php' : 'sql';
    $chemin = sprintf('%s/%04d_%s.%s', DOSSIER_MIGRATIONS, $num, $nom, $ext);
    $modele = $ext === 'php'
        ? "<?php\n// Migration : $nom\n// \$pdo est fourni. Lever une exception en cas d'échec.\n/** @var PDO \$pdo */\n\n"
        : "-- Migration : $nom\n-- Une modification par migration ; ne plus modifier ce fichier une fois appliqué.\n\n";
    file_put_contents($chemin, $modele);
    echo "Créée : sql/migrations/" . basename($chemin) . "\n";
    exit(0);
}

if (!is_file(__DIR__ . '/../db_config.php')) {
    fwrite(STDERR, "db_config.php absent : lancer d'abord php scripts/installer.php\n");
    exit(1);
}
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

if ($cmd === 'statut') {
    $faites = migrations_appliquees($pdo);
    foreach (migrations_disponibles() as $f) {
        echo isset($faites[$f]) ? "  \033[32m✔\033[0m $f  ({$faites[$f]})\n" : "  \033[33m…\033[0m $f  (en attente)\n";
    }
    foreach (array_diff(array_keys($faites), migrations_disponibles()) as $f) {
        echo "  \033[31m?\033[0m $f  (appliquée mais fichier absent)\n";
    }
    exit(0);
}

if ($cmd !== 'appliquer') {
    fwrite(STDERR, "Commande inconnue : $cmd (appliquer | statut | creer)\n");
    exit(1);
}

try {
    $n = migrer($pdo, fn($m) => print("  $m\n"));
    echo $n ? "$n migration(s) appliquée(s).\n" : "Base à jour.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\033[31m✘ " . $e->getMessage() . "\033[0m\n");
    exit(1);
}
