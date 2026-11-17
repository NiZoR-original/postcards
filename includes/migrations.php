<?php
// Système de migrations du schéma.
//
// Fichiers : sql/migrations/NNNN_description.sql (ou .php), appliqués une seule fois,
// dans l'ordre des noms ; chaque migration appliquée est enregistrée dans la table `migrations`.
//  - .sql : requêtes séparées par « ; » (commentaires -- et /* */ autorisés).
//  - .php : le fichier reçoit $pdo et fait ce qu'il veut (données, fichiers…) ; il lève une exception en cas d'échec.
// Ne jamais modifier une migration déjà appliquée quelque part : en écrire une nouvelle.
//
// Attention : en MySQL, les ordres DDL (CREATE/ALTER/DROP) valident implicitement la transaction.
// Une migration .sql qui échoue au milieu peut donc rester à moitié appliquée : préférer une
// modification par migration et des instructions idempotentes quand c'est possible.

const DOSSIER_MIGRATIONS = __DIR__ . '/../sql/migrations';

function migrations_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        fichier VARCHAR(190) NOT NULL PRIMARY KEY,
        appliquee_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        duree_ms INT UNSIGNED NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Fichiers de migration disponibles, triés. */
function migrations_disponibles(): array
{
    $f = array_map('basename', glob(DOSSIER_MIGRATIONS . '/[0-9][0-9][0-9][0-9]_*.{sql,php}', GLOB_BRACE) ?: []);
    sort($f, SORT_STRING);
    return $f;
}

/** [fichier => date] des migrations déjà appliquées. */
function migrations_appliquees(PDO $pdo): array
{
    migrations_table($pdo);
    return $pdo->query('SELECT fichier, appliquee_le FROM migrations ORDER BY fichier')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function migrations_en_attente(PDO $pdo): array
{
    return array_values(array_diff(migrations_disponibles(), array_keys(migrations_appliquees($pdo))));
}

/**
 * Applique toutes les migrations en attente, dans l'ordre. S'arrête à la première erreur.
 * @param callable(string $message):void $log
 * @return int nombre de migrations appliquées
 */
function migrer(PDO $pdo, callable $log): int
{
    migrations_table($pdo);
    // Verrou : deux exécutions simultanées ne doivent pas appliquer la même migration
    if (!$pdo->query("SELECT GET_LOCK('postcards_migrations', 10)")->fetchColumn()) {
        throw new RuntimeException('Une autre migration est en cours.');
    }
    try {
        $n = 0;
        foreach (migrations_en_attente($pdo) as $fichier) {
            $debut = hrtime(true);
            $log("→ $fichier");
            try {
                appliquer_migration($pdo, DOSSIER_MIGRATIONS . '/' . $fichier);
            } catch (Throwable $e) {
                throw new RuntimeException("Échec de $fichier : " . $e->getMessage(), 0, $e);
            }
            $pdo->prepare('INSERT INTO migrations (fichier, duree_ms) VALUES (?, ?)')
                ->execute([$fichier, (int) ((hrtime(true) - $debut) / 1e6)]);
            $n++;
        }
        return $n;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('postcards_migrations')");
    }
}

function appliquer_migration(PDO $pdo, string $chemin): void
{
    if (str_ends_with($chemin, '.php')) {
        (static function (PDO $pdo) use ($chemin) {
            require $chemin;
        })($pdo);
        return;
    }
    foreach (decouper_sql(file_get_contents($chemin)) as $requete) {
        $pdo->exec($requete);
    }
}

/**
 * Découpe un script SQL en requêtes sur les « ; » hors chaînes, identifiants et commentaires.
 * @return string[]
 */
function decouper_sql(string $sql): array
{
    $requetes = [];
    $courante = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $suiv = $sql[$i + 1] ?? '';

        // Commentaire de ligne : -- (suivi d'un blanc) ou #
        if (($c === '-' && $suiv === '-' && ctype_space($sql[$i + 2] ?? ' ')) || $c === '#') {
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $len : $fin;
            $courante .= "\n";
            continue;
        }
        // Commentaire de bloc
        if ($c === '/' && $suiv === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            $i = $fin === false ? $len : $fin + 1;
            $courante .= ' ';
            continue;
        }
        // Chaîne ou identifiant entre quotes : recopié tel quel
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $len) {
                if ($sql[$j] === '\\' && $c !== '`') {
                    $j += 2;
                    continue;
                }
                if ($sql[$j] === $c) {
                    if (($sql[$j + 1] ?? '') === $c) { // quote doublée
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $j++;
            }
            $courante .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === ';') {
            if (trim($courante) !== '') {
                $requetes[] = trim($courante);
            }
            $courante = '';
            continue;
        }
        $courante .= $c;
    }
    if (trim($courante) !== '') {
        $requetes[] = trim($courante);
    }
    return $requetes;
}
