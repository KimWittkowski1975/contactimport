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
 * \file        process.php
 * \ingroup     contactimport
 * \brief       Process CSV import
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
dol_include_once('/contactimport/class/contactimportprocessor.class.php');
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
if (!$user->hasRight('contactimport', 'contactimport', 'write')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$session_id = GETPOST('id', 'int'); // Changed from 'session_id' to 'id' to match URL parameter

// Initialize objects
$session = new ContactImportSession($db);
$processor = new ContactImportProcessor($db);

// Initialize technical objects
$hookmanager->initHooks(array('contactimportprocess'));
$parameters = array();

/*
 * Actions
 */
$object = $session;
$parameters = array('id' => $session_id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($action == 'import' && $session_id > 0) {
		// Load session
		$result = $session->fetch($session_id);
		if ($result <= 0) {
			setEventMessages($langs->trans('ErrorLoadingImportSession'), null, 'errors');
		} else {
			// Check session status
			if ($session->status != 3) { // Not in preview status
				setEventMessages($langs->trans('ImportSessionNotInPreviewStatus'), null, 'errors');
			} else {
				// Start import process
				$session->status = 2; // Processing
				$session->date_import = dol_now();
				$session->update($user);

				// Process import
				$import_result = $processor->processImport($session, $user);

				if ($import_result['success']) {
					setEventMessages($langs->trans('ImportCompletedSuccessfully', $import_result['companies_created'], $import_result['contacts_created']), null, 'mesgs');
					
					// Redirect to session details
					header('Location: '.dol_buildpath('/contactimport/session.php', 1).'?id='.$session_id);
					exit;
				} else {
					setEventMessages($langs->trans('ImportFailedWithErrors', $import_result['errors']), null, 'errors');
					
					// Set session status to error
					$session->status = 5;
					$session->update($user);
				}
			}
		}
	}
}

/*
 * View
 */
$title = $langs->trans('ProcessImport');
$help_url = '';

llxHeader('', $title, $help_url);

// Load session if specified
if ($session_id > 0) {
	$result = $session->fetch($session_id);
	if ($result <= 0) {
		print '<div class="error">'.$langs->trans('ErrorLoadingImportSession').'</div>';
		llxFooter();
		exit;
	}
}

print load_fiche_titre($title);

// Check if we have a valid session
if ($session_id <= 0 || empty($session->id) || $session->id <= 0) {
	print '<div class="error">'.$langs->trans('NoImportSessionSelected').'</div>';
	print '<p><a href="'.dol_buildpath('/contactimport/import.php', 1).'" class="butAction">'.$langs->trans('BackToImportList').'</a></p>';
	llxFooter();
	exit;
}

// Show session information
print '<div class="fiche">';
print '<div class="fichetitle">';
print '<div class="fichethirdleft">';
print '<div class="refidno">';
print $langs->trans('ImportSession').' : '.$session->ref;
print '</div>';
print '</div>';
print '<div class="fichetitle">';
print '<div class="fichetitle">';
print '</div>';
print '</div>';
print '<div class="clearboth"></div>';
print '</div>';

print dol_get_fiche_head(contactimportPrepareHead($session), 'process', $langs->trans('ImportSession'), -1, 'contactimport');

// Check session status
if ($session->status == 4) {
	// Already completed
	print '<div class="info">'.$langs->trans('ImportAlreadyCompleted').'</div>';
	
	// Show import statistics
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('ImportStatistics').'</th>';
	print '<th class="right">'.$langs->trans('Count').'</th>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('ProcessedLines').'</td>';
	print '<td class="right">'.($session->processed_lines ? $session->processed_lines : 0).'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('SuccessfulLines').'</td>';
	print '<td class="right">'.($session->success_lines ? $session->success_lines : 0).'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('ErrorLines').'</td>';
	print '<td class="right">'.($session->error_lines ? $session->error_lines : 0).'</td>';
	print '</tr>';
	
	print '</table>';
	print '</div>';
	
} elseif ($session->status == 5) {
	// Error status
	print '<div class="error">'.$langs->trans('ImportCompletedWithErrors').'</div>';
	
	// Show import statistics
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('ImportStatistics').'</th>';
	print '<th class="right">'.$langs->trans('Count').'</th>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('ProcessedLines').'</td>';
	print '<td class="right">'.($session->processed_lines ? $session->processed_lines : 0).'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('SuccessfulLines').'</td>';
	print '<td class="right">'.($session->success_lines ? $session->success_lines : 0).'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('ErrorLines').'</td>';
	print '<td class="right">'.($session->error_lines ? $session->error_lines : 0).'</td>';
	print '</tr>';
	
	print '</table>';
	print '</div>';
	
} elseif ($session->status == 3) {
	// Ready for import
	print '<div class="info">'.$langs->trans('ImportSessionReadyForProcessing').'</div>';
	
	// Show confirmation form
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$session_id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="import">';
	print '<input type="hidden" name="id" value="'.$session->id.'">';
	
	print '<div class="center">';
	print '<p><strong>'.$langs->trans('ConfirmImportExecution').'</strong></p>';
	print '<p>'.$langs->trans('ImportWillProcessAllData').'</p>';
	
	print '<div style="margin: 20px 0;">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('StartImport').'">';
	print ' &nbsp; ';
	print '<a href="'.dol_buildpath('/contactimport/preview.php', 1).'?id='.$session->id.'" class="butActionDelete">'.$langs->trans('BackToPreview').'</a>';
	print '</div>';
	
	print '</div>';
	print '</form>';
	
} else {
	// Invalid status
	print '<div class="error">'.$langs->trans('ImportSessionInvalidStatus').'</div>';
	print '<p><a href="'.dol_buildpath('/contactimport/import.php', 1).'" class="butAction">'.$langs->trans('BackToImportList').'</a></p>';
}

print dol_get_fiche_end();

// Show import logs if available
if ($session->status >= 4) {
	print '<div class="tabsAction">';
	print '<div class="inline-block divButAction">';
	print '<a href="'.dol_buildpath('/contactimport/logs.php', 1).'?id='.$session->id.'" class="butAction">'.$langs->trans('ViewImportLogs').'</a>';
	print '</div>';
	print '</div>';
}

// End of page
llxFooter();
$db->close();