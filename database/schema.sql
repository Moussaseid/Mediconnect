-- MediConnect — Schéma de base de données
CREATE DATABASE IF NOT EXISTS mediconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediconnect;

CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100)  NOT NULL,
    prenom            VARCHAR(100)  NOT NULL,
    email             VARCHAR(255)  NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255)  NOT NULL,
    telephone         VARCHAR(20),
    adresse           VARCHAR(255),
    ville             VARCHAR(100),
    role              ENUM('patient','medecin','admin') NOT NULL DEFAULT 'patient',
    statut            ENUM('actif','en_attente','rejete') NOT NULL DEFAULT 'actif',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medecins (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT          NOT NULL,
    specialisation  VARCHAR(100) NOT NULL,
    numero_rpps     VARCHAR(11)  NOT NULL UNIQUE,
    adresse_cabinet VARCHAR(255),
    latitude        DECIMAL(10,8),
    longitude       DECIMAL(11,8),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE pharmacies (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE centres_sante (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE centres_analyse (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE creneaux_disponibilite (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id   INT     NOT NULL,
    jour_semaine TINYINT NOT NULL COMMENT '1=Lundi … 7=Dimanche',
    heure_debut  TIME    NOT NULL,
    heure_fin    TIME    NOT NULL,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

CREATE TABLE rendez_vous (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medecin_id INT NOT NULL,
    date_heure DATETIME NOT NULL,
    statut     ENUM('en_attente','confirme','refuse','annule') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY rdv_unique (medecin_id, date_heure),
    FOREIGN KEY (patient_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (medecin_id) REFERENCES medecins(id)
);

CREATE TABLE medicaments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    description TEXT
);

CREATE TABLE inventaire (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pharmacie_id  INT NOT NULL,
    medicament_id INT NOT NULL,
    quantite      INT NOT NULL DEFAULT 0,
    FOREIGN KEY (pharmacie_id)  REFERENCES pharmacies(id),
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);
