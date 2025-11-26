<?php
/* Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        download.php
 * \ingroup     contactimport
 * \brief       Download CSV file
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/contactimport/class/contactimportsession.class.php');

// Load translation files required by the page
$langs->loadLangs(array("contactimport@contactimport"));

// Access control
if (empty($conf->contactimport->enabled)) {
	accessforbidden('Module not enabled');
}

// Security check
if (empty($conf->contactimport->enabled)) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('contactimport', 'contactimport', 'read')) {
	accessforbidden();
}

// Always load the newest session, ignoring any session_id parameter
$session = new ContactImportSession($db);

// Query to get the newest session
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."contactimport_sessions";
$sql .= " WHERE entity = ".$conf->entity;
$sql .= " ORDER BY date_creation DESC";
$sql .= " LIMIT 1";

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	$obj = $db->fetch_object($resql);
	$session_id = $obj->rowid;
	$db->free($resql);
	
	$result = $session->fetch($session_id);
	if ($result <= 0) {
		print 'Error: Could not load newest import session';
		exit;
	}
} else {
	print 'Error: No import sessions found';
	exit;
}

// Check if file exists
if (!file_exists($session->file_path)) {
	print 'Error: CSV file not found';
	exit;
}

// Security check - make sure file is in the uploads directory
$uploads_dir = $conf->contactimport->multidir_output[$session->entity].'/uploads/';
if (strpos(realpath($session->file_path), realpath($uploads_dir)) !== 0) {
	print 'Error: Access denied';
	exit;
}

// Get file info
$file_size = filesize($session->file_path);
$file_name = $session->filename;

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$file_name.'"');
header('Content-Length: '.$file_size);
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file content
readfile($session->file_path);

exit;