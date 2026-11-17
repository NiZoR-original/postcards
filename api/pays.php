<?php
// JSON des cartes publiques d'un pays ou d'un État / province, pour le panneau des cartes.
// GET code=FR | subdivision=US-CA   [&sens=recue|envoyee] [&lang=fr|en]
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');
$lang = langue_courante();

$lien = null;
if (isset($_GET['subdivision'])) {
    $code = strtoupper((string) $_GET['subdivision']);
    if (!isset(SUBDIVISIONS[$code])) {
        http_response_code(400);
        echo json_encode(['erreur' => 'subdivision invalide']);
        exit;
    }
    $filtre = 'c.subdivision = ?';
    $nom = nom_subdivision($code, $lang);
    $drapeau = drapeau(substr($code, 0, 2));
} else {
    $code = strtoupper((string) ($_GET['code'] ?? ''));
    if (!pays_valide($code)) {
        http_response_code(400);
        echo json_encode(['erreur' => 'code pays invalide']);
        exit;
    }
    $filtre = 'c.pays_code = ?';
    $nom = nom_pays($code, $lang);
    $drapeau = drapeau($code);
    if (in_array($code, PAYS_AVEC_SUBDIVISIONS, true) || in_array($code, TERRITOIRES_US, true)) {
        $lien = ['url' => 'usa-canada.php', 'texte' => t('voir_regions')];
    }
}
$sens = in_array($_GET['sens'] ?? '', ['recue', 'envoyee'], true) ? $_GET['sens'] : null;

// Première photo publique de chaque carte, en sous-requête
$sql = "SELECT c.id, c.sens, c.reference, c.titre, c.date_envoi, c.date_reception,
               (SELECT p.id FROM photos p WHERE p.carte_id = c.id AND p.publique = 1 ORDER BY p.ordre, p.id LIMIT 1) AS photo
        FROM cartes c
        WHERE c.publique = 1 AND $filtre" . ($sens ? ' AND c.sens = ?' : '') . "
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
    'nom' => $nom,
    'drapeau' => $drapeau,
    'lien' => $lien,
    'cartes' => $cartes,
], JSON_UNESCAPED_UNICODE);
