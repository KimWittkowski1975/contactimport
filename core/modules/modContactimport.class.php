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
 * 	\defgroup   contactimport     Module Contactimport
 *  \brief      Contactimport module descriptor.
 *
 *  \file       htdocs/contactimport/core/modules/modContactimport.class.php
 *  \ingroup    contactimport
 *  \brief      Description and activation file for module Contactimport
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Contactimport
 */
class modContactimport extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		$this->numero = 918399;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'contactimport';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "interface";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Module label (no space allowed), used if translation string 'ModuleContactimportName' not found
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleContactimportDesc' not found
		$this->description = "ContactimportDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "ContactimportDescriptionLong";

		// Author
		$this->editor_name = 'Kim Wittkowski';
		$this->editor_url = 'https://';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0.1';

		// Key used in llx_const table to save module status enabled/disabled
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

		// Name of image file used for this module.
		$this->picto = 'contactimport@contactimport';

		// Define some features supported by module
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 0,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(),
			// Set here all hooks context managed by module
			'hooks' => array(),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		$this->dirs = array("/contactimport/temp");

		// Config pages. Put here list of php page, stored into contactimport/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@contactimport");

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled
		$this->depends = array();
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with

		// The language file dedicated to your module
		$this->langfiles = array("contactimport@contactimport");

		// Prerequisites
		$this->phpmin = array(7, 4); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(21, 0); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module

		// Constants
		// List of particular constants to add when module is enabled
		$this->const = array(
			1 => array('CONTACTIMPORT_DEFAULT_CSV_SEPARATOR', 'chaine', ';', 'Default CSV separator', 1),
			2 => array('CONTACTIMPORT_DEFAULT_CSV_ENCLOSURE', 'chaine', '"', 'Default CSV enclosure', 1),
			3 => array('CONTACTIMPORT_MAX_FILE_SIZE', 'chaine', '10485760', 'Maximum file size for CSV upload (10MB)', 1),
			4 => array('CONTACTIMPORT_TEMP_DIR', 'chaine', DOL_DATA_ROOT.'/contactimport/temp', 'Temporary directory for CSV files', 1),
		);

		if (!isset($conf->contactimport) || !isset($conf->contactimport->enabled)) {
			$conf->contactimport = new stdClass();
			$conf->contactimport->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		// Read permission
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Read contact import';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'contactimport';
		$this->rights[$r][5] = 'read';
		$r++;

		// Write permission
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Create/Update contact import';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'contactimport';
		$this->rights[$r][5] = 'write';
		$r++;

		// Delete permission
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Delete contact import';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'contactimport';
		$this->rights[$r][5] = 'delete';
		$r++;

		// Main menu entries to add
		$this->menu = array();
		$r = 0;

		// Left menu entry for Contact Import
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'ContactImport',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'contactimport',
			'langs' => 'contactimport@contactimport',
			'position' => 1100 + $r,
			'enabled' => '$conf->contactimport->enabled',
			'perms' => '$user->hasRight("contactimport", "contactimport", "read")',
			'target' => '',
			'user' => 2,
		);

		// CSV Upload submenu
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=contactimport',
			'type' => 'left',
			'titre' => 'CSVUpload',
			'mainmenu' => 'tools',
			'leftmenu' => 'contactimport_upload',
			'url' => '/contactimport/upload.php',
			'langs' => 'contactimport@contactimport',
			'position' => 1100 + $r,
			'enabled' => '$conf->contactimport->enabled',
			'perms' => '$user->hasRight("contactimport", "contactimport", "write")',
			'target' => '',
			'user' => 2,
		);

		// Mapping submenu
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=contactimport',
			'type' => 'left',
			'titre' => 'MappingInterface',
			'mainmenu' => 'tools',
			'leftmenu' => 'contactimport_mapping',
			'url' => '/contactimport/mapping.php',
			'langs' => 'contactimport@contactimport',
			'position' => 1100 + $r,
			'enabled' => '$conf->contactimport->enabled',
			'perms' => '$user->hasRight("contactimport", "contactimport", "write")',
			'target' => '',
			'user' => 2,
		);

		// Import History submenu
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=contactimport',
			'type' => 'left',
			'titre' => 'ImportHistory',
			'mainmenu' => 'tools',
			'leftmenu' => 'contactimport_history',
			'url' => '/contactimport/import.php',
			'langs' => 'contactimport@contactimport',
			'position' => 1100 + $r,
			'enabled' => '$conf->contactimport->enabled',
			'perms' => '$user->hasRight("contactimport", "contactimport", "read")',
			'target' => '',
			'user' => 2,
		);

		// Exports profiles provided by this module
		$r = 1;

		// Imports profiles provided by this module
		$r = 1;
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Setup document directories for file access
		if (!isset($conf->contactimport)) {
			$conf->contactimport = new stdClass();
		}
		if (!isset($conf->contactimport->multidir_output)) {
			$conf->contactimport->multidir_output = array();
		}
		if (!isset($conf->contactimport->multidir_temp)) {
			$conf->contactimport->multidir_temp = array();
		}
		
		// Set directories for all entities
		for ($i = 0; $i <= (empty($conf->multicompany->enabled) ? 0 : $conf->entity); $i++) {
			$conf->contactimport->multidir_output[$i] = DOL_DATA_ROOT.($i > 1 ? '/'.$i : '').'/contactimport';
			$conf->contactimport->multidir_temp[$i] = DOL_DATA_ROOT.($i > 1 ? '/'.$i : '').'/contactimport/temp';
		}

		$result = $this->_load_tables('/contactimport/sql/');
		if ($result < 0) {
			return -1;
		}

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}