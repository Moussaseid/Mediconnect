-- MediConnect — Requêtes SQL documentées

-- Recherche de médecins par rayon (formule Haversine)
-- :lat   → latitude patient   (DECIMAL)
-- :lon   → longitude patient  (DECIMAL)
-- :rayon → rayon en km        (INT)
SELECT
    u.nom, u.prenom, m.specialisation, m.adresse_cabinet,
    m.latitude, m.longitude,
    (6371 * ACOS(
        COS(RADIANS(:lat)) * COS(RADIANS(m.latitude))
        * COS(RADIANS(m.longitude) - RADIANS(:lon))
        + SIN(RADIANS(:lat)) * SIN(RADIANS(m.latitude))
    )) AS distance_km
FROM medecins m
JOIN utilisateurs u ON u.id = m.utilisateur_id
WHERE u.statut = 'actif'
HAVING distance_km <= :rayon
ORDER BY distance_km ASC;
