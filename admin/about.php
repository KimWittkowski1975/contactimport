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
 * \file        admin/about.php
 * \ingroup     contactimport
 * \brief       ContactImport about page.
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/contactimport.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("errors", "admin", "contactimport@contactimport"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

// None

/*
 * View
 */

$form = new Form($db);

$page_name = "ContactImportAbout";
llxHeader('', $langs->trans($page_name), '');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans($page_name), -1, 'contactimport@contactimport');

// About page goes here
echo '<div class="div-table-responsive-no-min">';
echo '<table class="noborder allwidth">';

echo '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

// Version
echo '<tr class="oddeven"><td>'.$langs->trans("Version").'</td><td>1.0.0</td></tr>';

// License
echo '<tr class="oddeven"><td>'.$langs->trans("License").'</td><td>GPL v3+</td></tr>';

// Author
echo '<tr class="oddeven"><td>'.$langs->trans("Author").'</td><td>Kim Wittkowski</td></tr>';

// Module number
echo '<tr class="oddeven"><td>'.$langs->trans("ModuleNumber").'</td><td>918399</td></tr>';

// Description
echo '<tr class="oddeven"><td>'.$langs->trans("Description").'</td><td>'.$langs->trans("ModuleContactimportDesc").'</td></tr>';

echo '</table>';
echo '</div>';

print '<br>';

// Show changelog
print load_fiche_titre($langs->trans("ChangeLog"), '', '');

echo '<div class="div-table-responsive-no-min">';
echo '<table class="noborder allwidth">';
echo '<tr class="liste_titre"><td>'.$langs->trans("Version").'</td><td>'.$langs->trans("Description").'</td></tr>';

echo '<tr class="oddeven"><td>1.0.0</td><td>';
echo '- Initial version<br>';
echo '- CSV import for companies and contacts<br>';
echo '- Template-based mapping system<br>';
echo '- FTP/SFTP automatic file retrieval<br>';
echo '- Import statistics and logging<br>';
echo '- Duplicate check functionality<br>';
echo '</td></tr>';

echo '</table>';
echo '</div>';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
