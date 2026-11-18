<?php
// Enregistrement anonyme des visites, appelé par assets/stats.js (navigator.sendBeacon).
// POST v=<jeton 32 hex> &tz=<fuseau IANA> &p=<pages vues en plus, 0|1> &d=<secondes visibles en plus>
// Aucune donnée d'identification : ni IP, ni navigateur, ni cookie. Le pays vient du fuseau horaire.
require_once __DIR__ . '/../config.php';

http_response_code(204);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || est_admin()
    || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1' || ($_SERVER['HTTP_DNT'] ?? '') === '1') {
    exit;
}
$jeton = (string) ($_POST['v'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $jeton)) {
    exit;
}
$pages = min(1, max(0, (int) ($_POST['p'] ?? 0)));
$duree = min(1800, max(0, (int) ($_POST['d'] ?? 0))); // au plus 30 min par envoi : écarte les valeurs aberrantes
if ($pages === 0 && $duree === 0) {
    exit;
}

try {
    $pdo = db();
    purger_visites($pdo);
    $pdo->prepare('INSERT INTO visites (jeton, debut, fin, duree, pages, pays) VALUES (?, NOW(), NOW(), ?, ?, ?)
                   ON DUPLICATE KEY UPDATE fin = NOW(), duree = LEAST(duree + VALUES(duree), 86400), pages = LEAST(pages + VALUES(pages), 10000)')
        ->execute([$jeton, $duree, $pages, pays_du_fuseau((string) ($_POST['tz'] ?? ''))]);

} catch (PDOException $e) {
    if ((int) ($e->errorInfo[1] ?? 0) !== 1146) {
        error_log('Statistiques Postcards : ' . $e->getMessage());
    }
}
