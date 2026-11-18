-- Liste de mots clés séparés par des virgules (facultative).
ALTER TABLE cartes ADD COLUMN mots_cles VARCHAR(1000) NULL AFTER description;
