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
 * \file        contactimport/admin/ftp.php
 * \ingroup     contactimport
 * \brief       FTP/SFTP configuration page for Contact Import module
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

// Translations
$langs->loadLangs(array("admin", "contactimport@contactimport"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters = array(
	'CONTACTIMPORT_FTP_ENABLED' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_TYPE' => array(
		'type' => 'select',
		'choices' => array('ftp' => 'FTP', 'sftp' => 'SFTP'),
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_HOST' => array(
		'css' => 'minwidth300',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_PORT' => array(
		'css' => 'minwidth100',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_USER' => array(
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_PASSWORD' => array(
		'type' => 'password',
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_PATH' => array(
		'css' => 'minwidth300',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_PASSIVE' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_AUTO_IMPORT' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_DELETE_AFTER_IMPORT' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_FILE_PATTERN' => array(
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_FTP_SYNC_INTERVAL' => array(
		'css' => 'minwidth100',
		'enabled' => 1
	),
);

/*
 * Actions
 */

if ($action == 'update' && !GETPOST('cancel', 'alpha')) {
	$db->begin();
	
	$error = 0;
	foreach ($arrayofparameters as $constname => $val) {
		if (empty($val['enabled'])) {
			continue;
		}
		
		$value = GETPOST($constname, ($val['type'] == 'password' ? 'none' : 'alpha'));
		
		// Special handling for password - encrypt it
		if ($val['type'] == 'password' && !empty($value)) {
			// Only update password if it was changed (not empty)
			$result = dolibarr_set_const($db, $constname, $value, 'chaine', 0, '', $conf->entity);
		} elseif ($val['type'] != 'password') {
			$result = dolibarr_set_const($db, $constname, $value, 'chaine', 0, '', $conf->entity);
		} else {
			$result = 1; // Skip empty password field
		}
		
		if (!$result > 0) {
			$error++;
		}
	}
	
	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
	
	$action = '';
}

// Test FTP connection
if ($action == 'test') {
	$ftp_type = getDolGlobalString('CONTACTIMPORT_FTP_TYPE', 'sftp');
	$ftp_host = getDolGlobalString('CONTACTIMPORT_FTP_HOST');
	$ftp_port = getDolGlobalInt('CONTACTIMPORT_FTP_PORT', ($ftp_type == 'sftp' ? 22 : 21));
	$ftp_user = getDolGlobalString('CONTACTIMPORT_FTP_USER');
	$ftp_password = getDolGlobalString('CONTACTIMPORT_FTP_PASSWORD');
	$ftp_path = getDolGlobalString('CONTACTIMPORT_FTP_PATH', '/');
	
	if (empty($ftp_host) || empty($ftp_user)) {
		setEventMessages($langs->trans("FTPConfigurationIncomplete"), null, 'errors');
	} else {
		$connection_success = false;
		$error_message = '';
		
		if ($ftp_type == 'sftp') {
			// Test SFTP connection
			if (!function_exists('ssh2_connect')) {
				setEventMessages($langs->trans("SSH2ExtensionNotAvailable"), null, 'errors');
			} else {
				$connection = @ssh2_connect($ftp_host, $ftp_port);
				if ($connection) {
					if (@ssh2_auth_password($connection, $ftp_user, $ftp_password)) {
						$sftp = @ssh2_sftp($connection);
						if ($sftp) {
							$connection_success = true;
						} else {
							$error_message = $langs->trans("SFTPInitializationFailed");
						}
					} else {
						$error_message = $langs->trans("AuthenticationFailed");
					}
				} else {
					$error_message = $langs->trans("ConnectionFailed");
				}
			}
		} else {
			// Test FTP connection
			$connection = @ftp_connect($ftp_host, $ftp_port, 30);
			if ($connection) {
				if (@ftp_login($connection, $ftp_user, $ftp_password)) {
					if (getDolGlobalString('CONTACTIMPORT_FTP_PASSIVE')) {
						ftp_pasv($connection, true);
					}
					$connection_success = true;
					@ftp_close($connection);
				} else {
					$error_message = $langs->trans("AuthenticationFailed");
					@ftp_close($connection);
				}
			} else {
				$error_message = $langs->trans("ConnectionFailed");
			}
		}
		
		if ($connection_success) {
			setEventMessages($langs->trans("FTPConnectionSuccessful"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("FTPConnectionFailed").': '.$error_message, null, 'errors');
		}
	}
	
	$action = '';
}

/*
 * View
 */

$page_name = "FTPConfiguration";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'ftp', $langs->trans("ContactImportSetup"), -1, 'contactimport@contactimport');

// Setup page
print info_admin($langs->trans("FTPConfigurationDescription"));

// Check if required PHP extensions are available
$warnings = array();
if (!function_exists('ftp_connect')) {
	$warnings[] = $langs->trans("FTPExtensionNotAvailable");
}
if (!function_exists('ssh2_connect')) {
	$warnings[] = $langs->trans("SSH2ExtensionNotAvailable");
}

if (!empty($warnings)) {
	print '<div class="warning">';
	print '<strong>'.$langs->trans("Warning").':</strong><br>';
	foreach ($warnings as $warning) {
		print '- '.$warning.'<br>';
	}
	print '</div><br>';
}

if ($action == 'edit') {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="titlefield">'.$langs->trans("Parameter").'</td>';
	print '<td>'.$langs->trans("Value").'</td>';
	print '</tr>';

	foreach ($arrayofparameters as $constname => $val) {
		if (empty($val['enabled'])) {
			continue;
		}
		
		print '<tr class="oddeven">';
		print '<td>';
		$tooltiphelp = (($langs->trans($constname.'Tooltip') != $constname.'Tooltip') ? $langs->trans($constname.'Tooltip') : '');
		print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
		print '</td>';
		print '<td>';

		if ($val['type'] == 'yesno') {
			print $form->selectyesno($constname, getDolGlobalString($constname), 1);
		} elseif ($val['type'] == 'select') {
			print $form->selectarray($constname, $val['choices'], getDolGlobalString($constname));
		} elseif ($val['type'] == 'password') {
			print '<input type="password" name="'.$constname.'" class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="" placeholder="'.(getDolGlobalString($constname) ? '••••••••' : $langs->trans("None")).'">';
			if (getDolGlobalString($constname)) {
				print '<br><small class="opacitymedium">'.$langs->trans("LeaveEmptyToKeepCurrent").'</small>';
			}
		} else {
			print '<input name="'.$constname.'" class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.getDolGlobalString($constname).'">';
		}
		
		print '</td></tr>';
	}
	print '</table>';

	print '<br><div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
	print '&nbsp;';
	print '<input class="button button-cancel" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print '</form>';
	print '<br>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="titlefield">'.$langs->trans("Parameter").'</td>';
	print '<td>'.$langs->trans("Value").'</td>';
	print '</tr>';

	foreach ($arrayofparameters as $constname => $val) {
		if (empty($val['enabled'])) {
			continue;
		}

		print '<tr class="oddeven">';
		print '<td>';
		$tooltiphelp = (($langs->trans($constname.'Tooltip') != $constname.'Tooltip') ? $langs->trans($constname.'Tooltip') : '');
		print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
		print '</td>';
		print '<td>';

		if ($val['type'] == 'select') {
			$selected = getDolGlobalString($constname);
			print $val['choices'][$selected];
		} elseif ($val['type'] == 'yesno') {
			print ajax_constantonoff($constname);
		} elseif ($val['type'] == 'password') {
			print getDolGlobalString($constname) ? '••••••••' : '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		} else {
			$value = getDolGlobalString($constname);
			print !empty($value) ? $value : '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		}

		print '</td></tr>';
	}

	print '</table>';

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=test&token='.newToken().'">'.$langs->trans("TestConnection").'</a>';
	print '</div>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
