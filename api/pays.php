<?php
// JSON des cartes publiques d'un pays, pour le panneau de la carte du monde.
// GET code=FR [&sens=recue|envoyee] [&lang=fr|en]
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');

$code = strtoupper((string) ($_GET['code'] ?? ''));
if (!pays_valide($code)) {
    http_response_code(400);
    echo json_encode(['erreur' => 'code pays invalide']);
    exit;
}
$sens = in_array($_GET['sens'] ?? '', ['recue', 'envoyee'], true) ? $_GET['sens'] : null;

// Première photo publique de chaque carte, en sous-requête
$sql = "SELECT c.id, c.sens, c.reference, c.titre, c.date_envoi, c.date_reception,
               (SELECT p.id FROM photos p WHERE p.carte_id = c.id AND p.publique = 1 ORDER BY p.ordre, p.id LIMIT 1) AS photo
        FROM cartes c
        WHERE c.publique = 1 AND c.pays_code = ?" . ($sens ? ' AND c.sens = ?' : '') . "
        ORDER BY COALESCE(c.date_reception, c.date_envoi) DESC, c.id DESC";
$st = db()->prepare($sql);
$st->execute($sens ? [$code, $sens] : [$code]);

$cartes = array_map(fn($c) => [
    'id' => (int) $c['id'],
    'sens' => $c['sens'],
    'reference' => $c['reference'],
    'titre' => $c['titre'],
    'photo' => $c['photo'] ? (int) $c['photo'] : null,
    'en_route' => $c['sens'] === 'envoyee' && !$c['date_reception'],
], $st->fetchAll());

echo json_encode([
    'code' => $code,
    'nom' => nom_pays($code, langue_courante()),
    'drapeau' => drapeau($code),
    'cartes' => $cartes,
], JSON_UNESCAPED_UNICODE);
