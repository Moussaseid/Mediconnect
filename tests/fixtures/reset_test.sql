-- reset_test.sql
-- Recrée les tables de test proprement avant chaque suite.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reset_tokens;
DROP TABLE IF EXISTS demandes_professionnels;
DROP TABLE IF EXISTS horaires_semaine;
DROP TABLE IF EXISTS indisponibilite;
DROP TABLE IF EXISTS rendez_vous;
DROP TABLE IF EXISTS medecins;
DROP TABLE IF EXISTS utilisateurs;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100)  NOT NULL,
    prenom            VARCHAR(100)  NOT NULL DEFAULT '',
    email             VARCHAR(255)  NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255)  NOT NULL,
    telephone         VARCHAR(20)   DEFAULT NULL,
    adresse           VARCHAR(255)  DEFAULT NULL,
    ville             VARCHAR(100)  DEFAULT NULL,
    role              ENUM('patient','medecin','admin','pharmacie','centre_sante','centre_analyse')
                      NOT NULL DEFAULT 'patient',
    statut            ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medecins (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id   INT           NOT NULL UNIQUE,
    specialisation   VARCHAR(100)  NOT NULL,
    numero_rpps      CHAR(11)      NOT NULL UNIQUE,
    adresse_cabinet  VARCHAR(255)  DEFAULT NULL,
    latitude         DECIMAL(10,7) DEFAULT NULL,
    longitude        DECIMAL(10,7) DEFAULT NULL,
    duree_rdv        SMALLINT      NOT NULL DEFAULT 30,
    valide_par       INT           DEFAULT NULL,
    valide_le        DATETIME      DEFAULT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (valide_par)     REFERENCES utilisateurs(id) ON DELETE SET NULL
);

CREATE TABLE horaires_semaine (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id   INT     NOT NULL,
    jour_semaine TINYINT NOT NULL COMMENT '1=lundi, 2=mardi, ..., 7=dimanche',
    heure_debut  TIME    NOT NULL,
    heure_fin    TIME    NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

CREATE TABLE indisponibilite (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id INT      NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin   DATETIME NOT NULL,
    motif      VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

CREATE TABLE rendez_vous (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT      NOT NULL,
    medecin_id       INT      NOT NULL,
    date_heure       DATETIME NOT NULL,
    statut           ENUM('confirme','annule') NOT NULL DEFAULT 'confirme',
    motif_annulation VARCHAR(500) DEFAULT NULL,
    annule_par       ENUM('patient','medecin') DEFAULT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)  REFERENCES utilisateurs(id),
    FOREIGN KEY (medecin_id)  REFERENCES medecins(id)
);

CREATE TABLE demandes_professionnels (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(100)  NOT NULL,
    prenom           VARCHAR(100)  NOT NULL,
    specialisation   VARCHAR(100)  NOT NULL,
    email            VARCHAR(255)  NOT NULL UNIQUE,
    numero_rpps      VARCHAR(11)   NOT NULL UNIQUE,
    adresse_cabinet  VARCHAR(255)  DEFAULT NULL,
    statut           ENUM('en_attente','approuve','rejete') NOT NULL DEFAULT 'en_attente',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      CHAR(64)     NOT NULL UNIQUE,
    expire_le  DATETIME     NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);
