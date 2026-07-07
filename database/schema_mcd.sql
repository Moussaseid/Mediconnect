-- ============================================================
-- MCD MediConnect — généré le 2026-04-07 depuis le code source
-- Projet WE4A-SI40 — PHP 8.2 MVC / MariaDB (mysql: driver PDO)
-- Sources : schema.sql + migration_fondations.sql + models PHP
-- ============================================================

-- ============================================================
-- ENTITÉ : UTILISATEUR
-- Rôle : table centrale unifiée pour tous les rôles
--        (patient / médecin / admin)
-- Attributs : id (PK), nom, prenom, email, role, statut,
--             telephone, adresse, ville
-- ============================================================

-- ASSOCIATION : UTILISATEUR (1,1) ——[Est profilé en]—— (0,1) MEDECIN
-- Justification : medecins.utilisateur_id FK NOT NULL (tout médecin est
--   un utilisateur), pas de UNIQUE déclaré mais logique métier = 0 ou 1
--   profil médecin par utilisateur.

-- ASSOCIATION : UTILISATEUR (0,n) ——[Valide]—— (0,1) MEDECIN
-- Justification : medecins.valide_par FK NULL → un médecin peut ne pas
--   encore avoir été validé (0) ou l'avoir été par exactement 1 admin.
--   Un admin peut en valider plusieurs.

-- ASSOCIATION : UTILISATEUR (0,n) ——[Prend]—— (1,1) RENDEZ_VOUS
-- Justification : rendez_vous.patient_id FK NOT NULL → 1 RDV = 1 patient.
--   Un patient peut n'avoir aucun RDV ou en avoir plusieurs.

-- ============================================================
-- ENTITÉ : MEDECIN
-- Rôle : profil étendu d'un utilisateur rôle='medecin'
-- Attributs : id (PK), specialisation, numero_rpps, adresse_cabinet,
--             latitude, longitude, statut, duree_rdv, valide_le
-- ============================================================

-- ASSOCIATION : MEDECIN (0,n) ——[Reçoit]—— (1,1) RENDEZ_VOUS
-- Justification : rendez_vous.medecin_id FK NOT NULL → 1 RDV = 1 médecin.
--   Un médecin peut n'avoir aucun RDV ou en avoir plusieurs.
--   Contrainte UNIQUE(medecin_id, date_heure) assure l'unicité du créneau.

-- ASSOCIATION : MEDECIN (0,n) ——[Suit]—— (1,1) HORAIRES_SEMAINE
-- Justification : horaires_semaine.medecin_id FK NOT NULL ON DELETE CASCADE.
--   Un médecin peut définir plusieurs plages horaires par semaine.

-- ASSOCIATION : MEDECIN (0,n) ——[Enregistre]—— (1,1) INDISPONIBILITE
-- Justification : indisponibilite.medecin_id FK NOT NULL ON DELETE CASCADE.
--   Un médecin peut déclarer plusieurs périodes d'indisponibilité.

-- ============================================================
-- ENTITÉ : DEMANDE_MEDECIN
-- Rôle : dossier de candidature soumis avant création du compte médecin.
--   Entité autonome (aucune FK vers utilisateurs — le lien est opérationnel,
--   pas structurel : l'approbation crée un UTILISATEUR + MEDECIN).
-- Attributs : id (PK), nom, prenom, specialisation, email, numero_rpps,
--             adresse_cabinet, statut
-- ============================================================

-- ============================================================
-- ENTITÉ : RENDEZ_VOUS
-- Rôle : créneau réservé entre un patient et un médecin
-- Attributs : id (PK), date_heure, statut
-- ============================================================

-- ============================================================
-- ENTITÉ : HORAIRES_SEMAINE
-- Rôle : plage horaire hebdomadaire récurrente d'un médecin
-- Attributs : id (PK), jour_semaine, heure_debut, heure_fin
-- ============================================================

-- ============================================================
-- ENTITÉ : INDISPONIBILITE
-- Rôle : période d'absence ponctuelle déclarée par un médecin
-- Attributs : id (PK), date_debut, date_fin, motif
-- ============================================================

-- ============================================================
-- ENTITÉ : PHARMACIE
-- Rôle : officine référencée pour la recherche géolocalisée
-- Attributs : id (PK), nom, adresse, latitude, longitude
-- ============================================================

-- ASSOCIATION : PHARMACIE (0,n) ——[Stocke]—— (0,n) MEDICAMENT
-- Attribut porté par l'association : quantite
-- Justification : table inventaire = matérialisation de cette
--   association n-n. pharmacie_id et medicament_id sont tous deux
--   FK NOT NULL dans inventaire.

-- ============================================================
-- ENTITÉ : MEDICAMENT
-- Rôle : référentiel des médicaments disponibles
-- Attributs : id (PK), nom, description
-- ============================================================

-- ============================================================
-- ENTITÉ : CENTRE_SANTE
-- Rôle : centre de santé référencé (géolocalisation)
-- Attributs : id (PK), nom, adresse, latitude, longitude
-- Note : aucune association définie à ce stade — sprint 2
-- ============================================================

-- ============================================================
-- ENTITÉ : CENTRE_ANALYSE
-- Rôle : laboratoire d'analyses référencé (géolocalisation)
-- Attributs : id (PK), nom, adresse, latitude, longitude
-- Note : aucune association définie à ce stade — sprint 2
-- ============================================================

-- ============================================================
-- DIVERGENCES MCD INITIAL → MCD RÉEL
-- ============================================================
--
-- | Point                    | MCD initial                    | MCD réel                            | Statut    |
-- |--------------------------|--------------------------------|-------------------------------------|-----------|
-- | Structure utilisateurs   | PATIENT + MEDECIN séparés      | Table unifiée utilisateurs (+ rôle) | Assumé    |
-- | Créneaux disponibilité   | CRENEAU_DISPONIBILITE          | HORAIRES_SEMAINE + INDISPONIBILITE  | Corrigé   |
-- | Spécialité               | Entité SPECIALITE normalisée   | VARCHAR libre dans medecins         | À faire   |
-- | Durée de RDV             | Absente                        | medecins.duree_rdv SMALLINT         | Ajouté    |
-- | Traçabilité validation   | Absente                        | medecins.valide_par + valide_le     | Ajouté    |
-- | Statut médecin           | Absent                         | medecins.statut ENUM                | Ajouté    |
-- | Admin                    | Absent du MCD                  | role='admin' dans UTILISATEUR       | Assumé    |
-- | Commande / Ligne commande| Présentes dans MCD initial     | Absentes du schéma réel             | Non impl. |
-- | Centres sante/analyse    | Absents du MCD initial         | Tables créées, sans relations       | Partiel   |
--
-- ============================================================
-- POINTS D'ATTENTION SPRINT 2
-- ============================================================
--
-- 1. SPECIALITE : normaliser en table dédiée + table de jonction
--    MEDECIN_SPECIALITE pour le moteur de recherche par spécialité.
--
-- 2. COMMANDE / LIGNE_COMMANDE : implémenter si le module pharmacie
--    (commande en ligne) est prévu.
--
-- 3. CENTRE_SANTE / CENTRE_ANALYSE : définir les associations
--    (ex : lien vers RENDEZ_VOUS, vers UTILISATEUR).
--
-- 4. UNIQUE(utilisateur_id) sur medecins : ajouter la contrainte SQL
--    pour garantir le (0,1) au niveau BDD, pas seulement applicatif.
--
-- 5. getCreneauxDisponibles() : calculer les créneaux libres à partir
--    de HORAIRES_SEMAINE - INDISPONIBILITE - RENDEZ_VOUS.
