<?php
/* Copyright (C) 2025 Kim Wittkowski <kim@nexor.de>
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
 * \file        session.php
 * \ingroup     contactimport
 * \brief       Contact import session details
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/contactimport/class/contactimportsession.class.php');
dol_include_once('/contactimport/lib/contactimport.lib.php');

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

// Parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$confirm = GETPOST('confirm', 'alpha');

// Initialize objects
$session = new ContactImportSession($db);
$formfile = new FormFile($db);

// Initialize technical objects
$hookmanager->initHooks(array('contactimportsession'));
$parameters = array();

/*
 * Actions
 */

// Load session
if ($id > 0) {
	$result = $session->fetch($id);
	if ($result <= 0) {
		setEventMessages($langs->trans('ErrorLoadingImportSession'), null, 'errors');
		header('Location: '.dol_buildpath('/contactimport/import.php', 1));
		exit;
	}
}

$object = $session;
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($action == 'confirm_delete' && $confirm == 'yes') {
		// Delete session
		$result = $session->delete($user);
		if ($result > 0) {
			setEventMessages($langs->trans('ImportSessionDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/contactimport/import.php', 1));
			exit;
		} else {
			setEventMessages($session->error, $session->errors, 'errors');
		}
	}
}

/*
 * View
 */
$title = $langs->trans('ImportSessionDetails');
$help_url = '';

llxHeader('', $title, $help_url);

if ($id <= 0 || $session->id <= 0) {
	print '<div class="error">'.$langs->trans('NoImportSessionSelected').'</div>';
	print '<p><a href="'.dol_buildpath('/contactimport/import.php', 1).'" class="butAction">'.$langs->trans('BackToImportList').'</a></p>';
	llxFooter();
	exit;
}

print load_fiche_titre($title);

print dol_get_fiche_head(contactimportPrepareHead($session), 'card', $langs->trans('ImportSession'), -1, 'contactimport');

// Confirm delete
if ($action == 'delete') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$session->id, $langs->trans('DeleteImportSession'), $langs->trans('ConfirmDeleteImportSession'), 'confirm_delete', '', 0, 1);
	print $formconfirm;
}

// Session information
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

// Reference
print '<tr><td class="titlefield fieldname_ref">'.$langs->trans('Ref').'</td>';
print '<td class="valuefield">';
print $session->ref;
print '</td></tr>';

// Label
if (!empty($session->label)) {
	print '<tr><td class="fieldname_label">'.$langs->trans('Label').'</td>';
	print '<td class="valuefield">';
	print $session->label;
	print '</td></tr>';
}

// Status
print '<tr><td class="fieldname_status">'.$langs->trans('Status').'</td>';
print '<td class="valuefield">';
print $session->getLibStatut(1);
print '</td></tr>';

// File name
print '<tr><td class="fieldname_filename">'.$langs->trans('FileName').'</td>';
print '<td class="valuefield">';
print $session->filename;
print '</td></tr>';

// File size
if ($session->file_size > 0) {
	print '<tr><td class="fieldname_filesize">'.$langs->trans('FileSize').'</td>';
	print '<td class="valuefield">';
	print dol_print_size($session->file_size);
	print '</td></tr>';
}

// CSV parameters
print '<tr><td class="fieldname_csvparams">'.$langs->trans('CSVParameters').'</td>';
print '<td class="valuefield">';
print $langs->trans('Separator').': <code>'.htmlentities($session->csv_separator).'</code><br>';
print $langs->trans('Enclosure').': <code>'.htmlentities($session->csv_enclosure).'</code><br>';
print $langs->trans('HasHeader').': '.($session->has_header ? $langs->trans('Yes') : $langs->trans('No'));
print '</td></tr>';

// Creation date
print '<tr><td class="fieldname_datecreation">'.$langs->trans('DateCreation').'</td>';
print '<td class="valuefield">';
print dol_print_date($session->date_creation, 'dayhour');
print '</td></tr>';

// Import date
if ($session->date_import) {
	print '<tr><td class="fieldname_dateimport">'.$langs->trans('DateImport').'</td>';
	print '<td class="valuefield">';
	print dol_print_date($session->date_import, 'dayhour');
	print '</td></tr>';
}

// Created by
print '<tr><td class="fieldname_author">'.$langs->trans('CreatedBy').'</td>';
print '<td class="valuefield">';
if ($session->fk_user_creat > 0) {
	$user_creat = new User($db);
	if ($user_creat->fetch($session->fk_user_creat) > 0) {
		print $user_creat->getNomUrl(1);
	}
}
print '</td></tr>';

print '</table>';

print '</div>';

print '<div class="fichehalfright">';

// Import statistics
if ($session->status >= 4) {
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('ImportStatistics').'</td></tr>';
	
	print '<tr><td class="titlefield">'.$langs->trans('ProcessedLines').'</td>';
	print '<td class="valuefield">'.($session->processed_lines ? $session->processed_lines : 0).'</td></tr>';
	
	print '<tr><td>'.$langs->trans('SuccessfulLines').'</td>';
	print '<td>'.($session->success_lines ? $session->success_lines : 0).'</td></tr>';
	
	print '<tr><td>'.$langs->trans('ErrorLines').'</td>';
	print '<td>'.($session->error_lines ? $session->error_lines : 0).'</td></tr>';
	
	// Success rate
	if ($session->processed_lines > 0) {
		$success_rate = round(($session->success_lines / $session->processed_lines) * 100, 2);
		print '<tr><td>'.$langs->trans('SuccessRate').'</td>';
		print '<td>'.$success_rate.'%</td></tr>';
	}
	
	print '</table>';
}

// Mapping configuration
$mapping_config = $session->getMappingConfig();
if (!empty($mapping_config)) {
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('MappingConfiguration').'</td></tr>';
	
	print '<tr><td class="titlefield">'.$langs->trans('ImportMode').'</td>';
	print '<td class="valuefield">';
	if ($mapping_config['import_mode'] == 'company_only') {
		print $langs->trans('CompanyOnly');
	} elseif ($mapping_config['import_mode'] == 'contact_only') {
		print $langs->trans('ContactOnly');
	} elseif ($mapping_config['import_mode'] == 'both') {
		print $langs->trans('CompanyAndContact');
	}
	print '</td></tr>';
	
	if ($mapping_config['import_mode'] == 'company_only' || $mapping_config['import_mode'] == 'both') {
		print '<tr><td>'.$langs->trans('CreateCompanies').'</td>';
		print '<td>'.($mapping_config['create_companies'] ? $langs->trans('Yes') : $langs->trans('No')).'</td></tr>';
	}
	
	if ($mapping_config['import_mode'] == 'contact_only' || $mapping_config['import_mode'] == 'both') {
		print '<tr><td>'.$langs->trans('CreateContacts').'</td>';
		print '<td>'.($mapping_config['create_contacts'] ? $langs->trans('Yes') : $langs->trans('No')).'</td></tr>';
	}
	
	print '</table>';
}

print '</div>';

print '</div>';

print dol_get_fiche_end();

// Action buttons
print '<div class="tabsAction">';

if ($session->status == 1) {
	// Uploaded - can map
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/mapping.php', 1).'?id='.$session->id.'" class="butAction">'.$langs->trans('ConfigureMapping').'</a>';
	print '</div>';
}

if ($session->status == 2) {
	// Mapped - can preview
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/preview.php', 1).'?id='.$session->id.'" class="butAction">'.$langs->trans('PreviewImport').'</a>';
	print '</div>';
}

if ($session->status == 3) {
	// Ready for import
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/process.php', 1).'?id='.$session->id.'" class="butAction">'.$langs->trans('StartImport').'</a>';
	print '</div>';
}

if ($session->status >= 4) {
	// Completed - view logs
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/logs.php', 1).'?id='.$session->id.'" class="butAction">'.$langs->trans('ViewImportLogs').'</a>';
	print '</div>';
}

// Always show delete option
if ($user->hasRight('contactimport', 'write')) {
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/import.php', 1).'?id='.$session->id.'&action=delete&token='.newToken().'" class="butActionDelete">'.$langs->trans('Delete').'</a>';
	print '</div>';
}

print '</div>';

// Show file download link if file exists
if (file_exists($session->file_path)) {
	print '<div class="center" style="margin-top: 20px;">';
	print '<a href="'.dol_buildpath('/contactimport/download.php', 1).'?id='.$session->id.'" class="button">';
	print '<span class="fa fa-download"></span> '.$langs->trans('DownloadCSVFile');
	print '</a>';
	print '</div>';
}

// End of page
llxFooter();
$db->close();