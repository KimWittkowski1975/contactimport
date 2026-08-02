-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
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

-- Update from 1.3.11 to 1.3.12: Add detailed statistics fields to sessions table

ALTER TABLE llx_contactimport_sessions 
  ADD COLUMN companies_created integer DEFAULT 0 AFTER error_lines,
  ADD COLUMN companies_updated integer DEFAULT 0 AFTER companies_created,
  ADD COLUMN contacts_created integer DEFAULT 0 AFTER companies_updated,
  ADD COLUMN contacts_updated integer DEFAULT 0 AFTER contacts_created;

-- Initialize values for existing sessions based on success_lines
-- This is an approximation - real values will be set for new imports
UPDATE llx_contactimport_sessions 
SET companies_created = FLOOR(success_lines / 2),
    contacts_created = FLOOR(success_lines / 2)
WHERE companies_created = 0 AND success_lines > 0;
