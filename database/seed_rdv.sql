-- MediConnect — Seed RDV (P2 : démo dashboard médecin)
-- Données de démonstration pour le dashboard médecin (Dr Jean Dupont, id=4).
-- Dates relatives à NOW() pour rester cohérentes (passé/en cours/à venir) à toute exécution.
-- Statuts limités à 'confirme'/'annule' : seuls statuts couverts par le contrat IRdv du frontend.
USE mediconnect;

-- ── Patients de démo (INSERT IGNORE = idempotent) ────────────────────────────
INSERT IGNORE INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut, ville) VALUES
('Leroy', 'Sophie', 'sophie.leroy@mediconnect.fr', '$2y$10$AfxoFYqlBidIi1xEPpsgUOKykw.BOro/KULzohggXJ9khSH/yImLG', 'patient', 'actif', 'Paris'),
('Petit', 'Thomas', 'thomas.petit@mediconnect.fr', '$2y$10$AfxoFYqlBidIi1xEPpsgUOKykw.BOro/KULzohggXJ9khSH/yImLG', 'patient', 'actif', 'Paris');

-- Résoudre l'id du médecin par email (évite de hardcoder un id AUTO_INCREMENT)
SET @med1 = (SELECT m.id FROM medecins m JOIN utilisateurs u ON m.utilisateur_id = u.id WHERE u.email = 'j.dupont@mediconnect.fr');

-- ── Rendez-vous de démo pour le Dr Jean Dupont ───────────────────────────────
INSERT INTO rendez_vous (patient_id, medecin_id, date_heure, statut) VALUES
((SELECT id FROM utilisateurs WHERE email='sophie.leroy@mediconnect.fr'), @med1, DATE_SUB(NOW(), INTERVAL 7 DAY),     'confirme'),
((SELECT id FROM utilisateurs WHERE email='thomas.petit@mediconnect.fr'), @med1, DATE_SUB(NOW(), INTERVAL 1 DAY),     'confirme'),
((SELECT id FROM utilisateurs WHERE email='sophie.leroy@mediconnect.fr'), @med1, DATE_SUB(NOW(), INTERVAL 2 HOUR),    'confirme'),
((SELECT id FROM utilisateurs WHERE email='thomas.petit@mediconnect.fr'), @med1, DATE_SUB(NOW(), INTERVAL 10 MINUTE), 'confirme'),
((SELECT id FROM utilisateurs WHERE email='sophie.leroy@mediconnect.fr'), @med1, DATE_ADD(NOW(), INTERVAL 3 HOUR),    'confirme'),
((SELECT id FROM utilisateurs WHERE email='thomas.petit@mediconnect.fr'), @med1, DATE_ADD(NOW(), INTERVAL 1 DAY),     'confirme'),
((SELECT id FROM utilisateurs WHERE email='sophie.leroy@mediconnect.fr'), @med1, DATE_ADD(NOW(), INTERVAL 3 DAY),     'confirme'),
((SELECT id FROM utilisateurs WHERE email='thomas.petit@mediconnect.fr'), @med1, DATE_ADD(NOW(), INTERVAL 10 DAY),    'confirme'),
((SELECT id FROM utilisateurs WHERE email='sophie.leroy@mediconnect.fr'), @med1, DATE_ADD(NOW(), INTERVAL 2 DAY),     'annule');
