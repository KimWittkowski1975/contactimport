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
 * \file       import.php
 * \ingroup    contactimport
 * \brief      Contact Import Sessions List
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
if (!$user->hasRight('contactimport', 'read')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$confirm = GETPOST('confirm', 'alpha');

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

/*
 * View
 */

$title = $langs->trans("ImportSessions");
$helpurl = '';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'bodyforlist mod-contactimport page-import');

$form = new Form($db);

// Tab navigation
$head = contactimportPrepareHead();
print dol_get_fiche_head($head, 'import', $title, -1, 'contactimport@contactimport');

print '<div class="fichecenter">';

// Query to get sessions
$sql = "SELECT s.rowid, s.ref, s.label, s.filename, s.file_size, s.status, s.date_creation";
$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions as s";
$sql .= " WHERE s.entity IN (".getEntity('contactimportsession').")";
$sql .= " ORDER BY s.date_creation DESC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Ref").'</th>';
	print '<th>'.$langs->trans("Label").'</th>';
	print '<th>'.$langs->trans("Filename").'</th>';
	print '<th class="right">'.$langs->trans("Size").'</th>';
	print '<th class="center">'.$langs->trans("DateCreation").'</th>';
	print '<th class="center">'.$langs->trans("Status").'</th>';
	print '<th class="center">'.$langs->trans("Action").'</th>';
	print '</tr>';
	
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
			
			print '<tr class="oddeven">';
			print '<td>'.$obj->ref.'</td>';
			print '<td>'.$obj->label.'</td>';
			print '<td>'.$obj->filename;
			if ($obj->file_size > 0) {
				print '<br><small>('.dol_print_size($obj->file_size).')</small>';
			}
			print '</td>';
			print '<td class="right">'.dol_print_size($obj->file_size).'</td>';
			print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
			print '<td class="center">'.$statuses[$obj->status].'</td>';
			print '<td class="center">';
			
			// Action buttons
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
		print '<tr><td colspan="7" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
	}
	
	print '</table>';
	
	$db->free($resql);
} else {
	dol_print_error($db);
}

print '<br>';
print '<a href="upload.php" class="butAction">'.$langs->trans('NewCSVUpload').'</a>';

print '</div>';

// Confirmation dialog for delete
if ($action == 'delete' && $id > 0) {
	$session = new ContactImportSession($db);
	if ($session->fetch($id) > 0) {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$id,
			$langs->trans('DeleteSession'),
			$langs->trans('ConfirmDeleteSession', $session->ref ?: 'Session #'.$session->rowid),
			'confirm_delete',
			'',
			0,
			1
		);
	}
}

// End of page
dol_get_fiche_end();
llxFooter();
$db->close();