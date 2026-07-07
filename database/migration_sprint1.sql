-- MediConnect — Migration Sprint 1 : Épic Inventaire & Admin
-- À exécuter une seule fois :
--   mysql -u root -p mediconnect < database/migration_sprint1.sql
-- Prérequis : migration_fondations.sql déjà exécutée.

USE mediconnect;

-- ── 1. Fix utilisateurs.statut ──────────────────────────────────────────────
-- AdminController::suspendre() écrit 'suspendu' mais l'ENUM original n'avait
-- que 'actif','en_attente','rejete' → bug silencieux MariaDB (valeur vide).
ALTER TABLE utilisateurs
    MODIFY COLUMN statut
    ENUM('actif','en_attente','rejete','suspendu','inactif')
    NOT NULL DEFAULT 'actif';

-- ── 2. Colonnes manquantes sur utilisateurs ─────────────────────────────────
-- photo_path déjà référencé dans AuthApiController::me() via COALESCE
ALTER TABLE utilisateurs
    ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL;

-- ── 3. Colonnes manquantes sur medecins ─────────────────────────────────────
ALTER TABLE medecins
    ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN actif      TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN telephone  VARCHAR(20)  DEFAULT NULL;

-- ── 4. Table pharmacies — colonnes complètes (IPharmacie) ───────────────────
ALTER TABLE pharmacies
    ADD COLUMN code_postal VARCHAR(10)  DEFAULT NULL,
    ADD COLUMN ville       VARCHAR(100) DEFAULT NULL,
    ADD COLUMN telephone   VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN email       VARCHAR(255) DEFAULT NULL,
    ADD COLUMN actif       TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ── 5. Table medicaments — colonnes complètes (IMedicament) ─────────────────
ALTER TABLE medicaments
    ADD COLUMN sur_ordonnance TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN forme          VARCHAR(50)  DEFAULT NULL,
    ADD COLUMN dosage         VARCHAR(50)  DEFAULT NULL,
    ADD COLUMN laboratoire    VARCHAR(150) DEFAULT NULL,
    ADD COLUMN created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP;

-- ── 6. Table inventaire — colonnes manquantes (IInventaire) ─────────────────
-- prix_unitaire requis pour les alertes stock dans les stats admin
ALTER TABLE inventaire
    ADD COLUMN prix_unitaire   DECIMAL(10,2) DEFAULT NULL,
    ADD COLUMN date_peremption DATE          DEFAULT NULL,
    ADD COLUMN updated_at      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ── 7. Table centres_sante — colonnes complètes (ICentreSante) ───────────────
ALTER TABLE centres_sante
    ADD COLUMN telephone   VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN email       VARCHAR(255) DEFAULT NULL,
    ADD COLUMN description TEXT         DEFAULT NULL,
    ADD COLUMN specialites TEXT         DEFAULT NULL,
    ADD COLUMN services    TEXT         DEFAULT NULL,
    ADD COLUMN actif       TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP;

-- ── 8. Table centres_analyse — colonnes complètes (ICentreAnalyse) ──────────
ALTER TABLE centres_analyse
    ADD COLUMN telephone  VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN email      VARCHAR(255) DEFAULT NULL,
    ADD COLUMN actif      TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN created_at DATETIME     DEFAULT CURRENT_TIMESTAMP;

-- ── 9. Table commandes (ICommande) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS commandes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT          NOT NULL,
    pharmacie_id      INT          NOT NULL,
    mode_retrait      ENUM('sur_place','livraison') NOT NULL DEFAULT 'sur_place',
    adresse_livraison VARCHAR(255) DEFAULT NULL,
    notes             TEXT         DEFAULT NULL,
    statut            ENUM('en_attente','preparee','prete','livree','annulee') NOT NULL DEFAULT 'en_attente',
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)   REFERENCES utilisateurs(id),
    FOREIGN KEY (pharmacie_id) REFERENCES pharmacies(id)
);

-- ── 10. Table lignes_commande (ILigneCommande) ──────────────────────────────
CREATE TABLE IF NOT EXISTS lignes_commande (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    commande_id   INT           NOT NULL,
    medicament_id INT           NOT NULL,
    quantite      INT           NOT NULL DEFAULT 1,
    prix_achat    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (commande_id)   REFERENCES commandes(id)   ON DELETE CASCADE,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);

-- ── 11. Table specialite (ISpecialite) ──────────────────────────────────────
-- medecins.specialisation reste VARCHAR en attendant refactoring Phase 3+
CREATE TABLE IF NOT EXISTS specialite (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL UNIQUE
);
