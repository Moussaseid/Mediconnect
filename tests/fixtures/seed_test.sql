-- seed_test.sql
-- Données de référence pour les tests PHPUnit.
-- Mot de passe : Test1234  (bcrypt, coût 10)

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, telephone, adresse, ville, role, statut)
VALUES
    ('Admin',   'Test',    'admin@test.fr',   '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK', NULL,         NULL,      NULL,    'admin',   'actif'),
    ('Dupont',  'Jean',    'jean@test.fr',    '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK', '0600000001', '1 rue A', 'Paris', 'patient', 'actif'),
    ('Bloq',    'Paul',    'bloq@test.fr',    '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK', NULL,         NULL,      NULL,    'patient', 'inactif'),
    ('Martin',  'Alice',   'alice@test.fr',   '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK', NULL,         NULL,      NULL,    'medecin', 'actif');

-- Profil médecin d'Alice (duree_rdv=30 pour les tests SlotService)
INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, adresse_cabinet, duree_rdv)
VALUES (
    (SELECT id FROM utilisateurs WHERE email = 'alice@test.fr'),
    'Cardiologie',
    '12345678901',
    '5 avenue B, Paris',
    30
);

-- Horaires d'Alice : Lun–Ven 09h00–12h00 → 6 créneaux de 30 min par jour
INSERT INTO horaires_semaine (medecin_id, jour_semaine, heure_debut, heure_fin)
VALUES
    ((SELECT id FROM medecins WHERE numero_rpps = '12345678901'), 1, '09:00:00', '12:00:00'),
    ((SELECT id FROM medecins WHERE numero_rpps = '12345678901'), 2, '09:00:00', '12:00:00'),
    ((SELECT id FROM medecins WHERE numero_rpps = '12345678901'), 3, '09:00:00', '12:00:00'),
    ((SELECT id FROM medecins WHERE numero_rpps = '12345678901'), 4, '09:00:00', '12:00:00'),
    ((SELECT id FROM medecins WHERE numero_rpps = '12345678901'), 5, '09:00:00', '12:00:00');

-- RDV posé le lundi 2028-01-03 à 09:00 (utilisé par SlotServiceTest)
INSERT INTO rendez_vous (patient_id, medecin_id, date_heure, statut)
VALUES (
    (SELECT id FROM utilisateurs WHERE email = 'jean@test.fr'),
    (SELECT id FROM medecins WHERE numero_rpps = '12345678901'),
    '2028-01-03 09:00:00',
    'confirme'
);

-- Demande professionnelle en attente (utilisée par MedecinModelTest + StatutCoherenceTest)
INSERT INTO demandes_professionnels (nom, prenom, specialisation, email, numero_rpps, adresse_cabinet, statut)
VALUES
    ('Lebrun', 'Marc', 'Dermatologie', 'marc@test.fr', '99988877700', '10 rue C, Lyon', 'en_attente');
