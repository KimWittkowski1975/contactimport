# ContactImport Module - Vollständige Zusammenfassung

## 📊 Statistiken

### Code-Umfang
- **Gesamt:** 6.910 Zeilen Code
- **PHP:** 6.778 Zeilen (98%)
- **SQL:** 132 Zeilen (2%)
- **Sprachen:** 343 Zeilen (Deutsch + Englisch)
- **Dokumentation:** 366 Zeilen (Markdown)

### Dateistruktur
- **22 PHP-Dateien** (Admin: 3, Classes: 4, Main: 10, Scripts: 2, Lib: 3)
- **1 SQL-Datei** (Datenbankschema)
- **2 Sprachdateien** (Deutsch/Englisch)
- **3 Dokumentationsdateien**

---

## 🎯 Modulübersicht

**ContactImport** ist ein vollständiges Dolibarr-Modul für den automatischen und manuellen Import von Kontakten und Unternehmen aus CSV-Dateien.

### Version & Lizenz
- **Version:** 1.0
- **Lizenz:** GPL v3
- **Autor:** Kim Wittkowski (kim.wittkowski@gmx.de)
- **Dolibarr-Kompatibilität:** 21.0+

---

## 🌟 Hauptfunktionen

### 1. **Manueller CSV-Import**
- Drag & Drop CSV-Upload
- Live-Vorschau der CSV-Daten
- Flexible Spalten-zu-Feld-Zuordnung
- Unterstützung für Unternehmen und/oder Kontakte
- Duplikatsprüfung
- Detaillierte Fehlerprotokollierung

### 2. **Automatischer FTP/SFTP-Import**
- Automatischer Download von FTP/SFTP-Server
- Templatebasierte Imports
- Konfigurierbares Sync-Intervall
- Zwei Cron-Optionen:
  - **Echter Cron-Job** (empfohlen)
  - **Pseudo-Cron** (für Shared-Hosting ohne Cron-Zugriff)

### 3. **Template-System**
- Wiederverwendbare Import-Vorlagen
- Standard-Template für Auto-Import
- CSV-Einstellungen pro Template (Separator, Enclosure, Header)
- JSON-basierte Mapping-Konfiguration

### 4. **Import-Session-Management**
- Vollständige Session-Historie
- Detaillierte Statistiken (Erfolge/Fehler)
- Status-Tracking (Upload → Mapped → Processing → Completed)
- Export und Wiederherstellung von Sessions

### 5. **Logging & Monitoring**
- Zeilen-basiertes Logging
- Fehler- und Erfolgsprotokollierung
- Filterbare Log-Ansichten
- CSV-Export von Logs

---

## 📁 Detaillierte Dateistruktur

### Admin-Bereich (1.273 Zeilen)
```
admin/
├── setup.php (274 Zeilen)       # Modul-Konfiguration
├── ftp.php (360 Zeilen)         # FTP/SFTP-Einstellungen
└── templates.php (639 Zeilen)   # Template-Verwaltung mit FTP-Download/Import
```

**Features:**
- Zentrale Modulkonfiguration
- FTP/SFTP-Verbindungstest
- Template CRUD-Operationen
- CSV-Sample-Upload
- Manuelle FTP-Download & Import-Funktion

### Kern-Klassen (1.613 Zeilen)

#### 1. **ContactImportSession** (553 Zeilen)
- Session-Verwaltung (CRUD)
- Status-Tracking
- Mapping-Konfiguration
- Statistik-Berechnung
- Export/Import-Funktionen

#### 2. **ContactImportProcessor** (584 Zeilen)
- CSV-Parsing
- Datenvalidierung
- Unternehmen-Erstellung
- Kontakt-Erstellung mit Unternehmensverknüpfung
- Fehlerbehandlung
- Transaktions-Management

#### 3. **ContactImportTemplate** (198 Zeilen)
- Template CRUD-Operationen
- Standard-Template-Management
- CSV-Einstellungs-Speicherung
- Mapping-Konfiguration

#### 4. **ContactImportFTP** (278 Zeilen)
- FTP/SFTP-Verbindung
- Datei-Download
- Auto-Import-Workflow
- Verbindungstest
- Fehlerbehandlung

### Haupt-Seiten (2.625 Zeilen)

#### Import-Workflow-Seiten:
- **upload.php** (365 Zeilen) - CSV-Upload mit Drag & Drop
- **preview.php** (319 Zeilen) - CSV-Datenvorschau
- **mapping.php** (415 Zeilen) - Spalten-zu-Feld-Zuordnung
- **process.php** (283 Zeilen) - Import-Verarbeitung
- **import.php** (217 Zeilen) - Import-Historie

#### Session-Management:
- **session.php** (341 Zeilen) - Session-Detailansicht
- **logs.php** (420 Zeilen) - Detaillierte Log-Ansicht

#### Zusätzliche Features:
- **download.php** (118 Zeilen) - Session-Export

### Automatisierung (237 Zeilen)

#### Cron-Scripts:
```
scripts/
├── cron_import.sh (12 Zeilen)        # Bash-Wrapper
├── cron_import.php (106 Zeilen)      # Original CLI-Script
└── cron_import_cli.php (76 Zeilen)   # Vereinfachtes CLI-Script
```

#### Pseudo-Cron (für Shared-Hosting):
```
lib/
├── pseudo_cron.lib.php (80 Zeilen)      # WordPress-style Pseudo-Cron
└── pseudo_cron_hook.php (15 Zeilen)     # Auto-Include Hook
```

**Features:**
- Dynamisches Intervall aus Konfiguration
- Lock-Mechanismus gegen parallele Ausführung
- Timestamp-basierte Zeitprüfung
- Fehlerprotokollierung

---

## 🗄️ Datenbankstruktur (132 Zeilen SQL)

### Tabellen:

#### 1. **llx_contactimport_sessions**
- Session-Verwaltung
- File-Informationen
- CSV-Einstellungen
- Mapping-Konfiguration
- Status & Statistiken
- Timestamps

#### 2. **llx_contactimport_logs**
- Zeilen-basiertes Logging
- Import-Typ (company/contact)
- Status (success/error)
- Fehler-Nachrichten
- Verknüpfung zu erstellten Objekten

#### 3. **llx_contactimport_templates**
- Template-Verwaltung
- CSV-Einstellungen
- Mapping-Konfiguration
- Standard-Template-Flag
- Entity-Support

**Indizes:** Optimiert für schnelle Abfragen nach Session, Status, Entity

---

## 🌐 Mehrsprachigkeit (343 Zeilen)

### Sprachen:
- **Deutsch** (212 Zeilen) - Vollständig
- **Englisch** (131 Zeilen) - Vollständig

### Übersetzungsbereiche:
- Menü-Einträge
- Formular-Labels
- Fehler-Meldungen
- Status-Texte
- Hilfe-Tooltips
- Admin-Interface
- FTP-Konfiguration
- Template-System

---

## 📚 Dokumentation (366 Zeilen)

### 1. **README.md** (144 Zeilen)
- Modul-Übersicht
- Feature-Liste
- Installation
- Basis-Verwendung
- Konfiguration

### 2. **CRON_INSTALLATION.md** (115 Zeilen)
- Cron-Job-Installation
- Konfigurationsoptionen
- Beispiele für verschiedene Intervalle
- Troubleshooting
- Log-Überwachung

### 3. **PSEUDO_CRON.md** (107 Zeilen)
- Alternative für Shared-Hosting
- Installation & Aktivierung
- Funktionsweise
- Performance-Überlegungen
- Vergleich mit echtem Cron

---

## 🔧 Technische Features

### CSV-Verarbeitung
- **Separatoren:** `,`, `;`, `\t`, `|`
- **Enclosure:** `"`, `'`, oder keine
- **Header:** Optional
- **Encoding:** UTF-8
- **Max. Dateigröße:** Konfigurierbar

### Feldmapping
- **Unternehmen:** Name, Adresse, PLZ, Stadt, Land, Telefon, Email, USt-IdNr.
- **Kontakte:** Vorname, Nachname, Position, Email, Telefon, Mobilnummer
- **Flexibel:** Beliebige CSV-Spalten zuordnen

### Duplikatsprüfung
- Nach Unternehmensname
- Nach Kontakt-Email
- Konfigurierbar ein/aus

### Import-Modi
1. **Nur Unternehmen**
2. **Nur Kontakte**
3. **Beide** (Kontakte werden Unternehmen zugeordnet)

### Fehlerbehandlung
- Try-Catch auf allen Ebenen
- Detaillierte Fehlermeldungen
- Rollback bei kritischen Fehlern
- Partial Success (einige Zeilen ok, andere fehlerhaft)

---

## 🚀 Workflow-Diagramm

### Manueller Import:
```
1. Upload CSV → 
2. Preview → 
3. Mapping → 
4. Process → 
5. History/Logs
```

### Automatischer Import:
```
1. Cron läuft (stündlich) →
2. Prüft Intervall →
3. Download FTP →
4. Auto-Import mit Template →
5. Logs speichern
```

### Pseudo-Cron:
```
1. Seitenaufruf (1% Chance) →
2. Prüft Intervall →
3. Download FTP →
4. Auto-Import →
5. Lock-File verwalten
```

---

## 💡 Besonderheiten

### 1. **Zwei-Spalten-Mapping-Interface**
- CSV-Spalten links
- Dolibarr-Felder rechts
- Übersichtliche Zuordnung
- Identisch für manuelle & Template-Imports

### 2. **Intelligentes Session-Management**
- Automatische Status-Updates
- Session-Export für Backup
- Session-Wiederherstellung
- Detaillierte Statistiken

### 3. **Flexible FTP-Integration**
- FTP und SFTP
- Passiv-Modus
- Custom Port
- File-Pattern-Matching
- Auto-Delete nach Import

### 4. **Template-System**
- Standard-Template für Auto-Import
- CSV-Einstellungen pro Template
- Wiederverwendbar
- JSON-basierte Konfiguration

### 5. **Dual-Cron-System**
- Echter Cron für Server
- Pseudo-Cron für Shared-Hosting
- Beide nutzen gleiche Konfiguration
- Dynamisches Intervall

---

## 📊 Performance & Skalierung

### Optimierungen:
- Transaktions-Management
- Batch-Verarbeitung
- Lock-Mechanismus bei Pseudo-Cron
- Indizierte Datenbank-Abfragen
- Minimale Session-Daten

### Limits:
- CSV-Größe: Abhängig von PHP-Konfiguration
- Gleichzeitige Imports: 1 (Lock-Mechanismus)
- Pseudo-Cron Overhead: <1% bei 1% Trigger-Rate

---

## 🔐 Sicherheit

- Dolibarr-Rechtesystem integriert
- CSRF-Protection
- SQL-Injection-Schutz (prepared statements wo möglich)
- File-Upload-Validierung
- Sichere FTP/SFTP-Verbindungen
- Lock-Files gegen Race-Conditions

---

## 🎨 Benutzerfreundlichkeit

- Drag & Drop Upload
- Live-CSV-Vorschau
- Klare Fortschrittsanzeige
- Detaillierte Fehlermeldungen
- Hilfe-Tooltips
- Deutsche & Englische Interface
- Responsive Design (Dolibarr-Standard)

---

## 🔮 Erweiterungsmöglichkeiten

### Bereits vorbereitet:
- Zusätzliche Feldtypen
- Custom-Validierungsregeln
- Weitere Import-Modi
- API-Integration
- Webhook-Benachrichtigungen

### Einfach hinzufügbar:
- Weitere Sprachen
- Zusätzliche CSV-Formate
- Custom-Mapping-Regeln
- Import-Templates exportieren/importieren
- Automatische Duplikat-Zusammenführung

---

## 📝 Zusammenfassung

Das **ContactImport Module** ist ein vollständig ausgearbeitetes, produktionsreifes Dolibarr-Modul mit:

✅ **6.910 Zeilen** professionellem Code
✅ **Vollständige Mehrsprachigkeit** (DE/EN)
✅ **Zwei Automatisierungsoptionen** (Cron & Pseudo-Cron)
✅ **Flexible Import-Workflows**
✅ **Template-System** für Wiederverwendbarkeit
✅ **Detailliertes Logging & Monitoring**
✅ **FTP/SFTP-Integration**
✅ **Umfangreiche Dokumentation**
✅ **Best Practices** für Dolibarr-Module
✅ **Skalierbar & Wartbar**

**Ideal für:**
- Unternehmen mit regelmäßigen Kontakt-Importen
- JTL-Kunden-Synchronisation
- ERP-zu-ERP-Datenmigration
- CSV-basierte Datenpflege
- Automatisierte Workflows

---

## 📞 Support & Kontakt

**Entwickler:** Kim Wittkowski  
**Email:** kim.wittkowski@gmx.de  
**Lizenz:** GNU GPL v3  
**Repository:** /custom/contactimport

---

*Erstellt: Oktober 2025 | Dolibarr 22.0.1*
