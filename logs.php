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
 * \file        logs.php
 * \ingroup     contactimport
 * \brief       View import logs
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
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
$session_id = GETPOST('id', 'int'); // Changed from 'session_id' to 'id' to match URL parameter
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$offset = $limit * $page;

$search_status = GETPOST('search_status', 'alpha');
$search_type = GETPOST('search_type', 'alpha');

// Initialize objects
$session = new ContactImportSession($db);
$formother = new FormOther($db);

// Initialize technical objects
$hookmanager->initHooks(array('contactimportlogs'));
$parameters = array();

/*
 * Actions
 */

// Delete logs action
if ($action == 'confirm_deletelogs' && GETPOST('confirm', 'alpha') == 'yes') {
	if ($session_id > 0) {
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_logs WHERE fk_session = ".((int) $session_id);
		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans('LogsDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/contactimport/session.php', 1).'?id='.$session_id);
			exit;
		} else {
			setEventMessages($langs->trans('ErrorDeletingLogs'), null, 'errors');
		}
	}
	$action = '';
}

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$object = $session;
$parameters = array('id' => $session_id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */
$title = $langs->trans('ImportLogs');
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
print '<div class="clearboth"></div>';
print '</div>';

print dol_get_fiche_head(contactimportPrepareHead($session), 'logs', $langs->trans('ImportSession'), -1, 'contactimport');

// Build parameter string for pagination
$param = '&id='.$session_id;
if ($search_status) {
	$param .= '&search_status='.urlencode($search_status);
}
if ($search_type) {
	$param .= '&search_type='.urlencode($search_type);
}

// Build SQL query for logs
$sql = "SELECT l.rowid, l.line_number, l.import_type, l.company_id, l.contact_id, l.status, l.error_message, l.date_creation";
$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_logs as l";
$sql .= " WHERE l.fk_session = ".((int) $session_id);

// Add search filters
if ($search_status) {
	$sql .= " AND l.status = '".$db->escape($search_status)."'";
}
if ($search_type) {
	$sql .= " AND l.import_type = '".$db->escape($search_type)."'";
}

// Add sort
if (!$sortfield) {
	$sortfield = "l.line_number";
}
if (!$sortorder) {
	$sortorder = "ASC";
}
$sql .= $db->order($sortfield, $sortorder);

// Count total results
$sqlforcount = "SELECT COUNT(*) as nb";
$sqlforcount .= " FROM ".MAIN_DB_PREFIX."contactimport_logs as l";
$sqlforcount .= " WHERE l.fk_session = ".((int) $session_id);

if ($search_status) {
	$sqlforcount .= " AND l.status = '".$db->escape($search_status)."'";
}
if ($search_type) {
	$sqlforcount .= " AND l.import_type = '".$db->escape($search_type)."'";
}

$resql = $db->query($sqlforcount);
if ($resql) {
	$objforcount = $db->fetch_object($resql);
	$nbtotalofrecords = $objforcount->nb;
} else {
	dol_print_error($db);
}

// Add limit
$sql .= $db->plimit($limit + 1, $offset);

// Execute query
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}

$num = $db->num_rows($resql);

// Search form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="session_id" value="'.$session_id.'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'contactimport', 0, '', '', $limit, 0, 0, 1);

// Search fields
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';

// Line number
print '<td class="liste_titre">';
print '</td>';

// Import type
print '<td class="liste_titre center">';
print $form->selectarray('search_type', array(
	'' => '',
	'company' => $langs->trans('Company'),
	'contact' => $langs->trans('Contact'),
	'both' => $langs->trans('Both')
), $search_type, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print '</td>';

// Status
print '<td class="liste_titre center">';
print $form->selectarray('search_status', array(
	'' => '',
	'success' => $langs->trans('Success'),
	'error' => $langs->trans('Error')
), $search_status, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print '</td>';

// Company/Contact
print '<td class="liste_titre">';
print '</td>';

// Error message
print '<td class="liste_titre">';
print '</td>';

// Date
print '<td class="liste_titre">';
print '</td>';

// Action column
print '<td class="liste_titre center maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';

print '</tr>';

// Column headers
print '<tr class="liste_titre">';
print_liste_field_titre('LineNumber', $_SERVER["PHP_SELF"], 'l.line_number', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ImportType', $_SERVER["PHP_SELF"], 'l.import_type', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER["PHP_SELF"], 'l.status', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('CreatedRecord', $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ErrorMessage', $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Date', $_SERVER["PHP_SELF"], 'l.date_creation', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', $param, 'class="center"', $sortfield, $sortorder);
print '</tr>';

// Show logs
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	
	print '<tr class="oddeven">';
	
	// Line number
	print '<td>';
	print $obj->line_number;
	print '</td>';
	
	// Import type
	print '<td class="center">';
	if ($obj->import_type == 'company') {
		print $langs->trans('Company');
	} elseif ($obj->import_type == 'contact') {
		print $langs->trans('Contact');
	} elseif ($obj->import_type == 'both') {
		print $langs->trans('Both');
	} else {
		print $obj->import_type;
	}
	print '</td>';
	
	// Status
	print '<td class="center">';
	if ($obj->status == 'success') {
		print '<span class="badge badge-status4 badge-status">'.$langs->trans('Success').'</span>';
	} else {
		print '<span class="badge badge-status8 badge-status">'.$langs->trans('Error').'</span>';
	}
	print '</td>';
	
	// Created records
	print '<td>';
	$created_records = array();
	if ($obj->company_id > 0) {
		$created_records[] = $langs->trans('Company').': '.$obj->company_id;
	}
	if ($obj->contact_id > 0) {
		$created_records[] = $langs->trans('Contact').': '.$obj->contact_id;
	}
	print implode('<br>', $created_records);
	print '</td>';
	
	// Error message
	print '<td>';
	if ($obj->error_message) {
		print '<span class="error">'.dol_escape_htmltag($obj->error_message).'</span>';
	}
	print '</td>';
	
	// Date
	print '<td class="center">';
	print dol_print_date($db->jdate($obj->date_creation), 'dayhour');
	print '</td>';
	
	// Actions
	print '<td class="center">';
	// View company
	if ($obj->company_id > 0) {
		print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$obj->company_id.'" title="'.$langs->trans('ViewCompany').'">';
		print img_object($langs->trans('ViewCompany'), 'company');
		print '</a> ';
	}
	// View contact
	if ($obj->contact_id > 0) {
		print '<a href="'.DOL_URL_ROOT.'/contact/card.php?id='.$obj->contact_id.'" title="'.$langs->trans('ViewContact').'">';
		print img_object($langs->trans('ViewContact'), 'contact');
		print '</a>';
	}
	print '</td>';
	
	print '</tr>';
	$i++;
}

// If no results
if ($num == 0) {
	$colspan = 7;
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoLogEntriesFound").'</td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

print dol_get_fiche_end();

// Actions buttons
print '<div class="tabsAction">';
print '<div class="inline-block divButAction">';
print '<a href="'.dol_buildpath('/contactimport/session.php', 1).'?id='.$session_id.'" class="butAction">'.$langs->trans('BackToSession').'</a>';

// Delete logs button with confirmation
if ($num > 0) {
	print '<a href="#" class="butActionDelete" onclick="return confirmDeleteLogs();">'.$langs->trans('DeleteLogs').'</a>';
	print '<script type="text/javascript">';
	print 'function confirmDeleteLogs() {';
	print '  if (confirm("'.$langs->trans('ConfirmDeleteLogs').'")) {';
	print '    window.location.href = "'.dol_buildpath('/contactimport/logs.php', 1).'?id='.$session_id.'&action=confirm_deletelogs&confirm=yes&token='.newToken().'";';
	print '    return true;';
	print '  }';
	print '  return false;';
	print '}';
	print '</script>';
}

print '</div>';
print '</div>';

// End of page
llxFooter();
$db->close();