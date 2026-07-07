-- MediConnect — Migration Sprint Fondations
-- À exécuter une seule fois : mysql -u root -p mediconnect < database/migration_fondations.sql

USE mediconnect;

-- Table des demandes de création de compte professionnel (Issue #5 & #6)
-- Colonne : specialite (sans accent — convention équipe section 1.4)
-- numero_rpps : CHAR(11) — longueur fixe garantie
CREATE TABLE IF NOT EXISTS demandes_professionnels (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    specialisation  VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    numero_rpps     CHAR(11)     NOT NULL UNIQUE,
    adresse_cabinet VARCHAR(255) NOT NULL,
    statut          ENUM('en_attente', 'approuve', 'rejete') NOT NULL DEFAULT 'en_attente',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Ajout des colonnes de traçabilité de validation dans medecins (Issue #6)
-- valide_par : ID de l'admin (utilisateurs.id, role='admin') ayant validé la demande
-- valide_le  : horodatage de la validation
-- Source de vérité unique pour l'accès : utilisateurs.statut
ALTER TABLE medecins
    ADD COLUMN valide_par INT      DEFAULT NULL,
    ADD COLUMN valide_le  DATETIME DEFAULT NULL;

-- FK séparée (ADD CONSTRAINT IF NOT EXISTS non supporté en MariaDB 10.4)
ALTER TABLE medecins
    ADD FOREIGN KEY (valide_par) REFERENCES utilisateurs(id) ON DELETE SET NULL;

-- Remplacement de creneaux_disponibilite par le modèle horaires/indisponibilite (Sprint 2)
CREATE TABLE IF NOT EXISTS horaires_semaine (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id   INT NOT NULL,
    jour_semaine TINYINT NOT NULL COMMENT '1=lundi, 2=mardi, ..., 7=dimanche',
    heure_debut  TIME NOT NULL,
    heure_fin    TIME NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS indisponibilite (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id INT NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin   DATETIME NOT NULL,
    motif      VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

ALTER TABLE medecins
    ADD COLUMN duree_rdv SMALLINT NOT NULL DEFAULT 30
    COMMENT 'Durée en minutes — ex: 15, 30, 45, 60';

-- #20 — Réinitialisation de mot de passe (tokens temporaires)
CREATE TABLE IF NOT EXISTS reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      CHAR(64)     NOT NULL UNIQUE,
    expire_le  DATETIME     NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- #29 — Extension de l'ENUM role pour les gestionnaires institutionnels
ALTER TABLE utilisateurs
    MODIFY COLUMN role ENUM('patient','medecin','admin','pharmacie','centre_sante','centre_analyse')
    NOT NULL DEFAULT 'patient';

-- Sprint 3 — medecins.statut supprimé (non présent dans schema.sql baseline)

-- Admin inséré dans seed.sql uniquement (évite doublon avec le seed de test)
