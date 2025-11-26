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
 * \file        contactimport/admin/setup.php
 * \ingroup     contactimport
 * \brief       Configuration page for Contact Import module
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

global $langs, $user, $conf;

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
	'CONTACTIMPORT_DEFAULT_CSV_SEPARATOR' => array(
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE' => array(
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_MAX_FILE_SIZE' => array(
		'css' => 'minwidth200',
		'enabled' => 1
	),
	'CONTACTIMPORT_AUTO_CREATE_COMPANIES' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_AUTO_CREATE_CONTACTS' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_DUPLICATE_CHECK' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
	'CONTACTIMPORT_LOG_LEVEL' => array(
		'type' => 'select',
		'choices' => array('0' => 'None', '1' => 'Errors only', '2' => 'Errors and warnings', '3' => 'All'),
		'enabled' => 1
	),
	'CONTACTIMPORT_PSEUDO_CRON' => array(
		'type' => 'yesno',
		'enabled' => 1
	),
);

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$page_name = "ContactImportSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = contactimportAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'contactimport@contactimport');

// Setup page goes here
print info_admin($langs->trans("ContactImportSetupDescription"));

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
		$setupnotenabled = 0;
		print '<tr class="oddeven">';
		print '<td>';
		$tooltiphelp = (($langs->trans($constname.'Tooltip') != $constname.'Tooltip') ? $langs->trans($constname.'Tooltip') : '');
		print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
		print '</td>';
		print '<td>';

		if ($val['type'] == 'textarea') {
			print '<textarea class="flat" name="'.$constname.'" id="'.$constname.'" cols="50" rows="5" wrap="soft">'."\n";
			print getDolGlobalString($constname);
			print "</textarea>\n";
		} elseif ($val['type'] == 'html') {
			require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
			$doleditor = new DolEditor($constname, getDolGlobalString($constname), '', 160, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), ROWS_5, '90%');
			$doleditor->Create();
		} elseif ($val['type'] == 'yesno') {
			print $form->selectyesno($constname, getDolGlobalString($constname), 1);
		} elseif ($val['type'] == 'securekey') {
			print '<input required="required" type="text" class="flat" id="'.$constname.'" name="'.$constname.'" value="'.(GETPOST($constname, 'alpha') ? GETPOST($constname, 'alpha') : getDolGlobalString($constname)).'" size="40">';
			if (!empty($conf->use_javascript_ajax)) {
				print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token" class="linkobject"');
			}
		} elseif ($val['type'] == 'product') {
			if (isModEnabled("product") || isModEnabled("service")) {
				$selected = getDolGlobalString($constname);
				print img_picto('', 'product', 'class="pictofixedwidth"').$form->select_produits($selected, $constname, '', 0, 0, 1, 2, '', 1);
			}
		} elseif ($val['type'] == 'category') {
			$selected = getDolGlobalString($constname);
			print img_picto('', 'category', 'class="pictofixedwidth"').$form->select_all_categories(Categorie::TYPE_CUSTOMER, $selected, 'parent', 64, 0, 1);
		} elseif ($val['type'] == 'thirdparty') {
			$selected = getDolGlobalString($constname);
			print img_picto('', 'company', 'class="pictofixedwidth"').$form->select_company($selected, $constname, '', 1);
		} elseif ($val['type'] == 'select') {
			print $form->selectarray($constname, $val['choices'], getDolGlobalString($constname));
		} else {
			print '<input name="'.$constname.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.getDolGlobalString($constname).'">';
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
	if (!empty($conf->use_javascript_ajax)) {
		print "\n".'<script type="text/javascript">';
		print '$(document).ready(function () {
                    $("#generate_token").click(function() {
                        $.get( "'.DOL_URL_ROOT.'/core/ajax/security.php", {
                            action: \'getrandompassword\',
                            format: \'hex\',
                            length: 32
                        })
                        .done(function( data ) {
                            if (data)
                            {
                                data = data.replace(/\s+/g, \'\');
                                $("#CONTACTIMPORT_SECURE_KEY").val(data);
                            }
                            else
                            {
                                $("#CONTACTIMPORT_SECURE_KEY").val("Bad value returned");
                            }
                        });
                    });
               });';
		print '</script>';
	}

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
		} elseif ($val['type'] == 'textarea') {
			print dol_nl2br(getDolGlobalString($constname));
		} elseif ($val['type'] == 'yesno') {
			print ajax_constantonoff($constname);
		} elseif ($val['type'] == 'chaine') {
			print getDolGlobalString($constname);
		} elseif ($val['type'] == 'product') {
			$selected = getDolGlobalString($constname);
			if ($selected > 0) {
				$tmpproduct = new Product($db);
				$tmpproduct->fetch($selected);
				print $tmpproduct->getNomUrl(1);
			}
		} else {
			print getDolGlobalString($constname);
		}

		print '</td></tr>';
	}

	print '</table>';

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '</div>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();