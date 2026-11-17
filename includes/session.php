<?php
// Démarrage de session commun (site, admin, installeur web).
if (session_status() === PHP_SESSION_NONE) {
    // Nom de session distinct : d'autres applis peuvent partager le domaine
    // et poser des cookies de session sur '/'.
    session_name('POSTCARDS_SESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

defined('ADMIN_SESSION_TIMEOUT') || define('ADMIN_SESSION_TIMEOUT', 4 * 3600);
