-- ============================================================
-- MIGRATION GESTION RDV
-- Ajout des colonnes annulation sur rendez_vous
-- Suppression de la contrainte UNIQUE pour permettre
-- la re-réservation d'un créneau libéré après annulation.
-- ============================================================
USE mediconnect;

ALTER TABLE rendez_vous
    ADD COLUMN motif_annulation VARCHAR(500) DEFAULT NULL
        COMMENT 'Motif saisi lors de l annulation (patient ou médecin)',
    ADD COLUMN annule_par ENUM('patient','medecin') NULL
        COMMENT 'Indique qui a annulé le rendez-vous';

-- Supprimer l'index unique medecin/date_heure pour permettre la re-réservation
-- d'un créneau après annulation par le médecin.
-- On crée d'abord un index simple sur medecin_id (nécessaire pour la FK)
-- avant de pouvoir supprimer rdv_unique qui servait d'index pour cette FK.
ALTER TABLE rendez_vous
    ADD INDEX idx_medecin_id (medecin_id);

ALTER TABLE rendez_vous
    DROP INDEX IF EXISTS rdv_unique;

-- Modifier l'ENUM statut : retrait de 'en_attente' et 'termine'
-- La confirmation est immédiate à la réservation (statut = 'confirme' par défaut).
ALTER TABLE rendez_vous
    MODIFY COLUMN statut ENUM('confirme','annule') NOT NULL DEFAULT 'confirme';
