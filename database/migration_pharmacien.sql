-- ============================================================
-- MIGRATION : Rôle pharmacien
-- Crée la table pharmaciens qui lie un compte utilisateur
-- (role = 'pharmacie') à une pharmacie spécifique.
-- Idempotente : utilise CREATE TABLE IF NOT EXISTS.
-- ============================================================
USE mediconnect;

CREATE TABLE IF NOT EXISTS pharmaciens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL UNIQUE,
    pharmacie_id   INT NOT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (pharmacie_id)  REFERENCES pharmacies(id_pharmacie) ON DELETE CASCADE
);

-- Étendre l'ENUM role si pas encore fait
ALTER TABLE utilisateurs
    MODIFY COLUMN role ENUM('patient','medecin','admin','pharmacie','centre_sante','centre_analyse')
    NOT NULL DEFAULT 'patient';
