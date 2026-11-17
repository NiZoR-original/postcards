-- Migration 0001 : schéma initial (cartes, swaps, photos, utilisateurs).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    derniere_connexion DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une carte reçue ou envoyée.
-- pays_code = pays d'origine (reçue) ou de destination (envoyée), ISO 3166-1 alpha-2.
-- « En route » = envoyée sans date_reception.
CREATE TABLE IF NOT EXISTS cartes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sens ENUM('recue','envoyee') NOT NULL,
    plateforme ENUM('postcrossing','cardzilla','swap','autre') NOT NULL DEFAULT 'postcrossing',
    reference VARCHAR(50) NULL,           -- ex. FR-1234567 ; NULL si aucune
    lien VARCHAR(255) NULL,               -- URL explicite ; sinon déduite de la référence (Postcrossing)
    pays_code CHAR(2) NOT NULL,
    ville VARCHAR(120) NULL,
    correspondant VARCHAR(120) NULL,      -- pseudo de l'expéditeur / du destinataire
    date_envoi DATE NULL,
    date_reception DATE NULL,
    distance_km INT UNSIGNED NULL,
    titre VARCHAR(255) NULL,              -- sujet de l'illustration
    description TEXT NULL,                -- texte public
    notes TEXT NULL,                      -- notes privées (jamais affichées publiquement)
    publique TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plateforme_ref (plateforme, reference),
    KEY idx_pays (pays_code, sens, publique)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue des cartes proposées à l'échange (Discord Postcrossing).
-- Publiquement : seules « disponible » et « reservee » sont listées.
CREATE TABLE IF NOT EXISTS swaps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT NULL,
    theme VARCHAR(120) NULL,
    etat ENUM('disponible','reservee','echangee') NOT NULL DEFAULT 'disponible',
    reserve_pour VARCHAR(120) NULL,       -- pseudo Discord, privé
    notes TEXT NULL,                      -- privé
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_etat (etat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos : appartiennent soit à une carte, soit à un swap (exactement un des deux).
-- Les fichiers sont dans uploads/full et uploads/thumbs (nom = fichier), servis par photo.php.
CREATE TABLE IF NOT EXISTS photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carte_id INT UNSIGNED NULL,
    swap_id INT UNSIGNED NULL,
    fichier VARCHAR(64) NOT NULL UNIQUE,
    largeur INT UNSIGNED NOT NULL,
    hauteur INT UNSIGNED NOT NULL,
    legende VARCHAR(255) NULL,
    publique TINYINT(1) NOT NULL DEFAULT 1,
    ordre INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_photos_carte FOREIGN KEY (carte_id) REFERENCES cartes(id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_swap FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE,
    KEY idx_carte (carte_id, ordre),
    KEY idx_swap (swap_id, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
