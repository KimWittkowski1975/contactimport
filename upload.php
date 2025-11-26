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
 * \file        contactimport/upload.php
 * \ingroup     contactimport
 * \brief       CSV file upload page for Contact Import module
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
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
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$form = new Form($db);
$formfile = new FormFile($db);
$contactimportsession = new ContactImportSession($db);

$upload_dir = getDolGlobalString('CONTACTIMPORT_TEMP_DIR', DOL_DATA_ROOT.'/contactimport/temp');
if (!is_dir($upload_dir)) {
	dol_mkdir($upload_dir);
}

$error = 0;
$errors = array();

/*
 * Actions
 */

// Delete file and session
if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
	$delete_id = GETPOST('delete_id', 'int');
	if ($delete_id > 0) {
		$session_to_delete = new ContactImportSession($db);
		$result = $session_to_delete->fetch($delete_id);
		if ($result > 0) {
			// Delete physical file
			if (!empty($session_to_delete->file_path) && file_exists($session_to_delete->file_path)) {
				unlink($session_to_delete->file_path);
			}
			// Delete logs
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_logs WHERE fk_session = ".((int) $delete_id);
			$db->query($sql);
			// Delete session
			$result = $session_to_delete->delete($user);
			if ($result > 0) {
				setEventMessages($langs->trans('FileAndSessionDeleted'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('ErrorDeletingSession'), null, 'errors');
			}
		}
	}
	$action = '';
}

if ($action == 'upload' && !$cancel) {
	if (!empty($_FILES['csvfile']['tmp_name'])) {
		$upload_file = $_FILES['csvfile'];
		
		// Check file size
		$max_size = getDolGlobalInt('CONTACTIMPORT_MAX_FILE_SIZE', 10485760); // Default 10MB
		if ($upload_file['size'] > $max_size) {
			$error++;
			$errors[] = $langs->trans("ErrorFileTooLarge", dol_print_size($max_size));
		}
		
		// Check file extension
		$pathinfo = pathinfo($upload_file['name']);
		if (!in_array(strtolower($pathinfo['extension']), array('csv', 'txt'))) {
			$error++;
			$errors[] = $langs->trans("ErrorInvalidFileExtension");
		}
		
		if (!$error) {
			// Generate unique filename
			$ref = 'UPLOAD_'.dol_now();
			$filename = $ref.'_'.dol_sanitizeFileName($upload_file['name']);
			$filepath = $upload_dir.'/'.$filename;
			
			// Move uploaded file
			if (move_uploaded_file($upload_file['tmp_name'], $filepath)) {
				// Get CSV parameters
				$csv_separator = GETPOST('csv_separator', 'alpha') ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_SEPARATOR', ';');
				$csv_enclosure = GETPOST('csv_enclosure', 'alpha') ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE', '"');
				$has_header = GETPOST('has_header', 'int') ? 1 : 0;
				
				// Validate CSV file
				$validation = validateCSVFile($filepath, $csv_separator, $csv_enclosure);
				
				if ($validation['status']) {
					// Create import session
					$contactimportsession->ref = $ref;
					$contactimportsession->label = GETPOST('label', 'alpha') ?: $upload_file['name'];
					$contactimportsession->description = GETPOST('description', 'text');
					$contactimportsession->filename = $upload_file['name'];
					$contactimportsession->file_path = $filepath;
					$contactimportsession->file_size = $upload_file['size'];
					$contactimportsession->csv_separator = $csv_separator;
					$contactimportsession->csv_enclosure = $csv_enclosure;
					$contactimportsession->has_header = $has_header;
					$contactimportsession->total_lines = count(file($filepath)) - ($has_header ? 1 : 0);
					$contactimportsession->status = 1; // Uploaded
					
					$result = $contactimportsession->create($user);
					
					if ($result > 0) {
						setEventMessages($langs->trans("FileUploadedSuccessfully"), null, 'mesgs');
						header("Location: mapping.php?id=".$result);
						exit;
					} else {
						$error++;
						$errors[] = $contactimportsession->error;
					}
				} else {
					$error++;
					$errors[] = $langs->trans("ErrorInvalidCSVFile").': '.$validation['message'];
					// Remove uploaded file
					unlink($filepath);
				}
			} else {
				$error++;
				$errors[] = $langs->trans("ErrorCannotUploadFile");
			}
		}
	} else {
		$error++;
		$errors[] = $langs->trans("ErrorNoFileSelected");
	}
}

/*
 * View
 */

$page_name = "CSVUpload";
llxHeader('', $langs->trans($page_name));

// Check if upload directory exists and is writable
if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
	print '<div class="error">'.$langs->trans("ErrorUploadDirNotWritable", $upload_dir).'</div>';
}

print load_fiche_titre($langs->trans($page_name), '', 'contactimport@contactimport');

// Error messages
if ($error) {
	foreach ($errors as $errmsg) {
		setEventMessages($errmsg, null, 'errors');
	}
}

print '<form name="upload_form" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload">';

print '<div class="fichecenter">';
print '<table class="border centpercent">';

// File selection
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans("CSVFile").'</td>';
print '<td>';
print '<input type="file" name="csvfile" accept=".csv,.txt" required>';
print '<br><small>'.$langs->trans("MaxFileSize").': '.dol_print_size(getDolGlobalInt('CONTACTIMPORT_MAX_FILE_SIZE', 10485760)).'</small>';
print '</td>';
print '</tr>';

// Label
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans("Label").'</td>';
print '<td>';
print '<input type="text" name="label" class="flat minwidth300" value="'.GETPOST('label', 'alpha').'">';
print '</td>';
print '</tr>';

// Description
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans("Description").'</td>';
print '<td>';
print '<textarea name="description" class="flat minwidth300" rows="3">'.GETPOST('description', 'restricthtml').'</textarea>';
print '</td>';
print '</tr>';

// CSV Separator
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans("CSVSeparator").'</td>';
print '<td>';
$separators = getCSVSeparatorOptions();
print $form->selectarray('csv_separator', $separators, GETPOST('csv_separator', 'alpha') ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_SEPARATOR', ';'));
print '</td>';
print '</tr>';

// CSV Enclosure
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans("CSVEnclosure").'</td>';
print '<td>';
$enclosures = getCSVEnclosureOptions();
print $form->selectarray('csv_enclosure', $enclosures, GETPOST('csv_enclosure', 'alpha') ?: getDolGlobalString('CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE', '"'));
print '</td>';
print '</tr>';

// Has header
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans("HasHeader").'</td>';
print '<td>';
print $form->selectyesno('has_header', GETPOST('has_header', 'int') ? 1 : 1, 1); // Default to yes
print '<br><small>'.$langs->trans("HasHeaderTooltip").'</small>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Upload").'">';
print '&nbsp;&nbsp;&nbsp;';
print '<input type="button" class="button button-cancel" value="'.$langs->trans("Cancel").'" onclick="history.back()">';
print '</div>';

print '</form>';

// Recent uploads
print '<br><br>';
print load_fiche_titre($langs->trans("RecentUploads"), '', '');

$sql = "SELECT rowid, ref, label, filename, file_size, date_creation, status";
$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions";
$sql .= " WHERE entity = ".$conf->entity;
$sql .= " ORDER BY date_creation DESC";
$sql .= " LIMIT 10";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Ref").'</th>';
	print '<th>'.$langs->trans("Label").'</th>';
	print '<th>'.$langs->trans("Filename").'</th>';
	print '<th class="right">'.$langs->trans("Size").'</th>';
	print '<th class="center">'.$langs->trans("Date").'</th>';
	print '<th class="center">'.$langs->trans("Status").'</th>';
	print '<th class="center">'.$langs->trans("Action").'</th>';
	print '<th class="center">'.$langs->trans("Delete").'</th>';
	print '</tr>';
	
	if ($num > 0) {
		$statuses = getImportSessionStatusOptions();
		
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			
			print '<tr class="oddeven">';
			print '<td>'.$obj->ref.'</td>';
			print '<td>'.$obj->label.'</td>';
			print '<td>'.$obj->filename.'</td>';
			print '<td class="right">'.dol_print_size($obj->file_size).'</td>';
			print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
			print '<td class="center">'.$statuses[$obj->status].'</td>';
			print '<td class="center">';
			if ($obj->status == 1) {
				print '<a href="mapping.php?id='.$obj->rowid.'" class="button">'.$langs->trans("Map").'</a>';
			} elseif ($obj->status >= 2) {
				print '<a href="preview.php?id='.$obj->rowid.'" class="button">'.$langs->trans("View").'</a>';
			}
			print '</td>';
			// Delete button
			print '<td class="center">';
			print '<a href="#" onclick="return confirmDeleteSession('.$obj->rowid.');" class="deletefilelink" title="'.$langs->trans("Delete").'">';
			print img_delete();
			print '</a>';
			print '</td>';
			print '</tr>';
			
			$i++;
		}
	} else {
		print '<tr><td colspan="8" class="opacitymedium">'.$langs->trans("NoRecentUploads").'</td></tr>';
	}
	
	print '</table>';
	
	// JavaScript for delete confirmation
	print '<script type="text/javascript">';
	print 'function confirmDeleteSession(sessionId) {';
	print '  if (confirm("'.$langs->trans("ConfirmDeleteFileAndSession").'")) {';
	print '    window.location.href = "upload.php?action=confirm_delete&delete_id=" + sessionId + "&confirm=yes&token='.newToken().'";';
	print '    return true;';
	print '  }';
	print '  return false;';
	print '}';
	print '</script>';
	
	$db->free($resql);
} else {
	dol_print_error($db);
}

// Page end
llxFooter();
$db->close();