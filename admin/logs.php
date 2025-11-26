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
 * \file        contactimport/admin/logs.php
 * \ingroup     contactimport
 * \brief       Logs and Downloaded Files management page
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once '../lib/contactimport.lib.php';
dol_include_once('/contactimport/class/contactimportsession.class.php');

// Translations
$langs->loadLangs(array("admin", "contactimport@contactimport", "other"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$file_to_delete = GETPOST('file', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$session_id = GETPOST('session_id', 'int');
$days_old = GETPOST('days_old', 'int');

// Directories
$upload_dir = getDolGlobalString('CONTACTIMPORT_TEMP_DIR', DOL_DATA_ROOT.'/contactimport/temp');
$archive_dir = $upload_dir.'/archive';

/*
 * Actions
 */

// Delete downloaded file
if ($action == 'confirm_delete_file' && $confirm == 'yes' && !empty($file_to_delete)) {
	$filepath = $upload_dir.'/'.basename($file_to_delete);
	if (file_exists($filepath)) {
		if (unlink($filepath)) {
			setEventMessages($langs->trans("FileDeleted"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorDeletingFile"), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans("FileNotFound"), null, 'errors');
	}
	$action = '';
}

// Delete all downloaded files
if ($action == 'confirm_delete_all' && $confirm == 'yes') {
	$deleted_count = 0;
	$error_count = 0;
	
	if (is_dir($upload_dir)) {
		$files = scandir($upload_dir);
		foreach ($files as $file) {
			if ($file != '.' && $file != '..' && is_file($upload_dir.'/'.$file)) {
				if (unlink($upload_dir.'/'.$file)) {
					$deleted_count++;
				} else {
					$error_count++;
				}
			}
		}
	}
	
	if ($deleted_count > 0) {
		setEventMessages($langs->trans("FilesDeleted", $deleted_count), null, 'mesgs');
	}
	if ($error_count > 0) {
		setEventMessages($langs->trans("ErrorDeletingFiles", $error_count), null, 'errors');
	}
	$action = '';
}

// Delete import logs
if ($action == 'confirm_delete_logs' && $confirm == 'yes') {
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_logs WHERE 1=1";
	
	// Optional: Only delete logs older than X days
	if ($days_old > 0) {
		$date_limit = dol_now() - ($days_old * 24 * 3600);
		$sql .= " AND fk_session IN (SELECT rowid FROM ".MAIN_DB_PREFIX."contactimport_sessions WHERE date_creation < '".$db->idate($date_limit)."')";
	}
	
	$resql = $db->query($sql);
	if ($resql) {
		$deleted_count = $db->affected_rows($resql);
		setEventMessages($langs->trans("LogsDeleted", $deleted_count), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	$action = '';
}

// Reset all statistics (delete sessions and logs)
if ($action == 'confirm_reset_statistics' && $confirm == 'yes') {
	$db->begin();
	
	$error = 0;
	
	// Delete all logs first
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_logs WHERE fk_session IN (SELECT rowid FROM ".MAIN_DB_PREFIX."contactimport_sessions WHERE entity = ".$conf->entity.")";
	$resql = $db->query($sql);
	if (!$resql) {
		$error++;
		setEventMessages($db->lasterror(), null, 'errors');
	}
	
	// Delete all sessions
	if (!$error) {
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_sessions WHERE entity = ".$conf->entity;
		$resql = $db->query($sql);
		if (!$resql) {
			$error++;
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	
	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans("StatisticsReset"), null, 'mesgs');
	} else {
		$db->rollback();
	}
	
	$action = '';
}

// View import session details
if ($action == 'view_session' && $session_id > 0) {
	// Details will be shown in the view section below
}

/*
 * View
 */

$page_name = "LogsAndFiles";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'logs', $langs->trans("ContactImportSetup"), -1, 'contactimport@contactimport');

print info_admin($langs->trans("LogsAndFilesDescription"));

// JavaScript for confirmations
?>
<script type="text/javascript">
function confirmDeleteFile(filename) {
	if (confirm("<?php echo $langs->trans("ConfirmDeleteFile"); ?>")) {
		window.location.href = "<?php echo $_SERVER["PHP_SELF"]; ?>?action=confirm_delete_file&file=" + encodeURIComponent(filename) + "&confirm=yes&token=<?php echo newToken(); ?>";
		return true;
	}
	return false;
}

function confirmDeleteAll() {
	if (confirm("<?php echo $langs->trans("ConfirmDeleteAllFiles"); ?>")) {
		window.location.href = "<?php echo $_SERVER["PHP_SELF"]; ?>?action=confirm_delete_all&confirm=yes&token=<?php echo newToken(); ?>";
		return true;
	}
	return false;
}

function confirmDeleteLogs(daysOld) {
	var message = daysOld > 0 
		? "<?php echo $langs->trans("ConfirmDeleteLogsOlderThan"); ?>".replace('%s', daysOld)
		: "<?php echo $langs->trans("ConfirmDeleteAllLogs"); ?>";
	if (confirm(message)) {
		window.location.href = "<?php echo $_SERVER["PHP_SELF"]; ?>?action=confirm_delete_logs&days_old=" + daysOld + "&confirm=yes&token=<?php echo newToken(); ?>";
		return true;
	}
	return false;
}

function confirmResetStatistics() {
	if (confirm("<?php echo $langs->trans("ConfirmResetAllStatistics"); ?>\n\n<?php echo $langs->trans("ThisWillDeleteAllSessionsAndLogs"); ?>")) {
		window.location.href = "<?php echo $_SERVER["PHP_SELF"]; ?>?action=confirm_reset_statistics&confirm=yes&token=<?php echo newToken(); ?>";
		return true;
	}
	return false;
}
</script>
<?php

// =====================================
// SECTION 1: Downloaded Files
// =====================================
print '<br>';
print load_fiche_titre($langs->trans("DownloadedFiles"), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Filename").'</th>';
print '<th class="right">'.$langs->trans("Size").'</th>';
print '<th class="center">'.$langs->trans("Date").'</th>';
print '<th class="center">'.$langs->trans("Action").'</th>';
print '</tr>';

$files = array();
if (is_dir($upload_dir)) {
	$dir_files = scandir($upload_dir);
	foreach ($dir_files as $file) {
		if ($file != '.' && $file != '..' && is_file($upload_dir.'/'.$file)) {
			$files[] = array(
				'name' => $file,
				'size' => filesize($upload_dir.'/'.$file),
				'date' => filemtime($upload_dir.'/'.$file),
				'path' => $upload_dir.'/'.$file
			);
		}
	}
}

// Sort by date descending
usort($files, function($a, $b) {
	return $b['date'] - $a['date'];
});

if (count($files) > 0) {
	foreach ($files as $file) {
		print '<tr class="oddeven">';
		print '<td>';
		print '<strong>'.dol_escape_htmltag($file['name']).'</strong>';
		print '</td>';
		print '<td class="right">'.dol_print_size($file['size']).'</td>';
		print '<td class="center">'.dol_print_date($file['date'], 'dayhour').'</td>';
		print '<td class="center">';
		// Download link
		print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=contactimport&file=temp/'.urlencode($file['name']).'" title="'.$langs->trans("Download").'">';
		print img_picto($langs->trans("Download"), 'download');
		print '</a> ';
		// Delete link
		print '<a href="#" onclick="return confirmDeleteFile(\''.dol_escape_js($file['name']).'\');" title="'.$langs->trans("Delete").'">';
		print img_delete();
		print '</a>';
		print '</td>';
		print '</tr>';
	}
	
	// Total row
	$total_size = array_sum(array_column($files, 'size'));
	print '<tr class="liste_total">';
	print '<td><strong>'.$langs->trans("Total").'</strong></td>';
	print '<td class="right"><strong>'.dol_print_size($total_size).'</strong></td>';
	print '<td colspan="2"></td>';
	print '</tr>';
	
} else {
	print '<tr><td colspan="4" class="opacitymedium center">'.$langs->trans("NoFilesFound").'</td></tr>';
}

print '</table>';

if (count($files) > 0) {
	print '<br>';
	print '<div class="tabsAction">';
	print '<a href="#" onclick="return confirmDeleteAll();" class="butActionDelete">'.$langs->trans("DeleteAllFiles").'</a>';
	print '</div>';
}

// =====================================
// SECTION 2: Import Statistics Summary
// =====================================
print '<br><br>';
print load_fiche_titre($langs->trans("ImportStatistics"), '', '');

// Get statistics from database
$sql = "SELECT ";
$sql .= " COUNT(*) as total_imports,";
$sql .= " SUM(total_lines) as total_lines,";
$sql .= " SUM(processed_lines) as total_processed,";
$sql .= " SUM(success_lines) as total_success,";
$sql .= " SUM(error_lines) as total_errors,";
$sql .= " SUM(CASE WHEN status = ".ContactImportSession::STATUS_COMPLETED." THEN 1 ELSE 0 END) as completed_imports,";
$sql .= " SUM(CASE WHEN status = ".ContactImportSession::STATUS_ERROR." THEN 1 ELSE 0 END) as failed_imports";
$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions";
$sql .= " WHERE entity = ".$conf->entity;

$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Statistic").'</th>';
	print '<th class="right">'.$langs->trans("Value").'</th>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("TotalImports").'</td>';
	print '<td class="right"><strong>'.($obj->total_imports ?: 0).'</strong></td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CompletedImports").'</td>';
	print '<td class="right"><span class="badge badge-status4 badge-status">'.($obj->completed_imports ?: 0).'</span></td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("FailedImports").'</td>';
	print '<td class="right"><span class="badge badge-status8 badge-status">'.($obj->failed_imports ?: 0).'</span></td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("TotalLinesProcessed").'</td>';
	print '<td class="right">'.number_format($obj->total_processed ?: 0, 0, ',', '.').'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("SuccessfulLines").'</td>';
	print '<td class="right"><span style="color: green; font-weight: bold;">'.number_format($obj->total_success ?: 0, 0, ',', '.').'</span></td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("ErrorLines").'</td>';
	print '<td class="right"><span style="color: red; font-weight: bold;">'.number_format($obj->total_errors ?: 0, 0, ',', '.').'</span></td>';
	print '</tr>';
	
	// Calculate success rate
	if ($obj->total_processed > 0) {
		$success_rate = round(($obj->total_success / $obj->total_processed) * 100, 2);
		print '<tr class="oddeven">';
		print '<td><strong>'.$langs->trans("SuccessRate").'</strong></td>';
		print '<td class="right"><strong>'.$success_rate.'%</strong></td>';
		print '</tr>';
	}
	
	print '</table>';
	
	$db->free($resql);
}

// Delete logs action buttons
print '<br>';
print '<div class="tabsAction">';
print '<a href="#" onclick="return confirmResetStatistics();" class="butActionDelete">'.$langs->trans("ResetAllStatistics").'</a>';
print '<a href="#" onclick="return confirmDeleteLogs(0);" class="butActionDelete">'.$langs->trans("DeleteAllLogs").'</a>';
print '<a href="#" onclick="return confirmDeleteLogs(30);" class="butAction">'.$langs->trans("DeleteLogsOlderThan", 30).'</a>';
print '<a href="#" onclick="return confirmDeleteLogs(90);" class="butAction">'.$langs->trans("DeleteLogsOlderThan", 90).'</a>';
print '</div>';

// =====================================
// SECTION 3: Import History
// =====================================
print '<br><br>';
print load_fiche_titre($langs->trans("ImportHistory"), '', '');

// View session details if requested
if ($action == 'view_session' && $session_id > 0) {
	$session = new ContactImportSession($db);
	$result = $session->fetch($session_id);
	
	if ($result > 0) {
		print '<div class="fiche">';
		print '<table class="border centpercent">';
		
		print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.$session->ref.'</td></tr>';
		print '<tr><td>'.$langs->trans("Filename").'</td><td><strong>'.$session->filename.'</strong></td></tr>';
		print '<tr><td>'.$langs->trans("Status").'</td><td>'.$session->getLibStatut(5).'</td></tr>';
		print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($session->date_creation, 'dayhour').'</td></tr>';
		
		if ($session->date_import) {
			print '<tr><td>'.$langs->trans("DateImport").'</td><td>'.dol_print_date($session->date_import, 'dayhour').'</td></tr>';
		}
		
		print '<tr><td>'.$langs->trans("TotalLines").'</td><td>'.$session->total_lines.'</td></tr>';
		print '<tr><td>'.$langs->trans("ProcessedLines").'</td><td>'.$session->processed_lines.'</td></tr>';
		print '<tr><td>'.$langs->trans("SuccessLines").'</td><td><span style="color: green; font-weight: bold;">'.$session->success_lines.'</span></td></tr>';
		print '<tr><td>'.$langs->trans("ErrorLines").'</td><td><span style="color: red; font-weight: bold;">'.$session->error_lines.'</span></td></tr>';
		
		if ($session->processed_lines > 0) {
			$success_rate = round(($session->success_lines / $session->processed_lines) * 100, 2);
			print '<tr><td><strong>'.$langs->trans("SuccessRate").'</strong></td><td><strong>'.$success_rate.'%</strong></td></tr>';
		}
		
		if (!empty($session->error_log)) {
			print '<tr><td valign="top">'.$langs->trans("ErrorLog").'</td><td>';
			$errors = json_decode($session->error_log, true);
			if (is_array($errors) && count($errors) > 0) {
				print '<ul>';
				foreach ($errors as $error) {
					print '<li>'.dol_escape_htmltag($error).'</li>';
				}
				print '</ul>';
			} else {
				print '<pre>'.dol_escape_htmltag($session->error_log).'</pre>';
			}
			print '</td></tr>';
		}
		
		print '</table>';
		print '</div>';
		
		print '<br>';
		print '<div class="tabsAction">';
		print '<a href="'.$_SERVER["PHP_SELF"].'?token='.newToken().'" class="butAction">'.$langs->trans("BackToList").'</a>';
		print '</div>';
		
	} else {
		setEventMessages($langs->trans("SessionNotFound"), null, 'errors');
	}
	
} else {
	// List all import sessions
	$sql = "SELECT s.rowid, s.ref, s.filename, s.status, s.date_creation, s.date_import,";
	$sql .= " s.total_lines, s.processed_lines, s.success_lines, s.error_lines";
	$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_sessions as s";
	$sql .= " WHERE s.entity = ".$conf->entity;
	$sql .= " ORDER BY s.date_creation DESC";
	$sql .= " LIMIT 50";
	
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Ref").'</th>';
		print '<th>'.$langs->trans("Filename").'</th>';
		print '<th class="center">'.$langs->trans("Status").'</th>';
		print '<th class="center">'.$langs->trans("Date").'</th>';
		print '<th class="right">'.$langs->trans("Lines").'</th>';
		print '<th class="right">'.$langs->trans("Success").'</th>';
		print '<th class="right">'.$langs->trans("Errors").'</th>';
		print '<th class="center">'.$langs->trans("Action").'</th>';
		print '</tr>';
		
		if ($num > 0) {
			$i = 0;
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				
				print '<tr class="oddeven">';
				print '<td>'.$obj->ref.'</td>';
				print '<td><strong>'.dol_escape_htmltag($obj->filename).'</strong></td>';
				print '<td class="center">';
				
				// Status badge
				$session_tmp = new ContactImportSession($db);
				$session_tmp->status = $obj->status;
				print $session_tmp->getLibStatut(5);
				
				print '</td>';
				print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
				print '<td class="right">'.$obj->processed_lines.' / '.$obj->total_lines.'</td>';
				print '<td class="right"><span style="color: green;">'.$obj->success_lines.'</span></td>';
				print '<td class="right"><span style="color: red;">'.$obj->error_lines.'</span></td>';
				print '<td class="center">';
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=view_session&session_id='.$obj->rowid.'&token='.newToken().'" title="'.$langs->trans("View").'">';
				print img_picto($langs->trans("View"), 'eye');
				print '</a>';
				print '</td>';
				print '</tr>';
				
				$i++;
			}
		} else {
			print '<tr><td colspan="8" class="opacitymedium center">'.$langs->trans("NoImportsFound").'</td></tr>';
		}
		
		print '</table>';
		
		if ($num >= 50) {
			print '<div class="opacitymedium center" style="margin-top: 10px;">'.$langs->trans("Showing50MostRecent").'</div>';
		}
		
		$db->free($resql);
	}
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
