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
echo '<tr class="oddeven"><td>'.$langs->trans("Version").'</td><td>1.3.0</td></tr>';

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

echo '<tr class="oddeven"><td><strong>1.3.4</strong><br><small>2026-02-28</small></td><td>';
echo '<strong>Test FTP Import Feature:</strong><br>';
echo '🧪 <strong>One-Click Test Import:</strong> Test FTP download and import from setup page<br>';
echo '✅ <strong>Auto-Import with Default Template:</strong> Uses default template automatically<br>';
echo '✅ <strong>Detailed Statistics:</strong> Shows file name, success/error counts, companies/contacts created/updated<br>';
echo '✅ <strong>Smart Button:</strong> Only enabled when FTP configured and default template exists<br>';
echo '✅ <strong>Error Handling:</strong> Clear messages for missing configuration<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td><strong>1.3.3</strong><br><small>2026-02-28</small></td><td>';
echo '<strong>Navigation Fix & Templates UI Redesign:</strong><br>';
echo '👍 <strong>Menu Fixed:</strong> "Mapping Templates" now accessible from Tools menu<br>';
echo '✅ <strong>Templates UI Redesigned:</strong> Same intuitive UI as mapping.php v1.3.1<br>';
echo '✅ <strong>Dolibarr-Field-Centric:</strong> List fields → select CSV column for each<br>';
echo '✅ <strong>Live Sample Data:</strong> Preview data based on selected mapping<br>';
echo '✅ <strong>Required Badges:</strong> Visual indicators for mandatory fields<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td><strong>1.3.2</strong><br><small>2026-02-28</small></td><td>';
echo '<strong>Navigation Improvement:</strong><br>';
echo '🧭 <strong>Workflow Guide on Setup:</strong> Interactive step-by-step guide with quick action buttons<br>';
echo '✅ <strong>Better Onboarding:</strong> Clear navigation from configuration to import process<br>';
echo '✅ <strong>Quick Actions:</strong> "Start New Import" and "Manage Templates" buttons<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td><strong>1.3.1</strong><br><small>2026-02-28</small></td><td>';
echo '<strong>UI Redesign - Mapping Interface:</strong><br>';
echo '🎨 <strong>Inverted Mapping UI:</strong> List Dolibarr fields → Select CSV column (more intuitive)<br>';
echo '✅ <strong>Clearer Headers:</strong> "Dolibarr Company/Contact Field" | "Select CSV Column" | "Sample Data"<br>';
echo '✅ <strong>Enhanced Dropdowns:</strong> CSV columns shown as "Column 1: Name", "Column 2: Email", etc.<br>';
echo '✅ <strong>Required Field Badges:</strong> Visual indicators for mandatory fields<br>';
echo '✅ <strong>Better Sample Data:</strong> Context-aware preview based on selected mapping<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td><strong>1.3.0</strong><br><small>2026-02-28</small></td><td>';
echo '<strong>Dual-Mapping System:</strong><br>';
echo '✅ <strong>Contact-to-Company Conversion:</strong> Each contact becomes both a Company and Contact in Dolibarr<br>';
echo '✅ <strong>Dual Field Mapping:</strong> Map single CSV fields to both Company and Contact fields simultaneously<br>';
echo '✅ <strong>New Mapping UI:</strong> Two separate tables (Company mapping + Contact mapping)<br>';
echo '✅ <strong>Smart Updates:</strong> Existing records get updated (only empty fields filled)<br>';
echo '✅ <strong>Automatic Status Management:</strong> Prospect → Customer upgrade when "Last Order Date" filled<br>';
echo '✅ <strong>Fixed Import Logic:</strong> Always creates Company+Contact pairs from CSV data<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td><strong>1.2.0</strong><br><small>2025-12-08</small></td><td>';
echo '<strong>Major Update:</strong><br>';
echo '✅ <strong>Auto-Import via Cron Job:</strong> Automated scheduled imports from FTP/SFTP<br>';
echo '✅ <strong>Update Logic for Duplicates:</strong> Updates existing records instead of skipping them<br>';
echo '✅ <strong>Smart Update:</strong> Only empty fields are updated, never overwrites existing data<br>';
echo '✅ <strong>Customer Status Automation:</strong> Automatic classification based on last order date<br>';
echo '✅ <strong>FTP File Cleanup:</strong> Automatically delete old files from FTP (configurable days)<br>';
echo '✅ <strong>Database Schema:</strong> Extended templates and FTP config tables<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td>1.1.1<br><small>2025-12-02</small></td><td>';
echo '- Duplicate check enabled by default<br>';
echo '- Separate tracking for skipped duplicates<br>';
echo '- Improved duplicate statistics<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td>1.1.0<br><small>2025-12-02</small></td><td>';
echo '- Fixed SQL installation with IF NOT EXISTS<br>';
echo '- Improved update robustness<br>';
echo '</td></tr>';

echo '<tr class="oddeven"><td>1.0.0<br><small>2025-11-23</small></td><td>';
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
