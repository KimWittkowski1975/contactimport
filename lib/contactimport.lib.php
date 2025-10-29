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
 * \file        lib/contactimport.lib.php
 * \ingroup     contactimport
 * \brief       Library files with common functions for ContactImport
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function contactimportAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("contactimport@contactimport");

	$h = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/setup.php";
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/templates.php";
	$head[$h][1] = $langs->trans("Templates");
	$head[$h][2] = 'templates';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/ftp.php";
	$head[$h][1] = $langs->trans("FTPConfiguration");
	$head[$h][2] = 'ftp';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/logs.php";
	$head[$h][1] = $langs->trans("LogsAndFiles");
	$head[$h][2] = 'logs';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/duplicates.php";
	$head[$h][1] = $langs->trans("DuplicateManagement");
	$head[$h][2] = 'duplicates';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/admin/about.php";
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'contactimport');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'contactimport', 'remove');

	return $head;
}

/**
 * Prepare main navigation head for ContactImport pages
 *
 * @return array
 */
function contactimportPrepareHead()
{
	global $langs, $conf;

	$langs->load("contactimport@contactimport");

	$h = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/upload.php";
	$head[$h][1] = $langs->trans("CSVUpload");
	$head[$h][2] = 'upload';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/contactimport/import.php";
	$head[$h][1] = $langs->trans("ImportHistory");
	$head[$h][2] = 'import';
	$h++;

	return $head;
}

/**
 * Get available Dolibarr company fields for mapping
 *
 * @return array Array of fields with their labels
 */
function getCompanyMappingFields()
{
	global $langs;
	
	$langs->load("companies");
	
	$fields = array(
		'' => $langs->trans("None"),
		'nom' => $langs->trans("CompanyName"),
		'name_alias' => $langs->trans("AliasNames"),
		'address' => $langs->trans("Address"),
		'zip' => $langs->trans("Zip"),
		'town' => $langs->trans("Town"),
		'country_code' => $langs->trans("Country"),
		'phone' => $langs->trans("Phone"),
		'fax' => $langs->trans("Fax"),
		'email' => $langs->trans("EMail"),
		'url' => $langs->trans("Web"),
		'siren' => $langs->trans("ProfId1"),
		'siret' => $langs->trans("ProfId2"),
		'ape' => $langs->trans("ProfId3"),
		'idprof4' => $langs->trans("ProfId4"),
		'idprof5' => $langs->trans("ProfId5"),
		'idprof6' => $langs->trans("ProfId6"),
		'tva_intra' => $langs->trans("VATIntra"),
		'capital' => $langs->trans("Capital"),
		'typent_code' => $langs->trans("CompanyType"),
		'code_client' => $langs->trans("CustomerCode"),
		'code_fournisseur' => $langs->trans("SupplierCode"),
		'note_public' => $langs->trans("NotePublic"),
		'note_private' => $langs->trans("NotePrivate"),
	);
	
	return $fields;
}

/**
 * Get available Dolibarr contact fields for mapping
 *
 * @return array Array of fields with their labels
 */
function getContactMappingFields()
{
	global $langs;
	
	$langs->load("contacts");
	
	$fields = array(
		'' => $langs->trans("None"),
		'civility_code' => $langs->trans("UserTitle"),
		'lastname' => $langs->trans("Lastname"),
		'firstname' => $langs->trans("Firstname"),
		'address' => $langs->trans("Address"),
		'zip' => $langs->trans("Zip"),
		'town' => $langs->trans("Town"),
		'country_code' => $langs->trans("Country"),
		'phone' => $langs->trans("Phone"),
		'phone_perso' => $langs->trans("PhonePerso"),
		'phone_mobile' => $langs->trans("PhoneMobile"),
		'fax' => $langs->trans("Fax"),
		'email' => $langs->trans("EMail"),
		'birthday' => $langs->trans("Birthday"),
		'poste' => $langs->trans("PostOrFunction"),
		'note_public' => $langs->trans("NotePublic"),
		'note_private' => $langs->trans("NotePrivate"),
	);
	
	return $fields;
}

/**
 * Get CSV separator options
 *
 * @return array Array of separator options
 */
function getCSVSeparatorOptions()
{
	global $langs;
	
	return array(
		';' => $langs->trans("Semicolon").' (;)',
		',' => $langs->trans("Comma").' (,)',
		'|' => $langs->trans("Pipe").' (|)',
		'\t' => $langs->trans("Tab"),
	);
}

/**
 * Get CSV enclosure options
 *
 * @return array Array of enclosure options
 */
function getCSVEnclosureOptions()
{
	global $langs;
	
	return array(
		'"' => $langs->trans("DoubleQuote").' (")',
		"'" => $langs->trans("SingleQuote")." (')",
		'' => $langs->trans("None"),
	);
}

/**
 * Validate CSV file format
 *
 * @param string $filepath Path to CSV file
 * @param string $separator CSV separator
 * @param string $enclosure CSV enclosure
 * @return array Validation result with status and message
 */
function validateCSVFile($filepath, $separator = ';', $enclosure = '"')
{
	$result = array('status' => true, 'message' => '', 'headers' => array(), 'sample_lines' => array());
	
	if (!file_exists($filepath)) {
		$result['status'] = false;
		$result['message'] = 'File not found';
		return $result;
	}
	
	$handle = fopen($filepath, 'r');
	if (!$handle) {
		$result['status'] = false;
		$result['message'] = 'Cannot read file';
		return $result;
	}
	
	// Read headers
	$headers = fgetcsv($handle, 0, $separator, $enclosure);
	if ($headers === false) {
		$result['status'] = false;
		$result['message'] = 'Cannot read CSV headers';
		fclose($handle);
		return $result;
	}
	
	$result['headers'] = $headers;
	
	// Read sample lines
	$lineCount = 0;
	while (($line = fgetcsv($handle, 0, $separator, $enclosure)) !== false && $lineCount < 5) {
		$result['sample_lines'][] = $line;
		$lineCount++;
	}
	
	fclose($handle);
	
	return $result;
}

/**
 * Get import session status options
 *
 * @return array Array of status options
 */
function getImportSessionStatusOptions()
{
	global $langs;
	
	return array(
		0 => $langs->trans("Draft"),
		1 => $langs->trans("Uploaded"),
		2 => $langs->trans("Mapped"),
		3 => $langs->trans("Processing"),
		4 => $langs->trans("Completed"),
		5 => $langs->trans("Error"),
	);
}