-- Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- ===================================================================
-- Default Template Data for ContactImport Module
-- This file will be loaded automatically when module is activated
-- ===================================================================

-- Insert Master Template (if not exists)
-- Updated v1.3.9: Includes email mapping and last_order_date extrafield
-- Optimized for JTL Kundenstammdaten Exports
INSERT INTO llx_contactimport_templates (
    ref,
    label,
    description,
    mapping_config,
    csv_separator,
    csv_enclosure,
    has_header,
    is_default,
    status,
    date_creation,
    fk_user_creat,
    entity
) 
SELECT * FROM (
    SELECT
        'JTL_MASTER_TEMPLATE' as ref,
        'JTL Import Template' as label,
        'Standard Template für JTL Kundenstammdaten - Company+Contact Dual-Mapping mit Email und Letzter Auftrag' as description,
        '{"import_mode":"both","create_companies":true,"create_contacts":true,"company":{"5":"nom","7":"address","9":"zip","10":"town","12":"phone","13":"email","19":"options_last_order_date"},"contact":{"3":"firstname","4":"lastname","7":"address","9":"zip","10":"town","11":"phone_mobile","12":"phone","13":"email"}}' as mapping_config,
        ';' as csv_separator,
        '"' as csv_enclosure,
        1 as has_header,
        1 as is_default,
        1 as status,
        NOW() as date_creation,
        1 as fk_user_creat,
        1 as entity
) tmp
WHERE NOT EXISTS (
    SELECT 1 FROM llx_contactimport_templates WHERE ref = 'JTL_MASTER_TEMPLATE'
);

-- ===================================================================
-- Mapping Configuration Details:
-- ===================================================================
-- CSV Column Index → Field Mapping (0-based in PHP, 1-based in nl display)
--
-- COMPANY FIELDS:
--   Index 5  (nl 6)  → nom (Company Name)
--   Index 7  (nl 8)  → address (Street + Number)
--   Index 9  (nl 10) → zip (Postal Code)
--   Index 10 (nl 11) → town (City)
--   Index 12 (nl 13) → phone (Phone)
--   Index 13 (nl 14) → email (Email Address)
--   Index 19 (nl 20) → options_last_order_date (Last Order Date - EXTRAFIELD)
--
-- CONTACT FIELDS:
--   Index 3  (nl 4)  → firstname (First Name)
--   Index 4  (nl 5)  → lastname (Last Name)
--   Index 7  (nl 8)  → address (Street + Number)
--   Index 9  (nl 10) → zip (Postal Code)
--   Index 10 (nl 11) → town (City)
--   Index 11 (nl 12) → phone_mobile (Mobile)
--   Index 12 (nl 13) → phone (Phone)
--   Index 13 (nl 14) → email (Email Address)
--
-- AUTOMATIC STATUS LOGIC:
--   - If options_last_order_date is EMPTY  → company.client = 2 (Prospect/Interessent)
--   - If options_last_order_date is FILLED → company.client = 1 (Customer/Kunde)
--
-- REQUIRES:
--   - Extrafield 'last_order_date' must exist (run sql/update_1.2.0_to_1.2.1.sql)
-- ===================================================================
