<?php
// Crée (ou réinitialise le mot de passe de) un compte admin.
// Usage : php scripts/creer_utilisateur.php <identifiant>   (le mot de passe est demandé)
if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../includes/db.php';

$nom = $argv[1] ?? null;
if (!$nom) {
    fwrite(STDERR, "Usage : php scripts/creer_utilisateur.php <identifiant>\n");
    exit(1);
}

echo 'Mot de passe : ';
system('stty -echo');
$mdp = trim((string) fgets(STDIN));
system('stty echo');
echo "\n";
if (strlen($mdp) < 10) {
    fwrite(STDERR, "Mot de passe trop court (10 caractères minimum).\n");
    exit(1);
}

db()->prepare('INSERT INTO utilisateurs (nom_utilisateur, password_hash) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
    ->execute([$nom, password_hash($mdp, PASSWORD_DEFAULT)]);
echo "Compte « $nom » enregistré.\n";
