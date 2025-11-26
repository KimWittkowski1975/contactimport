<?php
/* Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        class/contactimportftp.class.php
 * \ingroup     contactimport
 * \brief       Class file for FTP operations
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/contactimporttemplate.class.php';
require_once __DIR__.'/contactimportsession.class.php';
require_once __DIR__.'/contactimportprocessor.class.php';

/**
 * Class for ContactImportFTP
 */
class ContactImportFTP
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error;

	/**
	 * @var array Error messages
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
	 * Download files from FTP/SFTP server
	 *
	 * @return int Number of files downloaded, <0 if error
	 */
	public function downloadFiles()
	{
		global $conf, $langs;

		$ftp_type = getDolGlobalString('CONTACTIMPORT_FTP_TYPE', 'sftp');
		$ftp_host = getDolGlobalString('CONTACTIMPORT_FTP_HOST');
		$ftp_port = getDolGlobalInt('CONTACTIMPORT_FTP_PORT', ($ftp_type == 'sftp' ? 22 : 21));
		$ftp_user = getDolGlobalString('CONTACTIMPORT_FTP_USER');
		$ftp_password = getDolGlobalString('CONTACTIMPORT_FTP_PASSWORD');
		$ftp_path = getDolGlobalString('CONTACTIMPORT_FTP_PATH', '/');
		$file_pattern = getDolGlobalString('CONTACTIMPORT_FTP_FILE_PATTERN', '*.csv');
		$delete_after = getDolGlobalString('CONTACTIMPORT_FTP_DELETE_AFTER_IMPORT');

		// Validate configuration
		if (empty($ftp_host) || empty($ftp_user)) {
			$this->error = $langs->trans("FTPConfigurationIncomplete");
			return -1;
		}

		$upload_dir = getDolGlobalString('CONTACTIMPORT_TEMP_DIR', DOL_DATA_ROOT.'/contactimport/temp');
		if (!is_dir($upload_dir)) {
			dol_mkdir($upload_dir);
		}

		$downloaded_count = 0;

		if ($ftp_type == 'sftp') {
			// SFTP download
			if (!function_exists('ssh2_connect')) {
				$this->error = $langs->trans("SSH2ExtensionNotAvailable");
				return -1;
			}

			$connection = @ssh2_connect($ftp_host, $ftp_port);
			if (!$connection) {
				$this->error = $langs->trans("ConnectionFailed");
				return -1;
			}

			if (!@ssh2_auth_password($connection, $ftp_user, $ftp_password)) {
				$this->error = $langs->trans("AuthenticationFailed");
				return -1;
			}

			$sftp = @ssh2_sftp($connection);
			if (!$sftp) {
				$this->error = $langs->trans("SFTPInitializationFailed");
				return -1;
			}

			// List files
			$files = array();
			$dir = "ssh2.sftp://".$sftp.$ftp_path;
			if (is_dir($dir)) {
				$handle = opendir($dir);
				if ($handle) {
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != ".." && $this->matchPattern($file, $file_pattern)) {
							$files[] = $file;
						}
					}
					closedir($handle);
				}
			}

			// Download files
			foreach ($files as $file) {
				$remote_file = $ftp_path.'/'.$file;
				$local_file = $upload_dir.'/'.$file;

				if (@ssh2_scp_recv($connection, $remote_file, $local_file)) {
					$downloaded_count++;

					// Delete from server if configured
					if ($delete_after) {
						@ssh2_sftp_unlink($sftp, $remote_file);
					}
				}
			}

		} else {
			// FTP download
			dol_syslog("ContactImportFTP: Connecting to FTP server ".$ftp_host.":".$ftp_port, LOG_DEBUG);
			$connection = @ftp_connect($ftp_host, $ftp_port, 30);
			if (!$connection) {
				$this->error = $langs->trans("ConnectionFailed")." - Host: ".$ftp_host.":".$ftp_port;
				dol_syslog("ContactImportFTP: Connection failed - ".$this->error, LOG_ERR);
				return -1;
			}

			dol_syslog("ContactImportFTP: Logging in as ".$ftp_user, LOG_DEBUG);
			if (!@ftp_login($connection, $ftp_user, $ftp_password)) {
				$this->error = $langs->trans("AuthenticationFailed")." - User: ".$ftp_user;
				dol_syslog("ContactImportFTP: Login failed - ".$this->error, LOG_ERR);
				@ftp_close($connection);
				return -1;
			}

			if (getDolGlobalString('CONTACTIMPORT_FTP_PASSIVE')) {
				ftp_pasv($connection, true);
				dol_syslog("ContactImportFTP: Passive mode enabled", LOG_DEBUG);
			}

			// Change to directory
			dol_syslog("ContactImportFTP: Changing to directory ".$ftp_path, LOG_DEBUG);
			if (!@ftp_chdir($connection, $ftp_path)) {
				$this->error = "Cannot change to directory: ".$ftp_path;
				dol_syslog("ContactImportFTP: ".$this->error, LOG_ERR);
				@ftp_close($connection);
				return -1;
			}

			// List files
			dol_syslog("ContactImportFTP: Listing files with pattern ".$file_pattern, LOG_DEBUG);
			$files = @ftp_nlist($connection, ".");
			if ($files === false) {
				dol_syslog("ContactImportFTP: ftp_nlist returned false, trying ftp_rawlist", LOG_WARNING);
				$files = array();
			} else {
				dol_syslog("ContactImportFTP: Found ".count($files)." files: ".implode(', ', $files), LOG_DEBUG);
			}

			// Download files
			foreach ($files as $file) {
				dol_syslog("ContactImportFTP: Checking file ".$file." against pattern ".$file_pattern, LOG_DEBUG);
				if ($this->matchPattern($file, $file_pattern)) {
					$local_file = $upload_dir.'/'.$file;
					
					dol_syslog("ContactImportFTP: Downloading ".$file." to ".$local_file, LOG_DEBUG);
					if (@ftp_get($connection, $local_file, $file, FTP_BINARY)) {
						$downloaded_count++;
						dol_syslog("ContactImportFTP: Successfully downloaded ".$file, LOG_INFO);

						// Delete from server if configured
						if ($delete_after) {
							if (@ftp_delete($connection, $file)) {
								dol_syslog("ContactImportFTP: Deleted ".$file." from server", LOG_INFO);
							} else {
								dol_syslog("ContactImportFTP: Failed to delete ".$file." from server", LOG_WARNING);
							}
						}
					} else {
						dol_syslog("ContactImportFTP: Failed to download ".$file, LOG_ERR);
					}
				} else {
					dol_syslog("ContactImportFTP: File ".$file." does not match pattern", LOG_DEBUG);
				}
			}

			@ftp_close($connection);
			dol_syslog("ContactImportFTP: FTP session closed. Downloaded ".$downloaded_count." files", LOG_INFO);
		}

		return $downloaded_count;
	}

	/**
	 * Auto-import downloaded files using default template
	 *
	 * @return int Number of files imported, <0 if error
	 */
	public function autoImportDownloadedFiles()
	{
		global $conf, $user, $langs;

		// Get default template
		$template = new ContactImportTemplate($this->db);
		$template_id = $template->getDefaultTemplate();
		
		if (!$template_id) {
			$this->error = $langs->trans("NoDefaultTemplateFound");
			return -1;
		}

		$template->fetch($template_id);
		$mapping = json_decode($template->mapping_config, true);

		$upload_dir = getDolGlobalString('CONTACTIMPORT_TEMP_DIR', DOL_DATA_ROOT.'/contactimport/temp');
		$imported_count = 0;

		// Get all CSV files
		$files = glob($upload_dir.'/*.csv');

		foreach ($files as $filepath) {
			$filename = basename($filepath);

			// Create import session
			$session = new ContactImportSession($this->db);
			$session->ref = 'AUTO_'.dol_now();
			$session->label = 'Auto Import: '.$filename;
			$session->description = 'Automatically imported via FTP';
			$session->filename = $filename;
			$session->file_path = $filepath;
			$session->file_size = filesize($filepath);
			$session->csv_separator = $template->csv_separator;
			$session->csv_enclosure = $template->csv_enclosure;
			$session->has_header = $template->has_header;
			$session->total_lines = count(file($filepath)) - ($template->has_header ? 1 : 0);
			$session->status = 2; // Mapped
			$session->mapping_config = $template->mapping_config;

			$session_id = $session->create($user);

		if ($session_id > 0) {
			// Process import
			$session->fetch($session_id); // Reload session object
			$processor = new ContactImportProcessor($this->db);
			$result = $processor->processImport($session, $user);
			
			// Check if import was successful (result is an array with 'success' key)
			if (!empty($result['success'])) {
				$imported_count++;
				
				// Session status is already updated by processImport()
				// Just delete local file if configured
				if (getDolGlobalString('CONTACTIMPORT_FTP_DELETE_AFTER_IMPORT')) {
					@unlink($filepath);
				}
			} else {
				// Mark as error if not already marked
				$session->fetch($session_id);
				if ($session->status != 5) {
					$session->status = 5; // Error
					$session->update($user);
				}
				
				// Store error message
				if (!empty($processor->error)) {
					$this->error = 'Import failed for '.$filename.': '.$processor->error;
				} elseif (!empty($result['messages'])) {
					$this->error = 'Import failed for '.$filename.': '.implode(', ', $result['messages']);
				}
			}
		}
	}

		return $imported_count;
	}

	/**
	 * Check if filename matches pattern
	 *
	 * @param string $filename Filename to check
	 * @param string $pattern  Pattern (supports * wildcard)
	 * @return bool
	 */
	private function matchPattern($filename, $pattern)
	{
		$pattern = str_replace('*', '.*', $pattern);
		$pattern = '/^'.$pattern.'$/i';
		return preg_match($pattern, $filename);
	}
}
