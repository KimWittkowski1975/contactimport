<?php
/**
 * Test import script for debugging
 */

// CLI mode
define('NOCSRFCHECK', 1);
$sapi_type = constant('PHP_SAPI');

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) {
	$res = include __DIR__."/../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails\n");
}

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../class/contactimportsession.class.php';
require_once __DIR__.'/../class/contactimportprocessor.class.php';

echo "=== Test Import for Session 121 ===\n\n";

// Create user object
$user = new User($db);
$user->fetch(2); // wittkowski user

// Fetch session
$session = new ContactImportSession($db);
$result = $session->fetch(121);

if ($result <= 0) {
	die("Error: Cannot load session 121\n");
}

echo "Session loaded:\n";
echo "  - Ref: ".$session->ref."\n";
echo "  - Filename: ".$session->filename."\n";
echo "  - File path: ".$session->file_path."\n";
echo "  - Status: ".$session->status."\n";
echo "  - Total lines: ".$session->total_lines."\n";
echo "  - Mapping config: ".$session->mapping_config."\n\n";

// Check if file exists
if (!file_exists($session->file_path)) {
	die("Error: File does not exist: ".$session->file_path."\n");
}

echo "File exists: YES\n";
echo "File size: ".filesize($session->file_path)." bytes\n\n";

// Process import
echo "Starting import...\n\n";
$processor = new ContactImportProcessor($db);
$result = $processor->processImport($session, $user);

echo "Import completed!\n\n";
echo "Results:\n";
print_r($result);

// Reload session to see updated stats
$session->fetch(121);
echo "\nUpdated Session Stats:\n";
echo "  - Status: ".$session->status."\n";
echo "  - Processed lines: ".$session->processed_lines."\n";
echo "  - Success lines: ".$session->success_lines."\n";
echo "  - Error lines: ".$session->error_lines."\n";
echo "  - Date import: ".$session->date_import."\n";

// Show import logs
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."contactimport_logs WHERE fk_session = 121 ORDER BY line_number LIMIT 10";
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	echo "\nImport Logs (first 10):\n";
	for ($i = 0; $i < $num; $i++) {
		$obj = $db->fetch_object($resql);
		echo "  Line ".$obj->line_number.": ".$obj->status." - ".$obj->import_type;
		if ($obj->error_message) {
			echo " - Error: ".$obj->error_message;
		}
		echo "\n";
	}
}

echo "\n=== Test completed ===\n";
