-- Migration : paramètres du site modifiables depuis l'admin (Admin > Paramètres), en clé / valeur.
-- Clés utilisées : profil_postcrossing, profil_cardzilla, profil_discord, pseudo_discord, email_contact (profils et contact, liens de la barre de menu publique).
CREATE TABLE parametres (
    cle VARCHAR(64) NOT NULL PRIMARY KEY,
    valeur TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
