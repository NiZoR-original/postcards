<?php
require_once __DIR__ . '/../config.php';

if (est_admin()) {
    rediriger('index.php');
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Freinage simple contre le bourrinage de mot de passe
    $_SESSION['echecs'] = $_SESSION['echecs'] ?? 0;
    if ($_SESSION['echecs'] >= 3) {
        sleep(min(10, $_SESSION['echecs']));
    }

    $st = db()->prepare('SELECT id, nom_utilisateur, password_hash FROM utilisateurs WHERE nom_utilisateur = ?');
    $st->execute([trim($_POST['nom_utilisateur'] ?? '')]);
    $u = $st->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION = ['pc_admin' => (int) $u['id'], 'pc_user' => $u['nom_utilisateur'], 'pc_last' => time()];
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
        }
        db()->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?')->execute([$u['id']]);
        rediriger('index.php');
    }
    $_SESSION['echecs']++;
    $erreur = 'Identifiants invalides.';
}
?>
<!DOCTYPE html>
<html lang="fr"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Connexion · Admin cartes postales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">
    <form method="post" class="login-card">
        <h1><i class="fa-solid fa-envelope-open-text"></i> Cartes postales</h1>
        <?php if ($erreur): ?><div class="flash erreur"><?= h($erreur) ?></div><?php endif; ?>
        <label>Identifiant <input type="text" name="nom_utilisateur" required autofocus autocomplete="username"></label>
        <label>Mot de passe <input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="bouton">Se connecter</button>
        <p><a href="../index.php">&larr; Site public</a></p>
    </form>
</body>
</html>
