-- ═══════════════════════════════════════════════════════════════════════════
-- MediConnect — Migration centres de santé & centres d'analyse (Belva)
-- Ajoute les colonnes manquantes sur centres_sante/centres_analyse
-- et crée les tables analyses + centre_analyse_analyses
--
-- Usage : mysql -u root mediconnect < database/migration_centres_sante_analyse.sql
-- ═══════════════════════════════════════════════════════════════════════════

USE mediconnect;

-- ── 1. Colonnes manquantes sur centres_sante ─────────────────────────────────

ALTER TABLE centres_sante
    ADD COLUMN IF NOT EXISTS telephone   VARCHAR(20)   DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS email       VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS specialites VARCHAR(500)  DEFAULT NULL
        COMMENT 'Liste séparée par des virgules (ex: Cardiologie, Pédiatrie)',
    ADD COLUMN IF NOT EXISTS services    VARCHAR(500)  DEFAULT NULL
        COMMENT 'Services/équipements séparés par virgule',
    ADD COLUMN IF NOT EXISTS description TEXT          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actif       TINYINT(1)    NOT NULL DEFAULT 1;

-- ── 2. Colonnes manquantes sur centres_analyse ───────────────────────────────

ALTER TABLE centres_analyse
    ADD COLUMN IF NOT EXISTS telephone   VARCHAR(20)   DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS email       VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actif       TINYINT(1)    NOT NULL DEFAULT 1;

-- ── 3. Table analyses (référentiel des examens) ──────────────────────────────

CREATE TABLE IF NOT EXISTS analyses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    description TEXT         DEFAULT NULL,
    categorie   VARCHAR(100) DEFAULT NULL,
    duree_jours TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Délai indicatif de rendu des résultats en jours',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── 4. Table pivot centre_analyse_analyses ───────────────────────────────────

CREATE TABLE IF NOT EXISTS centre_analyse_analyses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    centre_id   INT           NOT NULL,
    analyse_id  INT           NOT NULL,
    prix        DECIMAL(8,2)  DEFAULT NULL COMMENT 'Prix en euros (NULL = non communiqué)',
    disponible  TINYINT(1)    NOT NULL DEFAULT 1,
    UNIQUE KEY  unique_centre_analyse (centre_id, analyse_id),
    FOREIGN KEY (centre_id)  REFERENCES centres_analyse(id) ON DELETE CASCADE,
    FOREIGN KEY (analyse_id) REFERENCES analyses(id)        ON DELETE CASCADE
);

-- ── 5. Index géo ─────────────────────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS idx_geo_centres_sante   ON centres_sante  (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_actif_centres_sante ON centres_sante  (actif);
CREATE INDEX IF NOT EXISTS idx_geo_centres_analyse  ON centres_analyse (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_actif_centres_analyse ON centres_analyse (actif);

-- ── 6. Données de démonstration ───────────────────────────────────────────────

-- Centres de santé
INSERT IGNORE INTO centres_sante (nom, adresse, telephone, email, specialites, services, description, latitude, longitude, actif)
VALUES
    ('Centre de Santé Paris 11',
     '45 rue de la Roquette, Paris 75011',
     '01 43 00 10 20',
     'contact@cs-paris11.fr',
     'Médecine générale, Cardiologie, Pédiatrie',
     'Téléconsultation, Prise en charge CMU, Accès PMR',
     'Centre pluridisciplinaire au cœur du 11e arrondissement, ouvert du lundi au vendredi de 8h à 20h.',
     48.85800, 2.37400, 1),
    ('Centre Médical Montparnasse',
     '10 boulevard du Montparnasse, Paris 75015',
     '01 42 00 33 44',
     'accueil@cm-montparnasse.fr',
     'Médecine générale, Dermatologie, Gynécologie, Ophtalmologie',
     'Radiologie, Laboratoire sur place, Parking',
     'Un centre moderne avec plateau technique complet, idéalement situé près de la gare Montparnasse.',
     48.84200, 2.32300, 1),
    ('Maison de Santé Lyon 3',
     '22 cours Gambetta, Lyon 69003',
     '04 72 11 22 33',
     'maison-sante@lyon3.fr',
     'Médecine générale, Rhumatologie, Kinésithérapie',
     'Infirmerie, Accueil sans rendez-vous mardi/jeudi',
     'Maison de santé pluriprofessionnelle engagée dans le suivi de proximité des patients lyonnais.',
     45.75400, 4.83900, 1);

-- Référentiel d'analyses
INSERT IGNORE INTO analyses (nom, categorie, duree_jours)
VALUES
    ('Numération Formule Sanguine (NFS)', 'Hématologie', 1),
    ('Glycémie à jeun',                  'Biochimie',   1),
    ('Cholestérol total + HDL/LDL',       'Biochimie',   1),
    ('TSH (thyroïde)',                    'Endocrinologie', 2),
    ('Créatininémie',                     'Biochimie',   1),
    ('Groupe sanguin ABO-Rhésus',         'Immunologie', 2),
    ('Sérologie VIH',                     'Virologie',   3),
    ('PCR Covid-19',                      'Virologie',   1),
    ('Bilan hépatique (ASAT/ALAT/GGT)',   'Biochimie',   1),
    ('Ionogramme sanguin',                'Biochimie',   1);

-- Centres d'analyse
INSERT IGNORE INTO centres_analyse (nom, adresse, telephone, email, latitude, longitude, actif)
VALUES
    ('Laboratoire BioAnalyse Paris 9',
     '5 rue de la Paix, Paris 75009',
     '01 47 00 55 66',
     'labo@bioanalyse9.fr',
     48.87200, 2.33000, 1),
    ('BioCentre République',
     '3 boulevard du Temple, Paris 75003',
     '01 42 71 88 99',
     'contact@biocentre-republique.fr',
     48.86400, 2.36300, 1),
    ('Laboratoire MédiLab Lyon',
     '18 rue Victor Hugo, Lyon 69002',
     '04 78 30 11 22',
     'info@medilab-lyon.fr',
     45.74900, 4.83200, 1);

-- Liaison centres d'analyse ↔ analyses (avec prix)
INSERT IGNORE INTO centre_analyse_analyses (centre_id, analyse_id, prix, disponible)
SELECT ca.id, a.id,
    CASE a.nom
        WHEN 'Numération Formule Sanguine (NFS)' THEN 12.50
        WHEN 'Glycémie à jeun'                   THEN 8.00
        WHEN 'Cholestérol total + HDL/LDL'        THEN 14.00
        WHEN 'TSH (thyroïde)'                     THEN 18.50
        WHEN 'PCR Covid-19'                       THEN 29.99
        WHEN 'Bilan hépatique (ASAT/ALAT/GGT)'   THEN 16.00
        ELSE NULL
    END,
    1
FROM centres_analyse ca
CROSS JOIN analyses a
WHERE ca.nom IN ('Laboratoire BioAnalyse Paris 9', 'BioCentre République')
  AND a.nom IN (
    'Numération Formule Sanguine (NFS)',
    'Glycémie à jeun',
    'Cholestérol total + HDL/LDL',
    'TSH (thyroïde)',
    'PCR Covid-19',
    'Bilan hépatique (ASAT/ALAT/GGT)'
  );

-- MédiLab Lyon a quelques analyses différentes
INSERT IGNORE INTO centre_analyse_analyses (centre_id, analyse_id, prix, disponible)
SELECT ca.id, a.id,
    CASE a.nom
        WHEN 'Numération Formule Sanguine (NFS)' THEN 11.50
        WHEN 'Ionogramme sanguin'                THEN 13.00
        WHEN 'Créatininémie'                     THEN 9.00
        WHEN 'Groupe sanguin ABO-Rhésus'         THEN 25.00
        WHEN 'Sérologie VIH'                     THEN NULL
        ELSE NULL
    END,
    1
FROM centres_analyse ca
CROSS JOIN analyses a
WHERE ca.nom = 'Laboratoire MédiLab Lyon'
  AND a.nom IN (
    'Numération Formule Sanguine (NFS)',
    'Ionogramme sanguin',
    'Créatininémie',
    'Groupe sanguin ABO-Rhésus',
    'Sérologie VIH'
  );

SELECT 'Migration centres de santé/analyse appliquée.' AS statut;
