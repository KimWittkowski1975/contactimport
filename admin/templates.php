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
 * \file        contactimport/admin/templates.php
 * \ingroup     contactimport
 * \brief       Template mapping configuration for automated imports
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; 
$tmp2 = realpath(__FILE__); 
$i = strlen($tmp) - 1; 
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
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

global $langs, $user, $conf, $db;

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/contactimport.lib.php';
require_once '../class/contactimporttemplate.class.php';
dol_include_once('/contactimport/class/contactimportprocessor.class.php');

// Translations
$langs->loadLangs(array("admin", "contactimport@contactimport"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$template_id = GETPOST('id', 'int');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize objects
$template = new ContactImportTemplate($db);

/*
 * Actions
 */

// Handle CSV file upload
if ($action == 'upload_sample' && !empty($_FILES['sample_csv']['name'])) {
	$upload_dir = DOL_DATA_ROOT.'/contactimport/samples';
	if (!file_exists($upload_dir)) {
		dol_mkdir($upload_dir);
	}
	
	$filename = 'sample_'.dol_sanitizeFileName($_FILES['sample_csv']['name']);
	$filepath = $upload_dir.'/'.$filename;
	
	if (move_uploaded_file($_FILES['sample_csv']['tmp_name'], $filepath)) {
		setEventMessages($langs->trans('FileUploadedSuccessfully'), null, 'mesgs');
		$_SESSION['contactimport_sample_file'] = $filepath;
		// Store CSV settings in session
		$_SESSION['contactimport_csv_separator'] = GETPOST('csv_separator', 'alpha');
		$_SESSION['contactimport_csv_enclosure'] = GETPOST('csv_enclosure', 'alpha');
		$_SESSION['contactimport_has_header'] = GETPOST('has_header', 'int');
		header('Location: '.$_SERVER['PHP_SELF'].'?action=create');
		exit;
	} else {
		setEventMessages($langs->trans('ErrorCannotUploadFile'), null, 'errors');
	}
}

// Clear sample file from session
if ($action == 'clear_sample') {
	unset($_SESSION['contactimport_sample_file']);
	unset($_SESSION['contactimport_csv_separator']);
	unset($_SESSION['contactimport_csv_enclosure']);
	unset($_SESSION['contactimport_has_header']);
	header('Location: '.$_SERVER['PHP_SELF'].'?action=create');
	exit;
}

// Save template
if ($action == 'save' && !GETPOST('cancel', 'alpha')) {
	$db->begin();
	
	$error = 0;
	
	// Get mapping configuration from POST (new format: company_fieldname, contact_fieldname)
	$company_mapping = array();
	$contact_mapping = array();
	
	// Get company fields
	$company_fields = getCompanyMappingFields();
	foreach ($company_fields as $field_key => $field_label) {
		if (empty($field_key)) continue;
		$post_key = 'company_'.$field_key;
		$csv_column = GETPOST($post_key, 'int');
		if ($csv_column !== '' && $csv_column >= 0) {
			// Store as CSV_column => dolibarr_field
			$company_mapping[$csv_column] = $field_key;
		}
	}
	
	// Get contact fields
	$contact_fields = getContactMappingFields();
	foreach ($contact_fields as $field_key => $field_label) {
		if (empty($field_key)) continue;
		$post_key = 'contact_'.$field_key;
		$csv_column = GETPOST($post_key, 'int');
		if ($csv_column !== '' && $csv_column >= 0) {
			// Store as CSV_column => dolibarr_field
			$contact_mapping[$csv_column] = $field_key;
		}
	}
	
	// Get import mode and creation flags
	$import_mode = GETPOST('import_mode', 'alpha');
	if (empty($import_mode)) $import_mode = 'both';
	
	$create_companies = GETPOST('create_companies', 'int') ? true : false;
	$create_contacts = GETPOST('create_contacts', 'int') ? true : false;
	
	// Set defaults based on import mode
	if ($import_mode === 'company_only') {
		$create_companies = true;
		$create_contacts = false;
	} elseif ($import_mode === 'contact_only') {
		$create_companies = false;
		$create_contacts = true;
	} else {
		// both mode - use checkboxes
		if (!$create_companies && !$create_contacts) {
			$create_companies = true;
			$create_contacts = true;
		}
	}
	
	$mapping_config = array(
		'import_mode' => $import_mode,
		'create_companies' => $create_companies,
		'create_contacts' => $create_contacts,
		'company' => $company_mapping,
		'contact' => $contact_mapping
	);
	
	if ($template_id > 0) {
		// Update existing template
		$template->fetch($template_id);
		$template->label = GETPOST('label', 'alpha');
		$template->description = GETPOST('description', 'restricthtml');
		$template->mapping_config = json_encode($mapping_config);
		$template->csv_separator = GETPOST('csv_separator', 'alpha');
		$template->csv_enclosure = GETPOST('csv_enclosure', 'alpha');
		$template->has_header = GETPOST('has_header', 'int');
		$template->is_default = GETPOST('is_default', 'int');
		
		$result = $template->update($user);
	} else {
		// Create new template
		$template->ref = 'TEMPLATE_'.dol_now();
		$template->label = GETPOST('label', 'alpha');
		$template->description = GETPOST('description', 'restricthtml');
		$template->mapping_config = json_encode($mapping_config);
		$template->csv_separator = GETPOST('csv_separator', 'alpha');
		$template->csv_enclosure = GETPOST('csv_enclosure', 'alpha');
		$template->has_header = GETPOST('has_header', 'int');
		$template->is_default = GETPOST('is_default', 'int');
		
		$result = $template->create($user);
	}
	
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans("TemplateSaved"), null, 'mesgs');
		$action = '';
		$template_id = $result;
	} else {
		$db->rollback();
		setEventMessages($template->error, null, 'errors');
		$error++;
	}
}

// Delete template
if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
	if ($template_id > 0) {
		$template->fetch($template_id);
		$result = $template->delete($user);
		if ($result > 0) {
			setEventMessages($langs->trans("TemplateDeleted"), null, 'mesgs');
			$action = '';
			$template_id = 0;
		} else {
			setEventMessages($template->error, null, 'errors');
		}
	}
}

// Manual FTP download and import
if ($action == 'ftp_download') {
	// Increase execution time limit for FTP download and import process
	// This can take a long time with large files or many records
	@set_time_limit(300); // 5 minutes
	ini_set('max_execution_time', '300');
	
	require_once '../class/contactimportftp.class.php';
	
	$ftp = new ContactImportFTP($db);
	$result = $ftp->downloadFiles();
	
	if ($result > 0) {
		setEventMessages($langs->trans("FilesDownloaded", $result), null, 'mesgs');
		
		// Always try to import downloaded files
		$auto_import_result = $ftp->autoImportDownloadedFiles();
		
		if ($auto_import_result > 0) {
			// Success message with details
			setEventMessages($langs->trans("ImportSuccessful").' - '.$auto_import_result.' '.$langs->trans("FilesImported"), null, 'mesgs');
			
			// Get import statistics from last sessions
			$sql = "SELECT s.rowid, s.ref, s.filename, s.total_lines, s.processed_lines, s.success_lines, s.error_lines";
			$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions as s";
			$sql .= " WHERE s.ref LIKE 'AUTO_%'";
			$sql .= " ORDER BY s.date_creation DESC";
			$sql .= " LIMIT ".$auto_import_result;
			
			$resql = $db->query($sql);
			if ($resql) {
				$details = array();
				while ($obj = $db->fetch_object($resql)) {
					if ($obj->error_lines > 0) {
						$details[] = $obj->filename.': '.$obj->success_lines.' '.$langs->trans("SuccessLines").', '.$obj->error_lines.' '.$langs->trans("ErrorLines");
					} else {
						$details[] = $obj->filename.': '.$obj->success_lines.' '.$langs->trans("SuccessLines");
					}
				}
				if (!empty($details)) {
					setEventMessages(implode('<br>', $details), null, 'mesgs');
				}
			}
			
		} elseif ($auto_import_result == 0) {
			setEventMessages($langs->trans("ImportWarning").' - '.$langs->trans("NoFilesToImport"), null, 'warnings');
		} else {
			// Error occurred
			setEventMessages($langs->trans("ImportFailed").' - '.$ftp->error, null, 'errors');
		}
		
	} elseif ($result == 0) {
		setEventMessages($langs->trans("NoFilesFound"), null, 'warnings');
	} else {
		setEventMessages($langs->trans("FTPDownloadFailed").' - '.$ftp->error, null, 'errors');
	}
	$action = '';
}

// Download the latest file from FTP/SFTP and show a dry-run preview, WITHOUT writing anything to the database yet
$preview_session_id = 0;
$preview_data = null;
$preview_session = null;
if ($action == 'ftp_preview') {
	@set_time_limit(300);
	ini_set('max_execution_time', '300');

	require_once '../class/contactimportftp.class.php';

	$template_for_preview = new ContactImportTemplate($db);
	$default_template_id = $template_for_preview->getDefaultTemplate();

	if (!$default_template_id) {
		setEventMessages($langs->trans("NoDefaultTemplateFound"), null, 'errors');
	} else {
		$ftp = new ContactImportFTP($db);
		$result = $ftp->downloadFiles();

		if ($result > 0 && !empty($ftp->lastDownloadedFilePath) && is_file($ftp->lastDownloadedFilePath)) {
			$template_for_preview->fetch($default_template_id);

			$session = new ContactImportSession($db);
			$session->ref = 'PREVIEW_'.dol_now();
			$session->label = $langs->trans("FTPPreviewSessionLabel", $ftp->lastDownloadedFilename);
			$session->description = $langs->trans("FTPPreviewSessionDescription");
			$session->filename = $ftp->lastDownloadedFilename;
			$session->file_path = $ftp->lastDownloadedFilePath;
			$session->file_size = filesize($ftp->lastDownloadedFilePath);
			$session->csv_separator = $template_for_preview->csv_separator;
			$session->csv_enclosure = $template_for_preview->csv_enclosure;
			$session->has_header = $template_for_preview->has_header;
			$session->total_lines = max(0, count(file($ftp->lastDownloadedFilePath)) - ($template_for_preview->has_header ? 1 : 0));
			$session->status = 2; // Mapped
			$session->mapping_config = $template_for_preview->mapping_config;

			$new_session_id = $session->create($user);

			if ($new_session_id > 0) {
				$session->fetch($new_session_id);
				$processor = new ContactImportProcessor($db);
				// generatePreview() only reads the CSV and runs SELECT queries - it never writes to the database
				$preview_data = $processor->generatePreview($session, $session->total_lines);
				$preview_session_id = $new_session_id;
				$preview_session = $session;
				$action = 'ftp_preview_result';
			} else {
				setEventMessages($langs->trans("ErrorCreatingPreviewSession"), null, 'errors');
				$action = '';
			}
		} elseif ($result == 0) {
			setEventMessages($langs->trans("NoFilesFound"), null, 'warnings');
			$action = '';
		} else {
			setEventMessages($langs->trans("FTPDownloadFailed").' - '.$ftp->error, null, 'errors');
			$action = '';
		}
	}
}

// User confirmed the preview - run the real import now
if ($action == 'ftp_confirm_import') {
	@set_time_limit(300);
	ini_set('max_execution_time', '300');

	$session_id = GETPOST('session_id', 'int');
	$session = new ContactImportSession($db);

	if ($session_id > 0 && $session->fetch($session_id) > 0) {
		$processor = new ContactImportProcessor($db);
		$import_result = $processor->processImport($session, $user);

		if (!empty($import_result['success'])) {
			// Single, clear summary instead of scattered messages
			$summary = $langs->trans("ImportSummaryLine",
				$import_result['companies_created'],
				$import_result['companies_updated'],
				$import_result['contacts_created'],
				$import_result['contacts_updated'],
				$import_result['errors']
			);
			if ($import_result['errors'] > 0) {
				setEventMessages($summary, null, 'warnings');
			} else {
				setEventMessages($summary, null, 'mesgs');
			}

			// Delete the local file if configured to do so after import
			if (getDolGlobalString('CONTACTIMPORT_FTP_DELETE_AFTER_IMPORT') && !empty($session->file_path) && is_file($session->file_path)) {
				@unlink($session->file_path);
			}
		} else {
			setEventMessages($langs->trans("ImportFailed").' - '.implode(', ', (array) ($import_result['messages'] ?? array())), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans("ErrorSessionNotFound"), null, 'errors');
	}

	$action = '';
}

// User cancelled the preview - nothing was written to the database, just clean up the downloaded file and session
if ($action == 'ftp_preview_cancel') {
	$session_id = GETPOST('session_id', 'int');
	$session = new ContactImportSession($db);

	if ($session_id > 0 && $session->fetch($session_id) > 0) {
		if (!empty($session->file_path) && is_file($session->file_path)) {
			@unlink($session->file_path);
		}
		$session->delete($user);
		setEventMessages($langs->trans("PreviewCancelled"), null, 'mesgs');
	}

	$action = '';
}

/*
 * View
 */

$page_name = "ImportTemplates";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'templates', $langs->trans("ContactImportSetup"), -1, 'contactimport@contactimport');

print info_admin($langs->trans("ImportTemplatesDescription"));

// Load template if ID specified
if ($template_id > 0 && $action != 'create') {
	$template->fetch($template_id);
}

// Create/Edit form
if ($action == 'create' || $action == 'edit' || ($template_id > 0 && $action != 'list')) {
	
	// Load sample CSV file to show headers
	$sample_file = '';
	if (!empty($_SESSION['contactimport_sample_file']) && file_exists($_SESSION['contactimport_sample_file'])) {
		$sample_file = $_SESSION['contactimport_sample_file'];
	} elseif (!empty($template->sample_file_path) && file_exists($template->sample_file_path)) {
		$sample_file = $template->sample_file_path;
	}
	
	$csv_headers = array();
	
	if (!empty($sample_file) && file_exists($sample_file)) {
		$handle = fopen($sample_file, 'r');
		// Use session settings if available, otherwise use template or defaults
		$csv_separator = !empty($_SESSION['contactimport_csv_separator']) ? $_SESSION['contactimport_csv_separator'] : ($template->csv_separator ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_SEPARATOR', ';'));
		$csv_enclosure = !empty($_SESSION['contactimport_csv_enclosure']) ? $_SESSION['contactimport_csv_enclosure'] : ($template->csv_enclosure ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE', '"'));
		
		// Read first line
		$csv_headers = fgetcsv($handle, 0, $csv_separator, $csv_enclosure);
		fclose($handle);
		
		// Convert encoding to UTF-8 if needed
		if (!empty($csv_headers)) {
			foreach ($csv_headers as $key => $value) {
				// Detect if string is not UTF-8 and convert
				if (!mb_check_encoding($value, 'UTF-8')) {
					// Try common encodings: Windows-1252, ISO-8859-1
					$detected = mb_detect_encoding($value, array('UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'), true);
					if ($detected && $detected !== 'UTF-8') {
						$csv_headers[$key] = mb_convert_encoding($value, 'UTF-8', $detected);
					} else {
						// Fallback to ISO-8859-1
						$csv_headers[$key] = utf8_encode($value);
					}
				}
			}
		}
	}
	
	// Decode existing mapping
	$mapping = array('company' => array(), 'contact' => array());
	if (!empty($template->mapping_config)) {
		$mapping = json_decode($template->mapping_config, true);
	}
	
	// CSV Upload form - show if no file uploaded yet OR user wants to change
	if ($action == 'create') {
		if (empty($csv_headers)) {
			print '<div class="info" style="margin-bottom: 20px;">';
			print '<strong>'.$langs->trans("UploadSampleCSV").':</strong> '.$langs->trans("UploadSampleCSVDescription");
			print '</div>';
		} else {
			print '<div class="info" style="margin-bottom: 20px;">';
			print '<strong>'.$langs->trans("CurrentSampleFile").':</strong> '.basename($sample_file);
			print ' &nbsp; <a href="'.$_SERVER["PHP_SELF"].'?action=clear_sample&token='.newToken().'" class="button">'.$langs->trans("UploadDifferentFile").'</a>';
			print '</div>';
		}
		
		if (empty($csv_headers)) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="upload_sample">';
			
			print '<table class="border centpercent">';
			
			// CSV Settings FIRST
			print '<tr>';
			print '<td class="titlefieldcreate">'.$langs->trans("CSVSeparator").'</td>';
			print '<td>';
			$separators = getCSVSeparatorOptions();
			print $form->selectarray('csv_separator', $separators, getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_SEPARATOR', ';'));
			print '</td>';
			print '</tr>';
			
			print '<tr>';
			print '<td>'.$langs->trans("CSVEnclosure").'</td>';
			print '<td>';
			$enclosures = getCSVEnclosureOptions();
			print $form->selectarray('csv_enclosure', $enclosures, getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE', '"'));
			print '</td>';
			print '</tr>';
			
			print '<tr>';
			print '<td>'.$langs->trans("HasHeader").'</td>';
			print '<td>'.$form->selectyesno('has_header', 1, 1).'</td>';
			print '</tr>';
			
			// Then file upload
			print '<tr>';
			print '<td class="fieldrequired">'.$langs->trans("SampleCSVFile").'</td>';
			print '<td>';
			print '<input type="file" name="sample_csv" accept=".csv,.txt" required>';
			print '</td>';
			print '</tr>';
			
			print '</table>';
			
			print '<br><div class="center">';
			print '<input type="submit" class="button" value="'.$langs->trans("UploadFile").'">';
			print '</div>';
			print '</form>';
			
			return; // Stop here, show upload form only
		}
	}
	
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	if ($template_id > 0) {
		print '<input type="hidden" name="id" value="'.$template_id.'">';
	}
	
	print '<table class="border centpercent">';
	
	// Template name
	print '<tr>';
	print '<td class="titlefieldcreate fieldrequired">'.$langs->trans("Label").'</td>';
	print '<td><input type="text" name="label" class="flat minwidth300" value="'.dol_escape_htmltag($template->label).'" required></td>';
	print '</tr>';
	
	// Description
	print '<tr>';
	print '<td class="titlefieldcreate">'.$langs->trans("Description").'</td>';
	print '<td><textarea name="description" class="flat minwidth300" rows="3">'.dol_escape_htmltag($template->description).'</textarea></td>';
	print '</tr>';
	
	// CSV Settings (read-only display, already set during upload)
	print '<tr>';
	print '<td>'.$langs->trans("CSVSeparator").'</td>';
	print '<td>';
	$csv_sep_display = !empty($_SESSION['contactimport_csv_separator']) ? $_SESSION['contactimport_csv_separator'] : ($template->csv_separator ?: ';');
	print '<strong>'.$csv_sep_display.'</strong>';
	print '<input type="hidden" name="csv_separator" value="'.dol_escape_htmltag($csv_sep_display).'">';
	print '</td>';
	print '</tr>';
	
	print '<tr>';
	print '<td>'.$langs->trans("CSVEnclosure").'</td>';
	print '<td>';
	$csv_enc_display = !empty($_SESSION['contactimport_csv_enclosure']) ? $_SESSION['contactimport_csv_enclosure'] : ($template->csv_enclosure ?: '"');
	print '<strong>'.$csv_enc_display.'</strong>';
	print '<input type="hidden" name="csv_enclosure" value="'.dol_escape_htmltag($csv_enc_display).'">';
	print '</td>';
	print '</tr>';
	
	print '<tr>';
	print '<td>'.$langs->trans("HasHeader").'</td>';
	print '<td>';
	$has_header_val = isset($_SESSION['contactimport_has_header']) ? $_SESSION['contactimport_has_header'] : (isset($template->has_header) ? $template->has_header : 1);
	print '<strong>'.($has_header_val ? $langs->trans('Yes') : $langs->trans('No')).'</strong>';
	print '<input type="hidden" name="has_header" value="'.$has_header_val.'">';
	print '</td>';
	print '</tr>';
	
	print '<tr>';
	print '<td>'.$langs->trans("DefaultTemplate").'</td>';
	print '<td>'.$form->selectyesno('is_default', $template->is_default, 1).'</td>';
	print '</tr>';
	
	print '</table>';
	
	// Import Mode Configuration
	print '<br><br>';
	print '<h3>'.$langs->trans("ImportMode").' <span class="badge badge-status4 badge-status" style="background-color: #d00; color: white;">'.$langs->trans("Required").'</span></h3>';
	
	$import_mode = isset($mapping['import_mode']) ? $mapping['import_mode'] : 'both';
	$create_companies = isset($mapping['create_companies']) ? $mapping['create_companies'] : true;
	$create_contacts = isset($mapping['create_contacts']) ? $mapping['create_contacts'] : true;
	
	print '<table class="border centpercent">';
	print '<tr>';
	print '<td class="fieldrequired" width="30%">'.$langs->trans("ImportWhat").'</td>';
	print '<td>';
	print '<input type="radio" name="import_mode" value="both" id="mode_both" '.($import_mode === 'both' ? 'checked' : '').'> ';
	print '<label for="mode_both">'.$langs->trans("CompaniesAndContacts").'</label><br>';
	print '<input type="radio" name="import_mode" value="company_only" id="mode_company" '.($import_mode === 'company_only' ? 'checked' : '').'> ';
	print '<label for="mode_company">'.$langs->trans("CompaniesOnly").'</label><br>';
	print '<input type="radio" name="import_mode" value="contact_only" id="mode_contact" '.($import_mode === 'contact_only' ? 'checked' : '').'> ';
	print '<label for="mode_contact">'.$langs->trans("ContactsOnly").'</label>';
	print '</td>';
	print '</tr>';
	
	print '<tr id="create_options_row">';
	print '<td>'.$langs->trans("CreateOptions").'</td>';
	print '<td>';
	print '<input type="checkbox" name="create_companies" value="1" id="create_companies" '.($create_companies ? 'checked' : '').'> ';
	print '<label for="create_companies">'.$langs->trans("CreateCompanies").'</label><br>';
	print '<input type="checkbox" name="create_contacts" value="1" id="create_contacts" '.($create_contacts ? 'checked' : '').'> ';
	print '<label for="create_contacts">'.$langs->trans("CreateContacts").'</label>';
	print '</td>';
	print '</tr>';
	print '</table>';
	
	?>
	<script>
	$(document).ready(function() {
		function updateCreateOptions() {
			var mode = $("input[name=import_mode]:checked").val();
			if (mode === "company_only") {
				$("#create_companies").prop("checked", true).prop("disabled", true);
				$("#create_contacts").prop("checked", false).prop("disabled", true);
			} else if (mode === "contact_only") {
				$("#create_companies").prop("checked", false).prop("disabled", true);
				$("#create_contacts").prop("checked", true).prop("disabled", true);
			} else {
				$("#create_companies").prop("disabled", false);
				$("#create_contacts").prop("disabled", false);
			}
			
			// Update required field highlighting
			updateRequiredFields(mode);
		}
		
		function updateRequiredFields(mode) {
			// Reset all required markers
			$('.mapping-item').removeClass('required');
			
			// Company name is ALWAYS required
			$('.mapping-item[data-field="company_nom"]').addClass('required');
			
			// Contact lastname is required for both and contact_only modes
			if (mode === 'both' || mode === 'contact_only') {
				$('.mapping-item[data-field="contact_lastname"]').addClass('required');
			}
		}
		
		$("input[name=import_mode]").change(updateCreateOptions);
		updateCreateOptions();
	});
	</script>
	<?php
	
	// Mapping configuration
	print '<br><br>';
	print '<h3>'.$langs->trans("FieldMapping").'</h3>';
	
	if (!empty($csv_headers)) {
		// Build CSV column options
		$csv_column_options = array('' => '-- '.$langs->trans("IgnoreColumn").' --');
		foreach ($csv_headers as $idx => $header) {
			$csv_column_options[$idx] = $header.' ('.$langs->trans("Column").' '.($idx + 1).')';
		}
		
		// Build combined Dolibarr fields list with required fields first
		$company_fields = getCompanyMappingFields();
		$contact_fields = getContactMappingFields();
		
		// Define required fields based on import mode
		$required_fields = array(
			'company' => array(),
			'contact' => array()
		);
		
		// Company name is ALWAYS required (even for contact-only imports)
		// because Dolibarr requires every contact to have a company
		$required_fields['company'][] = 'nom';
		
		// Contact lastname is required when importing contacts
		if ($import_mode === 'both' || $import_mode === 'contact_only') {
			$required_fields['contact'][] = 'lastname';
		}
		
		$dolibarr_fields = array();
		
		// Add required company fields first
		foreach ($required_fields['company'] as $req_field) {
			if (isset($company_fields[$req_field])) {
				$dolibarr_fields[] = array(
					'key' => 'company_'.$req_field,
					'label' => '['.$langs->trans("Company").'] '.$company_fields[$req_field],
					'type' => 'company',
					'required' => true
				);
			}
		}
		
		// Add required contact fields
		foreach ($required_fields['contact'] as $req_field) {
			if (isset($contact_fields[$req_field])) {
				$dolibarr_fields[] = array(
					'key' => 'contact_'.$req_field,
					'label' => '['.$langs->trans("Contact").'] '.$contact_fields[$req_field],
					'type' => 'contact',
					'required' => true
				);
			}
		}
		
		// Add optional company fields
		foreach ($company_fields as $field_key => $field_label) {
			if (!empty($field_key) && !in_array($field_key, $required_fields['company'])) {
				$dolibarr_fields[] = array(
					'key' => 'company_'.$field_key,
					'label' => '['.$langs->trans("Company").'] '.$field_label,
					'type' => 'company',
					'required' => false
				);
			}
		}
		
		// Add optional contact fields
		foreach ($contact_fields as $field_key => $field_label) {
			if (!empty($field_key) && !in_array($field_key, $required_fields['contact'])) {
				$dolibarr_fields[] = array(
					'key' => 'contact_'.$field_key,
					'label' => '['.$langs->trans("Contact").'] '.$field_label,
					'type' => 'contact',
					'required' => false
				);
			}
		}
		
		// NEW UI: Dolibarr-field-centric tables (like mapping.php v1.3.1)
		
		// Build CSV column dropdown options
		$csv_dropdown_options = array('' => $langs->trans("None"));
		foreach ($csv_headers as $idx => $header) {
			$csv_dropdown_options[$idx] = $langs->trans("Column").' '.($idx + 1).': '.dol_trunc($header, 30);
		}
		
		// Read sample data once for performance
		$sample_line = array();
		if (file_exists($_SESSION['contactimport_sample_file'])) {
			$fp = fopen($_SESSION['contactimport_sample_file'], 'r');
			if ($has_header_val) fgetcsv($fp, 0, $csv_sep_display, $csv_enc_display); // Skip header
			$sample_line = fgetcsv($fp, 0, $csv_sep_display, $csv_enc_display);
			fclose($fp);
		}
		
		// COMPANY MAPPING TABLE
		print '<div style="background-color: #e8f4f8; padding: 10px; margin: 20px 0 10px 0; border-left: 4px solid #4e9fdd;">';
		print '<strong>🏢 '.$langs->trans("CompanyMapping").'</strong>';
		print '</div>';
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th class="left" width="40%">'.$langs->trans("DolibarrCompanyField").'</th>';
		print '<th class="left" width="40%">'.$langs->trans("SelectCSVColumn").'</th>';
		print '<th class="center" width="20%">'.$langs->trans("SampleData").'</th>';
		print '</tr>';
		
		foreach ($company_fields as $field_key => $field_label) {
			if (empty($field_key)) continue;
			
			print '<tr class="oddeven">';
			
			// Dolibarr Field
			print '<td><strong>'.$field_label.'</strong>';
			if ($field_key == 'nom') {
				print ' <span class="badge badge-status4">'.$langs->trans("Required").'</span>';
			}
			print '</td>';
			
			// CSV Column Dropdown
			print '<td>';
			$selected_csv_column = '';
			if (!empty($mapping['company'])) {
				foreach ($mapping['company'] as $csv_idx => $mapped_field) {
					if ($mapped_field === $field_key) {
						$selected_csv_column = $csv_idx;
						break;
					}
				}
			}
			print $form->selectarray('company_'.$field_key, $csv_dropdown_options, $selected_csv_column, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
			print '</td>';
			
			// Sample Data
			print '<td class="center" style="font-size: 0.85em; color: #666;">';
			if ($selected_csv_column !== '' && !empty($sample_line[$selected_csv_column])) {
				print dol_escape_htmltag(dol_trunc($sample_line[$selected_csv_column], 30));
			} else {
				print '<span class="opacitymedium">-</span>';
			}
			print '</td>';
			
			print '</tr>';
		}
		
		print '</table>';
		print '<br>';
		
		// CONTACT MAPPING TABLE
		print '<div style="background-color: #f8f4e8; padding: 10px; margin: 20px 0 10px 0; border-left: 4px solid #dd9f4e;">';
		print '<strong>👤 '.$langs->trans("ContactMapping").'</strong>';
		print '</div>';
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th class="left" width="40%">'.$langs->trans("DolibarrContactField").'</th>';
		print '<th class="left" width="40%">'.$langs->trans("SelectCSVColumn").'</th>';
		print '<th class="center" width="20%">'.$langs->trans("SampleData").'</th>';
		print '</tr>';
		
		foreach ($contact_fields as $field_key => $field_label) {
			if (empty($field_key)) continue;
			
			print '<tr class="oddeven">';
			
			// Dolibarr Field
			print '<td><strong>'.$field_label.'</strong>';
			if ($field_key == 'lastname') {
				print ' <span class="badge badge-status4">'.$langs->trans("Required").'</span>';
			}
			print '</td>';
			
			// CSV Column Dropdown
			print '<td>';
			$selected_csv_column = '';
			if (!empty($mapping['contact'])) {
				foreach ($mapping['contact'] as $csv_idx => $mapped_field) {
					if ($mapped_field === $field_key) {
						$selected_csv_column = $csv_idx;
						break;
					}
				}
			}
			print $form->selectarray('contact_'.$field_key, $csv_dropdown_options, $selected_csv_column, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
			print '</td>';
			
			// Sample Data
			print '<td class="center" style="font-size: 0.85em; color: #666;">';
			if ($selected_csv_column !== '' && !empty($sample_line[$selected_csv_column])) {
				print dol_escape_htmltag(dol_trunc($sample_line[$selected_csv_column], 30));
			} else {
				print '<span class="opacitymedium">-</span>';
			}
			print '</td>';
			
			print '</tr>';
		}
		
		print '</table>';
	} else {
		print '<div class="warning">'.$langs->trans("NoSampleCSVFileFound").'</div>';
	}
	
	// Save buttons
	print '<br><div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
	print '&nbsp;';
	print '<input class="button button-cancel" type="button" value="'.$langs->trans("Cancel").'" onclick="window.location.href=\''.$_SERVER["PHP_SELF"].'\'">';
	print '</div>';
	
	print '</form>';
	
} else {
	// List templates
	$sql = "SELECT t.rowid, t.ref, t.label, t.description, t.is_default, t.date_creation, t.date_modification";
	$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_templates as t";
	$sql .= " WHERE t.entity = ".$conf->entity;
	$sql .= " ORDER BY t.is_default DESC, t.date_creation DESC";
	
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Ref").'</th>';
		print '<th>'.$langs->trans("Label").'</th>';
		print '<th>'.$langs->trans("Description").'</th>';
		print '<th class="center">'.$langs->trans("Default").'</th>';
		print '<th class="center">'.$langs->trans("DateCreation").'</th>';
		print '<th class="center">'.$langs->trans("Action").'</th>';
		print '</tr>';
		
		if ($num > 0) {
			$i = 0;
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				
				print '<tr class="oddeven">';
				print '<td>'.$obj->ref.'</td>';
				print '<td>'.$obj->label;
				// Add badge for default template
				if ($obj->is_default) {
					print ' <span class="badge badge-status4" style="background-color: #25a864; color: white; margin-left: 8px;">'.$langs->trans("Default").'</span>';
				}
				print '</td>';
				print '<td>'.dol_trunc($obj->description, 100).'</td>';
				print '<td class="center">'.yn($obj->is_default).'</td>';
				print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
				print '<td class="center">';
				print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$obj->rowid.'" class="editfielda">';
				print img_edit();
				print '</a> ';
				print '<a href="#" onclick="return confirmDeleteTemplate('.$obj->rowid.');" class="deletefilelink">';
				print img_delete();
				print '</a>';
				print '</td>';
				print '</tr>';
				
				$i++;
			}
		} else {
			print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans("NoTemplatesFound").'</td></tr>';
		}
		
		print '</table>';
		
		// JavaScript for delete confirmation
		print '<script type="text/javascript">';
		print 'function confirmDeleteTemplate(templateId) {';
		print '  if (confirm("'.$langs->trans("ConfirmDeleteTemplate").'")) {';
		print '    window.location.href = "'.$_SERVER["PHP_SELF"].'?action=confirm_delete&id=" + templateId + "&confirm=yes&token='.newToken().'";';
		print '    return true;';
		print '  }';
		print '  return false;';
		print '}';
		print '</script>';
		
		$db->free($resql);
	}
	
	// Action buttons
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=create&token='.newToken().'">'.$langs->trans("CreateTemplate").'</a>';
	
	// FTP Download & Import button
	if (getDolGlobalString('CONTACTIMPORT_FTP_ENABLED')) {
		$ftp_host = getDolGlobalString('CONTACTIMPORT_FTP_HOST');
		$ftp_user = getDolGlobalString('CONTACTIMPORT_FTP_USER');
		
		// Show button as disabled if FTP not configured (with helpful tooltip)
		if (!empty($ftp_host) && !empty($ftp_user)) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=ftp_preview&token='.newToken().'">'.$langs->trans("PreviewFtpImport").'</a>';
		} else {
			print '<a class="butActionRefused" href="#" title="'.$langs->trans("FTPConfigurationIncomplete").'">'.$langs->trans("PreviewFtpImport").'</a>';
		}
	}
	print '</div>';

	// Dry-run preview result: nothing has been written to the database yet, the user must confirm
	if ($action == 'ftp_preview_result' && $preview_session_id > 0 && is_array($preview_data)) {
		print '<br>';
		print '<div class="info">'.$langs->trans("FtpImportPreviewIntro").'</div>';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans("Filename").'</th><th class="right">'.$langs->trans("Size").'</th><th class="right">'.$langs->trans("TotalLines").'</th></tr>';
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($preview_session->filename).'</td>';
		print '<td class="right">'.dol_print_size($preview_session->file_size).'</td>';
		print '<td class="right">'.$preview_session->total_lines.'</td>';
		print '</tr>';
		print '</table>';

		print '<br><table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans("CompaniesToCreate").'</th><th>'.$langs->trans("ContactsToCreate").'</th><th>'.$langs->trans("PotentialErrors").'</th></tr>';
		print '<tr class="oddeven">';
		print '<td>'.(int) $preview_data['stats']['companies_to_create'].'</td>';
		print '<td>'.(int) $preview_data['stats']['contacts_to_create'].'</td>';
		print '<td>'.(int) $preview_data['stats']['potential_errors'].'</td>';
		print '</tr>';
		print '</table>';

		if (!empty($preview_data['warnings'])) {
			print '<div class="warning" style="margin-top: 10px;">';
			foreach ($preview_data['warnings'] as $warning) {
				print '- '.dol_escape_htmltag($warning).'<br>';
			}
			print '</div>';
		}

		print '<div class="center" style="margin-top: 15px;">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="ftp_confirm_import">';
		print '<input type="hidden" name="session_id" value="'.$preview_session_id.'">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("ConfirmImportNow").'">';
		print '</form>';
		print ' ';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="ftp_preview_cancel">';
		print '<input type="hidden" name="session_id" value="'.$preview_session_id.'">';
		print '<input type="submit" class="butActionDelete" value="'.$langs->trans("CancelPreview").'">';
		print '</form>';
		print '</div>';
	}
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
