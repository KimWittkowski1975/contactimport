-- Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
--
-- SQL Update from version 1.1.0 to 1.1.1
-- Adds duplicates_skipped field to track skipped duplicate records

-- Add duplicates_skipped column to sessions table
ALTER TABLE llx_contactimport_sessions 
ADD COLUMN duplicates_skipped integer DEFAULT 0 
AFTER error_lines;
