-- Migration : État (USA) ou province / territoire (Canada) d'une carte, pour la carte USA & Canada.
-- Code ISO 3166-2 complet (US-CA, CA-QC…), NULL pour les autres pays ou si inconnu.
ALTER TABLE cartes
    ADD COLUMN subdivision VARCHAR(6) NULL AFTER pays_code,
    ADD KEY idx_subdivision (subdivision, sens, publique);
