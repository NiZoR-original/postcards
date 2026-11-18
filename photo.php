<?php
// Sert une photo (uploads/ est interdit d'accès direct).
// Visible publiquement si la photo est publique ET son parent aussi
// (carte publique, ou swap non échangé). Sinon réservé à l'admin connecté.
require_once __DIR__ . '/config.php';
header('Cache-Control: private, no-store');

$id = (int) ($_GET['id'] ?? 0);
$taille = ($_GET['s'] ?? '') === 'full' ? 'full' : 'thumbs';

$st = db()->prepare("SELECT p.fichier, p.publique,
        (p.carte_id IS NOT NULL AND c.publique = 1) OR (p.swap_id IS NOT NULL AND s.etat <> 'echangee') AS parent_public
    FROM photos p
    LEFT JOIN cartes c ON c.id = p.carte_id
    LEFT JOIN swaps s ON s.id = p.swap_id
    WHERE p.id = ?");
$st->execute([$id]);
$p = $st->fetch();

$public = $p && $p['publique'] && $p['parent_public'];
if (!$p || (!$public && !est_admin())) {
    http_response_code(404);
    exit;
}

$chemin = UPLOAD_DIR . '/' . $taille . '/' . basename($p['fichier']);
if (!is_file($chemin)) {
    http_response_code(404);
    exit;
}

$etag = '"' . $p['fichier'] . '-' . $taille . '"';
header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('ETag: ' . $etag);
// Les fichiers sont immuables (nom aléatoire) ; seul le droit d'accès peut changer.
if ($public) {
    header('Cache-Control: public, no-cache');
}
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag && $public) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($chemin));
readfile($chemin);
