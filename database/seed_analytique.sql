-- seed_analytique.sql — Données analytiques Sprint 3 (Power BI)
-- 80 commandes réparties sur Jan-Jun 2026 + lignes_commande cohérentes
-- Entièrement auto-suffisant : crée patients, pharmacies et médicaments manquants.
-- mode_retrait ENUM: 'sur_place', 'livraison'

USE mediconnect;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE lignes_commande;
TRUNCATE TABLE commandes;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Patients analytiques (INSERT IGNORE = idempotent) ────────────────────────
INSERT IGNORE INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut, ville) VALUES
('Patient', 'Demo',    'patient@dev.fr',          '$2y$10$AfxoFYqlBidIi1xEPpsgUOKykw.BOro/KULzohggXJ9khSH/yImLG', 'patient', 'actif', 'Paris'),
('Garcia',  'Gabriela','gabrielagrres@gmail.com', '$2y$10$AfxoFYqlBidIi1xEPpsgUOKykw.BOro/KULzohggXJ9khSH/yImLG', 'patient', 'actif', 'Paris');

-- ── 12 Pharmacies (INSERT IGNORE = idempotent, id_pharmacie explicite) ────────
INSERT IGNORE INTO pharmacies (id_pharmacie, nom, adresse, ville, code_postal, telephone, actif) VALUES
(1,  'Pharmacie Centrale',     '1 place de la Republique, Paris',     'Paris',        '75011', '0145001122', 1),
(2,  'Pharmacie du Marché',    '15 rue du Commerce, Paris',           'Paris',        '75015', '0145003344', 1),
(3,  'Pharmacie des Arts',     '5 rue des Arts, Bordeaux',            'Bordeaux',     '33000', '0556001133', 1),
(4,  'Pharmacie de la Gare',   '12 place de la Gare, Lyon',           'Lyon',         '69001', '0472001144', 1),
(5,  'Pharmacie Victor Hugo',  '45 boulevard Victor Hugo, Nice',      'Nice',         '06000', '0493001155', 1),
(6,  'Pharmacie du Palais',    '8 rue du Palais, Bordeaux',           'Bordeaux',     '33000', '0556001166', 1),
(7,  'Pharmacie Saint-Michel', '23 place Saint-Michel, Paris',        'Paris',        '75005', '0145001177', 1),
(8,  'Pharmacie des Fleurs',   '67 avenue des Fleurs, Montpellier',   'Montpellier',  '34000', '0467001188', 1),
(9,  'Pharmacie de Europe',    '14 rue de Europe, Strasbourg',        'Strasbourg',   '67000', '0388001199', 1),
(10, 'Pharmacie du Soleil',    '3 avenue du Soleil, Marseille',       'Marseille',    '13001', '0491001110', 1),
(11, 'Pharmacie Nationale',    '89 rue Nationale, Lille',             'Lille',        '59000', '0320001111', 1),
(12, 'Pharmacie de la Paix',   '56 rue de la Paix, Rennes',           'Rennes',       '35000', '0299001112', 1);

-- ── 18 Médicaments (INSERT IGNORE = idempotent, id_medicament explicite) ──────
INSERT IGNORE INTO medicaments (id_medicament, nom, sur_ordonnance, forme, dosage, laboratoire) VALUES
(1,  'Doliprane 1000mg',    0, 'Comprimé',     '1000 mg', 'Sanofi'),
(2,  'Amoxicilline 500mg',  1, 'Gélule',       '500 mg',  'Sandoz'),
(3,  'Ibuprofène 400mg',    0, 'Comprimé',     '400 mg',  'Mylan'),
(4,  'Metformine 850mg',    1, 'Comprimé',     '850 mg',  'Biogaran'),
(5,  'Paracétamol 500mg',   0, 'Comprimé',     '500 mg',  'Sanofi'),
(6,  'Spasfon Lyoc',        0, 'Lyophilisat',  '80 mg',   'Zambon'),
(7,  'Vitamines C 1000mg',  0, 'Comprimé',     '1000 mg', 'Bayer'),
(8,  'Xyzall 5mg',          1, 'Comprimé',     '5 mg',    'UCB Pharma'),
(9,  'Smecta',              0, 'Poudre',       '3 g',     'Ipsen'),
(10, 'Strepsils',           0, 'Pastille',     '1,2 mg',  'Reckitt'),
(11, 'Amoxicilline 1g',     1, 'Gélule',       '1000 mg', 'Sandoz'),
(12, 'Augmentin 1g',        1, 'Comprimé',     '1000 mg', 'GSK'),
(13, 'Dafalgan 1000mg',     0, 'Comprimé',     '1000 mg', 'UPSA'),
(14, 'Efferalgan 500mg',    0, 'Comprimé',     '500 mg',  'UPSA'),
(15, 'Metformine 500mg',    1, 'Comprimé',     '500 mg',  'Biogaran'),
(16, 'Oméprazole 20mg',     1, 'Gélule',       '20 mg',   'Mylan'),
(17, 'Lansoprazole 30mg',   1, 'Gélule',       '30 mg',   'Sandoz'),
(18, 'Pantoprazole 40mg',   1, 'Comprimé',     '40 mg',   'Biogaran');

-- ── Résoudre les ids par email (évite de hardcoder des AUTO_INCREMENT fragiles) ─
SET @p1   = (SELECT id FROM utilisateurs WHERE email = 'patient@dev.fr');
SET @p2   = (SELECT id FROM utilisateurs WHERE email = 'gabrielagrres@gmail.com');
SET @med1 = (SELECT m.id FROM medecins m JOIN utilisateurs u ON m.utilisateur_id = u.id WHERE u.email = 'j.dupont@mediconnect.fr');
SET @med2 = (SELECT m.id FROM medecins m JOIN utilisateurs u ON m.utilisateur_id = u.id WHERE u.email = 'm.martin@mediconnect.fr');
SET @med3 = (SELECT m.id FROM medecins m JOIN utilisateurs u ON m.utilisateur_id = u.id WHERE u.email = 'p.bernard@mediconnect.fr');
-- med4-6 cyclent sur les 3 médecins de seed.sql
SET @med4 = @med1;
SET @med5 = @med2;
SET @med6 = @med3;

-- ── 80 commandes réparties sur 6 mois ────────────────────────────────────────
INSERT INTO commandes (patient_id, pharmacie_id, mode_retrait, adresse_livraison, statut, created_at) VALUES
-- Janvier 2026 (13 commandes)
(@p1,1,  'livraison', '12 rue de la Paix, Paris 75001',              'livree',     '2026-01-03 09:15:00'),
(@p2,2,  'sur_place', NULL,                                           'livree',     '2026-01-05 11:30:00'),
(@p1,3,  'sur_place', NULL,                                           'annulee',    '2026-01-08 14:00:00'),
(@p2,4,  'livraison', '45 avenue Victor Hugo, Paris 75016',           'livree',     '2026-01-10 10:45:00'),
(@p1,5,  'sur_place', NULL,                                           'livree',     '2026-01-12 16:20:00'),
(@p2,1,  'livraison', '7 rue du Temple, Paris 75004',                 'livree',     '2026-01-14 09:00:00'),
(@p1,6,  'sur_place', NULL,                                           'annulee',    '2026-01-17 13:30:00'),
(@p2,7,  'sur_place', NULL,                                           'livree',     '2026-01-19 11:00:00'),
(@p1,2,  'livraison', '23 boulevard Haussmann, Paris 75009',          'livree',     '2026-01-21 15:45:00'),
(@p2,8,  'sur_place', NULL,                                           'livree',     '2026-01-24 10:30:00'),
(@p1,9,  'sur_place', NULL,                                           'livree',     '2026-01-27 14:15:00'),
(@p2,3,  'livraison', '3 place de la Republique, Lyon 69001',         'livree',     '2026-01-29 09:45:00'),
(@p1,10, 'sur_place', NULL,                                           'annulee',    '2026-01-31 16:00:00'),

-- Février 2026 (13 commandes)
(@p2,4,  'livraison', '18 cours Pierre Puget, Marseille 13006',       'livree',     '2026-02-02 11:00:00'),
(@p1,5,  'sur_place', NULL,                                           'livree',     '2026-02-04 14:30:00'),
(@p2,1,  'sur_place', NULL,                                           'annulee',    '2026-02-07 09:30:00'),
(@p1,11, 'livraison', '56 allees Jean Jaures, Toulouse 31000',        'livree',     '2026-02-09 13:15:00'),
(@p2,6,  'sur_place', NULL,                                           'livree',     '2026-02-11 10:45:00'),
(@p1,2,  'sur_place', NULL,                                           'livree',     '2026-02-13 15:00:00'),
(@p2,7,  'livraison', '12 rue de la Paix, Paris 75001',               'livree',     '2026-02-15 09:00:00'),
(@p1,12, 'sur_place', NULL,                                           'annulee',    '2026-02-18 14:00:00'),
(@p2,3,  'sur_place', NULL,                                           'livree',     '2026-02-20 11:30:00'),
(@p1,8,  'livraison', '45 avenue Victor Hugo, Paris 75016',           'livree',     '2026-02-22 16:30:00'),
(@p2,9,  'sur_place', NULL,                                           'livree',     '2026-02-24 10:00:00'),
(@p1,1,  'livraison', '23 boulevard Haussmann, Paris 75009',          'livree',     '2026-02-26 13:45:00'),
(@p2,2,  'sur_place', NULL,                                           'annulee',    '2026-02-28 09:15:00'),

-- Mars 2026 (14 commandes)
(@p1,10, 'livraison', '7 rue du Temple, Paris 75004',                 'livree',     '2026-03-01 10:00:00'),
(@p2,4,  'sur_place', NULL,                                           'livree',     '2026-03-03 14:30:00'),
(@p1,5,  'sur_place', NULL,                                           'livree',     '2026-03-05 09:15:00'),
(@p2,6,  'livraison', '3 place de la Republique, Lyon 69001',         'livree',     '2026-03-07 11:45:00'),
(@p1,7,  'sur_place', NULL,                                           'annulee',    '2026-03-10 15:30:00'),
(@p2,11, 'livraison', '18 cours Pierre Puget, Marseille 13006',       'livree',     '2026-03-12 10:30:00'),
(@p1,8,  'sur_place', NULL,                                           'livree',     '2026-03-14 13:00:00'),
(@p2,3,  'sur_place', NULL,                                           'livree',     '2026-03-17 09:45:00'),
(@p1,1,  'livraison', '56 allees Jean Jaures, Toulouse 31000',        'livree',     '2026-03-19 14:00:00'),
(@p2,2,  'sur_place', NULL,                                           'preparee',   '2026-03-22 11:15:00'),
(@p1,9,  'livraison', '12 rue de la Paix, Paris 75001',               'livree',     '2026-03-24 10:00:00'),
(@p2,12, 'sur_place', NULL,                                           'livree',     '2026-03-26 15:45:00'),
(@p1,4,  'livraison', '45 avenue Victor Hugo, Paris 75016',           'livree',     '2026-03-28 09:30:00'),
(@p2,5,  'sur_place', NULL,                                           'annulee',    '2026-03-30 13:00:00'),

-- Avril 2026 (13 commandes)
(@p1,6,  'sur_place', NULL,                                           'livree',     '2026-04-01 10:30:00'),
(@p2,7,  'livraison', '23 boulevard Haussmann, Paris 75009',          'livree',     '2026-04-03 14:00:00'),
(@p1,10, 'sur_place', NULL,                                           'livree',     '2026-04-06 09:00:00'),
(@p2,3,  'sur_place', NULL,                                           'annulee',    '2026-04-08 11:30:00'),
(@p1,8,  'livraison', '7 rue du Temple, Paris 75004',                 'livree',     '2026-04-10 15:00:00'),
(@p2,9,  'sur_place', NULL,                                           'livree',     '2026-04-13 10:15:00'),
(@p1,11, 'livraison', '3 place de la Republique, Lyon 69001',         'livree',     '2026-04-15 14:30:00'),
(@p2,1,  'sur_place', NULL,                                           'prete',      '2026-04-17 09:45:00'),
(@p1,2,  'sur_place', NULL,                                           'livree',     '2026-04-20 13:00:00'),
(@p2,12, 'livraison', '18 cours Pierre Puget, Marseille 13006',       'livree',     '2026-04-22 11:00:00'),
(@p1,4,  'sur_place', NULL,                                           'livree',     '2026-04-24 15:30:00'),
(@p2,5,  'sur_place', NULL,                                           'livree',     '2026-04-27 10:00:00'),
(@p1,6,  'livraison', '56 allees Jean Jaures, Toulouse 31000',        'annulee',    '2026-04-29 14:15:00'),

-- Mai 2026 (14 commandes)
(@p2,7,  'sur_place', NULL,                                           'livree',     '2026-05-02 09:30:00'),
(@p1,10, 'livraison', '12 rue de la Paix, Paris 75001',               'livree',     '2026-05-04 13:45:00'),
(@p2,3,  'sur_place', NULL,                                           'livree',     '2026-05-07 10:00:00'),
(@p1,8,  'livraison', '45 avenue Victor Hugo, Paris 75016',           'livree',     '2026-05-09 14:30:00'),
(@p2,9,  'sur_place', NULL,                                           'livree',     '2026-05-12 09:15:00'),
(@p1,11, 'sur_place', NULL,                                           'annulee',    '2026-05-14 11:30:00'),
(@p2,2,  'livraison', '23 boulevard Haussmann, Paris 75009',          'livree',     '2026-05-16 15:00:00'),
(@p1,12, 'sur_place', NULL,                                           'livree',     '2026-05-19 10:30:00'),
(@p2,1,  'livraison', '7 rue du Temple, Paris 75004',                 'prete',      '2026-05-21 14:00:00'),
(@p1,4,  'sur_place', NULL,                                           'livree',     '2026-05-23 09:45:00'),
(@p2,5,  'sur_place', NULL,                                           'livree',     '2026-05-26 13:15:00'),
(@p1,7,  'livraison', '3 place de la Republique, Lyon 69001',         'preparee',   '2026-05-28 10:15:00'),
(@p2,6,  'sur_place', NULL,                                           'livree',     '2026-05-30 15:30:00'),
(@p1,9,  'livraison', '18 cours Pierre Puget, Marseille 13006',       'livree',     '2026-05-31 11:00:00'),

-- Juin 2026 (13 commandes)
(@p2,10, 'sur_place', NULL,                                           'livree',     '2026-06-02 09:00:00'),
(@p1,3,  'livraison', '56 allees Jean Jaures, Toulouse 31000',        'livree',     '2026-06-04 13:30:00'),
(@p2,11, 'sur_place', NULL,                                           'prete',      '2026-06-06 10:45:00'),
(@p1,8,  'livraison', '12 rue de la Paix, Paris 75001',               'en_attente', '2026-06-08 14:00:00'),
(@p2,9,  'sur_place', NULL,                                           'en_attente', '2026-06-10 09:30:00'),
(@p1,12, 'livraison', '45 avenue Victor Hugo, Paris 75016',           'preparee',   '2026-06-11 11:15:00'),
(@p2,2,  'sur_place', NULL,                                           'en_attente', '2026-06-12 15:00:00'),
(@p1,1,  'livraison', '23 boulevard Haussmann, Paris 75009',          'en_attente', '2026-06-13 10:00:00'),
(@p2,4,  'sur_place', NULL,                                           'prete',      '2026-06-14 14:30:00'),
(@p1,5,  'livraison', '7 rue du Temple, Paris 75004',                 'en_attente', '2026-06-15 09:45:00'),
(@p2,6,  'sur_place', NULL,                                           'preparee',   '2026-06-15 16:00:00'),
(@p1,7,  'livraison', '3 place de la Republique, Lyon 69001',         'en_attente', '2026-06-16 10:30:00'),
(@p2,8,  'sur_place', NULL,                                           'preparee',   '2026-06-16 14:00:00');

-- ── Lignes de commande (via procédure) ───────────────────────────────────────
DROP PROCEDURE IF EXISTS seed_lignes;
DELIMITER //
CREATE PROCEDURE seed_lignes()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE m1 INT;
    DECLARE m2 INT;
    DECLARE q1 INT;
    DECLARE q2 INT;
    DECLARE p1 DECIMAL(10,2);
    DECLARE p2 DECIMAL(10,2);

    WHILE i <= 80 DO
        SET m1 = CASE
            WHEN i % 10 IN (0, 1, 2) THEN 1
            WHEN i % 10 IN (3, 4)    THEN 2
            WHEN i % 10 = 5          THEN 3
            WHEN i % 10 = 6          THEN 7
            WHEN i % 10 = 7          THEN 4
            WHEN i % 10 = 8          THEN 6
            ELSE                          16
        END;

        SET m2 = CASE
            WHEN i % 9 = 0 THEN 2
            WHEN i % 9 = 1 THEN 1
            WHEN i % 9 = 2 THEN 7
            WHEN i % 9 = 3 THEN 3
            WHEN i % 9 = 4 THEN 8
            WHEN i % 9 = 5 THEN 15
            WHEN i % 9 = 6 THEN 11
            WHEN i % 9 = 7 THEN 4
            ELSE                 16
        END;

        IF m2 = m1 THEN SET m2 = m2 + 1; END IF;
        IF m2 > 18 THEN SET m2 = 1; END IF;

        SET q1 = (i % 3) + 1;
        SET q2 = ((i + 1) % 2) + 1;
        SET p1 = ROUND(3.00 + (m1 * 1.30), 2);
        SET p2 = ROUND(3.00 + (m2 * 1.10), 2);

        INSERT INTO lignes_commande (commande_id, medicament_id, quantite, prix_achat)
        VALUES (i, m1, q1, p1), (i, m2, q2, p2);

        IF i % 5 = 0 THEN
            INSERT INTO lignes_commande (commande_id, medicament_id, quantite, prix_achat)
            VALUES (i, 10, 2, 4.20);
        END IF;

        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL seed_lignes();
DROP PROCEDURE IF EXISTS seed_lignes;

-- ── Quelques RDV supplémentaires ─────────────────────────────────────────────
INSERT INTO rendez_vous (patient_id, medecin_id, date_heure, statut, created_at) VALUES
(@p1, @med1, '2026-01-08 10:00:00', 'confirme', '2026-01-05 09:00:00'),
(@p2, @med2, '2026-01-15 14:30:00', 'confirme', '2026-01-12 11:00:00'),
(@p1, @med3, '2026-01-22 09:00:00', 'annule',   '2026-01-18 10:00:00'),
(@p2, @med1, '2026-02-05 11:00:00', 'confirme', '2026-02-02 09:30:00'),
(@p1, @med4, '2026-02-12 15:30:00', 'confirme', '2026-02-09 14:00:00'),
(@p2, @med5, '2026-02-19 10:00:00', 'annule',   '2026-02-15 09:00:00'),
(@p1, @med2, '2026-03-04 09:30:00', 'confirme', '2026-03-01 10:00:00'),
(@p2, @med6, '2026-03-11 14:00:00', 'confirme', '2026-03-08 11:30:00'),
(@p1, @med1, '2026-03-18 11:00:00', 'annule',   '2026-03-14 09:00:00'),
(@p2, @med3, '2026-03-25 15:00:00', 'confirme', '2026-03-22 10:00:00'),
(@p1, @med4, '2026-04-02 09:00:00', 'confirme', '2026-03-29 11:00:00'),
(@p2, @med5, '2026-04-09 14:30:00', 'annule',   '2026-04-05 09:30:00'),
(@p1, @med2, '2026-04-16 10:30:00', 'confirme', '2026-04-13 10:00:00'),
(@p2, @med1, '2026-04-23 09:00:00', 'confirme', '2026-04-20 11:00:00'),
(@p1, @med6, '2026-04-30 15:30:00', 'annule',   '2026-04-26 09:00:00'),
(@p2, @med3, '2026-05-07 11:00:00', 'confirme', '2026-05-04 10:00:00'),
(@p1, @med4, '2026-05-14 14:00:00', 'confirme', '2026-05-11 09:30:00'),
(@p2, @med2, '2026-05-21 09:30:00', 'annule',   '2026-05-17 11:00:00'),
(@p1, @med1, '2026-05-28 10:00:00', 'confirme', '2026-05-25 09:00:00'),
(@p2, @med5, '2026-06-04 15:00:00', 'confirme', '2026-06-01 10:00:00'),
(@p1, @med6, '2026-06-11 09:00:00', 'confirme', '2026-06-08 11:00:00'),
(@p2, @med3, '2026-06-16 14:30:00', 'confirme', '2026-06-13 09:30:00');

SELECT CONCAT('commandes: ',     COUNT(*)) AS recap FROM commandes
UNION ALL SELECT CONCAT('lignes_commande: ', COUNT(*)) FROM lignes_commande
UNION ALL SELECT CONCAT('rendez_vous: ',     COUNT(*)) FROM rendez_vous;