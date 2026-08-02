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
	 * @var string|null Filename of the last file downloaded by downloadFiles()
	 */
	public $lastDownloadedFilename = null;

	/**
	 * @var string|null Local path of the last file downloaded by downloadFiles()
	 */
	public $lastDownloadedFilePath = null;

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
		
		// Increase execution time for FTP operations
		@set_time_limit(120); // 2 minutes for download

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

			// List files with modification times
			$files = array();
			$dir = "ssh2.sftp://".$sftp.$ftp_path;
			if (is_dir($dir)) {
				$handle = opendir($dir);
				if ($handle) {
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != ".." && $this->matchPattern($file, $file_pattern)) {
							$remote_file = $ftp_path.'/'.$file;
							$stat = @ssh2_sftp_stat($sftp, $remote_file);
							$mtime = isset($stat['mtime']) ? $stat['mtime'] : 0;
							$files[$file] = $mtime;
							dol_syslog("ContactImportFTP: SFTP File ".$file." matches pattern, mtime=".$mtime, LOG_DEBUG);
						}
					}
					closedir($handle);
				}
			}

			// Sort by modification time (newest first) and take only the latest
			if (!empty($files)) {
				arsort($files); // Sort descending by timestamp
				$latest_file = key($files); // Get first key (newest file)
				dol_syslog("ContactImportFTP: Latest SFTP file: ".$latest_file." (mtime=".date('Y-m-d H:i:s', $files[$latest_file]).")", LOG_INFO);
				
				$remote_file = $ftp_path.'/'.$latest_file;
				$local_file = $upload_dir.'/'.$latest_file;

				if (@ssh2_scp_recv($connection, $remote_file, $local_file)) {
					$downloaded_count++;
					$this->lastDownloadedFilename = $latest_file;
					$this->lastDownloadedFilePath = $local_file;
					dol_syslog("ContactImportFTP: Successfully downloaded ".$latest_file." via SFTP", LOG_INFO);

					// Delete from server if configured
					if ($delete_after) {
						if (@ssh2_sftp_unlink($sftp, $remote_file)) {
							dol_syslog("ContactImportFTP: Deleted ".$latest_file." from SFTP server", LOG_INFO);
						} else {
							dol_syslog("ContactImportFTP: Failed to delete ".$latest_file." from SFTP server", LOG_WARNING);
						}
					}
				} else {
					dol_syslog("ContactImportFTP: Failed to download ".$latest_file." via SFTP", LOG_ERR);
				}
			} else {
				dol_syslog("ContactImportFTP: No files matching pattern ".$file_pattern, LOG_WARNING);
			}

		} else {
			// FTP download
			dol_syslog("ContactImportFTP: Connecting to FTP server ".$ftp_host.":".$ftp_port, LOG_DEBUG);
			// Reduced timeout from 30s to 10s to prevent max_execution_time issues
			$connection = @ftp_connect($ftp_host, $ftp_port, 10);
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

			// List files with modification times
			dol_syslog("ContactImportFTP: Listing files with pattern ".$file_pattern, LOG_DEBUG);
			$files = @ftp_nlist($connection, ".");
			if ($files === false) {
				dol_syslog("ContactImportFTP: ftp_nlist returned false, trying ftp_rawlist", LOG_WARNING);
				$files = array();
			} else {
				dol_syslog("ContactImportFTP: Found ".count($files)." files: ".implode(', ', $files), LOG_DEBUG);
			}
			
			// Find matching files with modification time
			$matching_files = array();
			foreach ($files as $file) {
				if ($this->matchPattern($file, $file_pattern)) {
					$mdtm = @ftp_mdtm($connection, $file);
					$matching_files[$file] = $mdtm !== -1 ? $mdtm : 0;
					dol_syslog("ContactImportFTP: File ".$file." matches pattern, mtime=".$mdtm, LOG_DEBUG);
				}
			}
			
			// Sort by modification time (newest first) and take only the latest
			if (!empty($matching_files)) {
				arsort($matching_files); // Sort descending by timestamp
				$latest_file = key($matching_files); // Get first key (newest file)
				dol_syslog("ContactImportFTP: Latest file: ".$latest_file." (mtime=".date('Y-m-d H:i:s', $matching_files[$latest_file]).")", LOG_INFO);
				
				$local_file = $upload_dir.'/'.$latest_file;
				
				dol_syslog("ContactImportFTP: Downloading latest file ".$latest_file." to ".$local_file, LOG_DEBUG);
				if (@ftp_get($connection, $local_file, $latest_file, FTP_BINARY)) {
					$downloaded_count++;
					$this->lastDownloadedFilename = $latest_file;
					$this->lastDownloadedFilePath = $local_file;
					dol_syslog("ContactImportFTP: Successfully downloaded ".$latest_file, LOG_INFO);

					// Delete from server if configured
					if ($delete_after) {
						if (@ftp_delete($connection, $latest_file)) {
							dol_syslog("ContactImportFTP: Deleted ".$latest_file." from server", LOG_INFO);
						} else {
							dol_syslog("ContactImportFTP: Failed to delete ".$latest_file." from server", LOG_WARNING);
						}
					}
				} else {
					dol_syslog("ContactImportFTP: Failed to download ".$latest_file, LOG_ERR);
				}
			} else {
				dol_syslog("ContactImportFTP: No files matching pattern ".$file_pattern, LOG_WARNING);
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
		
		// Increase execution time for large imports
		@set_time_limit(0); // No limit for background import processing
		
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
	 * List files on the FTP/SFTP server matching the configured pattern, without downloading them
	 *
	 * @return array|int Array of files (each: name, size, mtime), or <0 if error
	 */
	public function listRemoteFiles()
	{
		global $langs;

		@set_time_limit(60);

		$ftp_type = getDolGlobalString('CONTACTIMPORT_FTP_TYPE', 'sftp');
		$ftp_host = getDolGlobalString('CONTACTIMPORT_FTP_HOST');
		$ftp_port = getDolGlobalInt('CONTACTIMPORT_FTP_PORT', ($ftp_type == 'sftp' ? 22 : 21));
		$ftp_user = getDolGlobalString('CONTACTIMPORT_FTP_USER');
		$ftp_password = getDolGlobalString('CONTACTIMPORT_FTP_PASSWORD');
		$ftp_path = getDolGlobalString('CONTACTIMPORT_FTP_PATH', '/');
		$file_pattern = getDolGlobalString('CONTACTIMPORT_FTP_FILE_PATTERN', '*.csv');

		if (empty($ftp_host) || empty($ftp_user)) {
			$this->error = $langs->trans("FTPConfigurationIncomplete");
			return -1;
		}

		$result = array();

		if ($ftp_type == 'sftp') {
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

			$dir = "ssh2.sftp://".$sftp.$ftp_path;
			if (is_dir($dir)) {
				$handle = opendir($dir);
				if ($handle) {
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != ".." && $this->matchPattern($file, $file_pattern)) {
							$stat = @ssh2_sftp_stat($sftp, $ftp_path.'/'.$file);
							$result[] = array(
								'name' => $file,
								'size' => isset($stat['size']) ? $stat['size'] : 0,
								'mtime' => isset($stat['mtime']) ? $stat['mtime'] : 0,
							);
						}
					}
					closedir($handle);
				}
			}
		} else {
			$connection = @ftp_connect($ftp_host, $ftp_port, 10);
			if (!$connection) {
				$this->error = $langs->trans("ConnectionFailed")." - Host: ".$ftp_host.":".$ftp_port;
				return -1;
			}
			if (!@ftp_login($connection, $ftp_user, $ftp_password)) {
				$this->error = $langs->trans("AuthenticationFailed")." - User: ".$ftp_user;
				@ftp_close($connection);
				return -1;
			}
			if (getDolGlobalString('CONTACTIMPORT_FTP_PASSIVE')) {
				ftp_pasv($connection, true);
			}
			if (!@ftp_chdir($connection, $ftp_path)) {
				$this->error = "Cannot change to directory: ".$ftp_path;
				@ftp_close($connection);
				return -1;
			}

			$files = @ftp_nlist($connection, ".");
			if ($files === false) {
				$files = array();
			}
			foreach ($files as $file) {
				if ($this->matchPattern($file, $file_pattern)) {
					$mdtm = @ftp_mdtm($connection, $file);
					$size = @ftp_size($connection, $file);
					$result[] = array(
						'name' => $file,
						'size' => $size !== -1 ? $size : 0,
						'mtime' => $mdtm !== -1 ? $mdtm : 0,
					);
				}
			}

			@ftp_close($connection);
		}

		// Sort by modification time, newest first
		usort($result, function ($a, $b) {
			return $b['mtime'] - $a['mtime'];
		});

		return $result;
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

	/**
	 * Cron job method for automatic FTP download and import
	 *
	 * @return int 0 if OK, <0 if error
	 */
	public function cronAutoImport()
	{
		global $conf, $langs;

		dol_syslog("ContactImportFTP::cronAutoImport - Starting auto-import cron job", LOG_INFO);

		// Download files from FTP
		$downloaded = $this->downloadFiles();
		if ($downloaded < 0) {
			dol_syslog("ContactImportFTP::cronAutoImport - Download failed: ".$this->error, LOG_ERR);
			return -1;
		}

		dol_syslog("ContactImportFTP::cronAutoImport - Downloaded ".$downloaded." files", LOG_INFO);

		if ($downloaded > 0) {
			// Auto-import downloaded files
			$imported = $this->autoImportDownloadedFiles();
			if ($imported < 0) {
				dol_syslog("ContactImportFTP::cronAutoImport - Import failed: ".$this->error, LOG_ERR);
				return -1;
			}

			dol_syslog("ContactImportFTP::cronAutoImport - Imported ".$imported." files", LOG_INFO);
		}

		// Delete old files from FTP if configured
		if (getDolGlobalInt('CONTACTIMPORT_FTP_DELETE_OLD_FILES')) {
			$deleted = $this->deleteOldFilesFromFTP();
			dol_syslog("ContactImportFTP::cronAutoImport - Deleted ".$deleted." old files from FTP", LOG_INFO);
		}

		return 0;
	}

	/**
	 * Delete old files from FTP server
	 *
	 * @return int Number of files deleted, <0 if error
	 */
	public function deleteOldFilesFromFTP()
	{
		global $conf, $langs;

		$ftp_type = getDolGlobalString('CONTACTIMPORT_FTP_TYPE', 'sftp');
		$ftp_host = getDolGlobalString('CONTACTIMPORT_FTP_HOST');
		$ftp_port = getDolGlobalInt('CONTACTIMPORT_FTP_PORT', ($ftp_type == 'sftp' ? 22 : 21));
		$ftp_user = getDolGlobalString('CONTACTIMPORT_FTP_USER');
		$ftp_password = getDolGlobalString('CONTACTIMPORT_FTP_PASSWORD');
		$ftp_path = getDolGlobalString('CONTACTIMPORT_FTP_PATH', '/');
		$old_files_days = getDolGlobalInt('CONTACTIMPORT_FTP_OLD_FILES_DAYS', 30);

		if (empty($ftp_host) || empty($ftp_user)) {
			$this->error = $langs->trans("FTPConfigurationIncomplete");
			return -1;
		}

		$deleted_count = 0;
		$cutoff_time = time() - ($old_files_days * 24 * 3600);

		if ($ftp_type == 'sftp') {
			// SFTP deletion
			if (!function_exists('ssh2_connect')) {
				$this->error = $langs->trans("SSH2ExtensionNotAvailable");
				return -1;
			}

			$connection = @ssh2_connect($ftp_host, $ftp_port);
			if (!$connection || !@ssh2_auth_password($connection, $ftp_user, $ftp_password)) {
				return -1;
			}

			$sftp = @ssh2_sftp($connection);
			if (!$sftp) return -1;

			$dir = "ssh2.sftp://".$sftp.$ftp_path;
			if (is_dir($dir)) {
				$handle = opendir($dir);
				if ($handle) {
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != "..") {
							$filepath = $ftp_path.'/'.$file;
							$stat = @ssh2_sftp_stat($sftp, $filepath);
							if ($stat && isset($stat['mtime']) && $stat['mtime'] < $cutoff_time) {
								if (@ssh2_sftp_unlink($sftp, $filepath)) {
									$deleted_count++;
								}
							}
						}
					}
					closedir($handle);
				}
			}
		} else {
			// FTP deletion
			$connection = @ftp_connect($ftp_host, $ftp_port, 30);
			if (!$connection || !@ftp_login($connection, $ftp_user, $ftp_password)) {
				return -1;
			}

			if (getDolGlobalString('CONTACTIMPORT_FTP_PASSIVE')) {
				ftp_pasv($connection, true);
			}

			if (@ftp_chdir($connection, $ftp_path)) {
				$files = @ftp_nlist($connection, ".");
				if ($files !== false) {
					foreach ($files as $file) {
						$modtime = @ftp_mdtm($connection, $file);
						if ($modtime != -1 && $modtime < $cutoff_time) {
							if (@ftp_delete($connection, $file)) {
								$deleted_count++;
								dol_syslog("ContactImportFTP: Deleted old file ".$file." from FTP", LOG_INFO);
							}
						}
					}
				}
			}

			@ftp_close($connection);
		}

		return $deleted_count;
	}
}
