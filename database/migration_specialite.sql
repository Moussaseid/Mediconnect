-- MediConnect — Migration : table specialite + FK medecins.specialisation
-- À exécuter une seule fois :
--   mysql -u root -p mediconnect < database/migration_specialite.sql

USE mediconnect;

-- ── 1. Création de la table référentiel ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS specialite (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL UNIQUE
);

-- ── 2. Seed des spécialités courantes ─────────────────────────────────────────
INSERT IGNORE INTO specialite (libelle) VALUES
    ('Médecine générale'),
    ('Cardiologie'),
    ('Dermatologie'),
    ('Gynécologie-Obstétrique'),
    ('Neurologie'),
    ('Ophtalmologie'),
    ('Orthopédie'),
    ('Pédiatrie'),
    ('Psychiatrie'),
    ('Radiologie'),
    ('Urologie'),
    ('Rhumatologie'),
    ('Gastro-entérologie'),
    ('Endocrinologie'),
    ('Pneumologie');

-- ── 3. Colonne temporaire INT pour la migration ────────────────────────────────
ALTER TABLE medecins
    ADD COLUMN specialisation_id INT NULL;

-- ── 4. Correspondance libellé existant → id  (données de dev) ─────────────────
UPDATE medecins m
    JOIN specialite s ON LOWER(TRIM(s.libelle)) = LOWER(TRIM(m.specialisation))
    SET m.specialisation_id = s.id;

-- ── 5. Médecins sans correspondance → Médecine générale (id = 1) ───────────────
UPDATE medecins
    SET specialisation_id = 1
    WHERE specialisation_id IS NULL;

-- ── 6. Suppression de l'ancienne colonne VARCHAR ───────────────────────────────
ALTER TABLE medecins DROP COLUMN specialisation;

-- ── 7. Renommage + NOT NULL ────────────────────────────────────────────────────
ALTER TABLE medecins
    CHANGE COLUMN specialisation_id specialisation INT NOT NULL DEFAULT 1;

-- ── 8. Clé étrangère ──────────────────────────────────────────────────────────
ALTER TABLE medecins
    ADD CONSTRAINT fk_medecin_specialite
    FOREIGN KEY (specialisation) REFERENCES specialite(id) ON DELETE RESTRICT;