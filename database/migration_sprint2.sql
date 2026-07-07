-- migration_sprint2.sql — Espace Centre Analyse & Centre Santé
-- Sprint 2 P4 : table centre_analyses (analyses propres à chaque centre)
--               colonne photo_path sur centres_sante
USE mediconnect;
SET FOREIGN_KEY_CHECKS = 0;

-- Analyses propres à chaque centre d'analyse (pas de catalogue global)
CREATE TABLE IF NOT EXISTS centre_analyses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    centre_id     INT          NOT NULL,
    nom           VARCHAR(150) NOT NULL,
    description   TEXT         DEFAULT NULL,
    prix          DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    duree_minutes SMALLINT     NOT NULL DEFAULT 30,
    disponible    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (centre_id) REFERENCES centres_analyse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Photo de couverture pour les centres de santé
ALTER TABLE centres_sante
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
