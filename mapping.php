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
 * \file        contactimport/mapping.php
 * \ingroup     contactimport
 * \brief       CSV field mapping page for Contact Import module
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

// Translations
$langs->loadLangs(array("contactimport@contactimport", "other"));

// Access control
if (empty($conf->contactimport->enabled)) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('contactimport', 'contactimport', 'write')) {
	accessforbidden();
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

// Initialize technical objects
$form = new Form($db);
$contactimportsession = new ContactImportSession($db);

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
} else {
	setEventMessages($langs->trans("ErrorSessionRequired"), null, 'errors');
	header("Location: upload.php");
	exit;
}

/*
 * Actions
 */

if ($action == 'save_mapping' && !$cancel) {
	$mapping_config = array();
	
	// Get company mapping
	$company_mapping = array();
	$company_fields = getCompanyMappingFields();
	foreach ($company_fields as $field_key => $field_label) {
		if (empty($field_key)) continue;
		$csv_column = GETPOST('company_'.$field_key, 'alpha');
		if (!empty($csv_column)) {
			$company_mapping[$field_key] = $csv_column;
		}
	}
	
	// Get contact mapping
	$contact_mapping = array();
	$contact_fields = getContactMappingFields();
	foreach ($contact_fields as $field_key => $field_label) {
		if (empty($field_key)) continue;
		$csv_column = GETPOST('contact_'.$field_key, 'alpha');
		if (!empty($csv_column)) {
			$contact_mapping[$field_key] = $csv_column;
		}
	}
	
	// Validation
	if (empty($company_mapping) && empty($contact_mapping)) {
		$error++;
		$errors[] = $langs->trans("ErrorNoMappingDefined");
	}
	
	// Check if at least company name or contact name is mapped
	if (!empty($company_mapping) && empty($company_mapping['nom'])) {
		$error++;
		$errors[] = $langs->trans("ErrorCompanyNameRequired");
	}
	
	if (!empty($contact_mapping) && empty($contact_mapping['lastname'])) {
		$error++;
		$errors[] = $langs->trans("ErrorContactLastnameRequired");
	}
	
	if (!$error) {
		$mapping_config = array(
			'company' => $company_mapping,
			'contact' => $contact_mapping,
			'import_mode' => GETPOST('import_mode', 'alpha'),
			'create_companies' => GETPOST('create_companies', 'int') ? 1 : 0,
			'create_contacts' => GETPOST('create_contacts', 'int') ? 1 : 0,
			'link_contacts_to_companies' => GETPOST('link_contacts_to_companies', 'int') ? 1 : 0,
		);
		
		$contactimportsession->setMappingConfig($mapping_config);
		$contactimportsession->status = 2; // Mapped
		
		$result = $contactimportsession->update($user);
		
		if ($result > 0) {
			setEventMessages($langs->trans("MappingSavedSuccessfully"), null, 'mesgs');
			header("Location: preview.php?id=".$id);
			exit;
		} else {
			$error++;
			$errors[] = $contactimportsession->error;
		}
	}
}

// Read CSV headers and sample data
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
$sample_lines = $csv_data['sample_lines'];

// Load existing mapping if available
$existing_mapping = $contactimportsession->getMappingConfig();

/*
 * View
 */

$page_name = "MappingInterface";
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
print '</table>';
print '</div>';

// Error messages
if ($error) {
	foreach ($errors as $errmsg) {
		setEventMessages($errmsg, null, 'errors');
	}
}

print '<br>';

// Mapping form - START EARLY before CSV preview
print '<form name="mapping_form" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_mapping">';

// Import mode selection
print load_fiche_titre($langs->trans("ImportSettings"), '', '');
print '<table class="border centpercent">';

print '<tr>';
print '<td class="titlefield">'.$langs->trans("ImportMode").'</td>';
print '<td>';
$import_modes = array(
	'company_only' => $langs->trans("CompanyOnly"),
	'contact_only' => $langs->trans("ContactOnly"),
	'both' => $langs->trans("CompanyAndContact"),
);
print $form->selectarray('import_mode', $import_modes, $existing_mapping['import_mode'] ?? 'both');
print '</td>';
print '</tr>';

print '<tr>';
print '<td>'.$langs->trans("CreateCompanies").'</td>';
print '<td>';
print $form->selectyesno('create_companies', $existing_mapping['create_companies'] ?? 1, 1);
print '</td>';
print '</tr>';

print '<tr>';
print '<td>'.$langs->trans("CreateContacts").'</td>';
print '<td>';
print $form->selectyesno('create_contacts', $existing_mapping['create_contacts'] ?? 1, 1);
print '</td>';
print '</tr>';

print '<tr>';
print '<td>'.$langs->trans("LinkContactsToCompanies").'</td>';
print '<td>';
print $form->selectyesno('link_contacts_to_companies', $existing_mapping['link_contacts_to_companies'] ?? 1, 1);
print '</td>';
print '</tr>';

print '</table>';

print '<br>';

// Build Dolibarr fields list (combined company and contact fields)
$dolibarr_fields = array();
$company_fields = getCompanyMappingFields();
$contact_fields = getContactMappingFields();

foreach ($company_fields as $field_key => $field_label) {
	if (!empty($field_key)) {
		$dolibarr_fields[] = array(
			'key' => 'company_'.$field_key,
			'label' => '['.$langs->trans("Company").'] '.$field_label,
			'type' => 'company'
		);
	}
}

foreach ($contact_fields as $field_key => $field_label) {
	if (!empty($field_key)) {
		$dolibarr_fields[] = array(
			'key' => 'contact_'.$field_key,
			'label' => '['.$langs->trans("Contact").'] '.$field_label,
			'type' => 'contact'
		);
	}
}

// Field mapping - Two column layout
print load_fiche_titre($langs->trans("FieldMapping"), '', '');
?>
<style>
.mapping-container { display: flex; gap: 30px; margin: 20px 0; }
.mapping-column { flex: 1; }
.mapping-column h3 { margin-top: 0; padding: 10px; background-color: #f5f5f5; border-radius: 3px; }
.mapping-list { list-style: none; padding: 0; margin: 0; }
.mapping-item { display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; margin-bottom: 5px; background-color: #fafafa; border-radius: 3px; }
.mapping-item:hover { background-color: #f0f0f0; }
.mapping-csv-header { font-weight: bold; flex: 0 0 35%; padding-right: 15px; word-break: break-word; color: #0066cc; }
.mapping-select { flex: 1; }
.mapping-select select { width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
</style>
<?php

print '<div class="fichecenter">';
print '<div class="mapping-container">';

// Left column - CSV Headers
print '<div class="mapping-column">';
print '<h3>'.$langs->trans("CSVColumns").'</h3>';
print '<p style="color: #666; font-size: 0.9em;">'.$langs->trans("TotalColumns").': <strong>'.count($csv_headers).'</strong></p>';
print '<ul class="mapping-list">';

foreach ($csv_headers as $idx => $header) {
	print '<li class="mapping-item">';
	print '<div class="mapping-csv-header">'.dol_escape_htmltag($header).'<br><small style="color: #999;">Col '.($idx + 1).'</small></div>';
	print '</li>';
}

print '</ul>';
print '</div>';

// Right column - Dolibarr Fields with dropdowns
print '<div class="mapping-column">';
print '<h3>'.$langs->trans("DolibarrFields").'</h3>';
print '<p style="color: #666; font-size: 0.9em;">'.$langs->trans("SelectFieldMapping").'</p>';

$csv_column_options = array('' => '-- '.$langs->trans("None").' --');
foreach ($csv_headers as $idx => $header) {
	$csv_column_options[$idx] = $header.' (Col '.($idx + 1).')';
}

print '<ul class="mapping-list">';

foreach ($dolibarr_fields as $field) {
	$field_key = $field['key'];
	$field_label = $field['label'];
	
	// Determine selected value based on existing mapping
	$selected_column = '';
	if ($field['type'] === 'company') {
		$company_key = str_replace('company_', '', $field_key);
		$selected_column = $existing_mapping['company'][$company_key] ?? '';
	} else {
		$contact_key = str_replace('contact_', '', $field_key);
		$selected_column = $existing_mapping['contact'][$contact_key] ?? '';
	}
	
	print '<li class="mapping-item">';
	print '<div class="mapping-select">';
	print '<strong style="display: block; margin-bottom: 5px; color: #333;">'.dol_escape_htmltag($field_label).'</strong>';
	print $form->selectarray($field_key, $csv_column_options, $selected_column, 0, 0, 0, '', 1);
	print '</div>';
	print '</li>';
}

print '</ul>';
print '</div>';

print '</div>';
print '</div>';

print '<br>';

// Optional CSV Preview (collapsible)
print '<details style="margin: 20px 0; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 3px;">';
print '<summary style="cursor: pointer; font-weight: bold; color: #0066cc;">📋 '.$langs->trans("CSVPreview").' (Click to expand)</summary>';
print '<div style="margin-top: 15px;">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

// Headers
print '<tr class="liste_titre">';
foreach ($csv_headers as $idx => $header) {
	print '<th class="center" style="font-size: 0.85em;">'.dol_escape_htmltag($header).'<br><small>('.$langs->trans("Column").' '.($idx + 1).')</small></th>';
}
print '</tr>';

// Sample data (only first 3 rows to keep it compact)
$preview_limit = min(3, count($sample_lines));
for ($line_idx = 0; $line_idx < $preview_limit; $line_idx++) {
	$line = $sample_lines[$line_idx];
	print '<tr class="oddeven">';
	foreach ($line as $cell_idx => $cell) {
		print '<td class="center" style="font-size: 0.85em; max-width: 150px; overflow: hidden; text-overflow: ellipsis;">'.dol_escape_htmltag($cell).'</td>';
	}
	// Fill missing cells
	for ($i = count($line); $i < count($csv_headers); $i++) {
		print '<td></td>';
	}
	print '</tr>';
}

print '</table>';
print '</div>';
print '<p style="color: #666; font-size: 0.85em; margin-top: 10px;">Showing first '.$preview_limit.' rows of '.$contactimportsession->total_lines.' total rows</p>';
print '</div>';
print '</details>';

print '<br>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("SaveMapping").'">';
print '&nbsp;&nbsp;&nbsp;';
print '<a href="upload.php" class="button button-cancel">'.$langs->trans("Cancel").'</a>';
print '</div>';

print '</form>';

// Page end
llxFooter();
$db->close();