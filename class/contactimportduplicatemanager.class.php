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
 * \file        class/contactimportduplicatemanager.class.php
 * \ingroup     contactimport
 * \brief       Class to manage duplicate companies and contacts
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/**
 * ContactImportDuplicateManager class
 */
class ContactImportDuplicateManager
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Find duplicate companies
	 *
	 * @return array Array of duplicates grouped by similarity
	 */
	public function findDuplicateCompanies()
	{
		global $conf;

		$identical = array();
		$similar = array();

		// Find companies with same name - order by date_creation to ensure oldest is master
		$sql = "SELECT s1.rowid as id1, s1.nom, s1.address, s1.zip, s1.town, s1.email, s1.phone, s1.datec as date1,";
		$sql .= " s2.rowid as id2, s2.address as address2, s2.zip as zip2, s2.town as town2, s2.email as email2, s2.phone as phone2, s2.datec as date2";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe as s1";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe as s2 ON s1.nom = s2.nom AND s1.rowid < s2.rowid";
		$sql .= " WHERE s1.entity = ".$conf->entity;
		$sql .= " AND s2.entity = ".$conf->entity;
		$sql .= " ORDER BY s1.nom, s1.datec, s2.datec";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				// Check if 100% identical
				if ($this->isIdenticalCompany($obj)) {
					if (!isset($identical[$obj->id1])) {
						$identical[$obj->id1] = array(
							'master_id' => $obj->id1,
							'name' => $obj->nom,
							'duplicates' => array()
						);
					}
					$identical[$obj->id1]['duplicates'][] = $obj->id2;
				} else {
					// Similar but not identical
					if (!isset($similar[$obj->id1])) {
						$similar[$obj->id1] = array(
							'master_id' => $obj->id1,
							'name' => $obj->nom,
							'address' => $obj->address,
							'zip' => $obj->zip,
							'town' => $obj->town,
							'email' => $obj->email,
							'phone' => $obj->phone,
							'duplicates' => array()
						);
					}
					$similar[$obj->id1]['duplicates'][] = array(
						'id' => $obj->id2,
						'address' => $obj->address2,
						'zip' => $obj->zip2,
						'town' => $obj->town2,
						'email' => $obj->email2,
						'phone' => $obj->phone2
					);
				}
			}
			$this->db->free($resql);
		}

		return array(
			'identical' => array_values($identical),
			'similar' => array_values($similar)
		);
	}

	/**
	 * Find duplicate contacts
	 *
	 * @return array Array of duplicates grouped by similarity
	 */
	public function findDuplicateContacts()
	{
		global $conf;

		$identical = array();
		$similar = array();

		// Find contacts with same lastname and firstname - order by date_creation to ensure oldest is master
		$sql = "SELECT c1.rowid as id1, c1.lastname, c1.firstname, c1.email, c1.phone_mobile, c1.phone_pro, c1.fk_soc, c1.datec as date1,";
		$sql .= " c2.rowid as id2, c2.email as email2, c2.phone_mobile as mobile2, c2.phone_pro as phone2, c2.fk_soc as fk_soc2, c2.datec as date2,";
		$sql .= " s1.nom as company1, s2.nom as company2";
		$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as c1";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople as c2 ON c1.lastname = c2.lastname AND c1.firstname = c2.firstname AND c1.rowid < c2.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s1 ON c1.fk_soc = s1.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s2 ON c2.fk_soc = s2.rowid";
		$sql .= " WHERE c1.entity = ".$conf->entity;
		$sql .= " AND c2.entity = ".$conf->entity;
		$sql .= " ORDER BY c1.lastname, c1.firstname, c1.datec, c2.datec";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				// Check if 100% identical
				if ($this->isIdenticalContact($obj)) {
					$key = $obj->id1;
					if (!isset($identical[$key])) {
						$identical[$key] = array(
							'master_id' => $obj->id1,
							'name' => trim($obj->firstname.' '.$obj->lastname),
							'company' => $obj->company1,
							'duplicates' => array()
						);
					}
					$identical[$key]['duplicates'][] = $obj->id2;
				} else {
					// Similar but not identical
					$key = $obj->id1;
					if (!isset($similar[$key])) {
						$similar[$key] = array(
							'master_id' => $obj->id1,
							'name' => trim($obj->firstname.' '.$obj->lastname),
							'email' => $obj->email,
							'phone' => $obj->phone_pro,
							'mobile' => $obj->phone_mobile,
							'company' => $obj->company1,
							'duplicates' => array()
						);
					}
					$similar[$key]['duplicates'][] = array(
						'id' => $obj->id2,
						'email' => $obj->email2,
						'phone' => $obj->phone2,
						'mobile' => $obj->mobile2,
						'company' => $obj->company2
					);
				}
			}
			$this->db->free($resql);
		}

		return array(
			'identical' => array_values($identical),
			'similar' => array_values($similar)
		);
	}

	/**
	 * Check if two companies are 100% identical
	 *
	 * @param object $obj Database result object
	 * @return bool True if identical
	 */
	private function isIdenticalCompany($obj)
	{
		return ($obj->address === $obj->address2)
			&& ($obj->zip === $obj->zip2)
			&& ($obj->town === $obj->town2)
			&& ($obj->email === $obj->email2)
			&& ($obj->phone === $obj->phone2);
	}

	/**
	 * Check if two contacts are 100% identical
	 *
	 * @param object $obj Database result object
	 * @return bool True if identical
	 */
	private function isIdenticalContact($obj)
	{
		return ($obj->email === $obj->email2)
			&& ($obj->phone_mobile === $obj->mobile2)
			&& ($obj->phone_pro === $obj->phone2)
			&& ($obj->fk_soc === $obj->fk_soc2);
	}

	/**
	 * Delete duplicate companies
	 *
	 * @param array $company_ids Array of company IDs to delete
	 * @param User $user User performing the deletion
	 * @return array Result with counts
	 */
	public function deleteCompanies($company_ids, $user)
	{
		$deleted = 0;
		$errors = 0;

		$this->db->begin();

		foreach ($company_ids as $id) {
			$company = new Societe($this->db);
			$result = $company->fetch($id);
			if ($result > 0) {
				$del_result = $company->delete($id, $user);
				if ($del_result > 0) {
					$deleted++;
					$this->logDuplicateAction('company', 'delete', $id, 0, $user);
				} else {
					$errors++;
					$this->errors[] = $company->error;
				}
			}
		}

		if ($errors == 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return array('deleted' => $deleted, 'errors' => $errors);
	}

	/**
	 * Delete duplicate contacts
	 *
	 * @param array $contact_ids Array of contact IDs to delete
	 * @param User $user User performing the deletion
	 * @return array Result with counts
	 */
	public function deleteContacts($contact_ids, $user)
	{
		$deleted = 0;
		$errors = 0;

		$this->db->begin();

		foreach ($contact_ids as $id) {
			$contact = new Contact($this->db);
			$result = $contact->fetch($id);
			if ($result > 0) {
				$del_result = $contact->delete($id);
				if ($del_result > 0) {
					$deleted++;
					$this->logDuplicateAction('contact', 'delete', $id, 0, $user);
				} else {
					$errors++;
					$this->errors[] = $contact->error;
				}
			}
		}

		if ($errors == 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return array('deleted' => $deleted, 'errors' => $errors);
	}

	/**
	 * Merge companies
	 *
	 * @param int $master_id Master company ID to keep
	 * @param array $duplicate_ids Array of duplicate company IDs to merge
	 * @param User $user User performing the merge
	 * @return array Result with counts
	 */
	public function mergeCompanies($master_id, $duplicate_ids, $user)
	{
		$merged = 0;
		$errors = 0;

		$this->db->begin();

		$master = new Societe($this->db);
		$master->fetch($master_id);

		foreach ($duplicate_ids as $dup_id) {
			$duplicate = new Societe($this->db);
			$duplicate->fetch($dup_id);

			// Fill empty fields in master with values from duplicate
			if (empty($master->address) && !empty($duplicate->address)) {
				$master->address = $duplicate->address;
			}
			if (empty($master->zip) && !empty($duplicate->zip)) {
				$master->zip = $duplicate->zip;
			}
			if (empty($master->town) && !empty($duplicate->town)) {
				$master->town = $duplicate->town;
			}
			if (empty($master->email) && !empty($duplicate->email)) {
				$master->email = $duplicate->email;
			}
			if (empty($master->phone) && !empty($duplicate->phone)) {
				$master->phone = $duplicate->phone;
			}
			if (empty($master->url) && !empty($duplicate->url)) {
				$master->url = $duplicate->url;
			}

			// Move contacts from duplicate to master
			$sql = "UPDATE ".MAIN_DB_PREFIX."socpeople SET fk_soc = ".$master_id." WHERE fk_soc = ".$dup_id;
			$this->db->query($sql);

			// Delete duplicate
			$del_result = $duplicate->delete($dup_id, $user);
			if ($del_result > 0) {
				$merged++;
				$this->logDuplicateAction('company', 'merge', $dup_id, $master_id, $user);
			} else {
				$errors++;
				$this->errors[] = $duplicate->error;
			}
		}

		// Update master with merged data
		if ($errors == 0) {
			$master->update($master_id, $user);
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return array('merged' => $merged, 'errors' => $errors);
	}

	/**
	 * Merge contacts
	 *
	 * @param int $master_id Master contact ID to keep
	 * @param array $duplicate_ids Array of duplicate contact IDs to merge
	 * @param User $user User performing the merge
	 * @return array Result with counts
	 */
	public function mergeContacts($master_id, $duplicate_ids, $user)
	{
		$merged = 0;
		$errors = 0;

		$this->db->begin();

		$master = new Contact($this->db);
		$master->fetch($master_id);

		foreach ($duplicate_ids as $dup_id) {
			$duplicate = new Contact($this->db);
			$duplicate->fetch($dup_id);

			// Fill empty fields in master with values from duplicate
			if (empty($master->email) && !empty($duplicate->email)) {
				$master->email = $duplicate->email;
			}
			if (empty($master->phone_pro) && !empty($duplicate->phone_pro)) {
				$master->phone_pro = $duplicate->phone_pro;
			}
			if (empty($master->phone_mobile) && !empty($duplicate->phone_mobile)) {
				$master->phone_mobile = $duplicate->phone_mobile;
			}
			if (empty($master->address) && !empty($duplicate->address)) {
				$master->address = $duplicate->address;
			}
			if (empty($master->zip) && !empty($duplicate->zip)) {
				$master->zip = $duplicate->zip;
			}
			if (empty($master->town) && !empty($duplicate->town)) {
				$master->town = $duplicate->town;
			}

			// Delete duplicate
			$del_result = $duplicate->delete($dup_id);
			if ($del_result > 0) {
				$merged++;
				$this->logDuplicateAction('contact', 'merge', $dup_id, $master_id, $user);
			} else {
				$errors++;
				$this->errors[] = $duplicate->error;
			}
		}

		// Update master with merged data
		if ($errors == 0) {
			$master->update($master_id, $user);
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return array('merged' => $merged, 'errors' => $errors);
	}

	/**
	 * Log duplicate action to database
	 *
	 * @param string $type 'company' or 'contact'
	 * @param string $action 'delete' or 'merge'
	 * @param int $source_id Source ID that was deleted/merged
	 * @param int $target_id Target ID (for merge), 0 for delete
	 * @param User $user User performing the action
	 * @return int Row ID or < 0 if error
	 */
	private function logDuplicateAction($type, $action, $source_id, $target_id, $user)
	{
		global $conf;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."contactimport_duplicate_logs (";
		$sql .= " entity, type, action, source_id, target_id, fk_user, date_action";
		$sql .= ") VALUES (";
		$sql .= " ".$conf->entity.",";
		$sql .= " '".$this->db->escape($type)."',";
		$sql .= " '".$this->db->escape($action)."',";
		$sql .= " ".(int)$source_id.",";
		$sql .= " ".(int)$target_id.",";
		$sql .= " ".$user->id.",";
		$sql .= " '".$this->db->idate(dol_now())."'";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->last_insert_id(MAIN_DB_PREFIX."contactimport_duplicate_logs");
		}
		return -1;
	}

	/**
	 * Get duplicate action logs
	 *
	 * @param int $limit Number of records to return
	 * @return array Array of log entries
	 */
	public function getDuplicateLogs($limit = 100)
	{
		global $conf;

		$logs = array();

		$sql = "SELECT l.*, u.login";
		$sql .= " FROM ".MAIN_DB_PREFIX."contactimport_duplicate_logs as l";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON l.fk_user = u.rowid";
		$sql .= " WHERE l.entity = ".$conf->entity;
		$sql .= " ORDER BY l.date_action DESC";
		$sql .= " LIMIT ".(int)$limit;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$logs[] = $obj;
			}
			$this->db->free($resql);
		}

		return $logs;
	}
}
