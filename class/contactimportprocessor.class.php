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
 * \file        class/contactimportprocessor.class.php
 * \ingroup     contactimport
 * \brief       Processor class for importing CSV data
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/**
 * Class for processing contact import data
 */
class ContactImportProcessor
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
	 * @var string Last error
	 */
	public $error = '';

	/**
	 * @var int Id of the existing record matched as a duplicate by the last createCompany()/createContact() call
	 */
	private $lastDuplicateId = 0;

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
	 * Generate preview data for import session
	 *
	 * @param ContactImportSession $session Import session
	 * @param int $max_lines Maximum lines to preview
	 * @return array Preview data with statistics and warnings
	 */
	public function generatePreview(ContactImportSession $session, $max_lines = 10)
	{
		$result = array(
			'companies' => array(),
			'contacts' => array(),
			'stats' => array(
				'companies_to_create' => 0,
				'contacts_to_create' => 0,
				'potential_errors' => 0,
			),
			'warnings' => array(),
		);

		$mapping_config = $session->getMappingConfig();
		if (empty($mapping_config)) {
			$result['warnings'][] = 'No mapping configuration found';
			return $result;
		}

		// Read CSV file
		$handle = fopen($session->file_path, 'r');
		if (!$handle) {
			$result['warnings'][] = 'Cannot read CSV file';
			return $result;
		}

		// Skip header if present
		if ($session->has_header) {
			fgetcsv($handle, 0, $session->csv_separator, $session->csv_enclosure);
		}

		$line_count = 0;
		while (($csv_line = fgetcsv($handle, 0, $session->csv_separator, $session->csv_enclosure)) !== false && $line_count < $max_lines) {
			// Convert encoding to UTF-8 if needed
			if (!empty($csv_line)) {
				foreach ($csv_line as $key => $value) {
					if (!mb_check_encoding($value, 'UTF-8')) {
						$detected = mb_detect_encoding($value, array('UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'), true);
						if ($detected && $detected !== 'UTF-8') {
							$csv_line[$key] = mb_convert_encoding($value, 'UTF-8', $detected);
						} else {
							$csv_line[$key] = utf8_encode($value);
						}
					}
				}
			}
			
			// Process company data
			if ($mapping_config['import_mode'] === 'company_only' || $mapping_config['import_mode'] === 'both') {
				$company_data = $this->processCompanyLine($csv_line, $mapping_config['company'], $line_count);
				$result['companies'][$line_count] = $company_data;
				
				if (empty($company_data['errors'])) {
					$result['stats']['companies_to_create']++;
				} else {
					$result['stats']['potential_errors']++;
				}
			}

			// Process contact data
			if ($mapping_config['import_mode'] === 'contact_only' || $mapping_config['import_mode'] === 'both') {
				$contact_data = $this->processContactLine($csv_line, $mapping_config['contact'], $line_count);
				$result['contacts'][$line_count] = $contact_data;
				
				if (empty($contact_data['errors'])) {
					$result['stats']['contacts_to_create']++;
				} else {
					$result['stats']['potential_errors']++;
				}
			}

			$line_count++;
		}

		fclose($handle);

		// Add general warnings
		if ($result['stats']['potential_errors'] > 0) {
			$result['warnings'][] = 'Found '.$result['stats']['potential_errors'].' lines with potential errors';
		}

		return $result;
	}

	/**
	 * Process a CSV line for company data
	 *
	 * @param array $csv_line CSV line data
	 * @param array $mapping Field mapping configuration
	 * @param int $line_number Line number for error reporting
	 * @return array Processed company data with errors and warnings
	 */
	private function processCompanyLine($csv_line, $mapping, $line_number)
	{
		global $conf;
		
		$data = array();
		$errors = array();
		$warnings = array();

		// Map CSV columns to company fields
		foreach ($mapping as $csv_column => $field) {
			// Convert string index to integer
			$csv_column = (int)$csv_column;
			$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
			$data[$field] = $value;
		}

		// Validate required fields
		if (empty($data['nom'])) {
			$errors[] = 'Company name is required';
		}

		// Validate email format
		if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			$warnings[] = 'Invalid email format';
		}

		// Validate phone format (basic check)
		if (!empty($data['phone']) && !preg_match('/^[\d\s\+\-\(\)\.]+$/', $data['phone'])) {
			$warnings[] = 'Phone format may be invalid';
		}

		// Check for potential duplicates (simplified check)
		if (!empty($data['nom'])) {
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom = '".$this->db->escape($data['nom'])."' AND entity = ".$conf->entity;
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$warnings[] = 'Company with this name already exists';
			}
		}

		return array(
			'data' => $data,
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Process a CSV line for contact data
	 *
	 * @param array $csv_line CSV line data
	 * @param array $mapping Field mapping configuration
	 * @param int $line_number Line number for error reporting
	 * @return array Processed contact data with errors and warnings
	 */
	private function processContactLine($csv_line, $mapping, $line_number)
	{
		global $conf;
		
		$data = array();
		$errors = array();
		$warnings = array();

		// Map CSV columns to contact fields
		foreach ($mapping as $csv_column => $field) {
			// Convert string index to integer
			$csv_column = (int)$csv_column;
			$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
			$data[$field] = $value;
		}

		// Validate required fields
		if (empty($data['lastname'])) {
			$errors[] = 'Last name is required';
		}

		// Validate email format
		if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			$warnings[] = 'Invalid email format';
		}

		// Validate phone formats
		$phone_fields = array('phone', 'phone_perso', 'phone_mobile');
		foreach ($phone_fields as $phone_field) {
			if (!empty($data[$phone_field]) && !preg_match('/^[\d\s\+\-\(\)\.]+$/', $data[$phone_field])) {
				$warnings[] = ucfirst($phone_field).' format may be invalid';
			}
		}

		// Validate birthday format
		if (!empty($data['birthday'])) {
			$birthday_timestamp = strtotime($data['birthday']);
			if ($birthday_timestamp === false) {
				$warnings[] = 'Invalid birthday format';
			}
		}

		// Check for potential duplicates (simplified check)
		if (!empty($data['lastname']) && !empty($data['firstname'])) {
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople WHERE lastname = '".$this->db->escape($data['lastname'])."'";
			$sql .= " AND firstname = '".$this->db->escape($data['firstname'])."' AND entity = ".$conf->entity;
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$warnings[] = 'Contact with this name already exists';
			}
		}

		return array(
			'data' => $data,
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Process full import for a session
	 *
	 * @param ContactImportSession $session Import session
	 * @param User $user User performing the import
	 * @return array Import results
	 */
	public function processImport(ContactImportSession $session, User $user)
	{
		global $conf;

		$result = array(
			'success' => false,
			'companies_created' => 0,
			'contacts_created' => 0,
		'companies_updated' => 0,
		'contacts_updated' => 0,
		'errors' => 0,
		'duplicates_skipped' => 0,
		'success_lines' => 0,  // Count of successfully processed CSV lines (not operations!)
		);

		$mapping_config = $session->getMappingConfig();
		dol_syslog("ContactImportProcessor: processImport() session=".$session->id." import_mode=".($mapping_config['import_mode'] ?? 'NOT SET')." create_companies=".(!empty($mapping_config['create_companies']) ? 'true' : 'false')." create_contacts=".(!empty($mapping_config['create_contacts']) ? 'true' : 'false'), LOG_DEBUG);

		if (empty($mapping_config)) {
			$result['messages'][] = 'No mapping configuration found';
			dol_syslog("ContactImportProcessor: no mapping configuration found for session=".$session->id, LOG_ERR);
			return $result;
		}

		// Read CSV file
		$handle = fopen($session->file_path, 'r');
		if (!$handle) {
			$result['messages'][] = 'Cannot read CSV file';
			return $result;
		}

		// Ensure CSV enclosure is exactly one character (default to double quote)
		$csv_enclosure = !empty($session->csv_enclosure) && strlen($session->csv_enclosure) === 1 ? $session->csv_enclosure : '"';
		$csv_separator = !empty($session->csv_separator) && strlen($session->csv_separator) === 1 ? $session->csv_separator : ';';

		// Skip header if present
		if ($session->has_header) {
			fgetcsv($handle, 0, $csv_separator, $csv_enclosure);
		}

		// PERFORMANCE: Disable triggers during batch import
		// This prevents slow trigger execution (especially multicompany) for each row
		global $conf;
		$old_trigger_disabled = isset($conf->global->MAIN_DISABLE_ALL_MAILS) ? $conf->global->MAIN_DISABLE_ALL_MAILS : 0;
		$conf->global->MAIN_DISABLE_ALL_MAILS = 1; // Disable email triggers
		
		// Store and disable trigger files during import to boost performance
		$backup_triggers_disabled = !empty($conf->global->MAIN_DISABLE_TRIGGERS) ? $conf->global->MAIN_DISABLE_TRIGGERS : '';
		$conf->global->MAIN_DISABLE_TRIGGERS = '1'; // Disable ALL triggers during import
		
		dol_syslog("ContactImportProcessor: Starting batch import with triggers disabled for performance", LOG_INFO);

		$this->db->begin();

		$line_count = 0;
		while (($csv_line = fgetcsv($handle, 0, $csv_separator, $csv_enclosure)) !== false) {
			// Convert encoding to UTF-8 if needed
			if (!empty($csv_line)) {
				foreach ($csv_line as $key => $value) {
					if (!mb_check_encoding($value, 'UTF-8')) {
						$detected = mb_detect_encoding($value, array('UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'), true);
						if ($detected && $detected !== 'UTF-8') {
							$csv_line[$key] = mb_convert_encoding($value, 'UTF-8', $detected);
						} else {
							$csv_line[$key] = utf8_encode($value);
						}
					}
				}
			}
			
			$line_count++;
			$company_id = 0;
			$contact_id = 0;
			$line_has_error = false;

			// Process company
			if ($mapping_config['import_mode'] === 'company_only' || $mapping_config['import_mode'] === 'both') {
				if ($mapping_config['create_companies']) {
				$company_id = $this->createCompany($csv_line, $mapping_config['company'], $user, $mapping_config['contact']);
				if ($company_id > 0) {
					$result['companies_created']++;
					// Log warnings if any (e.g., auto-filled fields)
					if (!empty($this->errors)) {
						$this->logError($session->id, $line_count, 'company', implode(', ', $this->errors), 'warning');
					}
				} elseif ($company_id === -1) {
				// Duplicate found - was updated (not skipped)
				$result['companies_updated']++;
				$this->logError($session->id, $line_count, 'company', implode(', ', $this->errors), 'updated', $this->lastDuplicateId);
				} else {
					$result['errors']++;
					$line_has_error = true;
					$this->logError($session->id, $line_count, 'company', implode(', ', $this->errors));
				}
				}
			}

			// Process contact
			if ($mapping_config['import_mode'] === 'contact_only' || $mapping_config['import_mode'] === 'both') {
				if ($mapping_config['create_contacts']) {
				$contact_id = $this->createContact($csv_line, $mapping_config['contact'], $user, $company_id, $mapping_config['company']);
				if ($contact_id > 0) {
					$result['contacts_created']++;
					// Log warnings if any (e.g., auto-filled fields)
					if (!empty($this->errors)) {
						$this->logError($session->id, $line_count, 'contact', implode(', ', $this->errors), 'warning');
					}
				} elseif ($contact_id === -1) {
				// Duplicate found - was updated (not skipped)
				$result['contacts_updated']++;
				$this->logError($session->id, $line_count, 'contact', implode(', ', $this->errors), 'updated', 0, $this->lastDuplicateId);
				} else {
					$result['errors']++;
					$line_has_error = true;
					$this->logError($session->id, $line_count, 'contact', implode(', ', $this->errors));
				}
				}
			}

			// Log successful import and count successful lines
			// A line is successful if at least one object was created or updated (id != 0)
			$line_had_success = ($company_id != 0 || $contact_id != 0);
			
			if ($line_had_success && !$line_has_error) {
				if ($company_id > 0 || $contact_id > 0) {
					$this->logSuccess($session->id, $line_count, $company_id, $contact_id);
				}
				$result['success_lines']++;  // Count this CSV line as successfully processed
			}

			// Clear errors for next iteration
			$this->errors = array();
		}

		fclose($handle);
		
		// Re-enable triggers after batch import
		$conf->global->MAIN_DISABLE_ALL_MAILS = $old_trigger_disabled;
		$conf->global->MAIN_DISABLE_TRIGGERS = $backup_triggers_disabled;
		dol_syslog("ContactImportProcessor: Batch import completed, triggers re-enabled", LOG_INFO);

		// Update session statistics
		$session->processed_lines = $line_count;
		$session->success_lines = $result['success_lines'];  // Use the line-based counter, not operation sum
		$session->error_lines = $result['errors'];
		$session->duplicates_skipped = $result['duplicates_skipped'];
		$session->companies_created = $result['companies_created'];
		$session->companies_updated = $result['companies_updated'];
		$session->contacts_created = $result['contacts_created'];
		$session->contacts_updated = $result['contacts_updated'];
		$session->status = ($result['errors'] > 0) ? 5 : 4; // Error or Completed
		$session->date_import = dol_now();
		$session->update($user);

		// Always commit - logs are already committed separately
		$this->db->commit();
		$result['success'] = true;

		return $result;
	}

	/**
	 * Create a company from CSV data
	 *
	 * @param array $csv_line CSV line data
	 * @param array $mapping Field mapping
	 * @param User $user User creating the company
	 * @param array $contact_mapping Contact mapping for fallback company name
	 * @return int Company ID if successful, 0 if error, -1 if duplicate found
	 */
	private function createCompany($csv_line, $mapping, User $user, $contact_mapping = array())
	{
		global $conf;
		$company = new Societe($this->db);

		// Track if we have a last order date for status determination
		$last_order_date = null;

		// Map CSV data to company fields
		foreach ($mapping as $csv_column => $field) {
			// Convert string index to integer
			$csv_column = (int)$csv_column;
			$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
			
			if (!empty($value)) {
				switch ($field) {
					case 'nom':
						$company->name = $value;
						break;
					case 'name_alias':
						$company->name_alias = $value;
						break;
					case 'address':
						$company->address = $value;
						break;
					case 'zip':
						$company->zip = $value;
						break;
					case 'town':
						$company->town = $value;
						break;
					case 'phone':
						$company->phone = $value;
						break;
					case 'fax':
						$company->fax = $value;
						break;
					case 'email':
						$company->email = $value;
						break;
					case 'url':
						$company->url = $value;
						break;
					case 'siren':
						$company->siren = $value;
						break;
					case 'siret':
						$company->siret = $value;
						break;
					case 'ape':
						$company->ape = $value;
						break;
					case 'tva_intra':
						$company->tva_intra = $value;
						break;
					case 'code_client':
						$company->code_client = $value;
						break;
					case 'options_last_order_date':
						// Handle extrafield for last order date
						$last_order_date = $value;
						break;
				}
			}
		}

		// Validate required fields - if no company name, try to generate from contact data
		if (empty($company->name) && !empty($contact_mapping)) {
			$firstname = '';
			$lastname = '';
			
			// Extract firstname and lastname from contact mapping
			foreach ($contact_mapping as $csv_column => $field) {
				$csv_column = (int)$csv_column;
				$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
				if ($field === 'firstname' && !empty($value)) {
					$firstname = $value;
				} elseif ($field === 'lastname' && !empty($value)) {
					$lastname = $value;
				}
			}
			
			// Generate company name from contact name (improved logic)
			if (!empty($lastname) || !empty($firstname)) {
				if (!empty($lastname) && !empty($firstname)) {
					// Both available: "Lastname, Firstname"
					$company->name = trim($lastname . ', ' . $firstname);
					$this->errors[] = 'InfoCompanyNameGenerated';
				} elseif (!empty($lastname)) {
					// Only lastname available
					$company->name = $lastname;
					$this->errors[] = 'InfoCompanyNameFromLastname';
				} else {
					// Only firstname available
					$company->name = $firstname;
					$this->errors[] = 'InfoCompanyNameFromFirstname';
				}
			}
		}
		
		// Final validation - Auto-fill empty required fields
		if (empty($company->name)) {
			$company->name = 'XX-AUTO';
			$this->errors[] = 'WarningCompanyNameAutoFilled';
		}

		// Check for duplicates before creating
		$duplicate_id = $this->checkCompanyDuplicate($company);
		if ($duplicate_id > 0) {
			// Duplicate found - update existing company
			$this->lastDuplicateId = $duplicate_id;
			$update_result = $this->updateCompany($duplicate_id, $company, $user, $last_order_date);
			if ($update_result > 0) {
				$this->errors[] = 'Company already exists - updated (ID: '.$duplicate_id.')';
				return -1; // Return -1 to indicate duplicate was updated
			} else {
				$this->errors[] = 'Company already exists but update failed (ID: '.$duplicate_id.')';
				return 0;
			}
		}

		// Set default customer status based on last_order_date
		// If last_order_date is filled → Customer (client=1)
		// If last_order_date is empty → Prospect (client=2)
		if (!empty($last_order_date)) {
			$company->client = 1; // Customer
		} else {
			$company->client = 2; // Prospect
		}
		$company->status = 1; // Active

		// Generate customer code automatically if required
		if (getDolGlobalString('SOCIETE_CODECLIENT_ADDON') && empty($company->code_client)) {
			$company->get_codeclient($company, 0); // 0 = customer type
		}

		$result = $company->create($user);
		if ($result > 0) {
			// Set extrafield if we have a last order date
			if (!empty($last_order_date)) {
				$this->setCompanyExtrafield($result, 'last_order_date', $last_order_date);
			}
			return $result;
		} else {
			// Collect detailed error information
			if (!empty($company->error)) {
				$this->errors[] = 'Company creation failed: '.$company->error;
			} elseif (!empty($company->errors) && is_array($company->errors)) {
				$this->errors[] = 'Company creation failed: '.implode(', ', $company->errors);
			} else {
				$this->errors[] = 'Company creation failed (unknown error)';
			}
			// Add database error if available
			if (!empty($this->db->lasterror())) {
				$this->errors[] = 'DB Error: '.$this->db->lasterror();
			}
			return 0;
		}
	}

	/**
	 * Create a contact from CSV data
	 *
	 * @param array $csv_line CSV line data
	 * @param array $mapping Field mapping (contact fields)
	 * @param User $user User creating the contact
	 * @param int $company_id Company ID to link contact to
	 * @param array $company_mapping Company field mapping for fallback
	 * @return int Contact ID if successful, 0 if error, -1 if duplicate found
	 */
	private function createContact($csv_line, $mapping, User $user, $company_id = 0, $company_mapping = array())
	{
		global $conf;
		$contact = new Contact($this->db);

		// Map CSV data to contact fields
		foreach ($mapping as $csv_column => $field) {
			// Convert string index to integer
			$csv_column = (int)$csv_column;
			$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
			
			if (!empty($value)) {
				switch ($field) {
					case 'lastname':
						$contact->lastname = $value;
						break;
					case 'firstname':
						$contact->firstname = $value;
						break;
					case 'address':
						$contact->address = $value;
						break;
					case 'zip':
						$contact->zip = $value;
						break;
					case 'town':
						$contact->town = $value;
						break;
					case 'phone':
						$contact->phone_pro = $value;
						break;
					case 'phone_perso':
						$contact->phone_perso = $value;
						break;
					case 'phone_mobile':
						$contact->phone_mobile = $value;
						break;
					case 'fax':
						$contact->fax = $value;
						break;
					case 'email':
						$contact->email = $value;
						break;
					case 'birthday':
						$birthday_timestamp = strtotime($value);
						if ($birthday_timestamp !== false) {
							$contact->birthday = $birthday_timestamp;
						}
						break;
					case 'poste':
						$contact->poste = $value;
						break;
				}
			}
		}

		// Validate required fields - if no lastname, try to get from company data
		if (empty($contact->lastname) && !empty($company_mapping)) {
			// Extract company name from CSV using company mapping
			foreach ($company_mapping as $csv_column => $field) {
				$csv_column = (int)$csv_column;
				$value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
				if ($field === 'nom' && !empty($value)) {
					$contact->lastname = $value;
					$this->errors[] = 'InfoContactLastnameFromCompany';
					break;
				}
			}
		}
		
		// Final validation - Auto-fill empty required fields
		if (empty($contact->lastname)) {
			$contact->lastname = 'XX-AUTO';
			$this->errors[] = 'WarningContactLastnameAutoFilled';
		}

		// Link to company if provided
		if ($company_id > 0) {
			$contact->socid = $company_id;
		}

		// Check for duplicates before creating
		$duplicate_id = $this->checkContactDuplicate($contact);
		if ($duplicate_id > 0) {
			// Duplicate found - update existing contact
			// Link to new company if provided and contact had no company before
			if ($company_id > 0) {
				$contact->socid = $company_id;
			}
			$this->lastDuplicateId = $duplicate_id;
			$update_result = $this->updateContact($duplicate_id, $contact, $user);
			if ($update_result > 0) {
				$this->errors[] = 'Contact already exists - updated (ID: '.$duplicate_id.')';
				return -1; // Return -1 to indicate duplicate was updated
			} else {
				$this->errors[] = 'Contact already exists but update failed (ID: '.$duplicate_id.')';
				return 0;
			}
		}

		// Set default values
		$contact->status = 1; // Active

		$result = $contact->create($user);
		if ($result > 0) {
			return $result;
		} else {
			// Collect detailed error information
			if (!empty($contact->error)) {
				$this->errors[] = 'Contact creation failed: '.$contact->error;
			} elseif (!empty($contact->errors) && is_array($contact->errors)) {
				$this->errors[] = 'Contact creation failed: '.implode(', ', $contact->errors);
			} else {
				$this->errors[] = 'Contact creation failed (unknown error)';
			}
			// Add database error if available
			if (!empty($this->db->lasterror())) {
				$this->errors[] = 'DB Error: '.$this->db->lasterror();
			}
			return 0;
		}
	}

	/**
	 * Log import error
	 *
	 * @param int $session_id Session ID
	 * @param int $line_number Line number
	 * @param string $import_type Import type (company/contact)
	 * @param string $error_message Error message
	 * @param string $status Status (error, warning or updated for duplicates)
	 * @param int $company_id Existing company id matched/updated, if any
	 * @param int $contact_id Existing contact id matched/updated, if any
	 */
	private function logError($session_id, $line_number, $import_type, $error_message, $status = 'error', $company_id = 0, $contact_id = 0)
	{
		// Use a separate transaction for logging to ensure logs persist even on rollback
		$this->db->commit(); // Commit any pending changes
		$this->db->begin();  // Start new transaction for log

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."contactimport_logs (";
		$sql .= "fk_session, line_number, import_type, company_id, contact_id, status, error_message, date_creation";
		$sql .= ") VALUES (";
		$sql .= $session_id.", ";
		$sql .= $line_number.", ";
		$sql .= "'".$this->db->escape($import_type)."', ";
		$sql .= ($company_id > 0 ? (int) $company_id : "NULL").", ";
		$sql .= ($contact_id > 0 ? (int) $contact_id : "NULL").", ";
		$sql .= "'".$this->db->escape($status)."', ";
		$sql .= "'".$this->db->escape($error_message)."', ";
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ")";

		$this->db->query($sql);
		$this->db->commit(); // Commit log immediately
		$this->db->begin();  // Resume main transaction
	}

	/**
	 * Log successful import
	 *
	 * @param int $session_id Session ID
	 * @param int $line_number Line number
	 * @param int $company_id Created company ID
	 * @param int $contact_id Created contact ID
	 */
	private function logSuccess($session_id, $line_number, $company_id, $contact_id)
	{
		$import_type = 'both';
		if ($company_id > 0 && $contact_id == 0) {
			$import_type = 'company';
		} elseif ($company_id == 0 && $contact_id > 0) {
			$import_type = 'contact';
		}

		// Use a separate transaction for logging to ensure logs persist even on rollback
		$this->db->commit(); // Commit any pending changes
		$this->db->begin();  // Start new transaction for log
		
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."contactimport_logs (";
		$sql .= "fk_session, line_number, import_type, company_id, contact_id, status, date_creation";
		$sql .= ") VALUES (";
		$sql .= $session_id.", ";
		$sql .= $line_number.", ";
		$sql .= "'".$this->db->escape($import_type)."', ";
		$sql .= ($company_id > 0 ? $company_id : "NULL").", ";
		$sql .= ($contact_id > 0 ? $contact_id : "NULL").", ";
		$sql .= "'success', ";
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ")";

		$this->db->query($sql);
		$this->db->commit(); // Commit log immediately
		$this->db->begin();  // Resume main transaction
	}

	/**
	 * Check if company already exists in database
	 *
	 * @param Societe $company Company object to check
	 * @return int Company ID if duplicate found, 0 if no duplicate
	 */
	private function checkCompanyDuplicate($company)
	{
		global $conf;

		// Check if duplicate check is enabled (default: enabled)
		if (getDolGlobalString('CONTACTIMPORT_DUPLICATE_CHECK') === '0') {
			return 0; // Duplicate check explicitly disabled, allow all imports
		}

		// Check by exact name match
		if (!empty($company->name)) {
			$sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= " WHERE s.nom = '".$this->db->escape($company->name)."'";
			$sql .= " AND s.entity IN (0, ".$conf->entity.")";
			
			// Additional checks for higher accuracy
			if (!empty($company->zip) && !empty($company->town)) {
				$sql .= " AND s.zip = '".$this->db->escape($company->zip)."'";
				$sql .= " AND s.town = '".$this->db->escape($company->town)."'";
			}
			
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		// Check by email if provided
		if (!empty($company->email)) {
			$sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= " WHERE s.email = '".$this->db->escape($company->email)."'";
			$sql .= " AND s.entity IN (0, ".$conf->entity.")";
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		// Check by SIREN/SIRET if provided (French companies)
		if (!empty($company->siren)) {
			$sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= " WHERE s.siren = '".$this->db->escape($company->siren)."'";
			$sql .= " AND s.entity IN (0, ".$conf->entity.")";
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		return 0;
	}

	/**
	 * Check if contact already exists in database
	 *
	 * @param Contact $contact Contact object to check
	 * @return int Contact ID if duplicate found, 0 if no duplicate
	 */
	private function checkContactDuplicate($contact)
	{
		global $conf;

		// Check if duplicate check is enabled (default: enabled)
		if (getDolGlobalString('CONTACTIMPORT_DUPLICATE_CHECK') === '0') {
			return 0; // Duplicate check explicitly disabled, allow all imports
		}

		// Check by email if provided (most reliable)
		if (!empty($contact->email)) {
			$sql = "SELECT c.rowid FROM ".MAIN_DB_PREFIX."socpeople as c";
			$sql .= " WHERE c.email = '".$this->db->escape($contact->email)."'";
			$sql .= " AND c.entity IN (0, ".$conf->entity.")";
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		// Check by name + company combination
		if (!empty($contact->lastname)) {
			$sql = "SELECT c.rowid FROM ".MAIN_DB_PREFIX."socpeople as c";
			$sql .= " WHERE c.lastname = '".$this->db->escape($contact->lastname)."'";
			
			if (!empty($contact->firstname)) {
				$sql .= " AND c.firstname = '".$this->db->escape($contact->firstname)."'";
			}
			
			if (!empty($contact->socid) && $contact->socid > 0) {
				$sql .= " AND c.fk_soc = ".(int)$contact->socid;
			}
			
			$sql .= " AND c.entity IN (0, ".$conf->entity.")";
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		// Check by mobile phone if provided (additional check)
		if (!empty($contact->phone_mobile)) {
			$sql = "SELECT c.rowid FROM ".MAIN_DB_PREFIX."socpeople as c";
			$sql .= " WHERE c.phone_mobile = '".$this->db->escape($contact->phone_mobile)."'";
			$sql .= " AND c.entity IN (0, ".$conf->entity.")";
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->rowid;
			}
		}

		return 0;
	}

	/**
	 * Update existing company - only fill empty fields
	 * 
	 * @param int $company_id Company ID
	 * @param Societe $new_data New data object
	 * @param User $user User performing update
	 * @param mixed $last_order_date Last order date value (can be string or null)
	 * @return int Company ID if successful, 0 otherwise
	 */
	private function updateCompany($company_id, $new_data, User $user, $last_order_date = null)
	{
		$company = new Societe($this->db);
		if ($company->fetch($company_id) <= 0) {
			$this->errors[] = 'Cannot load existing company';
			return 0;
		}
		
		// Update only empty fields
		if (empty($company->name_alias) && !empty($new_data->name_alias)) $company->name_alias = $new_data->name_alias;
		if (empty($company->address) && !empty($new_data->address)) $company->address = $new_data->address;
		if (empty($company->zip) && !empty($new_data->zip)) $company->zip = $new_data->zip;
		if (empty($company->town) && !empty($new_data->town)) $company->town = $new_data->town;
		if (empty($company->phone) && !empty($new_data->phone)) $company->phone = $new_data->phone;
		if (empty($company->fax) && !empty($new_data->fax)) $company->fax = $new_data->fax;
		if (empty($company->email) && !empty($new_data->email)) $company->email = $new_data->email;
		if (empty($company->url) && !empty($new_data->url)) $company->url = $new_data->url;
		if (empty($company->siren) && !empty($new_data->siren)) $company->siren = $new_data->siren;
		if (empty($company->siret) && !empty($new_data->siret)) $company->siret = $new_data->siret;
		if (empty($company->ape) && !empty($new_data->ape)) $company->ape = $new_data->ape;
		if (empty($company->tva_intra) && !empty($new_data->tva_intra)) $company->tva_intra = $new_data->tva_intra;
		
		// Update status based on last_order_date
		// If last_order_date is filled and company is Prospect → upgrade to Customer
		if (!empty($last_order_date) && $company->client == 2) {
			$company->client = 1;
		}
		
		// Generate customer code if missing and required
		if (getDolGlobalString('SOCIETE_CODECLIENT_ADDON') && empty($company->code_client)) {
			$company->get_codeclient($company, 0); // 0 = customer type
		}
		
		$result = $company->update($company_id, $user);
		
	// Check for errors
	if ($result <= 0) {
		if (!empty($company->error)) {
			$this->errors[] = 'Company update failed: '.$company->error;
		} elseif (!empty($company->errors) && is_array($company->errors)) {
			$this->errors[] = 'Company update failed: '.implode(', ', $company->errors);
		} else {
			$this->errors[] = 'Company update failed (unknown error)';
		}
		if (!empty($this->db->lasterror())) {
			$this->errors[] = 'DB Error: '.$this->db->lasterror();
		}
	}
	
	// Update extrafield if we have a last order date
	if ($result > 0 && !empty($last_order_date)) {
		$this->setCompanyExtrafield($company_id, 'last_order_date', $last_order_date);
	}
	
	return ($result > 0) ? $company_id : 0;
}

/**
 * Update existing contact - only fill empty fields
 */
private function updateContact($contact_id, $new_data, User $user)
{
$contact = new Contact($this->db);
if ($contact->fetch($contact_id) <= 0) {
$this->errors[] = 'Cannot load existing contact';
return 0;
}
if (empty($contact->firstname) && !empty($new_data->firstname)) $contact->firstname = $new_data->firstname;
if (empty($contact->address) && !empty($new_data->address)) $contact->address = $new_data->address;
if (empty($contact->zip) && !empty($new_data->zip)) $contact->zip = $new_data->zip;
if (empty($contact->town) && !empty($new_data->town)) $contact->town = $new_data->town;
if (empty($contact->phone_pro) && !empty($new_data->phone_pro)) $contact->phone_pro = $new_data->phone_pro;
if (empty($contact->phone_perso) && !empty($new_data->phone_perso)) $contact->phone_perso = $new_data->phone_perso;
if (empty($contact->phone_mobile) && !empty($new_data->phone_mobile)) $contact->phone_mobile = $new_data->phone_mobile;
if (empty($contact->fax) && !empty($new_data->fax)) $contact->fax = $new_data->fax;
if (empty($contact->email) && !empty($new_data->email)) $contact->email = $new_data->email;
if (empty($contact->birthday) && !empty($new_data->birthday)) $contact->birthday = $new_data->birthday;
if (empty($contact->poste) && !empty($new_data->poste)) $contact->poste = $new_data->poste;
if (empty($contact->socid) && !empty($new_data->socid)) $contact->socid = $new_data->socid;
$result = $contact->update($contact_id, $user);

// Check for errors
if ($result <= 0) {
	if (!empty($contact->error)) {
		$this->errors[] = 'Contact update failed: '.$contact->error;
	} elseif (!empty($contact->errors) && is_array($contact->errors)) {
		$this->errors[] = 'Contact update failed: '.implode(', ', $contact->errors);
	} else {
		$this->errors[] = 'Contact update failed (unknown error)';
	}
	if (!empty($this->db->lasterror())) {
		$this->errors[] = 'DB Error: '.$this->db->lasterror();
	}
}

return ($result > 0) ? $contact_id : 0;
}

	/**
	 * Set extrafield value for a company
	 *
	 * @param int $company_id Company ID
	 * @param string $field_name Extrafield name (without options_ prefix)
	 * @param mixed $value Value to set
	 * @return bool True if successful, false otherwise
	 */
	private function setCompanyExtrafield($company_id, $field_name, $value)
	{
		global $conf;
		
		// Validate that extrafield column exists in database
		$sql_check = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."societe_extrafields LIKE '".$this->db->escape($field_name)."'";
		$resql_check = $this->db->query($sql_check);
		if (!$resql_check || $this->db->num_rows($resql_check) == 0) {
			$this->errors[] = "Extrafield column '".$field_name."' does not exist in societe_extrafields table! Please run SQL update script: sql/update_1.2.0_to_1.2.1.sql";
			dol_syslog(get_class($this)."::setCompanyExtrafield ERROR: Extrafield '".$field_name."' missing in database", LOG_ERR);
			return false;
		}
		
		// Convert date string to timestamp if it's a date field
		if ($field_name === 'last_order_date' && !empty($value)) {
			// Try to parse date
			$timestamp = strtotime($value);
			if ($timestamp === false) {
				// Invalid date format
				return false;
			}
			$value = $timestamp;
		}
		
		// Insert or update extrafield
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."societe_extrafields ";
		$sql .= "(fk_object, ".$field_name.") ";
		$sql .= "VALUES (".(int)$company_id.", ";
		
		if ($field_name === 'last_order_date') {
			$sql .= "FROM_UNIXTIME(".(int)$value.")";
		} else {
			$sql .= "'".$this->db->escape($value)."'";
		}
		
		$sql .= ") ON DUPLICATE KEY UPDATE ".$field_name." = ";
		
		if ($field_name === 'last_order_date') {
			$sql .= "FROM_UNIXTIME(".(int)$value.")";
		} else {
			$sql .= "'".$this->db->escape($value)."'";
		}
		
		$resql = $this->db->query($sql);
		
		return ($resql !== false);
	}
}
