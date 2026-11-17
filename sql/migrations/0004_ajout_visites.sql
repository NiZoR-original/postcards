-- Migration : statistiques de visite anonymes (api/visite.php, Admin > Statistiques).
-- Une ligne par visite : jeton aléatoire tiré par le navigateur pour la durée de l'onglet (sessionStorage),
-- sans lien avec l'adresse IP, le navigateur ou une personne. Pays déduit du fuseau horaire, jamais de l'IP.
-- Conservation : 25 mois (purge automatique), conformément aux recommandations de la CNIL.
CREATE TABLE visites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jeton CHAR(32) NOT NULL,
    debut DATETIME NOT NULL,
    fin DATETIME NOT NULL,
    duree INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'secondes de page visible',
    pages SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    pays CHAR(2) NULL,
    UNIQUE KEY uk_jeton (jeton),
    KEY idx_debut (debut),
    KEY idx_pays (pays, debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
