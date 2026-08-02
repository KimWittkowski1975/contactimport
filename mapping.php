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

// Read CSV headers and sample data - MUST BE BEFORE ACTIONS
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

/*
 * Actions
 */

if ($action == 'save_mapping' && !$cancel) {
	$mapping_config = array();
	
	// Get company mapping: Dolibarr field => CSV column index
	$company_mapping = array();
	$company_fields = getCompanyMappingFields();
	foreach ($company_fields as $field_key => $field_label) {
		if (empty($field_key)) continue; // Skip "None" option
		$csv_column_idx = GETPOST('company_'.$field_key, 'int');
		if ($csv_column_idx !== '' && $csv_column_idx >= 0) {
			// Store as CSV_column_index => dolibarr_field
			$company_mapping[$csv_column_idx] = $field_key;
		}
	}
	
	// Get contact mapping: Dolibarr field => CSV column index
	$contact_mapping = array();
	$contact_fields = getContactMappingFields();
	foreach ($contact_fields as $field_key => $field_label) {
		if (empty($field_key)) continue; // Skip "None" option
		$csv_column_idx = GETPOST('contact_'.$field_key, 'int');
		if ($csv_column_idx !== '' && $csv_column_idx >= 0) {
			// Store as CSV_column_index => dolibarr_field
			$contact_mapping[$csv_column_idx] = $field_key;
		}
	}
	
	// Validation
	if (empty($company_mapping) && empty($contact_mapping)) {
		$error++;
		$errors[] = $langs->trans("ErrorNoMappingDefined");
	}
	
	// Check if at least company name or contact name is mapped
	if (!empty($company_mapping) && !in_array('nom', $company_mapping)) {
		$error++;
		$errors[] = $langs->trans("ErrorCompanyNameRequired");
	}
	
	if (!empty($contact_mapping) && !in_array('lastname', $contact_mapping)) {
		$error++;
		$errors[] = $langs->trans("ErrorContactLastnameRequired");
	}
	
	if (!$error) {
		$mapping_config = array(
			'company' => $company_mapping,
			'contact' => $contact_mapping,
			'import_mode' => 'both', // Always import both
			'create_companies' => 1,
			'create_contacts' => 1,
			'link_contacts_to_companies' => 1,
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

// Mapping form
print '<form name="mapping_form" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_mapping">';

// Info box about import mode
print '<div class="info" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 5px;">';
print '<strong>ℹ️ '.$langs->trans("ImportMode").':</strong> ';
print $langs->trans("AlwaysImportBothCompanyAndContact");
print '<br><small>'.$langs->trans("EachCSVRowCreatesCompanyAndLinkedContact").'</small>';
print '</div>';

print '<br>';

// Field mapping - Two separate tables (Company + Contact)
print load_fiche_titre($langs->trans("FieldMapping"), '', '');
?>
<style>
.mapping-table-container { margin: 20px 0; }
.mapping-section-title { padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; font-size: 1.1em; border-radius: 5px 5px 0 0; margin-bottom: 0; }
.mapping-section-title.company-section { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.mapping-section-title.contact-section { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.mapping-instructions { padding: 10px; background-color: #f0f8ff; border: 1px solid #b0d4f1; border-radius: 3px; margin-bottom: 15px; font-size: 0.9em; }
</style>
<?php

print '<div class="fichecenter">';

// Instructions
print '<div class="mapping-instructions">';
print '<strong>ℹ️ '.$langs->trans("Instructions").':</strong><br>';
print '• '.$langs->trans("MapCSVColumnsToBothCompanyAndContact").'<br>';
print '• '.$langs->trans("SameCSVColumnCanBeMappedToBothTypes").'<br>';
print '• '.$langs->trans("RequiredCompanyName").'<br>';
print '• '.$langs->trans("RequiredContactLastname");
print '</div>';

// COMPANY MAPPING TABLE
print '<div class="mapping-table-container">';
print '<div class="mapping-section-title company-section">🏢 '.$langs->trans("CompanyMapping").'</div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="left" width="40%">'.$langs->trans("DolibarrCompanyField").'</th>';
print '<th class="left" width="40%">'.$langs->trans("SelectCSVColumn").'</th>';
print '<th class="center" width="20%">'.$langs->trans("SampleData").'</th>';
print '</tr>';

$company_fields = getCompanyMappingFields();

// Build CSV column options for dropdown
$csv_column_options = array('' => $langs->trans("None"));
foreach ($csv_headers as $idx => $header) {
	$csv_column_options[$idx] = $langs->trans("Column").' '.($idx + 1).': '.dol_trunc($header, 30);
}

foreach ($company_fields as $field_key => $field_label) {
	if (empty($field_key)) continue; // Skip the "None" option from field list
	
	print '<tr class="oddeven">';
	
	// Dolibarr Field Name
	print '<td><strong>'.$field_label.'</strong>';
	if ($field_key == 'nom') {
		print ' <span class="badge badge-status4">'.$langs->trans("Required").'</span>';
	}
	print '</td>';
	
	// CSV Column Dropdown
	print '<td>';
	$selected_csv_column = '';
	// Find which CSV column is mapped to this field
	if (!empty($existing_mapping['company'])) {
		foreach ($existing_mapping['company'] as $csv_idx => $mapped_field) {
			if ($mapped_field === $field_key) {
				$selected_csv_column = $csv_idx;
				break;
			}
		}
	}
	print $form->selectarray('company_'.$field_key, $csv_column_options, $selected_csv_column, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
	print '</td>';
	
	// Sample data (show sample from selected column)
	print '<td class="center" style="font-size: 0.85em; color: #666;">';
	if ($selected_csv_column !== '' && isset($sample_lines[0][$selected_csv_column])) {
		print dol_escape_htmltag(dol_trunc($sample_lines[0][$selected_csv_column], 30));
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';
	
	print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';

print '<br>';

// CONTACT MAPPING TABLE
print '<div class="mapping-table-container">';
print '<div class="mapping-section-title contact-section">👤 '.$langs->trans("ContactMapping").'</div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="left" width="40%">'.$langs->trans("DolibarrContactField").'</th>';
print '<th class="left" width="40%">'.$langs->trans("SelectCSVColumn").'</th>';
print '<th class="center" width="20%">'.$langs->trans("SampleData").'</th>';
print '</tr>';

$contact_fields = getContactMappingFields();

foreach ($contact_fields as $field_key => $field_label) {
	if (empty($field_key)) continue; // Skip the "None" option
	
	print '<tr class="oddeven">';
	
	// Dolibarr Field Name
	print '<td><strong>'.$field_label.'</strong>';
	if ($field_key == 'lastname') {
		print ' <span class="badge badge-status4">'.$langs->trans("Required").'</span>';
	}
	print '</td>';
	
	// CSV Column Dropdown
	print '<td>';
	$selected_csv_column = '';
	// Find which CSV column is mapped to this field
	if (!empty($existing_mapping['contact'])) {
		foreach ($existing_mapping['contact'] as $csv_idx => $mapped_field) {
			if ($mapped_field === $field_key) {
				$selected_csv_column = $csv_idx;
				break;
			}
		}
	}
	print $form->selectarray('contact_'.$field_key, $csv_column_options, $selected_csv_column, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
	print '</td>';
	
	// Sample data
	print '<td class="center" style="font-size: 0.85em; color: #666;">';
	if ($selected_csv_column !== '' && isset($sample_lines[0][$selected_csv_column])) {
		print dol_escape_htmltag(dol_trunc($sample_lines[0][$selected_csv_column], 30));
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';
	
	print '</tr>';
}

print '</table>';
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