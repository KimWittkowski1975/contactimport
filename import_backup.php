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
 * \file        import.php
 * \ingroup     contactimport
 * \brief       Import session list
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/contactimport/class/contactimportsession.class.php');
dol_include_once('/contactimport/lib/contactimport.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("contactimport@contactimport"));

// Access control
if (empty($conf->contactimport->enabled)) {
	accessforbidden('Module not enabled');
}

// Security check
if (!$user->hasRight('contactimport', 'contactimport', 'read')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$confirm = GETPOST('confirm', 'alpha');
$token = GETPOST('token', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$offset = $limit * $page;

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_status = GETPOST('search_status', 'alpha');

// Initialize objects
$session = new ContactImportSession($db);
$formother = new FormOther($db);

// Initialize technical objects
$hookmanager->initHooks(array('contactimportlist'));
$parameters = array();

/*
 * Actions
 */

// Delete action
if ($action == 'confirm_delete' && $confirm == 'yes' && $user->hasRight('contactimport', 'write')) {
	if (GETPOST('token') == newToken()) {
		$session = new ContactImportSession($db);
		if ($session->fetch($id) > 0) {
			$result = $session->delete($user);
			if ($result > 0) {
				setEventMessages($langs->trans('SessionDeleted'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF']);
				exit;
			} else {
				setEventMessages($langs->trans('ErrorDeleteSession'), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans('ErrorSessionNotFound'), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	}
}

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$object = $session;
$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */
$title = $langs->trans('ImportSessions');
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title);

// Build SQL query
$sql = "SELECT s.rowid, s.ref, s.label, s.filename, s.file_size, s.status, s.date_creation, s.date_import, s.processed_lines, s.success_lines, s.error_lines";
$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions as s";
$sql .= " WHERE s.entity = ".$conf->entity;

// Add search filters
if ($search_ref) {
	$sql .= " AND s.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_label) {
	$sql .= " AND s.label LIKE '%".$db->escape($search_label)."%'";
}
if ($search_status) {
	$sql .= " AND s.status = '".$db->escape($search_status)."'";
}

// Add sort
if (!$sortfield) {
	$sortfield = "s.date_creation";
}
if (!$sortorder) {
	$sortorder = "DESC";
}
$sql .= $db->order($sortfield, $sortorder);

// Count total results
$sqlforcount = "SELECT COUNT(*) as nb";
$sqlforcount .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions as s";
$sqlforcount .= " WHERE s.entity = ".$conf->entity;

if ($search_ref) {
	$sqlforcount .= " AND s.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_label) {
	$sqlforcount .= " AND s.label LIKE '%".$db->escape($search_label)."%'";
}
if ($search_status) {
	$sqlforcount .= " AND s.status = '".$db->escape($search_status)."'";
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
if ($resql) {
	$num = $db->num_rows($resql);

// CRITICAL DEBUG: Check query execution
if (getDolGlobalString('MAIN_FEATURES_LEVEL') >= 0) { // Always show for debugging
	print "<!-- DEBUG: SQL executed successfully -->";
	print "<!-- DEBUG: NUM rows = ".$num." -->";
	print "<!-- DEBUG: RESQL valid = ".($resql ? 'YES' : 'NO')." -->";
	print "<!-- DEBUG: SQL = ".$sql." -->";
}

// Search form
$param = '';
if ($search_ref) $param .= '&search_ref='.urlencode($search_ref);
if ($search_label) $param .= '&search_label='.urlencode($search_label);
if ($search_status) $param .= '&search_status='.urlencode($search_status);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

// Use simple table like upload.php instead of complex Dolibarr functions
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("Label").'</th>';
print '<th>'.$langs->trans("Filename").'</th>';
print '<th class="center">'.$langs->trans("Status").'</th>';
print '<th class="center">'.$langs->trans("DateCreation").'</th>';
print '<th class="center">'.$langs->trans("DateImport").'</th>';
print '<th class="center">'.$langs->trans("Statistics").'</th>';
print '<th class="center">'.$langs->trans("Action").'</th>';
print '</tr>';

// Show sessions using simple approach like upload.php
if ($num > 0) {
	$statuses = array(
		1 => $langs->trans('Uploaded'),
		2 => $langs->trans('Mapped'),  
		3 => $langs->trans('ReadyForImport'),
		4 => $langs->trans('Completed'),
		5 => $langs->trans('Error')
	);
	
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		if (!$obj) break;
	
		print '<tr class="oddeven">';
		print '<td>'.$obj->ref.'</td>';
		print '<td>'.$obj->label.'</td>';
		print '<td>'.$obj->filename;
		if ($obj->file_size > 0) {
			print '<br><small>('.dol_print_size($obj->file_size).')</small>';
		}
		print '</td>';
		print '<td class="center">'.$statuses[$obj->status].'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
		print '<td class="center">';
		if ($obj->date_import) {
			print dol_print_date($db->jdate($obj->date_import), 'dayhour');
		}
		print '</td>';
		print '<td class="center">';
		if ($obj->processed_lines > 0) {
			print $obj->processed_lines.' processed<br>';
			print $obj->success_lines.' success';
			if ($obj->error_lines > 0) {
				print ' / '.$obj->error_lines.' errors';
			}
		}
		print '</td>';
		print '<td class="center">';
		
		// Simple action buttons
		if ($obj->status == 1) {
			print '<a href="mapping.php?id='.$obj->rowid.'" class="button">'.$langs->trans("Map").'</a>';
		} elseif ($obj->status >= 2) {
			print '<a href="session.php?id='.$obj->rowid.'" class="button">'.$langs->trans("View").'</a>';
		}
		
		// Delete button
		if ($user->hasRight('contactimport', 'write')) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$obj->rowid.'&token='.newToken().'" class="button">'.$langs->trans('Delete').'</a>';
		}
		
		print '</td>';
		print '</tr>';
		
		$i++;
	}
} else {
	print '<tr><td colspan="8" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}

print '</table>';

$db->free($resql);
} else {
	dol_print_error($db);
}

print '<br>';
print '<a href="upload.php" class="butAction">'.$langs->trans('NewCSVUpload').'</a>';

print '</form>';

// Confirmation dialog for delete
if ($action == 'delete' && $id > 0) {
	$session = new ContactImportSession($db);
	if ($session->fetch($id) > 0) {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$id,
			$langs->trans('DeleteSession'),
			$langs->trans('ConfirmDeleteSession', $session->ref ?: 'Session #'.$session->id),
			'confirm_delete',
			'',
			0,
			1
		);
	}
}

// End of page
llxFooter();
$db->close();