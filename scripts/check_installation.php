#!/usr/bin/env php
<?php
/* Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        scripts/check_installation.php
 * \ingroup     contactimport
 * \brief       Check if all tables and default data are installed correctly
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__ . '/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute " . $script_file . " from command line, you must use PHP for CLI mode.\n";
	exit(1);
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $db, $conf;

echo "===============================================\n";
echo "ContactImport Module - Installation Check\n";
echo "===============================================\n\n";

// Check required tables
$required_tables = array(
	'llx_contactimport_sessions' => 'Import Sessions',
	'llx_contactimport_logs' => 'Import Logs',
	'llx_contactimport_templates' => 'Mapping Templates',
	'llx_contactimport_ftp_config' => 'FTP Configuration',
	'llx_contactimport_duplicate_logs' => 'Duplicate Logs'
);

$all_ok = true;

echo "Checking Database Tables:\n";
echo "-------------------------\n";
foreach ($required_tables as $table => $description) {
	$sql = "SHOW TABLES LIKE '" . $table . "'";
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			echo "✓ " . $table . " (" . $description . ")\n";
		} else {
			echo "✗ " . $table . " (" . $description . ") - MISSING!\n";
			$all_ok = false;
		}
	} else {
		echo "✗ Error checking " . $table . "\n";
		$all_ok = false;
	}
}

echo "\nChecking Default Data:\n";
echo "----------------------\n";

// Check Master Template
$sql = "SELECT COUNT(*) as count FROM llx_contactimport_templates WHERE ref = 'MASTER_TEMPLATE'";
$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	if ($obj->count > 0) {
		echo "✓ Master Template exists\n";
	} else {
		echo "✗ Master Template MISSING!\n";
		$all_ok = false;
		
		// Offer to install
		echo "\n⚠️  Master Template not found. Install it now? (y/n): ";
		$handle = fopen("php://stdin", "r");
		$line = fgets($handle);
		if (trim($line) == 'y' || trim($line) == 'Y') {
			$sql_insert = "INSERT INTO llx_contactimport_templates (
				ref, label, description, mapping_config, csv_separator, csv_enclosure, 
				has_header, is_default, status, date_creation, fk_user_creat, entity
			) VALUES (
				'MASTER_TEMPLATE',
				'Master Template',
				'Standard Import Template für Firmen und Kontakte',
				'{\"import_mode\":\"both\",\"create_companies\":true,\"create_contacts\":true,\"company\":{\"4\":\"nom\",\"7\":\"address\",\"9\":\"zip\",\"10\":\"town\",\"12\":\"phone\",\"13\":\"email\"},\"contact\":{\"4\":\"lastname\",\"3\":\"firstname\",\"7\":\"address\",\"9\":\"zip\",\"10\":\"town\",\"12\":\"phone\",\"11\":\"phone_mobile\",\"13\":\"email\"}}',
				';', '\"', 1, 1, 1, NOW(), 1, 1
			)";
			
			$resql_insert = $db->query($sql_insert);
			if ($resql_insert) {
				echo "✓ Master Template successfully installed!\n";
				$all_ok = true;
			} else {
				echo "✗ Failed to install Master Template: " . $db->lasterror() . "\n";
			}
		}
		fclose($handle);
	}
} else {
	echo "✗ Error checking Master Template\n";
	$all_ok = false;
}

// Check total templates
$sql = "SELECT COUNT(*) as count FROM llx_contactimport_templates";
$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	echo "  → Total Templates: " . $obj->count . "\n";
}

echo "\n===============================================\n";
if ($all_ok) {
	echo "✓ Installation Check PASSED - All OK!\n";
	exit(0);
} else {
	echo "✗ Installation Check FAILED - See errors above\n";
	exit(1);
}
