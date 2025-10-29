<?php
/* Copyright (C) 2025 Kim Wittkowski <kim.wittkowski@gmx.de>
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
 * \file        contactimport/admin/duplicates.php
 * \ingroup     contactimport
 * \brief       Manage duplicate companies and contacts
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
require_once '../class/contactimportduplicatemanager.class.php';

// Translations
$langs->loadLangs(array("admin", "contactimport@contactimport", "companies", "other"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$type = GETPOST('type', 'alpha'); // 'company' or 'contact'

/*
 * Actions
 */

$duplicateManager = new ContactImportDuplicateManager($db);

// Analyze duplicates
if ($action == 'analyze') {
	// Will be displayed in view section
}

// Delete identical companies
if ($action == 'confirm_delete_companies' && $confirm == 'yes') {
	$company_ids = GETPOST('company_ids', 'array');
	if (!empty($company_ids)) {
		$result = $duplicateManager->deleteCompanies($company_ids, $user);
		if ($result['errors'] == 0) {
			setEventMessages($langs->trans("CompaniesDeleted", $result['deleted']), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorDeletingCompanies", $result['errors']), null, 'errors');
		}
	}
	$action = '';
}

// Delete identical contacts
if ($action == 'confirm_delete_contacts' && $confirm == 'yes') {
	$contact_ids = GETPOST('contact_ids', 'array');
	if (!empty($contact_ids)) {
		$result = $duplicateManager->deleteContacts($contact_ids, $user);
		if ($result['errors'] == 0) {
			setEventMessages($langs->trans("ContactsDeleted", $result['deleted']), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorDeletingContacts", $result['errors']), null, 'errors');
		}
	}
	$action = '';
}

// Merge companies
if ($action == 'confirm_merge_companies' && $confirm == 'yes') {
	$master_id = GETPOST('master_id', 'int');
	$duplicate_ids = GETPOST('duplicate_ids', 'array');
	if ($master_id > 0 && !empty($duplicate_ids)) {
		$result = $duplicateManager->mergeCompanies($master_id, $duplicate_ids, $user);
		if ($result['errors'] == 0) {
			setEventMessages($langs->trans("CompaniesMerged", $result['merged']), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorMergingCompanies", $result['errors']), null, 'errors');
		}
	}
	$action = '';
}

// Merge contacts
if ($action == 'confirm_merge_contacts' && $confirm == 'yes') {
	$master_id = GETPOST('master_id', 'int');
	$duplicate_ids = GETPOST('duplicate_ids', 'array');
	if ($master_id > 0 && !empty($duplicate_ids)) {
		$result = $duplicateManager->mergeContacts($master_id, $duplicate_ids, $user);
		if ($result['errors'] == 0) {
			setEventMessages($langs->trans("ContactsMerged", $result['merged']), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorMergingContacts", $result['errors']), null, 'errors');
		}
	}
	$action = '';
}

// Delete duplicate logs
if ($action == 'confirm_delete_duplicate_logs' && $confirm == 'yes') {
	$days_old = GETPOST('days_old', 'int');
	
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_duplicate_logs WHERE 1=1";
	
	// Optional: Only delete logs older than X days
	if ($days_old > 0) {
		$date_limit = dol_now() - ($days_old * 24 * 3600);
		$sql .= " AND date_action < '".$db->idate($date_limit)."'";
	}
	
	$sql .= " AND entity = ".$conf->entity;
	
	$resql = $db->query($sql);
	if ($resql) {
		$deleted_count = $db->affected_rows($resql);
		setEventMessages($langs->trans("DuplicateLogsDeleted", $deleted_count), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	$action = '';
}

/*
 * View
 */

$page_name = "DuplicateManagement";
llxHeader('', $langs->trans($page_name));

// JavaScript for confirmation dialogs
print '<script type="text/javascript">
function confirmDeleteDuplicateLogs(days) {
	var message = "'.$langs->trans("ConfirmDeleteAllDuplicateLogs").'";
	if (days > 0) {
		message = "'.$langs->trans("ConfirmDeleteDuplicateLogsOlderThan").'".replace("%s", days);
	}
	if (confirm(message)) {
		var url = "'.$_SERVER["PHP_SELF"].'?action=confirm_delete_duplicate_logs&confirm=yes&token='.newToken().'";
		if (days > 0) {
			url += "&days_old=" + days;
		}
		window.location.href = url;
	}
	return false;
}
</script>';

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'duplicates', $langs->trans("ContactImportSetup"), -1, 'contactimport@contactimport');

print info_admin($langs->trans("DuplicateManagementDescription"));

// Analyze button
print '<div class="tabsAction">';
print '<a href="'.$_SERVER["PHP_SELF"].'?action=analyze&type=company&token='.newToken().'" class="butAction">'.$langs->trans("AnalyzeCompanyDuplicates").'</a>';
print '<a href="'.$_SERVER["PHP_SELF"].'?action=analyze&type=contact&token='.newToken().'" class="butAction">'.$langs->trans("AnalyzeContactDuplicates").'</a>';
print '</div>';

// Show results if analyze was clicked
if ($action == 'analyze' && !empty($type)) {
	if ($type == 'company') {
		$duplicates = $duplicateManager->findDuplicateCompanies();
		
		print '<br><h3>'.$langs->trans("CompanyDuplicates").'</h3>';
		
		// Two-column layout
		print '<div style="display: flex; gap: 20px;">';
		
		// LEFT COLUMN: Identical (to delete)
		print '<div style="flex: 1;">';
		print '<h4 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #d00;">'.$langs->trans("IdenticalCompanies").' ('.count($duplicates['identical']).')</h4>';
		
		if (!empty($duplicates['identical'])) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="confirm_delete_companies">';
			print '<input type="hidden" name="confirm" value="yes">';
			
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<th width="30"><input type="checkbox" id="select_all_companies"></th>';
			print '<th>'.$langs->trans("Master").' ('.$langs->trans("KeepThis").')</th>';
			print '<th>'.$langs->trans("Duplicates").' ('.$langs->trans("WillBeDeleted").')</th>';
			print '</tr>';
			
			foreach ($duplicates['identical'] as $group) {
				print '<tr class="oddeven">';
				print '<td>';
				foreach ($group['duplicates'] as $dup_id) {
					print '<input type="checkbox" name="company_ids[]" value="'.$dup_id.'" class="company_checkbox">';
				}
				print '</td>';
				print '<td><strong>'.$group['name'].'</strong><br>';
				print '<span class="opacitymedium">ID: '.$group['master_id'].'</span>';
				print '</td>';
				print '<td>';
				foreach ($group['duplicates'] as $dup_id) {
					print '<span class="badge badge-status8 badge-status">ID: '.$dup_id.'</span> ';
				}
				print '<br><span class="opacitymedium">'.count($group['duplicates']).' Duplikat(e)</span>';
				print '</td>';
				print '</tr>';
			}
			
			print '</table>';
			print '<br><div class="center"><input type="submit" class="button butActionDelete" value="'.$langs->trans("DeleteSelected").'"></div>';
			print '</form>';
			
			print '<script>
			document.getElementById("select_all_companies").addEventListener("change", function() {
				var checkboxes = document.getElementsByClassName("company_checkbox");
				for (var i = 0; i < checkboxes.length; i++) {
					checkboxes[i].checked = this.checked;
				}
			});
			</script>';
		} else {
			print '<div class="info">'.$langs->trans("NoIdenticalCompaniesFound").'</div>';
		}
		
		print '</div>';
		
		// RIGHT COLUMN: Similar (to merge)
		print '<div style="flex: 1;">';
		print '<h4 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffa500;">'.$langs->trans("SimilarCompanies").' ('.count($duplicates['similar']).')</h4>';
		
		if (!empty($duplicates['similar'])) {
			foreach ($duplicates['similar'] as $group) {
				print '<div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #fafafa;">';
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="confirm_merge_companies">';
				print '<input type="hidden" name="confirm" value="yes">';
				print '<input type="hidden" name="master_id" value="'.$group['master_id'].'">';
				
				print '<strong>'.$group['name'].'</strong><br>';
				print '<small>'.$group['address'].', '.$group['zip'].' '.$group['town'].'</small><br>';
				print '<small>'.$group['email'].' | '.$group['phone'].'</small><br><br>';
				
				print '<strong>'.$langs->trans("Duplicates").':</strong><br>';
				foreach ($group['duplicates'] as $dup) {
					print '<label><input type="checkbox" name="duplicate_ids[]" value="'.$dup['id'].'"> ';
					print $dup['address'].', '.$dup['zip'].' '.$dup['town'].' | '.$dup['email'].'</label><br>';
				}
				
				print '<br><input type="submit" class="button smallpaddingimp" value="'.$langs->trans("MergeSelected").'">';
				print '</form>';
				print '</div>';
			}
		} else {
			print '<div class="info">'.$langs->trans("NoSimilarCompaniesFound").'</div>';
		}
		
		print '</div>';
		print '</div>';
		
	} elseif ($type == 'contact') {
		$duplicates = $duplicateManager->findDuplicateContacts();
		
		print '<br><h3>'.$langs->trans("ContactDuplicates").'</h3>';
		
		// Two-column layout
		print '<div style="display: flex; gap: 20px;">';
		
		// LEFT COLUMN: Identical (to delete)
		print '<div style="flex: 1;">';
		print '<h4 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #d00;">'.$langs->trans("IdenticalContacts").' ('.count($duplicates['identical']).')</h4>';
		
		if (!empty($duplicates['identical'])) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="confirm_delete_contacts">';
			print '<input type="hidden" name="confirm" value="yes">';
			
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<th width="30"><input type="checkbox" id="select_all_contacts"></th>';
			print '<th>'.$langs->trans("Master").' ('.$langs->trans("KeepThis").')</th>';
			print '<th>'.$langs->trans("Company").'</th>';
			print '<th>'.$langs->trans("Duplicates").' ('.$langs->trans("WillBeDeleted").')</th>';
			print '</tr>';
			
			foreach ($duplicates['identical'] as $group) {
				print '<tr class="oddeven">';
				print '<td>';
				foreach ($group['duplicates'] as $dup_id) {
					print '<input type="checkbox" name="contact_ids[]" value="'.$dup_id.'" class="contact_checkbox">';
				}
				print '</td>';
				print '<td><strong>'.$group['name'].'</strong><br>';
				print '<span class="opacitymedium">ID: '.$group['master_id'].'</span>';
				print '</td>';
				print '<td>'.$group['company'].'</td>';
				print '<td>';
				foreach ($group['duplicates'] as $dup_id) {
					print '<span class="badge badge-status8 badge-status">ID: '.$dup_id.'</span> ';
				}
				print '<br><span class="opacitymedium">'.count($group['duplicates']).' Duplikat(e)</span>';
				print '</td>';
				print '</tr>';
			}
			
			print '</table>';
			print '<br><div class="center"><input type="submit" class="button butActionDelete" value="'.$langs->trans("DeleteSelected").'"></div>';
			print '</form>';
			
			print '<script>
			document.getElementById("select_all_contacts").addEventListener("change", function() {
				var checkboxes = document.getElementsByClassName("contact_checkbox");
				for (var i = 0; i < checkboxes.length; i++) {
					checkboxes[i].checked = this.checked;
				}
			});
			</script>';
		} else {
			print '<div class="info">'.$langs->trans("NoIdenticalContactsFound").'</div>';
		}
		
		print '</div>';
		
		// RIGHT COLUMN: Similar (to merge)
		print '<div style="flex: 1;">';
		print '<h4 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffa500;">'.$langs->trans("SimilarContacts").' ('.count($duplicates['similar']).')</h4>';
		
		if (!empty($duplicates['similar'])) {
			foreach ($duplicates['similar'] as $group) {
				print '<div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #fafafa;">';
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="confirm_merge_contacts">';
				print '<input type="hidden" name="confirm" value="yes">';
				print '<input type="hidden" name="master_id" value="'.$group['master_id'].'">';
				
				print '<strong>'.$group['name'].'</strong> ('.$group['company'].')<br>';
				print '<small>'.$group['email'].' | '.$group['phone'].' | '.$group['mobile'].'</small><br><br>';
				
				print '<strong>'.$langs->trans("Duplicates").':</strong><br>';
				foreach ($group['duplicates'] as $dup) {
					print '<label><input type="checkbox" name="duplicate_ids[]" value="'.$dup['id'].'"> ';
					print $dup['company'].' | '.$dup['email'].' | '.$dup['mobile'].'</label><br>';
				}
				
				print '<br><input type="submit" class="button smallpaddingimp" value="'.$langs->trans("MergeSelected").'">';
				print '</form>';
				print '</div>';
			}
		} else {
			print '<div class="info">'.$langs->trans("NoSimilarContactsFound").'</div>';
		}
		
		print '</div>';
		print '</div>';
	}
}

// Show recent duplicate actions log
print '<br><br>';
print load_fiche_titre($langs->trans("RecentDuplicateActions"), '', '');

// Delete logs buttons
print '<div class="tabsAction">';
print '<a href="#" onclick="return confirmDeleteDuplicateLogs(0);" class="butActionDelete">'.$langs->trans("DeleteAllDuplicateLogs").'</a>';
print '<a href="#" onclick="return confirmDeleteDuplicateLogs(30);" class="butAction">'.$langs->trans("DeleteDuplicateLogsOlderThan30Days").'</a>';
print '<a href="#" onclick="return confirmDeleteDuplicateLogs(90);" class="butAction">'.$langs->trans("DeleteDuplicateLogsOlderThan90Days").'</a>';
print '</div>';

$logs = $duplicateManager->getDuplicateLogs(50);

if (!empty($logs)) {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Date").'</th>';
	print '<th>'.$langs->trans("Type").'</th>';
	print '<th>'.$langs->trans("Action").'</th>';
	print '<th>'.$langs->trans("SourceID").'</th>';
	print '<th>'.$langs->trans("TargetID").'</th>';
	print '<th>'.$langs->trans("User").'</th>';
	print '</tr>';
	
	foreach ($logs as $log) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($log->date_action, 'dayhour').'</td>';
		print '<td>'.ucfirst($log->type).'</td>';
		print '<td>';
		if ($log->action == 'delete') {
			print '<span class="badge badge-status8 badge-status">'.$langs->trans("Delete").'</span>';
		} else {
			print '<span class="badge badge-status4 badge-status">'.$langs->trans("Merge").'</span>';
		}
		print '</td>';
		print '<td>'.$log->source_id.'</td>';
		print '<td>'.($log->target_id > 0 ? $log->target_id : '-').'</td>';
		print '<td>'.$log->login.'</td>';
		print '</tr>';
	}
	
	print '</table>';
} else {
	print '<div class="info">'.$langs->trans("NoRecentActions").'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
