<?php
/**
 * CLI script for automatic FTP import
 * Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__."/";

@set_time_limit(0);
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);

require $path."../../../main.inc.php";
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../class/contactimportftp.class.php';

// Get configuration
$sync_interval = !empty($conf->global->CONTACTIMPORT_FTP_SYNC_INTERVAL) ? $conf->global->CONTACTIMPORT_FTP_SYNC_INTERVAL : 60;
$last_run_file = DOL_DATA_ROOT.'/contactimport/last_cron_run.txt';

// Check if enough time has passed
if (file_exists($last_run_file)) {
	$last_run = (int)file_get_contents($last_run_file);
	$time_since_last_run = time() - $last_run;
	$sync_interval_seconds = $sync_interval * 60;
	
	if ($time_since_last_run < $sync_interval_seconds) {
		echo "Skip: Only ".floor($time_since_last_run/60)." minutes since last run (interval: ".$sync_interval." minutes)\n";
		exit(0);
	}
}

echo "=== ContactImport FTP Sync ===\n";
echo "Started at: ".date('Y-m-d H:i:s')."\n";
echo "Sync interval: ".$sync_interval." minutes\n\n";

// Create user
$user = new User($db);
$user->fetch($conf->global->CONTACTIMPORT_DEFAULT_USER_ID ? $conf->global->CONTACTIMPORT_DEFAULT_USER_ID : 1);

// Initialize FTP
$ftp = new ContactImportFTP($db);

// Download files
echo "Downloading files from FTP...\n";
$downloaded = $ftp->downloadFiles();

if ($downloaded > 0) {
	echo "✓ Downloaded ".$downloaded." file(s)\n\n";
	
	// Auto import
	echo "Starting auto import...\n";
	$imported = $ftp->autoImportDownloadedFiles();
	
	if ($imported > 0) {
		echo "✓ Successfully imported ".$imported." file(s)\n\n";
		
		// Update last run timestamp
		if (!is_dir(DOL_DATA_ROOT.'/contactimport')) {
			mkdir(DOL_DATA_ROOT.'/contactimport', 0755, true);
		}
		file_put_contents($last_run_file, time());
		
		echo "Finished at: ".date('Y-m-d H:i:s')."\n";
		exit(0);
	} else {
		echo "✗ Import failed\n";
		if (!empty($ftp->errors)) {
			foreach ($ftp->errors as $error) {
				echo "  Error: ".$error."\n";
			}
		}
		exit(1);
	}
} elseif ($downloaded === 0) {
	echo "ℹ No files to download\n";
	echo "Finished at: ".date('Y-m-d H:i:s')."\n";
	exit(0);
} else {
	echo "✗ Download failed\n";
	if (!empty($ftp->errors)) {
		foreach ($ftp->errors as $error) {
			echo "  Error: ".$error."\n";
		}
	}
	exit(1);
}
