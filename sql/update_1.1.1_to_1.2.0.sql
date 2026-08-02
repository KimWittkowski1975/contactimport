-- Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
--
-- SQL Update Script: Version 1.1.1 to 1.2.0
-- Description: Add support for auto-import, last order column mapping, and old files cleanup

-- ===================================================================
-- Add last_order_column to templates table
-- ===================================================================
ALTER TABLE llx_contactimport_templates 
ADD COLUMN IF NOT EXISTS last_order_column varchar(255) DEFAULT NULL 
COMMENT 'CSV column name for "Letzter Auftrag (Datum)" field';

-- ===================================================================
-- Add delete_old_files and old_files_days to FTP config table
-- ===================================================================
ALTER TABLE llx_contactimport_ftp_config 
ADD COLUMN IF NOT EXISTS delete_old_files tinyint DEFAULT 0 
COMMENT 'Automatically delete files older than X days from FTP server';

ALTER TABLE llx_contactimport_ftp_config 
ADD COLUMN IF NOT EXISTS old_files_days integer DEFAULT 30 
COMMENT 'Number of days after which files are considered old';
