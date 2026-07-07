-- MediConnect — Migration Sprint 1 (Partie 2) : Espace Patient
-- À exécuter : mysql -u root mediconnect < database/migration_patient.sql

USE mediconnect;

-- Désactive la vérification des FK pendant la migration
-- (évite errno 150 quelle que soit l'engine ou l'ordre des tables)
SET FOREIGN_KEY_CHECKS = 0;

-- ── 0. Garantir InnoDB sur toutes les tables référencées ─────────────────────
ALTER TABLE utilisateurs ENGINE=InnoDB;
ALTER TABLE medecins     ENGINE=InnoDB;
ALTER TABLE rendez_vous  ENGINE=InnoDB;
ALTER TABLE medicaments  ENGINE=InnoDB;

-- ── 1. rendez_vous : colonnes ajoutées par migration_gestion_rdv (idempotent)
ALTER TABLE rendez_vous
    ADD COLUMN IF NOT EXISTS annule_par       ENUM('patient','medecin') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS motif_annulation VARCHAR(255) DEFAULT NULL;

-- ── 2. Nettoyage des tables partiellement créées lors d'exécutions précédentes ─
DROP TABLE IF EXISTS ordonnance_lignes;
DROP TABLE IF EXISTS prescriptions;

-- ── 3. Table prescriptions ───────────────────────────────────────────────────
CREATE TABLE prescriptions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT      NOT NULL,
    medecin_id        INT      NOT NULL,
    rdv_id            INT      DEFAULT NULL,
    date_prescription DATE     NOT NULL,
    validite_jours    SMALLINT NOT NULL DEFAULT 90,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (medecin_id) REFERENCES medecins(id),
    FOREIGN KEY (rdv_id)     REFERENCES rendez_vous(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Table ordonnance_lignes ────────────────────────────────────────────────
CREATE TABLE ordonnance_lignes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT          NOT NULL,
    medicament_id   INT          NOT NULL,
    posologie       VARCHAR(255) NOT NULL,
    duree_jours     SMALLINT     DEFAULT NULL,
    quantite        SMALLINT     NOT NULL DEFAULT 1,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicament_id)   REFERENCES medicaments(id_medicament)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Réactive la vérification des FK
SET FOREIGN_KEY_CHECKS = 1;
