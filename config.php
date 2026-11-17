<?php
// Point d'entrée commun : session, constantes, fonctions partagées.
// Chaque page fait require_once de ce fichier (via includes/admin.php pour l'admin).

if (!is_file(__DIR__ . '/db_config.php')) {
    // Pas encore installé : direction l'installeur web
    // Relatif : les pages sont à la racine ou dans un sous-dossier (admin/, api/)
    if (PHP_SAPI !== 'cli') {
        $dansSousDossier = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath(__DIR__);
        header('Location: ' . ($dansSousDossier ? '../' : '') . 'install.php');
    }
    exit("Application non installée : ouvrir install.php dans le navigateur.\n");
}

require_once __DIR__ . '/includes/session.php';

define('APP_ROOT', __DIR__);
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('PHOTO_MAX', 1600);   // plus grand côté de l'image « full », en px
define('THUMB_MAX', 480);    // plus grand côté de la miniature

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/pays.php';
require_once __DIR__ . '/includes/subdivisions.php';
