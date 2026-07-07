-- MediConnect — Données de test
-- Mot de passe de tous les comptes : Test1234!
USE mediconnect;

-- ── Utilisateurs ────────────────────────────────────────────────────────────

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role) VALUES
('Admin', 'MediConnect', 'admin@mediconnect.fr', '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', 'admin');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut) VALUES
('Dupont',  'Jean',  'j.dupont@mediconnect.fr',  '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', 'medecin', 'actif'),
('Martin',  'Marie', 'm.martin@mediconnect.fr',  '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', 'medecin', 'actif'),
('Bernard', 'Paul',  'p.bernard@mediconnect.fr', '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', 'medecin', 'actif');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, telephone, adresse, ville, role, statut) VALUES
('Leroy',  'Sophie', 's.leroy@mediconnect.fr',  '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', '0600000001', '3 rue des Lilas', 'Paris',  'patient', 'actif'),
('Moreau', 'Lucas',  'l.moreau@mediconnect.fr', '$2y$12$7Vf1SdqfZcnOUQENHsTpD.qDo4tSZXayBFMkYjSw1VlVpl8ALzmF6', '0600000002', '7 avenue des Roses', 'Lyon', 'patient', 'actif');

-- ── Médecins ─────────────────────────────────────────────────────────────────

INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, adresse_cabinet, latitude, longitude, duree_rdv) VALUES
(2, 'Médecine générale', '10003456789', '12 rue de la Paix, Paris',     48.86950, 2.33130, 30),
(3, 'Cardiologie',       '10003456790', '5 avenue Montaigne, Paris',    48.86612, 2.30514, 30),
(4, 'Pédiatrie',         '10003456791', '8 boulevard Haussmann, Paris', 48.87395, 2.33100, 30);

-- ── Horaires du premier médecin (Dupont Jean) ────────────────────────────────

INSERT INTO horaires_semaine (medecin_id, jour_semaine, heure_debut, heure_fin) VALUES
(1, 1, '09:00:00', '17:00:00'),
(1, 2, '09:00:00', '17:00:00'),
(1, 3, '09:00:00', '12:00:00'),
(1, 4, '09:00:00', '17:00:00'),
(1, 5, '09:00:00', '16:00:00');

INSERT INTO indisponibilite (medecin_id, date_debut, date_fin, motif) VALUES
(1, '2026-08-01 00:00:00', '2026-08-15 23:59:59', 'Congés été');

-- ── Pharmacies ───────────────────────────────────────────────────────────────

INSERT INTO pharmacies (nom, adresse, latitude, longitude, ville, code_postal, telephone, actif) VALUES
('Pharmacie Centrale',  '1 place de la Republique, Paris', 48.86735, 2.36302, 'Paris', '75011', '0145001122', 1),
('Pharmacie du Marché', '15 rue du Commerce, Paris',       48.84879, 2.29474, 'Paris', '75015', '0145003344', 1);

-- ── Médicaments ──────────────────────────────────────────────────────────────

INSERT INTO medicaments (nom, description, sur_ordonnance, forme, dosage, laboratoire) VALUES
('Doliprane 1000mg',  'Paracétamol — antalgique et antipyrétique', 0, 'Comprimé', '1000 mg', 'Sanofi'),
('Amoxicilline 500mg','Antibiotique à large spectre',               1, 'Gélule',   '500 mg',  'Sandoz'),
('Ibuprofène 400mg',  'Anti-inflammatoire non stéroïdien',          0, 'Comprimé', '400 mg',  'Mylan'),
('Metformine 850mg',  'Antidiabétique oral',                        1, 'Comprimé', '850 mg',  'Biogaran');

-- ── Inventaire (pharmacie 1 = id_pharmacie=1) ────────────────────────────────

INSERT INTO inventaire (id_pharmacie, id_medicament, quantite, prix_unitaire, date_peremption) VALUES
(1, 1, 150, 2.50,  '2027-06-01'),
(1, 2,  45, 5.80,  '2026-12-01'),
(1, 3,  80, 3.10,  '2027-03-01'),
(1, 4,  20, 4.20,  '2027-09-01'),
(2, 1, 200, 2.50,  '2027-06-01'),
(2, 3,  60, 3.10,  '2027-03-01');

-- ── Centres de santé ─────────────────────────────────────────────────────────

INSERT INTO centres_sante (nom, adresse, latitude, longitude, telephone, email, specialites, actif) VALUES
('Centre de Santé Paris 11', '45 rue de la Roquette, Paris 75011', 48.85800, 2.37400,
 '01 43 00 10 20', 'contact@cs-paris11.fr', 'Médecine générale, Cardiologie', 1);

-- ── Centres d'analyse ────────────────────────────────────────────────────────

INSERT INTO centres_analyse (nom, adresse, latitude, longitude, telephone, email, actif) VALUES
('Laboratoire BioAnalyse Paris', '5 rue de la Paix, Paris 75009', 48.87200, 2.33000,
 '01 47 00 55 66', 'labo@bioanalyse.fr', 1);

-- ── Spécialités ──────────────────────────────────────────────────────────────

INSERT IGNORE INTO specialite (libelle) VALUES
('Médecine générale'),
('Cardiologie'),
('Pédiatrie'),
('Dermatologie'),
('Neurologie'),
('Ophtalmologie'),
('Psychiatrie'),
('Gynécologie-Obstétrique'),
('Rhumatologie'),
('Gastro-entérologie');
