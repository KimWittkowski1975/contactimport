<?php
/* Copyright (C) 2025 Kim Wittkowski <kim.wittkowski@gmx.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/trigger_import.php
 * \ingroup contactimport
 * \brief   Trigger import for specific session
 */

// Load Dolibarr environment
$res = 0;
$path = __DIR__.'/../../../';
if (!$res && file_exists($path."main.inc.php")) {
	$res = @include $path."main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/contactimport.lib.php';
require_once __DIR__.'/../class/contactimportsession.class.php';
require_once __DIR__.'/../class/contactimportprocessor.class.php';

// Security check
if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(array("contactimport@contactimport"));

$action = GETPOST('action', 'alpha');
$session_id = GETPOST('session_id', 'int');

/*
 * Actions
 */

if ($action == 'trigger' && $session_id > 0) {
	$session = new ContactImportSession($db);
	$result = $session->fetch($session_id);
	
	if ($result > 0) {
		$processor = new ContactImportProcessor($db);
		$import_result = $processor->processImport($session, $user);
		
		if (!empty($import_result['success'])) {
			setEventMessages('Import successfully processed', null, 'mesgs');
		} else {
			setEventMessages('Import failed: '.implode(', ', $import_result['messages']), null, 'errors');
		}
		
		header('Location: logs.php');
		exit;
	} else {
		setEventMessages('Session not found', null, 'errors');
	}
}

/*
 * View
 */

llxHeader('', $langs->trans('TriggerImport'));

$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'logs', $langs->trans("ContactImportConfig"), -1, 'contactimport@contactimport');

print '<div class="info">';
print $langs->trans("TriggerImportHelp");
print '</div>';

// Show form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="trigger">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("SessionID").'</td>';
print '<td>'.$langs->trans("Action").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><input type="number" name="session_id" value="121" class="flat minwidth200"></td>';
print '<td><input type="submit" class="button" value="'.$langs->trans("TriggerImport").'"></td>';
print '</tr>';

print '</table>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
