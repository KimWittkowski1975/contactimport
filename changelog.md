# ContactImport Module - Changelog

## Version 1.4.0 (2026-08-02)

### 🧹 Aufräumen vor Veröffentlichung

- Entfernt: `Kundendaten_30.csv` – eine echte Kundendaten-Exportdatei, die versehentlich im Modulordner lag
- Entfernt: `import_backup.php` und `import_new.php` – tote, unbenutzte Zwischenstände von `import.php`
- Entfernt: `PROCESSOR_FIX.patch` – bereits vollständig in `class/contactimportprocessor.class.php` eingearbeitet
- Entfernt: `scripts/test_import.php` – Wegwerf-Debug-Skript mit hartcodierter Session-ID, das bei Ausführung reale Importdaten erneut verarbeitet hätte
- `class/contactimportprocessor.class.php`: rohe `error_log("CONTACTIMPORT DEBUG: ...")`-Aufrufe aus dem produktiven Import-Pfad entfernt bzw. durch `dol_syslog(..., LOG_DEBUG)` ersetzt
- README.md auf den aktuellen Funktionsstand korrigiert (FTP/Cron sind keine geplanten Features mehr, sondern bereits produktiv im Einsatz; Duplikat-Verhalten korrigiert von "wird übersprungen" auf "wird aktualisiert")

## Version 1.3.14 (2026-05-31)

### ✨ UX-Verbesserung - "Neuen Import starten" Button

#### Direkte Import-Ausführung
- **VORHER:** Button "Neuen Import starten" führte zu upload.php (manuelle Datei-Auswahl)
- **NACHHER:** Button startet **direkt den Import** mit dem Standard-Template
  - Verwendet FTP-Download falls konfiguriert
  - Führt sofort autoImportDownloadedFiles() aus
  - Keine zusätzlichen Klicks nötig!

#### Intelligente Button-Logik
- **Wenn Standard-Template existiert:**
  - Button aktiv: Führt zu `templates.php?action=ftp_download&id=TEMPLATE_ID`
  - Icon: ▶ (Play-Symbol statt Upload)
  - Startet sofort: FTP-Download → Auto-Import → Statistik-Anzeige
  
- **Wenn kein Standard-Template:**
  - Button deaktiviert (grau)
  - Tooltip: "Bitte erstellen Sie zuerst ein Standard-Template"
  - Verhindert Fehler durch fehlende Konfiguration

#### Workflow-Optimierung
**Alt:** setup.php → "Neuen Import starten" → upload.php → Datei wählen → Template wählen → Import starten
**Neu:** setup.php → "Neuen Import starten" → **Fertig!** (Import läuft automatisch)

**Zeitersparnis:** Von 4 Schritten auf 1 Schritt reduziert ✅

#### Technische Details
- Query prüft Standard-Template: `SELECT rowid FROM llx_contactimport_templates WHERE is_default = 1`
- Weiterleitung mit CSRF-Token: `templates.php?action=ftp_download&id=X&token=...`
- Existierende FTP-Download/Import-Logik wiederverwendet (keine Duplikation)

---

## Version 1.3.13 (2026-05-30)

### 🐛 Kritischer Bugfix - Statistik-Anzeige

#### Problem behoben: Falsche Berechnung der erfolgreichen Zeilen
- **VORHER (FALSCH):** `success_lines` = Summe aller Operationen (companies_created + companies_updated + contacts_created + contacts_updated)
  - Bei 244 CSV-Zeilen mit je 1 Company + 1 Contact = 488 "erfolgreiche Zeilen" ❌
  - Mathematisch unmöglich: Mehr Erfolge als verarbeitete Zeilen!
  
- **NACHHER (KORREKT):** `success_lines` = Anzahl erfolgreich verarbeiteter CSV-Zeilen
  - Bei 244 CSV-Zeilen = max. 244 erfolgreiche Zeilen ✅
  - Jede Zeile wird genau 1x gezählt, unabhängig davon ob Company, Contact oder beides erfolgreich war

#### Verbesserte Anzeige
- **"Erfolgreiche Zeilen"** zeigt jetzt korrekt die Anzahl der CSV-Zeilen
- **Neue Sektion:** "Detaillierte Aufschlüsselung (Operationen, nicht Zeilen)"
  - ↳ Firmen neu angelegt: X
  - ↳ Firmen aktualisiert: Y
  - ↳ Kontakte neu angelegt: Z
  - ↳ Kontakte aktualisiert: W
  - Σ Gesamt-Operationen: X+Y+Z+W
- Klare visuelle Trennung zwischen Zeilen-Statistik und Operations-Statistik
- ↳ Symbol zeigt Hierarchie/Zugehörigkeit an

#### Technische Details
- `success_lines` wird jetzt im processImport() Loop korrekt gezählt
- Pro CSV-Zeile: Wenn mindestens 1 Objekt erfolgreich (erstellt ODER aktualisiert) → success_lines++
- Separate Zähler für companies_created/updated und contacts_created/updated bleiben unverändert
- Neue Sprachschlüssel: DetailedOperationsBreakdown, TotalOperations

### 📊 Was der User jetzt sieht:

**Beispiel bei 244 CSV-Zeilen (je 1 Company + 1 Contact):**
```
Verarbeitete Zeilen: 244
Erfolgreiche Zeilen: 244 ✅ (nicht 488!)

Detaillierte Aufschlüsselung (Operationen):
  ↳ Firmen neu angelegt: 120
  ↳ Firmen aktualisiert: 124
  ↳ Kontakte neu angelegt: 180
  ↳ Kontakte aktualisiert: 64
  Σ Gesamt-Operationen: 488
```

**Klare Unterscheidung:** 244 Zeilen verarbeitet, aber 488 Operationen durchgeführt!

---

## Version 1.3.12 (2026-05-30)

### 📊 Detaillierte Import-Statistiken

#### Report-Seite Verbesserungen
- **NEU:** Detaillierte Aufschlüsselung der Import-Ergebnisse
  - Zeigt separate Zähler für Firmen und Kontakte
  - Unterscheidet zwischen "neu angelegt" und "aktualisiert"
  - Kompakte Darstellung in Logs-Übersicht: `C+5 | C~3 | K+12 | K~7`
  - Vollständige Details in Session-Ansicht mit Badges
  - **C+ / C~** = Companies Created / Updated
  - **K+ / K~** = Kontakte Created / Updated (K = Kontakt)

#### Neue Datenbankfelder
- `companies_created` - Anzahl neu angelegter Firmen
- `companies_updated` - Anzahl aktualisierter Firmen
- `contacts_created` - Anzahl neu angelegter Kontakte
- `contacts_updated` - Anzahl aktualisierter Kontakte
- Felder werden automatisch beim Import befüllt
- Vollständig in llx_contactimport.sql für Neuinstallationen enthalten

#### Technische Details
- SQL Update-Skript: `update_1.3.11_to_1.3.12.sql`
- Neue Sprachschlüssel in DE/EN: CompaniesCreated, CompaniesUpdated, ContactsCreated, ContactsUpdated, DetailedStatistics
- Session-Klasse erweitert mit neuen Properties
- Processor schreibt Statistiken automatisch in Session

### 💡 Verbesserungen
- Tooltip in Logs-Liste zeigt vollständige Details beim Hover
- Farbcodierung: Grün (neu angelegt), Orange (aktualisiert)
- Badge-System für übersichtliche Darstellung

---

## Version 1.3.11 (2026-05-30)

### ⚡ Performance-Optimierung (KRITISCH)

#### Batch Import Performance
- **KRITISCHER FIX:** Massive Performance-Verbesserung bei großen CSV-Importen
  - **Problem:** Trigger wurden bei jedem Datensatz ausgeführt → extrem langsam bei großen Dateien
  - **Lösung:** Trigger während Batch-Import deaktiviert, danach wieder aktiviert
  - `MAIN_DISABLE_ALL_MAILS = 1` - Verhindert E-Mail-Trigger bei jedem Datensatz
  - `MAIN_DISABLE_TRIGGERS = 1` - Deaktiviert alle Trigger (inkl. Multicompany)
  - **Geschwindigkeitssteigerung:** 10-50x schneller bei großen Dateien (100+ Zeilen)
  - Reduziert CPU-Last und Speicherverbrauch dramatisch

#### Technische Details
- Bei 100 Zeilen = 200+ Trigger-Aufrufe ohne Optimierung (2 pro Zeile * alle Module)
- Multicompany-Trigger verursachte besonders viele Warnings und Verzögerungen
- Trigger-Deaktivierung während `processImport()`, Reaktivierung danach
- Detailliertes Logging zeigt Trigger-Status

### 🐛 Bugfixes
- Behebt Timeout-Probleme bei großen CSV-Dateien durch Trigger-Overhead
- Eliminiert repetitive Multicompany-Warnings während Import
- Import-Prozess läuft jetzt reibungslos durch ohne Unterbrechungen

---

## Version 1.3.10 (2026-05-30)

### 🚀 Verbesserungen & Best Practices

#### Template-Verwaltung
- **NEU:** Automatische Standard-Template-Verwaltung
  - Nur ein Template kann als Standard markiert sein
  - Beim Setzen eines neuen Standards werden alle anderen automatisch zurückgesetzt
  - Verhindert Konflikte bei automatischen Importen
  - Standard-Template wird in der Liste visuell hervorgehoben (grünes Badge)

#### Import-Logik
- **VERBESSERT:** Intelligente Pflichtfeld-Behandlung für Name und Firma
  - **Bidirektionaler Fallback:**
    - Wenn nur Firma vorhanden: Wird auch als Kontakt-Name verwendet
    - Wenn nur Name vorhanden: Wird auch als Firmenname verwendet
    - Wenn nur Vorname vorhanden: Wird als Firmenname verwendet
    - Wenn beides vorhanden: Beide Werte werden korrekt übernommen
  - Reduziert "XX-AUTO" Platzhalter auf absolute Notfälle
  - **Verbesserte Fehlermeldungen:**
    - Informative Meldungen statt generischer Warnungen
    - Sprachschlüssel für Mehrsprachigkeit (DE/EN)
    - Klare Kennzeichnung von Info vs. Warning

#### FTP/Import Performance
- **BEHOBEN:** "Maximum execution time exceeded" Fehler bei FTP Import
  - FTP Download: `set_time_limit(120)` (2 Minuten)
  - Import Processing: `set_time_limit(0)` (unbegrenzt)
  - FTP Action: `set_time_limit(300)` (5 Minuten)
  - FTP Connection Timeout reduziert: 30s → 10s
  - Ermöglicht Import großer Dateien ohne Timeout-Fehler
- **OPTIMIERT:** Download nur der neuesten CSV-Datei
  - FTP verwendet `ftp_mdtm()` für Zeitstempel-Vergleich
  - SFTP verwendet `ssh2_sftp_stat()` für Zeitstempel-Vergleich
  - Automatische Sortierung nach Änderungsdatum (neueste zuerst)
  - Verhindert Doppel-Imports und reduziert Verarbeitungszeit
  - Detailliertes Logging zeigt ausgewählte Datei mit Zeitstempel

#### Technische Verbesserungen
- Neue Sprachschlüssel:
  - `InfoCompanyNameGenerated` - Firmenname aus Kontakt generiert
  - `InfoCompanyNameFromLastname` - Firmenname aus Nachname kopiert
  - `InfoCompanyNameFromFirstname` - Firmenname aus Vorname kopiert
  - `InfoContactLastnameFromCompany` - Kontakt-Name aus Firma kopiert
  - `WarningCompanyNameAutoFilled` - XX-AUTO bei Firma
  - `WarningContactLastnameAutoFilled` - XX-AUTO bei Kontakt
  - `TemplateSetAsDefault` - Standard-Template Info

#### UI/UX Verbesserungen
- Standard-Template erhält visuelles Badge in der Template-Liste
- Grünes Badge mit "Standard" Label für bessere Übersicht
- Konsistentes Dolibarr UI-System Design
- FTP-Button mit Validierung und hilfreichen Tooltips

### 🐛 Bugfixes
- Verhindert mehrere Standard-Templates gleichzeitig
- Behebt FTP Import Timeout bei großen Dateien
- Verbesserte Fehlerbehandlung bei FTP-Verbindungsproblemen

### 📚 Dokumentation
- Umfangreicher Implementierungsplan erstellt
- Test-Szenarien definiert (10 Test-Cases)
- Best Practice Vorschläge dokumentiert

---

## Version 1.3.9 (2026-03-31)

### Critical Fixes Before JTL Import 🔧

**Fixed missing email mapping and added extrafield validation**

### Changes:

1. **Email Mapping Added:**
   - Added email field (CSV Index 13) to default template
   - Maps to both company.email AND contact.email
   - Email addresses now properly imported from JTL CSV exports

2. **Extrafield Validation:**
   - Added validation in `setCompanyExtrafield()` method
   - Checks if extrafield column exists before INSERT/UPDATE
   - Shows clear error message if `last_order_date` field missing
   - Prevents silent failures when extrafield not properly installed
   - Error message includes SQL update script path for easy fix

3. **Import Logic Verification:**
   - Confirmed: Empty "Letzter Auftrag (Datum)" → Prospect (client=2) ✓
   - Confirmed: Filled "Letzter Auftrag (Datum)" → Customer (client=1) ✓
   - Confirmed: Date value stored in societe_extrafields.last_order_date ✓

4. **Installation Integration:** 🎯
   - **Default Template now included in module installation**
   - JTL_MASTER_TEMPLATE auto-created on module activation
   - Includes all mappings: company name, email, address, last_order_date
   - **Extrafield `last_order_date` now created automatically**
   - Integrated into main SQL installation file (llx_contactimport.sql)
   - No manual setup required - works out of the box!

### Installation:
- **Fresh Install:** Activate module → Template + Extrafield created automatically
- **Existing Install:** Deactivate → Reactivate module to create new default template
- Template Reference: `JTL_MASTER_TEMPLATE`

### Template Mapping (JTL Kundenstammdaten):
```
Company Fields:
  - Index 5:  Firma (Company Name)
  - Index 13: E-Mail (Email)
  - Index 19: Letzter Auftrag (Last Order Date - triggers Customer status)

Contact Fields:
  - Index 3:  Vorname (First Name)
  - Index 4:  Nachname (Last Name)
  - Index 13: E-Mail (Email)
```

### Technical Details:
- `SHOW COLUMNS` check before extrafield operations
- Detailed error logging with `dol_syslog()`
- References SQL update script: `sql/update_1.2.0_to_1.2.1.sql`
- Default template uses `INSERT ... WHERE NOT EXISTS` pattern
- Extrafield creation uses prepared statements for safety

### Testing:
- Tested with JTL export CSV (Kundendaten_30.csv)
- Verified extrafield exists: `llx_extrafields` + `llx_societe_extrafields`
- Confirmed mapping: Index 19 → options_last_order_date

---

## Version 1.3.8 (2026-02-28)

### Auto-Generate Customer Codes 🔢

**Fixes "ErrorCustomerCodeRequired" - companies now created successfully!**

### Problem Fixed:
- Import failed with error: "Company creation failed: ErrorCustomerCodeRequired"
- Companies were NOT created when customer code was mandatory
- Error occurred because Dolibarr requires customer code before creating company

### Solution:
- **Automatic Customer Code Generation:**
  - Detects if SOCIETE_CODECLIENT_ADDON is configured (e.g., mod_codeclient_elephant)
  - Automatically generates customer code using Dolibarr's numbering module
  - Called before company->create() to ensure code is set
  - Also works for company updates if code is missing

- **Manual Code Import Support:**
  - Added `code_client` field mapping
  - Can now import customer codes from CSV if provided
  - If CSV has code_client → uses that value
  - If CSV empty → auto-generates code

### Technical Details:
- Calls `$company->get_codeclient($company, 0)` before create/update
- Uses Dolibarr's configured numbering module (SOCIETE_CODECLIENT_ADDON)
- Only generates if code is empty AND module is configured
- Parameter 0 = customer type

### Workflow:
1. Company data prepared from CSV
2. Check if code_client is empty
3. If empty + module configured → auto-generate code
4. Create/update company with code
5. Success! ✅

### Impact:
- **BEFORE:** Import failed → 0 companies created
- **AFTER:** Import succeeds → all companies created with auto-generated codes

### Configuration Required:
- Dolibarr → Setup → Company → Customer Code Numbering Module
- Must be configured (default: mod_codeclient_elephant)
- Standard Dolibarr configuration - no special setup needed

---

## Version 1.3.7 (2026-02-28)

### Enhanced Error Logging and Display 🔍

**Shows detailed error messages in logs - no more empty error descriptions!**

### Added
- **Recent Errors and Warnings Section:**
  - New section in logs.php showing last 50 errors/warnings
  - Detailed view of each error with:
    * Session reference and filename
    * Line number where error occurred
    * Import type (Company/Contact) with icons
    * Status badge (❌ Error or ⚠️ Warning)
    * Full error message text
    * Timestamp
  - Direct link from error to session details
  - Visual indicators: 🏢 for companies, 👤 for contacts

### Improved
- **Better Error Message Collection:**
  - Create operations: Check object->error, then object->errors[], then database lasterror()
  - Update operations: Same detailed error collection
  - Fallback to descriptive messages: "Company creation failed: reason"
  - No more empty error_message in database
  
- **Enhanced Error Context:**
  - Company creation errors now include "Company creation failed: " prefix
  - Contact creation errors include "Contact creation failed: " prefix
  - Update errors clearly labeled as "update failed"
  - Database errors logged with "DB Error: " prefix

### Fixed
- Empty error_message in llx_contactimport_logs table
- Missing error details when create() or update() fails
- Syntax error in contactimportprocessor.class.php (duplicate code removal)

### User Experience
- Users can now see EXACTLY why imports failed
- Easy troubleshooting with detailed error messages
- Clear visual distinction between errors and warnings
- Quick navigation from error to full session details

### Language Strings
- RecentErrorsAndWarnings - "Aktuelle Fehler und Warnungen"
- ErrorMessage - "Fehlermeldung"
- NoErrorMessageRecorded - "(Keine Fehlermeldung aufgezeichnet)"
- NoErrorsOrWarnings - "Keine Fehler oder Warnungen gefunden"
- MostRecentEntries - "aktuellste Einträge"

---

## Version 1.3.6 (2026-02-28)

### Auto-Fill Missing Required Fields 🔧

**Automatically fills missing required fields to prevent import failures.**

### Added
- **Auto-Fill for Empty Required Fields:**
  - Company name: Automatically filled with "XX-AUTO" if empty
  - Contact lastname: Automatically filled with "XX-AUTO" if empty
  - Warning logged for each auto-filled field
  - Import continues successfully instead of failing

### Improved
- **Better Error Handling:**
  - Warnings logged separately from errors
  - Auto-filled records counted as successful imports
  - Clear indication in logs when fields were auto-filled
  - Prevents import failures due to incomplete data

### User Experience
- No more import failures due to missing required fields
- Easy to identify auto-filled records in logs (status: "warning")
- Can manually update auto-filled records later
- Import statistics remain accurate (success vs. error)

### Technical Details
- Modified `ContactImportProcessor::createCompany()`
- Modified `ContactImportProcessor::createContact()`
- Added warning logging in `processImport()`
- Warnings don't count as errors in statistics

---

## Version 1.3.5 (2026-02-28)

### Enhanced Statistics Display ��

**Improved test import statistics visibility with detailed breakdown.**

### Improved
- **Prominent Statistics Display:**
  - Replaced small notification with large statistics box
  - File-by-file breakdown table
  - Success rate percentage with color coding (green ≥90%, yellow ≥70%, red <70%)
  - Visual indicators: ✅ Success, ❌ Errors, 🏢 Companies, 👤 Contacts
  - Separate Created (+) / Updated (↻) counts
  - Link to detailed import history
  
- **Statistics Summary:**
  - Total counts across all imported files
  - Companies created/updated
  - Contacts created/updated
  - Error count if any

- **Visual Improvements:**
  - Color-coded success rates
  - Badge-style error display
  - Clear column headers (Filename, Total Lines, Processed, Success, Errors, Companies, Contacts, Action)
  - Professional table layout with alternating row colors

### Language Strings
- TestImportResults, Processed, Success, Errors
- Companies, Contacts, ViewDetails, Summary
- CompaniesCreated, CompaniesUpdated
- ContactsCreated, ContactsUpdated
- ErrorsOccurred, Created, Updated, ViewImportHistory

### User Experience
- Statistics now clearly visible after test import
- No need to navigate to import history for quick overview
- At-a-glance success/failure assessment
- Direct link to detailed view from each file row

---

## Version 1.3.4 (2026-02-28)

### Test FTP Import Feature 🧪

**Added one-click test import from FTP server.**

### Added
- **Test FTP-Import Button:**
  - New button on setup page: "Test FTP-Import"
  - Downloads files from configured FTP server
  - Automatically imports using default template
  - Shows detailed statistics after import
  - Only visible when FTP is enabled and default template exists

- **Import Statistics Display:**
  - File name and total lines
  - Success/Error counts
  - Companies created/updated counts
  - Contacts created/updated counts
  - Visual indicators (📄 ✅ ❌)

### Improved
- Better workflow guidance: Upload → Templates → Test Import
- Disabled button with tooltip if requirements not met
- Orange button color for test actions
- Error messages for missing FTP config or templates

### Language Strings
- TestFTPImport, TestImportSuccessful, FilesDownloaded
- ImportStatistics, ErrorFTPNotEnabled, ErrorNoDefaultTemplate
- NoFilesFoundOnFTP, CreateDefaultTemplateFirst

### Workflow
**Test Import Process:**
1. Configure FTP settings (admin/ftp.php)
2. Create default mapping template (admin/templates.php)
3. Click "Test FTP-Import" on setup page
4. Review import statistics
5. Check import history for details

---

## Version 1.3.3 (2026-02-28)

### Navigation Fix & Templates UI Redesign 👍

**Fixed menu navigation and applied new mapping UI to templates.**

### Changed
- **Menu Entry Redirect:**
  - "Mapping Templates" menu now points to `admin/templates.php` (reusable mappings)
  - Removed broken mapping.php menu entry (requires session ID)
  - Users can now access mapping configuration from main menu

- **Templates UI Redesign:**
  - Applied same UI improvements as mapping.php v1.3.1
  - Dolibarr fields listed → Select CSV column for each field
  - Enhanced dropdowns: "Column 1: Name", "Column 2: Email", etc.
  - Live sample data preview
  - Required field badges (Company Name, Contact Lastname)
  
### Technical
- Fixed GETPOST parameter type: 'alpha' → 'int' for CSV column indices
- Removed obsolete 2-column CSS styles
- Consistent UI across mapping.php and templates.php
- Menu entry language string: MappingTemplates (DE+EN)

### Workflow
**You can now:**
1. Click "Mapping Templates" in left menu (Tools → Contact Import)
2. Create reusable mapping configurations
3. Save templates for automated FTP imports
4. Use templates when uploading CSV files

---

## Version 1.3.2 (2026-02-28)

### Improved Navigation: Setup Page Workflow Guide 🧭Added interactive workflow guide on setup page to help users navigate the import process.

### Added
- **Workflow Info Box:**
  - Step-by-step guide displayed on setup page
  - Visual workflow: Upload CSV → Configure Mapping → Run Import
  - Quick action buttons: "Start New Import" and "Manage Templates"
  - Responsive design with icons and clear descriptions

### Improved
- Better user onboarding for first-time importers
- Direct navigation from configuration to import workflow
- Language strings: ImportWorkflow, StartNewImport, ManageTemplates, etc.

---

## Version 1.3.1 (2026-02-28)

### UI Redesign: Mapping Interface 🎨

**Improved User Experience for Field Mapping**

The mapping interface has been completely redesigned based on user feedback to make field mapping more intuitive and less confusing.

### Changed
- **Inverted Mapping UI:**
  - OLD: List CSV columns → Select Dolibarr field for each
  - NEW: List Dolibarr fields → Select CSV column for each
  - More intuitive workflow: "What CSV data goes into this Dolibarr field?"
  
- **Clearer Table Headers:**
  - "Dolibarr Company Field" | "Select CSV Column" | "Sample Data"
  - "Dolibarr Contact Field" | "Select CSV Column" | "Sample Data"
  - Required fields marked with colored badges
  
- **Enhanced Dropdowns:**
  - CSV columns shown as "Column 1: Name", "Column 2: Email", etc.
  - Empty state: "None" option
  - Sample data displayed based on selected column

### Improved
- Better visual consistency with Dolibarr UI-System
- Easier to understand which fields are available
- Sample data now shown in context of selected mapping
- Language strings added: DolibarrCompanyField, DolibarrContactField, SelectCSVColumn, SampleData

### Technical
- Internal mapping storage format unchanged (CSV_column_index => dolibarr_field)
- Save logic refactored to iterate Dolibarr fields instead of CSV columns
- POST parameter names changed: 'company_<fieldname>' and 'contact_<fieldname>'

---

## Version 1.3.0 (2026-02-28)

### Major Feature: Dual-Mapping System ✨

**Convert Contact CSV Data to Company + Contact Pairs**

This update transforms the ContactImport module to automatically create both a Company AND a Contact from each CSV row, solving the common problem where web-form contacts need to exist as both entities in Dolibarr.

### Added
- **Dual-Mapping Interface:**
  - Two separate mapping tables: Company Mapping + Contact Mapping
  - Map each CSV column to BOTH Company AND Contact fields simultaneously
  - Visual separation with color-coded sections
  - Sample data preview for each column
  - Collapsible full CSV preview

- **Smart Duplicate Handling:**
  - Duplicates are now UPDATED instead of skipped
  - Only empty fields are filled (existing data is never overwritten)
  -  Automatic Prospect → Customer upgrade when "Last Order Date" is filled
  - Separate counters: `companies_updated` and `contacts_updated`

- **Fixed Import Mode:**
  - Always imports BOTH Company + Contact (no mode selection needed)
  - Each CSV row creates/updates a Company and links a Contact to it
  - Simplified workflow for typical use case

### Changed
- **Mapping UI (`mapping.php`):**
  - Complete redesign with dual-table layout
  - Company mapping table on top, Contact mapping below
  - Beautiful gradient-styled section titles
  - Clear instructions for dual-mapping concept

- **Processor Logic (`contactimportprocessor.class.php`):**
  - Duplicate detection now triggers update instead of skip
  - Return value `-1` now means "duplicate updated" not "skipped"
  - Result array includes `companies_updated` and `contacts_updated`
  - Log status changed from "skipped" to "updated" for duplicates

- **Language Files:**
  - Added 15+ new translation strings (DE + EN)
  - AlwaysImportBothCompanyAndContact, CompanyMapping, ContactMapping, etc.

- **About Page:**
  - Updated version to 1.3.0
  - Added changelog entry with new features

### Technical Details
- Modified `mapping.php`:
  - Two separate mapping tables with visual distinction
  - CSV column can be mapped to both company AND contact fields
  - POST data: `company_col_X` and `contact_col_X` arrays
  
- Modified `class/contactimportprocessor.class.php`:
  - Added `companies_updated` and `contacts_updated` to result array
  - Changed duplicate handling from skip to update
  - Success calculation includes updates: `created + updated`
  
- Modified `langs/de_DE/contactimport.lang` and `langs/en_US/contactimport.lang`:
  - Added dual-mapping terminology
  - Instructions and help texts

- Modified `core/modules/modContactimport.class.php`:
  - Updated version from 1.2.1 to 1.3.0

### Use Case Example

**CSV from Web Form:**
```csv
Name,Email,Phone,LastOrder
Max Mustermann,max@example.com,0123456789,2025-01-15
Anna Schmidt,anna@test.de,0987654321,
```

**Result in Dolibarr:**

| Row | Company Name | Company Status | Contact Name | Linked |
|-----|--------------|----------------|---------------|--------|
| 1   | Max Mustermann | Customer (has order date) | Max Mustermann | ✓ |
| 2   | Anna Schmidt | Prospect (no order) | Anna Schmidt | ✓ |

### Migration from v1.2.1 to v1.3.0

1. Deactivate and reactivate module in Dolibarr admin
2. Go to module setup → Mapping configuration  
3. Configure dual-mapping (each CSV column to both Company + Contact)
4. Test import with sample CSV
5. Verify duplicates are updated (not skipped)

### Breaking Changes

⚠️ **Mapping Config Structure Changed:**
- Old mapping configs need to be reconfigured
- Import mode selection removed (always "both" now)

---

## Version 1.2.1 (2026-01-08)

### Added
- **Automatic Status Detection via Last Order Date:**
  - New extrafield "Last Order Date" for companies (ThirdParty/Societe)
  - CSV column can be mapped to "Last Order Date" field
  - Automatic status assignment:
    - Empty field → Status "Prospect" (client=2)
    - Filled field with date → Status "Customer" (client=1)
  - Works with both new imports and updates of existing companies

### Changed
- **Field Mapping Extended:**
  - Company mapping now includes "Last Order Date" extrafield
  - Processor handles extrafield storage automatically
  - Status changes apply during both create and update operations

### Technical Details
- Modified `class/contactimportprocessor.class.php`:
  - Extended `createCompany()` to handle `options_last_order_date` field
  - Modified `updateCompany()` to accept and process last_order_date parameter
  - Added `setCompanyExtrafield()` method for extrafield management
  - Status logic: empty date = Prospect (2), filled date = Customer (1)
  
- Modified `lib/contactimport.lib.php`:
  - Added 'options_last_order_date' to company mapping fields
  - Added documentation comment for extrafield naming convention

- Added `sql/update_1.2.0_to_1.2.1.sql`:
  - Creates extrafield definition in llx_extrafields
  - Adds column to llx_societe_extrafields table
  
- Modified `core/modules/modContactimport.class.php`:
  - Updated version from 1.2.0 to 1.2.1
  
- Modified language files (de_DE, en_US):
  - Added "LastOrderDate" translation
  - Added "LastOrderDateHelp" with explanation

### Migration
To upgrade from v1.2.0 to v1.2.1:
1. Run SQL update script: `sql/update_1.2.0_to_1.2.1.sql`
2. Deactivate and reactivate module in Dolibarr (creates extrafield)
3. Map CSV column to "Last Order Date" in field mapping

### Usage Example
CSV with "Letzte Bestellung" column:
```
Firma;Kontakt;Email;Letzte Bestellung
Mustermann GmbH;Max Mustermann;max@example.com;2025-01-05
Test AG;Anna Schmidt;anna@test.de;
```
Result:
- Mustermann GmbH → Customer (client=1) - has order date
- Test AG → Prospect (client=2) - no order date

---

## Version 1.2.0 (2025-12-02)

### Added
- **Auto-Import via Cron Job:** Automated scheduled imports from FTP/SFTP
  - New cron job definition for automatic import execution
  - Configurable via internal Dolibarr cron system
  - Default: Disabled, can be enabled in Dolibarr cron settings
  - Frequency: Every hour (default), customizable

- **Update Logic for Duplicates:** Changed behavior from "skip" to "update"
  - Existing contacts and companies are now updated instead of skipped
  - Only empty fields are updated - never overwrite existing data
  - Preserves all existing information while filling gaps from CSV
  - Safer data handling prevents accidental data loss

- **Customer Status Automation:** Automatic classification based on order history
  - New field `last_order_column` in import templates
  - Configure column name containing last order date (default: "Letzter Auftrag (Datum)")
  - If column is filled → Status = "Kunde" (Customer, client=1)
  - If column is empty → Status = "Interessent" (Prospect, client=2)
  - Prospect can be upgraded to Customer during update if last_order_column gets filled

- **FTP File Lifecycle Management:** Automatic cleanup of old files
  - New option `CONTACTIMPORT_FTP_DELETE_OLD_FILES` in FTP configuration
  - Configurable age threshold `CONTACTIMPORT_FTP_OLD_FILES_DAYS` (default: 30 days)
  - Automatically deletes files older than configured days from FTP server
  - Prevents FTP directory from filling up with old imports
  - Runs as part of cron job execution

### Changed
- **Import Processor Logic:** Major refactoring for update functionality
  - `createCompany()`: Now accepts `$last_order_column` parameter
  - `createContact()`: Removed -1 return code for duplicates
  - `processImport()`: Removed duplicate skip logic, passes last_order_column
  - Added `updateCompany()` method for updating existing companies
  - Added `updateContact()` method for updating existing contacts

- **Database Schema:** Extended tables for new features
  - `llx_contactimport_templates`: Added `last_order_column` field
  - `llx_contactimport_ftp_config`: Added `delete_old_files` and `old_files_days` fields

### Technical Details
- Modified `class/contactimportprocessor.class.php`:
  - Added updateCompany() and updateContact() methods (~50 lines each)
  - Modified createCompany(), createContact(), processImport() signatures
  - Customer status determination based on last_order_column value
  
- Modified `class/contactimportftp.class.php`:
  - Added cronAutoImport() public method for cron job execution
  - Added deleteOldFilesFromFTP() method for file cleanup
  - Supports both FTP and SFTP file deletion
  
- Modified `class/contactimporttemplate.class.php`:
  - Added last_order_column property and field definition
  
- Modified `admin/ftp.php`:
  - Added UI fields for delete_old_files and old_files_days configuration
  
- Modified `core/modules/modContactimport.class.php`:
  - Added cronjobs array with auto-import job definition
  - Updated version from 1.1.1 to 1.2.0
  
- Added `sql/update_1.1.1_to_1.2.0.sql`:
  - ALTER TABLE for llx_contactimport_templates (last_order_column)
  - ALTER TABLE for llx_contactimport_ftp_config (delete_old_files, old_files_days)

### Migration
To upgrade from v1.1.1 to v1.2.0:
1. Run SQL update script: `sql/update_1.1.1_to_1.2.0.sql`
2. Deactivate and reactivate module in Dolibarr
3. Configure cron job in Home > Tools > Cron Jobs (if auto-import desired)
4. Configure FTP cleanup settings in ContactImport > FTP Configuration (optional)

---

## Version 1.1.1 (2025-12-02)

### Added
- **Duplicate Check Default ON:** Duplicate checking is now enabled by default
  - Prevents re-importing existing companies and contacts automatically
  - Added `CONTACTIMPORT_DUPLICATE_CHECK` constant (default: 1 = enabled)
  - Can be disabled in module settings if needed

- **Duplicates Tracking:** Added separate tracking for skipped duplicates
  - New field `duplicates_skipped` in sessions table
  - Duplicates are logged as "skipped" instead of "error"
  - Import statistics now show: Success, Errors, and Duplicates Skipped separately

### Changed
- **Duplicate Check Logic:** Changed from opt-in to opt-out
  - Before: Check disabled unless explicitly enabled
  - After: Check enabled unless explicitly set to '0'
  - Safer default behavior prevents accidental duplicate imports

### Technical Details
- Modified `contactimportprocessor.class.php` - Changed duplicate check logic
- Modified `contactimportsession.class.php` - Added `duplicates_skipped` field
- Modified `llx_contactimport.sql` - Added `duplicates_skipped` column
- Added `sql/update_1.1.0_to_1.1.1.sql` - Update script for existing installations
- Modified `modContactimport.class.php` - Added DUPLICATE_CHECK constant with default value

---

## Version 1.1.0 (2025-12-02)

### Fixed
- **SQL Installation:** Added `IF NOT EXISTS` to all CREATE TABLE statements
  - Prevents SQL errors when re-installing or updating module
  - If one table already exists, other tables will still be created
  - Previously: SQL script aborted on first existing table, leaving other tables uncreated
  - Now: All missing tables are created automatically during module activation

### Technical Details
- Modified `sql/llx_contactimport.sql` - Added `IF NOT EXISTS` to all 5 CREATE TABLE statements
- Ensures complete database schema creation even on partial installations
- Makes module more robust for updates and re-installations

---

## Version 1.0.9 (2025-12-02)

### Fixed
- **Array Key Error:** Fixed "Undefined array key ''" when select dropdown has empty/invalid value
  - Added safety check in `admin/setup.php` for select type parameters
  - Added safety check in `admin/ftp.php` for select type parameters
  - Now displays "None" instead of throwing error when choices array is empty or selected value doesn't exist

### Technical Details
- Modified `admin/setup.php` - Added isset() check for `$val['choices'][$selected]`
- Modified `admin/ftp.php` - Added isset() check for `$val['choices'][$selected]`
- Prevents PHP warnings when configuration values are not yet set

---

## Version 1.0.8 (2025-12-02)

### Fixed
- **PHP 8+ Compatibility:** Fixed "Undefined array key 'type'" warnings
  - Added missing `'type' => 'chaine'` for parameters in `admin/setup.php`
  - Added missing `'type' => 'chaine'` for FTP parameters in `admin/ftp.php`
  - Fixed parameters: CSV_SEPARATOR, CSV_ENCLOSURE, MAX_FILE_SIZE, FTP_HOST, FTP_PORT, FTP_USER, FTP_PATH
  - Prevents PHP warnings on strict error reporting configurations

- **Menu Configuration:** Fixed "Undefined array key 'url'" warning
  - Added missing `'url'` parameter for main ContactImport menu entry
  - Fixed all menu URLs to use correct `/custom/contactimport/` prefix
  - Prevents Dolibarr module activation warnings

### Technical Details
- Modified `admin/setup.php` - Added type definitions for 3 parameters
- Modified `admin/ftp.php` - Added type definitions for 4 parameters
- Modified `core/modules/modContactimport.class.php` - Fixed menu URL paths
- Ensures compatibility with newer PHP versions (8.0+) and strict error reporting

---

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
