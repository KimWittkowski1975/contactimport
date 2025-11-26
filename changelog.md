# ContactImport Module - Changelog

## Version 1.0.7 (2025-11-23)

### Fixed
- **CRITICAL: Duplicate Check Not Working:** Fixed duplicate check toggle not being respected
  - Added check for `CONTACTIMPORT_DUPLICATE_CHECK` setting in both `checkCompanyDuplicate()` and `checkContactDuplicate()`
  - When duplicate check is disabled (0), all imports are now allowed even if records exist
  - Previously the setting existed but was never actually checked during import

### Technical Details
- Modified `class/contactimportprocessor.class.php`
- Added `getDolGlobalString('CONTACTIMPORT_DUPLICATE_CHECK')` check at start of both duplicate check functions
- Returns 0 (no duplicate) when setting is disabled, allowing imports to proceed

---

## Version 1.0.6 (2025-11-23)

### Added
- **Duplicate Check Toggle:** Added on/off switch for duplicate detection on duplicates management page
  - New toggle button at top of `admin/duplicates.php` page
  - Allows enabling/disabling duplicate check (`CONTACTIMPORT_DUPLICATE_CHECK`)
  - Visual feedback with green (enabled) / red (disabled) button styling
  - When disabled, all records will be imported even if they already exist
  - Useful for re-importing or updating existing data

### Fixed
- **FTP Download Issues:** Fixed automatic FTP download and import functionality
  - Created missing temp directory `/usr/share/dolibarr/documents/contactimport/temp`
  - Added extensive debug logging to FTP class for better troubleshooting
  - Enabled passive mode for better compatibility with KAS servers
  - Added proper error handling for connection, login and directory changes
  - FTP download now works correctly and automatically imports downloaded files

### Improved
- **FTP Error Logging:** Enhanced logging in `ContactImportFTP` class
  - Added `dol_syslog()` calls for all FTP operations
  - Better error messages including host, user, and path information
  - File pattern matching is now logged for debugging
  - Download success/failure is logged for each file

### Technical Details
- Modified `class/contactimportftp.class.php` - Added debug logging
- Modified `admin/duplicates.php` - Added toggle action and UI
- Modified `langs/de_DE/contactimport.lang` - Added translations for toggle
- Set `CONTACTIMPORT_FTP_PASSIVE=1` in database for better compatibility

---

## Version 1.0.5 (2025-11-12)

### Added
- **Reset Statistics Function:** Added button to reset all import statistics on logs page
  - New "Reset All Statistics" button deletes all sessions and logs
  - Requires confirmation dialog with warning message
  - Completely clears import history and statistics
  - Useful for starting fresh or cleaning test data

### Technical Details
- Modified `admin/logs.php`
- Added `confirm_reset_statistics` action handler
- Added translations for reset confirmation messages (DE + EN)
- Uses database transaction for safe deletion of sessions and logs

---

## Version 1.0.4 (2025-11-12)

### Fixed
- **CSV Enclosure Error:** Fixed "ERROR 500" when clicking "Download and Import" button
  - Added validation to ensure CSV enclosure is exactly one character
  - Defaults to double quote (") if enclosure is empty or invalid
  - Added validation for CSV separator as well
  - This fixes the `ValueError: fgetcsv(): Argument #4 ($enclosure) must be a single character` error

### Technical Details
- Modified `class/contactimportprocessor.class.php`
- Added safety checks before calling `fgetcsv()` function
- Prevents crashes when template has invalid CSV settings

---

## Version 1.0.3 (2025-11-12)

### Changed
- **Always Download Latest File:** Modified `download.php` to ALWAYS download the newest uploaded file
  - Ignores any `session_id` parameter that might be passed
  - Always queries database for the most recently created session
  - Sorted by `date_creation DESC` to ensure latest file is always selected
  - **Breaking Change:** Previous behavior of downloading specific sessions is removed

### Technical Details
- Removed conditional logic for `session_id` parameter
- Direct SQL query to always fetch newest session by `date_creation DESC LIMIT 1`
- Simplified code flow - no parameter checking needed

---

## Version 1.0.2 (2025-11-12)

### Enhanced
- **Auto-Download Latest File:** Modified `download.php` to automatically download the newest uploaded file when no `session_id` is provided
  - If `session_id` parameter is present, downloads the specific session's file (backward compatible)
  - If `session_id` is missing, automatically selects and downloads the most recently created session's file

### Technical Details
- Modified `download.php` to include SQL query for fetching the newest session when `session_id` is not provided
- Maintains backward compatibility with existing links that include `session_id`

---

## Version 1.0.1 (2025-10-29)

### Fixed
- **Menu Position Conflict:** Changed menu positions from 1000+r to 1100+r to avoid conflict with WhatsApp module
  - Previously used positions 1001-1004 which were already occupied by WhatsApp module
  - Now using positions 1101-1104 to ensure no conflicts
  - This fixes the "Error Menu entry (all,1001,) already exists" error when activating the module

### Technical Details
- Modified `core/modules/modContactimport.class.php`
- All menu entries now use position `1100 + $r` instead of `1000 + $r`

---

## Version 1.0.0 (Initial Release)

### Features
- CSV import for contacts and companies
- Field mapping interface
- Template management
- FTP/SFTP integration for automatic imports
- Duplicate detection
- Import history and logging
- Pseudo-cron for scheduled imports
- Multi-language support (EN, DE)

### Capabilities
- Import contacts with automatic company creation
- Flexible CSV field mapping
- Support for various CSV formats and separators
- Automatic and manual import modes
- Comprehensive error handling and logging
