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
 * \file        contactimport/preview.php
 * \ingroup     contactimport
 * \brief       Preview page for Contact Import module
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
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $conf, $db;

// Libraries
require_once './lib/contactimport.lib.php';
require_once './class/contactimportsession.class.php';
require_once './class/contactimportprocessor.class.php';

// Translations
$langs->loadLangs(array("contactimport@contactimport", "other"));

// Security check
if (empty($conf->contactimport->enabled)) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('contactimport', 'contactimport', 'read')) {
	accessforbidden();
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

// Initialize technical objects
$form = new Form($db);
$contactimportsession = new ContactImportSession($db);
$processor = new ContactImportProcessor($db);

$error = 0;
$errors = array();

// Load session
if ($id > 0) {
	$result = $contactimportsession->fetch($id);
	if ($result <= 0) {
		setEventMessages($langs->trans("ErrorRecordNotFound"), null, 'errors');
		header("Location: upload.php");
		exit;
	}
	
	// Check if file still exists
	if (!file_exists($contactimportsession->file_path)) {
		setEventMessages($langs->trans("ErrorFileNotFound"), null, 'errors');
		header("Location: upload.php");
		exit;
	}
	
	// Check if mapping exists
	$mapping_config = $contactimportsession->getMappingConfig();
	if (empty($mapping_config)) {
		setEventMessages($langs->trans("ErrorMappingRequired"), null, 'errors');
		header("Location: mapping.php?id=".$id);
		exit;
	}
} else {
	setEventMessages($langs->trans("ErrorSessionRequired"), null, 'errors');
	header("Location: upload.php");
	exit;
}

/*
 * Actions
 */

if ($action == 'start_import' && !$cancel) {
	if ($user->hasRight('contactimport', 'contactimport', 'write')) {
		$contactimportsession->status = 3; // Processing
		$contactimportsession->update($user);
		
		// Redirect to import processing
		header("Location: process.php?id=".$id);
		exit;
	} else {
		$error++;
		$errors[] = $langs->trans("ErrorInsufficientPermissions");
	}
}

// Read CSV data and generate preview
$csv_data = validateCSVFile(
	$contactimportsession->file_path, 
	$contactimportsession->csv_separator, 
	$contactimportsession->csv_enclosure
);

if (!$csv_data['status']) {
	setEventMessages($langs->trans("ErrorReadingCSVFile").': '.$csv_data['message'], null, 'errors');
	header("Location: upload.php");
	exit;
}

$csv_headers = $csv_data['headers'];
$mapping_config = $contactimportsession->getMappingConfig();

// Generate preview data
$preview_data = $processor->generatePreview($contactimportsession, 10); // Preview first 10 rows

/*
 * View
 */

$page_name = "ImportPreview";
llxHeader('', $langs->trans($page_name));

print load_fiche_titre($langs->trans($page_name), '', 'contactimport@contactimport');

// Session info
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.$contactimportsession->ref.'</td></tr>';
print '<tr><td>'.$langs->trans("Label").'</td><td>'.$contactimportsession->label.'</td></tr>';
print '<tr><td>'.$langs->trans("Filename").'</td><td>'.$contactimportsession->filename.'</td></tr>';
print '<tr><td>'.$langs->trans("TotalLines").'</td><td>'.$contactimportsession->total_lines.'</td></tr>';
print '<tr><td>'.$langs->trans("Status").'</td><td>'.$contactimportsession->getLibStatut().'</td></tr>';
print '</table>';
print '</div>';

// Error messages
if ($error) {
	foreach ($errors as $errmsg) {
		setEventMessages($errmsg, null, 'errors');
	}
}

print '<br>';

// Import configuration summary
print load_fiche_titre($langs->trans("ImportConfiguration"), '', '');
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("ImportMode").'</td><td>';
$import_modes = array(
	'company_only' => $langs->trans("CompanyOnly"),
	'contact_only' => $langs->trans("ContactOnly"),
	'both' => $langs->trans("CompanyAndContact"),
);
print $import_modes[$mapping_config['import_mode']] ?? $langs->trans("Unknown");
print '</td></tr>';
print '<tr><td>'.$langs->trans("CreateCompanies").'</td><td>'.($mapping_config['create_companies'] ? $langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
print '<tr><td>'.$langs->trans("CreateContacts").'</td><td>'.($mapping_config['create_contacts'] ? $langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
print '<tr><td>'.$langs->trans("LinkContactsToCompanies").'</td><td>'.($mapping_config['link_contacts_to_companies'] ? $langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
print '</table>';

print '<br>';

// Preview warnings and statistics
if (!empty($preview_data['warnings'])) {
	print '<div class="warning">';
	print '<strong>'.$langs->trans("ImportWarnings").':</strong><br>';
	foreach ($preview_data['warnings'] as $warning) {
		print '• '.$warning.'<br>';
	}
	print '</div><br>';
}

// Statistics
print load_fiche_titre($langs->trans("ImportStatistics"), '', '');
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("TotalLines").'</td><td>'.$contactimportsession->total_lines.'</td></tr>';
print '<tr><td>'.$langs->trans("CompaniesToCreate").'</td><td>'.$preview_data['stats']['companies_to_create'].'</td></tr>';
print '<tr><td>'.$langs->trans("ContactsToCreate").'</td><td>'.$preview_data['stats']['contacts_to_create'].'</td></tr>';
print '<tr><td>'.$langs->trans("PotentialErrors").'</td><td>'.$preview_data['stats']['potential_errors'].'</td></tr>';
print '</table>';

print '<br>';

// Data preview
print load_fiche_titre($langs->trans("DataPreview").' ('.$langs->trans("First10Lines").')', '', '');

if ($mapping_config['import_mode'] === 'company_only' || $mapping_config['import_mode'] === 'both') {
	print '<h4>'.$langs->trans("Companies").'</h4>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	
	// Company headers
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Line").'</th>';
	foreach ($mapping_config['company'] as $field => $csv_column) {
		$company_fields = getCompanyMappingFields();
		print '<th>'.$company_fields[$field].'</th>';
	}
	print '<th>'.$langs->trans("Status").'</th>';
	print '</tr>';
	
	// Company preview data
	foreach ($preview_data['companies'] as $line_num => $company_data) {
		print '<tr class="oddeven">';
		print '<td>'.($line_num + 1).'</td>';
		foreach ($mapping_config['company'] as $field => $csv_column) {
			$value = $company_data['data'][$field] ?? '';
			print '<td>'.dol_escape_htmltag($value).'</td>';
		}
		print '<td>';
		if (!empty($company_data['errors'])) {
			print '<span class="badge badge-danger">'.$langs->trans("Error").'</span>';
		} elseif (!empty($company_data['warnings'])) {
			print '<span class="badge badge-warning">'.$langs->trans("Warning").'</span>';
		} else {
			print '<span class="badge badge-success">'.$langs->trans("OK").'</span>';
		}
		print '</td>';
		print '</tr>';
	}
	
	print '</table>';
	print '</div>';
	print '<br>';
}

if ($mapping_config['import_mode'] === 'contact_only' || $mapping_config['import_mode'] === 'both') {
	print '<h4>'.$langs->trans("Contacts").'</h4>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	
	// Contact headers
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Line").'</th>';
	foreach ($mapping_config['contact'] as $field => $csv_column) {
		$contact_fields = getContactMappingFields();
		print '<th>'.$contact_fields[$field].'</th>';
	}
	print '<th>'.$langs->trans("Status").'</th>';
	print '</tr>';
	
	// Contact preview data
	foreach ($preview_data['contacts'] as $line_num => $contact_data) {
		print '<tr class="oddeven">';
		print '<td>'.($line_num + 1).'</td>';
		foreach ($mapping_config['contact'] as $field => $csv_column) {
			$value = $contact_data['data'][$field] ?? '';
			print '<td>'.dol_escape_htmltag($value).'</td>';
		}
		print '<td>';
		if (!empty($contact_data['errors'])) {
			print '<span class="badge badge-danger">'.$langs->trans("Error").'</span>';
		} elseif (!empty($contact_data['warnings'])) {
			print '<span class="badge badge-warning">'.$langs->trans("Warning").'</span>';
		} else {
			print '<span class="badge badge-success">'.$langs->trans("OK").'</span>';
		}
		print '</td>';
		print '</tr>';
	}
	
	print '</table>';
	print '</div>';
}

print '<br>';

// Action buttons
print '<div class="center">';
if ($contactimportsession->status < 3 && $user->hasRight('contactimport', 'contactimport', 'write')) {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" style="display: inline;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="start_import">';
	print '<input type="submit" class="button" value="'.$langs->trans("StartImport").'" onclick="return confirm(\''.$langs->trans("ConfirmStartImport").'\');">';
	print '</form>';
	print '&nbsp;&nbsp;&nbsp;';
}

print '<a href="mapping.php?id='.$id.'" class="button button-cancel">'.$langs->trans("ModifyMapping").'</a>';
print '&nbsp;&nbsp;&nbsp;';
print '<a href="upload.php" class="button">'.$langs->trans("BackToUpload").'</a>';
print '</div>';

// Page end
llxFooter();
$db->close();