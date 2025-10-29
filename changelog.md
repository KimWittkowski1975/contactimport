# ContactImport Module - Changelog

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
