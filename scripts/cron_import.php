#!/usr/bin/php
<?php
/**
 * Cron script for automatic FTP import
 * 
 * This script should be called by cron job
 * Example crontab entry (runs every hour):
 * 0 * * * * /usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php
 * 
 * Copyright (C) 2025 Kim Wittkowski <kim.wittkowski@gmx.de>
 */

// Ensure this is run from command line only
if (php_sapi_name() !== 'cli') {
	die("This script must be run from command line\n");
}

// Set CLI mode for Dolibarr
define('NOCSRFCHECK', 1);
$sapi_type = constant('PHP_SAPI');
$script_file = basename(__FILE__);
$path = __DIR__."/";

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists($path."../../main.inc.php")) {
	$res = include $path."../../main.inc.php";
}
if (!$res && file_exists($path."../../../main.inc.php")) {
	$res = include $path."../../../main.inc.php";
}
if (!$res && file_exists($path."../../../../main.inc.php")) {
	$res = include $path."../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails\n");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../class/contactimportftp.class.php';

// Get last run timestamp file
$last_run_file = DOL_DATA_ROOT.'/contactimport/last_cron_run.txt';
$sync_interval = $conf->global->CONTACTIMPORT_FTP_SYNC_INTERVAL;

if (empty($sync_interval)) {
	echo "Error: CONTACTIMPORT_FTP_SYNC_INTERVAL not configured\n";
	exit(1);
}

$sync_interval_seconds = $sync_interval * 60; // Convert minutes to seconds

// Check if enough time has passed since last run
if (file_exists($last_run_file)) {
	$last_run = (int)file_get_contents($last_run_file);
	$time_since_last_run = time() - $last_run;
	
	if ($time_since_last_run < $sync_interval_seconds) {
		echo "Skip: Only ".$time_since_last_run." seconds since last run (interval: ".$sync_interval_seconds." seconds)\n";
		exit(0);
	}
}

echo "Starting automatic FTP import at ".date('Y-m-d H:i:s')."\n";

// Create user object for import
$user = new User($db);
$user->fetch($conf->global->CONTACTIMPORT_DEFAULT_USER_ID ?: 1);

// Initialize FTP
$ftp = new ContactImportFTP($db);

// Download files
echo "Downloading files from FTP...\n";
$downloaded = $ftp->downloadFiles();

if ($downloaded > 0) {
	echo "Downloaded ".$downloaded." file(s)\n";
	
	// Auto import
	echo "Starting auto import...\n";
	$imported = $ftp->autoImportDownloadedFiles();
	
	if ($imported > 0) {
		echo "Successfully imported ".$imported." file(s)\n";
	} else {
		echo "Error: Import failed\n";
		if (!empty($ftp->errors)) {
			foreach ($ftp->errors as $error) {
				echo "  - ".$error."\n";
			}
		}
		exit(1);
	}
} elseif ($downloaded === 0) {
	echo "No files to download\n";
} else {
	echo "Error: Download failed\n";
	if (!empty($ftp->errors)) {
		foreach ($ftp->errors as $error) {
			echo "  - ".$error."\n";
		}
	}
	exit(1);
}

// Update last run timestamp
if (!is_dir(DOL_DATA_ROOT.'/contactimport')) {
	mkdir(DOL_DATA_ROOT.'/contactimport', 0755, true);
}
file_put_contents($last_run_file, time());

echo "Finished at ".date('Y-m-d H:i:s')."\n";
exit(0);
