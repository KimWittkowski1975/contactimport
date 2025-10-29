-- Copyright (C) 2025 Kim Wittkowski <kim@nexor.de>
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
-- Table to store import sessions and their configurations
-- ===================================================================

CREATE TABLE llx_contactimport_sessions (
    rowid                   integer AUTO_INCREMENT PRIMARY KEY,
    ref                     varchar(128) NOT NULL,
    label                   varchar(255),
    description             text,
    filename                varchar(255) NOT NULL,
    file_path               varchar(500),
    file_size               integer DEFAULT 0,
    csv_separator           varchar(10) DEFAULT ';',
    csv_enclosure           varchar(10) DEFAULT '"',
    csv_escape              varchar(10) DEFAULT '\\',
    has_header              tinyint DEFAULT 1,
    total_lines             integer DEFAULT 0,
    processed_lines         integer DEFAULT 0,
    success_lines           integer DEFAULT 0,
    error_lines             integer DEFAULT 0,
    mapping_config          text,
    status                  integer NOT NULL DEFAULT 0,
    date_creation           datetime NOT NULL,
    date_modification       datetime,
    date_import             datetime,
    fk_user_creat           integer NOT NULL,
    fk_user_modif           integer,
    entity                  integer DEFAULT 1 NOT NULL,
    import_key              varchar(14),
    
    INDEX idx_contactimport_sessions_ref (ref),
    INDEX idx_contactimport_sessions_status (status),
    INDEX idx_contactimport_sessions_entity (entity),
    INDEX idx_contactimport_sessions_user (fk_user_creat)
) ENGINE=innodb;

-- ===================================================================
-- Table to store detailed import logs for each processed line
-- ===================================================================

CREATE TABLE llx_contactimport_logs (
    rowid                   integer AUTO_INCREMENT PRIMARY KEY,
    fk_session              integer NOT NULL,
    line_number             integer NOT NULL,
    line_data               text,
    import_type             varchar(20) NOT NULL, -- 'company', 'contact', 'both'
    company_id              integer,
    contact_id              integer,
    status                  varchar(20) NOT NULL, -- 'success', 'error', 'warning'
    error_message           text,
    date_creation           datetime NOT NULL,
    
    INDEX idx_contactimport_logs_session (fk_session),
    INDEX idx_contactimport_logs_status (status),
    INDEX idx_contactimport_logs_line (line_number),
    FOREIGN KEY (fk_session) REFERENCES llx_contactimport_sessions(rowid) ON DELETE CASCADE
) ENGINE=innodb;

-- ===================================================================
-- Table to store field mapping templates for reuse
-- ===================================================================

CREATE TABLE llx_contactimport_templates (
    rowid                   integer AUTO_INCREMENT PRIMARY KEY,
    ref                     varchar(128) NOT NULL,
    label                   varchar(255) NOT NULL,
    description             text,
    mapping_config          text NOT NULL,
    csv_separator           varchar(10) DEFAULT ';',
    csv_enclosure           varchar(10) DEFAULT '"',
    has_header              tinyint DEFAULT 1,
    is_default              tinyint DEFAULT 0,
    status                  integer NOT NULL DEFAULT 1,
    date_creation           datetime NOT NULL,
    date_modification       datetime,
    fk_user_creat           integer NOT NULL,
    fk_user_modif           integer,
    entity                  integer DEFAULT 1 NOT NULL,
    
    INDEX idx_contactimport_templates_ref (ref),
    INDEX idx_contactimport_templates_status (status),
    INDEX idx_contactimport_templates_entity (entity),
    UNIQUE INDEX uk_contactimport_templates_ref (ref, entity)
) ENGINE=innodb;

-- ===================================================================
-- Table to store FTP configuration for automated downloads
-- ===================================================================

CREATE TABLE llx_contactimport_ftp_config (
    rowid                   integer AUTO_INCREMENT PRIMARY KEY,
    ref                     varchar(128) NOT NULL,
    label                   varchar(255) NOT NULL,
    ftp_host                varchar(255) NOT NULL,
    ftp_port                integer DEFAULT 21,
    ftp_user                varchar(255),
    ftp_password            varchar(255),
    ftp_path                varchar(500),
    ftp_passive             tinyint DEFAULT 1,
    ftp_ssl                 tinyint DEFAULT 0,
    download_frequency      integer DEFAULT 3600, -- in seconds
    last_download           datetime,
    next_download           datetime,
    auto_import             tinyint DEFAULT 0,
    fk_template             integer,
    status                  integer NOT NULL DEFAULT 1,
    date_creation           datetime NOT NULL,
    date_modification       datetime,
    fk_user_creat           integer NOT NULL,
    fk_user_modif           integer,
    entity                  integer DEFAULT 1 NOT NULL,
    
    INDEX idx_contactimport_ftp_ref (ref),
    INDEX idx_contactimport_ftp_status (status),
    INDEX idx_contactimport_ftp_entity (entity),
    INDEX idx_contactimport_ftp_next_download (next_download),
    FOREIGN KEY (fk_template) REFERENCES llx_contactimport_templates(rowid) ON DELETE SET NULL
) ENGINE=innodb;

-- ===================================================================
-- Table to log duplicate management actions (delete/merge)
-- ===================================================================

CREATE TABLE llx_contactimport_duplicate_logs (
    rowid                   integer AUTO_INCREMENT PRIMARY KEY,
    entity                  integer DEFAULT 1 NOT NULL,
    type                    varchar(20) NOT NULL, -- 'company' or 'contact'
    action                  varchar(20) NOT NULL, -- 'delete' or 'merge'
    source_id               integer NOT NULL, -- ID that was deleted/merged
    target_id               integer DEFAULT 0, -- Target ID for merge (0 for delete)
    fk_user                 integer NOT NULL,
    date_action             datetime NOT NULL,
    
    INDEX idx_duplicate_logs_entity (entity),
    INDEX idx_duplicate_logs_type (type),
    INDEX idx_duplicate_logs_date (date_action),
    INDEX idx_duplicate_logs_user (fk_user)
) ENGINE=innodb;
