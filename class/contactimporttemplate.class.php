<?php
/* Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        class/contactimporttemplate.class.php
 * \ingroup     contactimport
 * \brief       Class file for import templates
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for ContactImportTemplate
 */
class ContactImportTemplate extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'contactimport_template';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'contactimport_templates';

	/**
	 * @var int  Does this object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var string String with name of icon for contactimporttemplate
	 */
	public $picto = 'generic';

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>1, 'index'=>1, 'comment'=>"Id"),
		'ref' => array('type'=>'varchar(128)', 'label'=>'Ref', 'enabled'=>1, 'position'=>10, 'notnull'=>1, 'visible'=>1, 'noteditable'=>1, 'default'=>'(PROV)', 'index'=>1, 'searchall'=>1, 'showoncombobox'=>1, 'comment'=>"Reference of object"),
		'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'enabled'=>1, 'position'=>30, 'notnull'=>1, 'visible'=>1, 'searchall'=>1, 'css'=>'minwidth300', 'csslist'=>'tdoverflowmax150'),
		'description' => array('type'=>'text', 'label'=>'Description', 'enabled'=>1, 'position'=>60, 'notnull'=>0, 'visible'=>3),
		'mapping_config' => array('type'=>'text', 'label'=>'MappingConfig', 'enabled'=>1, 'position'=>70, 'notnull'=>1, 'visible'=>0),
		'csv_separator' => array('type'=>'varchar(10)', 'label'=>'CSVSeparator', 'enabled'=>1, 'position'=>80, 'notnull'=>0, 'visible'=>1, 'default'=>';'),
		'csv_enclosure' => array('type'=>'varchar(10)', 'label'=>'CSVEnclosure', 'enabled'=>1, 'position'=>90, 'notnull'=>0, 'visible'=>1, 'default'=>'"'),
		'has_header' => array('type'=>'integer', 'label'=>'HasHeader', 'enabled'=>1, 'position'=>100, 'notnull'=>0, 'visible'=>1, 'default'=>'1'),
		'is_default' => array('type'=>'integer', 'label'=>'DefaultTemplate', 'enabled'=>1, 'position'=>110, 'notnull'=>0, 'visible'=>1, 'default'=>'0'),
		'status' => array('type'=>'integer', 'label'=>'Status', 'enabled'=>1, 'position'=>120, 'notnull'=>1, 'visible'=>1, 'default'=>'1', 'index'=>1),
		'date_creation' => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>1, 'position'=>500, 'notnull'=>1, 'visible'=>-2),
		'date_modification' => array('type'=>'datetime', 'label'=>'DateModification', 'enabled'=>1, 'position'=>501, 'notnull'=>0, 'visible'=>-2),
		'fk_user_creat' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserAuthor', 'enabled'=>1, 'position'=>510, 'notnull'=>1, 'visible'=>-2, 'foreignkey'=>'user.rowid'),
		'fk_user_modif' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserModif', 'enabled'=>1, 'position'=>511, 'notnull'=>-1, 'visible'=>-2),
	);

	public $rowid;
	public $ref;
	public $label;
	public $description;
	public $mapping_config;
	public $csv_separator;
	public $csv_enclosure;
	public $has_header;
	public $is_default;
	public $status;
	public $date_creation;
	public $date_modification;
	public $fk_user_creat;
	public $fk_user_modif;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		$resultcreate = $this->createCommon($user, $notrigger);

		return $resultcreate;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		return $result;
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Get default template
	 *
	 * @return int|false Template ID if found, false otherwise
	 */
	public function getDefaultTemplate()
	{
		global $conf;
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE is_default = 1";
		$sql .= " AND entity IN (0, ".($conf->entity > 0 ? $conf->entity : 1).")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}
		return false;
	}
}
