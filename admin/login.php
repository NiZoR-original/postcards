<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/limite_connexion.php';

if (est_admin()) {
    rediriger('index.php');
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    try {
        $attente = reserver_tentative_connexion($_SERVER['REMOTE_ADDR'] ?? '');
    } catch (Throwable $e) {
        error_log('Connexion Postcards : ' . $e->getMessage());
        http_response_code(503);
        $erreur = 'Connexion temporairement indisponible.';
    }

    if (isset($attente) && $attente > 0) {
        http_response_code(429);
        header('Retry-After: ' . $attente);
        $erreur = 'Trop de tentatives de connexion. Réessaie dans quelques minutes.';
    }

    if ($erreur === '') {
        $nom = $_POST['nom_utilisateur'] ?? '';
        $motDePasse = $_POST['password'] ?? '';
        $nom = is_string($nom) ? trim($nom) : '';
        $motDePasse = is_string($motDePasse) ? $motDePasse : '';

        $st = db()->prepare('SELECT id, nom_utilisateur, password_hash FROM utilisateurs WHERE nom_utilisateur = ?');
        $st->execute([$nom]);
        $u = $st->fetch();

        if ($u && password_verify($motDePasse, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION = ['pc_admin' => (int) $u['id'], 'pc_user' => $u['nom_utilisateur'], 'pc_last' => time()];
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $u['id']]);
            }
            db()->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?')->execute([$u['id']]);
            rediriger('index.php');
        }
        $erreur = 'Identifiants invalides.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr"<?= attr_theme() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#151b28">
    <meta name="robots" content="noindex">
    <title>Connexion · Admin cartes postales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= h(url_asset('assets/style.css', '../')) ?>">
    <link rel="stylesheet" href="<?= h(url_asset('assets/admin.css', '../')) ?>">
    <link rel="icon" href="../assets/icones/atlas-192.png" type="image/png">
</head>
<body class="login-page admin-login">
    <form method="post" class="login-card">
        <?= csrf_field() ?>
        <h1><i class="fa-solid fa-envelope-open-text"></i> Cartes postales</h1>
        <?php if ($erreur): ?><div class="flash erreur"><?= h($erreur) ?></div><?php endif; ?>
        <label>Identifiant <input type="text" name="nom_utilisateur" required autocomplete="username" autocapitalize="none" spellcheck="false"></label>
        <label>Mot de passe <input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="bouton">Se connecter</button>
        <p><a href="../index.php">&larr; Site public</a></p>
    </form>
    <script src="../assets/theme.js"></script>
</body>
</html>
