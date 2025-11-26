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
 * \file        class/contactimportsession.class.php
 * \ingroup     contactimport
 * \brief       This file is a CRUD class file for ContactImportSession (Create/Read/Update/Delete)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for ContactImportSession
 */
class ContactImportSession extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'contactimportsession';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'contactimport_sessions';

	/**
	 * @var int Does object support multicompany module ? 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var string String with name of icon for contactimportsession
	 */
	public $picto = 'contactimport@contactimport';

	const STATUS_DRAFT = 0;
	const STATUS_UPLOADED = 1;
	const STATUS_MAPPED = 2;
	const STATUS_PROCESSING = 3;
	const STATUS_COMPLETED = 4;
	const STATUS_ERROR = 5;

	/**
	 * 'type' field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter]]', 'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter]]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'text:none', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 */
	public $fields = array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>'1', 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'ref' => array('type'=>'varchar(128)', 'label'=>'Ref', 'enabled'=>'1', 'position'=>10, 'notnull'=>1, 'visible'=>1, 'index'=>1, 'searchall'=>1, 'comment'=>"Reference"),
		'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'enabled'=>'1', 'position'=>20, 'notnull'=>0, 'visible'=>1, 'searchall'=>1),
		'description' => array('type'=>'text', 'label'=>'Description', 'enabled'=>'1', 'position'=>30, 'notnull'=>0, 'visible'=>3),
		'filename' => array('type'=>'varchar(255)', 'label'=>'Filename', 'enabled'=>'1', 'position'=>40, 'notnull'=>1, 'visible'=>1),
		'file_path' => array('type'=>'varchar(500)', 'label'=>'FilePath', 'enabled'=>'1', 'position'=>50, 'notnull'=>0, 'visible'=>0),
		'file_size' => array('type'=>'integer', 'label'=>'FileSize', 'enabled'=>'1', 'position'=>60, 'notnull'=>0, 'visible'=>1),
		'csv_separator' => array('type'=>'varchar(10)', 'label'=>'CSVSeparator', 'enabled'=>'1', 'position'=>70, 'notnull'=>0, 'visible'=>1),
		'csv_enclosure' => array('type'=>'varchar(10)', 'label'=>'CSVEnclosure', 'enabled'=>'1', 'position'=>80, 'notnull'=>0, 'visible'=>1),
		'csv_escape' => array('type'=>'varchar(10)', 'label'=>'CSVEscape', 'enabled'=>'1', 'position'=>90, 'notnull'=>0, 'visible'=>1),
		'has_header' => array('type'=>'integer', 'label'=>'HasHeader', 'enabled'=>'1', 'position'=>100, 'notnull'=>0, 'visible'=>1),
		'total_lines' => array('type'=>'integer', 'label'=>'TotalLines', 'enabled'=>'1', 'position'=>110, 'notnull'=>0, 'visible'=>1),
		'processed_lines' => array('type'=>'integer', 'label'=>'ProcessedLines', 'enabled'=>'1', 'position'=>120, 'notnull'=>0, 'visible'=>1),
		'success_lines' => array('type'=>'integer', 'label'=>'SuccessLines', 'enabled'=>'1', 'position'=>130, 'notnull'=>0, 'visible'=>1),
		'error_lines' => array('type'=>'integer', 'label'=>'ErrorLines', 'enabled'=>'1', 'position'=>140, 'notnull'=>0, 'visible'=>1),
		'mapping_config' => array('type'=>'text', 'label'=>'MappingConfig', 'enabled'=>'1', 'position'=>150, 'notnull'=>0, 'visible'=>0),
		'status' => array('type'=>'integer', 'label'=>'Status', 'enabled'=>'1', 'position'=>1000, 'notnull'=>1, 'visible'=>1, 'index'=>1),
		'date_creation' => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>'1', 'position'=>500, 'notnull'=>1, 'visible'=>2),
		'date_modification' => array('type'=>'datetime', 'label'=>'DateModification', 'enabled'=>'1', 'position'=>501, 'notnull'=>0, 'visible'=>0),
		'date_import' => array('type'=>'datetime', 'label'=>'DateImport', 'enabled'=>'1', 'position'=>502, 'notnull'=>0, 'visible'=>1),
		'fk_user_creat' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserAuthor', 'enabled'=>'1', 'position'=>510, 'notnull'=>1, 'visible'=>0, 'foreignkey'=>'user.rowid'),
		'fk_user_modif' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserModif', 'enabled'=>'1', 'position'=>511, 'notnull'=>-1, 'visible'=>0),
		'import_key' => array('type'=>'varchar(14)', 'label'=>'ImportId', 'enabled'=>'1', 'position'=>1001, 'notnull'=>-1, 'visible'=>0),
	);

	public $rowid;
	public $ref;
	public $label;
	public $description;
	public $filename;
	public $file_path;
	public $file_size;
	public $csv_separator;
	public $csv_enclosure;
	public $csv_escape;
	public $has_header;
	public $total_lines;
	public $processed_lines;
	public $success_lines;
	public $error_lines;
	public $mapping_config;
	public $status;
	public $date_creation;
	public $date_modification;
	public $date_import;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) {
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
		global $conf, $langs;
		$error = 0;

		// Clean parameters
		if (isset($this->ref)) {
			$this->ref = trim($this->ref);
		}
		if (isset($this->label)) {
			$this->label = trim($this->label);
		}
		if (isset($this->description)) {
			$this->description = trim($this->description);
		}
		if (isset($this->filename)) {
			$this->filename = trim($this->filename);
		}
		if (isset($this->file_path)) {
			$this->file_path = trim($this->file_path);
		}

		// Check parameters
		if (empty($this->ref)) {
			$this->errors[] = 'Error: ref is required';
			return -1;
		}

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
		$sql .= "ref,";
		$sql .= "label,";
		$sql .= "description,";
		$sql .= "filename,";
		$sql .= "file_path,";
		$sql .= "file_size,";
		$sql .= "csv_separator,";
		$sql .= "csv_enclosure,";
		$sql .= "csv_escape,";
		$sql .= "has_header,";
		$sql .= "total_lines,";
		$sql .= "processed_lines,";
		$sql .= "success_lines,";
		$sql .= "error_lines,";
		$sql .= "mapping_config,";
		$sql .= "status,";
		$sql .= "date_creation,";
		$sql .= "fk_user_creat,";
		$sql .= "entity";
		$sql .= ") VALUES (";
		$sql .= " ".(!isset($this->ref) ? 'NULL' : "'".$this->db->escape($this->ref)."'").",";
		$sql .= " ".(!isset($this->label) ? 'NULL' : "'".$this->db->escape($this->label)."'").",";
		$sql .= " ".(!isset($this->description) ? 'NULL' : "'".$this->db->escape($this->description)."'").",";
		$sql .= " ".(!isset($this->filename) ? 'NULL' : "'".$this->db->escape($this->filename)."'").",";
		$sql .= " ".(!isset($this->file_path) ? 'NULL' : "'".$this->db->escape($this->file_path)."'").",";
		$sql .= " ".(!isset($this->file_size) ? 'NULL' : $this->file_size).",";
		$sql .= " ".(!isset($this->csv_separator) ? 'NULL' : "'".$this->db->escape($this->csv_separator)."'").",";
		$sql .= " ".(!isset($this->csv_enclosure) ? 'NULL' : "'".$this->db->escape($this->csv_enclosure)."'").",";
		$sql .= " ".(!isset($this->csv_escape) ? 'NULL' : "'".$this->db->escape($this->csv_escape)."'").",";
		$sql .= " ".(!isset($this->has_header) ? 'NULL' : $this->has_header).",";
		$sql .= " ".(!isset($this->total_lines) ? 'NULL' : $this->total_lines).",";
		$sql .= " ".(!isset($this->processed_lines) ? 'NULL' : $this->processed_lines).",";
		$sql .= " ".(!isset($this->success_lines) ? 'NULL' : $this->success_lines).",";
		$sql .= " ".(!isset($this->error_lines) ? 'NULL' : $this->error_lines).",";
		$sql .= " ".(!isset($this->mapping_config) ? 'NULL' : "'".$this->db->escape($this->mapping_config)."'").",";
		$sql .= " ".(!isset($this->status) ? 'NULL' : $this->status).",";
		$sql .= " '".$this->db->idate(dol_now())."',";
		$sql .= " ".(!isset($this->fk_user_creat) ? $user->id : $this->fk_user_creat).",";
		$sql .= " ".$conf->entity;
		$sql .= ")";

		$this->db->begin();

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++; $this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

			// Call triggers
			if (!$notrigger) {
				// Call triggers
				$result = $this->call_trigger('CONTACTIMPORTSESSION_CREATE', $user);
				if ($result < 0) {
					$error++;
				}
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
				$this->error = ($this->error ? $this->error.', '.$errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return $this->id;
		}
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
		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);

		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.ref,";
		$sql .= " t.label,";
		$sql .= " t.description,";
		$sql .= " t.filename,";
		$sql .= " t.file_path,";
		$sql .= " t.file_size,";
		$sql .= " t.csv_separator,";
		$sql .= " t.csv_enclosure,";
		$sql .= " t.csv_escape,";
		$sql .= " t.has_header,";
		$sql .= " t.total_lines,";
		$sql .= " t.processed_lines,";
		$sql .= " t.success_lines,";
		$sql .= " t.error_lines,";
		$sql .= " t.mapping_config,";
		$sql .= " t.status,";
		$sql .= " t.date_creation,";
		$sql .= " t.date_modification,";
		$sql .= " t.date_import,";
		$sql .= " t.fk_user_creat,";
		$sql .= " t.fk_user_modif,";
		$sql .= " t.import_key";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE 1 = 1";
		if (null !== $ref) {
			$sql .= " AND t.ref = '".$this->db->escape($ref)."'";
		} else {
			$sql .= " AND t.rowid = ".((int) $id);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				$this->ref = $obj->ref;
				$this->label = $obj->label;
				$this->description = $obj->description;
				$this->filename = $obj->filename;
				$this->file_path = $obj->file_path;
				$this->file_size = $obj->file_size;
				$this->csv_separator = $obj->csv_separator;
				$this->csv_enclosure = $obj->csv_enclosure;
				$this->csv_escape = $obj->csv_escape;
				$this->has_header = $obj->has_header;
				$this->total_lines = $obj->total_lines;
				$this->processed_lines = $obj->processed_lines;
				$this->success_lines = $obj->success_lines;
				$this->error_lines = $obj->error_lines;
				$this->mapping_config = $obj->mapping_config;
				$this->status = $obj->status;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				$this->date_modification = $this->db->jdate($obj->date_modification);
				$this->date_import = $this->db->jdate($obj->date_import);
				$this->fk_user_creat = $obj->fk_user_creat;
				$this->fk_user_modif = $obj->fk_user_modif;
				$this->import_key = $obj->import_key;
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			return -1;
		}
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
		$error = 0;

		// Clean parameters
		if (isset($this->ref)) {
			$this->ref = trim($this->ref);
		}
		if (isset($this->label)) {
			$this->label = trim($this->label);
		}
		if (isset($this->description)) {
			$this->description = trim($this->description);
		}

		// Check parameters
		// Put here code to add a control on parameters values

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " ref = ".(isset($this->ref) ? "'".$this->db->escape($this->ref)."'" : "null").",";
		$sql .= " label = ".(isset($this->label) ? "'".$this->db->escape($this->label)."'" : "null").",";
		$sql .= " description = ".(isset($this->description) ? "'".$this->db->escape($this->description)."'" : "null").",";
		$sql .= " filename = ".(isset($this->filename) ? "'".$this->db->escape($this->filename)."'" : "null").",";
		$sql .= " file_path = ".(isset($this->file_path) ? "'".$this->db->escape($this->file_path)."'" : "null").",";
		$sql .= " file_size = ".(isset($this->file_size) ? $this->file_size : "null").",";
		$sql .= " csv_separator = ".(isset($this->csv_separator) ? "'".$this->db->escape($this->csv_separator)."'" : "null").",";
		$sql .= " csv_enclosure = ".(isset($this->csv_enclosure) ? "'".$this->db->escape($this->csv_enclosure)."'" : "null").",";
		$sql .= " csv_escape = ".(isset($this->csv_escape) ? "'".$this->db->escape($this->csv_escape)."'" : "null").",";
		$sql .= " has_header = ".(isset($this->has_header) ? $this->has_header : "null").",";
		$sql .= " total_lines = ".(isset($this->total_lines) ? $this->total_lines : "null").",";
		$sql .= " processed_lines = ".(isset($this->processed_lines) ? $this->processed_lines : "null").",";
		$sql .= " success_lines = ".(isset($this->success_lines) ? $this->success_lines : "null").",";
		$sql .= " error_lines = ".(isset($this->error_lines) ? $this->error_lines : "null").",";
		$sql .= " mapping_config = ".(isset($this->mapping_config) ? "'".$this->db->escape($this->mapping_config)."'" : "null").",";
		$sql .= " status = ".(isset($this->status) ? $this->status : "null").",";
		$sql .= " date_modification = '".$this->db->idate(dol_now())."',";
		$sql .= " fk_user_modif = ".$user->id;
		$sql .= " WHERE rowid = ".((int) $this->id);

		$this->db->begin();

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++; $this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			if (!$notrigger) {
				// Call triggers
				$result = $this->call_trigger('CONTACTIMPORTSESSION_MODIFY', $user);
				if ($result < 0) {
					$error++;
				}
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
				$this->error = ($this->error ? $this->error.', '.$errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Get mapping configuration as array
	 *
	 * @return array
	 */
	public function getMappingConfig()
	{
		if (empty($this->mapping_config)) {
			return array();
		}
		
		return json_decode($this->mapping_config, true) ?: array();
	}

	/**
	 * Set mapping configuration from array
	 *
	 * @param array $config
	 */
	public function setMappingConfig($config)
	{
		$this->mapping_config = json_encode($config);
	}

	/**
	 * Get status label
	 *
	 * @return string
	 */
	public function getLibStatut()
	{
		global $langs;
		
		$langs->load("contactimport@contactimport");
		
		$statuses = array(
			self::STATUS_DRAFT => $langs->trans("Draft"),
			self::STATUS_UPLOADED => $langs->trans("Uploaded"),
			self::STATUS_MAPPED => $langs->trans("Mapped"),
			self::STATUS_PROCESSING => $langs->trans("Processing"),
			self::STATUS_COMPLETED => $langs->trans("Completed"),
			self::STATUS_ERROR => $langs->trans("Error"),
		);
		
		return isset($statuses[$this->status]) ? $statuses[$this->status] : $langs->trans("Unknown");
	}

	/**
	 * Get CSV data
	 *
	 * @return array|false Array of CSV data or false on error
	 */
	public function getCSVData()
	{
		if (empty($this->csv_file_path) || !file_exists($this->csv_file_path)) {
			return false;
		}

		$data = array();
		$handle = fopen($this->csv_file_path, 'r');
		if ($handle) {
			while (($row = fgetcsv($handle, 0, $this->csv_separator)) !== false) {
				$data[] = $row;
			}
			fclose($handle);
		}

		return $data;
	}

	/**
	 * Delete session and associated files
	 *
	 * @param User $user User object
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int >0 if OK, <0 if error
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf, $langs;

		$error = 0;

		$this->db->begin();

		// Delete associated file if exists
		if (!empty($this->file_path) && file_exists($this->file_path)) {
			if (!unlink($this->file_path)) {
				dol_syslog("ContactImportSession::delete Error deleting file ".$this->file_path, LOG_ERR);
				// Don't count as error - continue with DB delete
			}
		}

		// Delete from database
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_sessions";
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			dol_syslog("ContactImportSession::delete Error deleting session: ".$this->db->lasterror(), LOG_ERR);
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		// Delete associated logs
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."contactimport_logs";
		$sql .= " WHERE session_id = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog("ContactImportSession::delete Error deleting logs: ".$this->db->lasterror(), LOG_ERR);
			// Don't count logs deletion as critical error
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}
}