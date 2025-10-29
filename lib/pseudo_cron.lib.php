<?php
/**
 * WordPress-style Pseudo-Cron for ContactImport
 * 
 * This file is called on every page load and checks if it's time to run the import.
 * Include this at the end of custom/contactimport/core/modules/modContactImport.class.php
 * 
 * Copyright (C) 2025 Kim Wittkowski <kim.wittkowski@gmx.de>
 */

// Only run in web context, not CLI
if (php_sapi_name() === 'cli') {
	return;
}

// Only run occasionally (1 in 100 requests to avoid performance impact)
if (rand(1, 100) > 1) {
	return;
}

// Check if import is already running (prevent concurrent executions)
$lock_file = DOL_DATA_ROOT.'/contactimport/pseudo_cron.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 300)) {
	// Lock file exists and is less than 5 minutes old
	return;
}

// Create lock file
if (!is_dir(DOL_DATA_ROOT.'/contactimport')) {
	mkdir(DOL_DATA_ROOT.'/contactimport', 0755, true);
}
touch($lock_file);

// Check if enough time has passed
$last_run_file = DOL_DATA_ROOT.'/contactimport/last_cron_run.txt';
$sync_interval = !empty($conf->global->CONTACTIMPORT_FTP_SYNC_INTERVAL) ? $conf->global->CONTACTIMPORT_FTP_SYNC_INTERVAL : 60;

if (file_exists($last_run_file)) {
	$last_run = (int)file_get_contents($last_run_file);
	$time_since_last_run = time() - $last_run;
	$sync_interval_seconds = $sync_interval * 60;
	
	if ($time_since_last_run < $sync_interval_seconds) {
		// Not time yet
		unlink($lock_file);
		return;
	}
}

// Time to run the import
try {
	require_once __DIR__.'/class/contactimportftp.class.php';
	
	global $db, $user;
	
	// Use current user or load admin user
	if (empty($user) || $user->id <= 0) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$user = new User($db);
		$user->fetch($conf->global->CONTACTIMPORT_DEFAULT_USER_ID ? $conf->global->CONTACTIMPORT_DEFAULT_USER_ID : 1);
	}
	
	$ftp = new ContactImportFTP($db);
	$downloaded = $ftp->downloadFiles();
	
	if ($downloaded > 0) {
		$ftp->autoImportDownloadedFiles();
	}
	
	// Update last run time
	file_put_contents($last_run_file, time());
	
} catch (Exception $e) {
	// Log error silently
	error_log('ContactImport Pseudo-Cron Error: '.$e->getMessage());
}

// Remove lock file
if (file_exists($lock_file)) {
	unlink($lock_file);
}
