-- ============================================================
-- MIGRATION : Renommer les colonnes PK/FK pharmacies, medicaments,
-- inventaire pour aligner le schéma BDD avec le code PHP.
-- Idempotente : vérifie le nom actuel avant chaque CHANGE.
-- À appliquer après migration_add_columns.sql et avant
-- migration_sprint3.sql.
-- ============================================================
USE mediconnect;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. pharmacies : id → id_pharmacie
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'pharmacies'
              AND COLUMN_NAME  = 'id');
SET @sql = IF(@col > 0,
    'ALTER TABLE pharmacies CHANGE id id_pharmacie INT AUTO_INCREMENT',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. medicaments : id → id_medicament
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'medicaments'
              AND COLUMN_NAME  = 'id');
SET @sql = IF(@col > 0,
    'ALTER TABLE medicaments CHANGE id id_medicament INT AUTO_INCREMENT',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. inventaire : pharmacie_id → id_pharmacie (si pas encore renommé)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'inventaire'
              AND COLUMN_NAME  = 'pharmacie_id');
SET @sql = IF(@col > 0,
    'ALTER TABLE inventaire CHANGE pharmacie_id id_pharmacie INT NOT NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. inventaire : medicament_id → id_medicament (si pas encore renommé)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'inventaire'
              AND COLUMN_NAME  = 'medicament_id');
SET @sql = IF(@col > 0,
    'ALTER TABLE inventaire CHANGE medicament_id id_medicament INT NOT NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET FOREIGN_KEY_CHECKS = 1;
