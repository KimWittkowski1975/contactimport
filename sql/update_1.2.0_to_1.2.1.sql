-- SQL Update Script for ContactImport Module
-- Update from version 1.2.0 to 1.2.1
-- Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>

-- ============================================================================
-- Add Extrafield for "Last Order Date" to ThirdParty (Societe)
-- This field stores the date of the last order to automatically set
-- customer status: empty = Prospect (client=2), filled = Customer (client=1)
-- ============================================================================

-- Create extrafield definition
INSERT INTO llx_extrafields (name, label, type, elementtype, pos, enabled, alwayseditable)
VALUES ('last_order_date', 'Letzter Auftrag (Datum)', 'date', 'societe', 1000, '1', '1')
ON DUPLICATE KEY UPDATE label='Letzter Auftrag (Datum)';

-- Add column to societe_extrafields table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'llx_societe_extrafields';
SET @columnname = 'last_order_date';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
     AND (table_schema = @dbname)
     AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATE NULL;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- Migration Note:
-- After running this script:
-- 1. Deactivate and reactivate the module in Dolibarr
-- 2. The new field "Last Order Date" will be available in company forms
-- 3. Import templates can now map CSV column to this extrafield
-- 4. Status will automatically change: empty = Prospect, filled = Customer
-- ============================================================================
