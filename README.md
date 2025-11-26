# ContactImport Module für Dolibarr

## Beschreibung

Das ContactImport-Modul ermöglicht den Import von Firmen- und Kontaktdaten aus CSV-Dateien in Dolibarr. Es löst das Problem, dass CSV-Dateien oft Firmen- und Kontaktdaten in einer Zeile enthalten, während Dolibarr diese in separaten Tabellen verwaltet.

## Features

- **CSV Upload**: Upload CSV files with customizable separators and encoding
- **Flexible Field Mapping**: Map CSV columns to Dolibarr company and contact fields
- **Duplicate Detection**: Automatic detection and skipping of existing records
- **Data Preview**: Preview imported data before processing
- **Import Processing**: Automatic creation of companies and contacts
- **Session Management**: Track and manage multiple import sessions
- **Detailed Logging**: Complete audit trail of all import operations
- **File Management**: Secure download of uploaded CSV files
- **Delete Functionality**: Remove unwanted import sessions and associated files
- **Duplicate Management**: Find, merge, or delete duplicate companies and contacts
- **Multi-language Support**: Available in German and English

## Installation

1. Extrahieren Sie das Modul-Archiv in das `/custom/` Verzeichnis Ihrer Dolibarr-Installation:
   ```
   /dolibarr/htdocs/custom/contactimport/
   ```

2. Gehen Sie zu **Home → Setup → Modules** in Dolibarr

3. Suchen Sie nach "ContactImport" und aktivieren Sie das Modul

4. Das Modul erstellt automatisch die benötigten Datenbanktabellen

## Konfiguration

Nach der Aktivierung können Sie das Modul unter **Tools → Contact Import** konfigurieren:

1. **Setup**: Grundkonfiguration (CSV-Trennzeichen, Dateigröße, etc.)
2. **Templates**: Wiederverwendbare Mapping-Vorlagen
3. **FTP Configuration**: FTP-Server für automatische Downloads (wird später implementiert)

## Verwendung

### 1. CSV-Datei hochladen

1. Gehen Sie zu **Tools → Contact Import → CSV Upload**
2. Wählen Sie Ihre CSV-Datei aus
3. Konfigurieren Sie CSV-Parameter (Trennzeichen, Anführungszeichen)
4. Geben Sie eine Beschreibung ein
5. Klicken Sie auf "Upload"

### 2. Feldmapping definieren

1. Nach dem Upload werden Sie automatisch zum Mapping-Interface weitergeleitet
2. Definieren Sie den Import-Modus (Firmen, Kontakte oder beides)
3. Ordnen Sie CSV-Spalten den entsprechenden Dolibarr-Feldern zu:
   - **Firmenfelder**: Name, Adresse, Telefon, E-Mail, etc.
   - **Kontaktfelder**: Vorname, Nachname, Position, Telefon, etc.
4. Speichern Sie das Mapping

### 3. Datenvorschau und Import

1. Überprüfen Sie die Vorschau Ihrer zu importierenden Daten
2. Bestätigen Sie den Import
3. Überwachen Sie den Fortschritt und die Ergebnisse

## Unterstützte CSV-Formate

- **Trennzeichen**: `;` (Standard), `,`, `|`, Tab
- **Anführungszeichen**: `"` (Standard), `'`, keine
- **Kodierung**: UTF-8 empfohlen
- **Dateiformate**: `.csv`, `.txt`
- **Maximale Dateigröße**: 10 MB (konfigurierbar)

## Feldmapping

### Firmenfelder
- Firmenname (erforderlich)
- Adresse, PLZ, Ort, Land
- Telefon, Fax, E-Mail, Website
- Steuernummern (SIREN, SIRET, etc.)
- Firmentyp, Kundennummer
- Öffentliche/Private Notizen

### Kontaktfelder
- Nachname (erforderlich), Vorname
- Anrede, Position
- Adresse, PLZ, Ort, Land
- Telefon (Büro, Privat, Mobil), Fax
- E-Mail, Geburtstag
- Öffentliche/Private Notizen

## Duplikat-Erkennung

Das Modul verhindert automatisch das Importieren von bereits vorhandenen Daten:

### Firmen-Duplikatsprüfung
1. **Primär**: Name + PLZ + Ort (exakte Übereinstimmung)
2. **Sekundär**: E-Mail-Adresse (falls vorhanden)
3. **Tertiär**: SIREN/SIRET (für französische Firmen)

### Kontakt-Duplikatsprüfung
1. **Primär**: E-Mail-Adresse (höchste Priorität)
2. **Sekundär**: Nachname + Vorname + Firma (kombinierte Prüfung)
3. **Tertiär**: Mobilnummer (falls vorhanden)

### Verhalten bei Duplikaten
- Duplikate werden **automatisch übersprungen**
- Im Import-Log wird die Duplikat-ID angezeigt
- Separate Statistik zeigt Anzahl übersprungener Datensätze
- Status: `skipped` statt `error` im Protokoll

### Duplikat-Verwaltung
Im Admin-Bereich unter **Duplikate Verwalten** können Sie:
- Identische Duplikate finden (100% Übereinstimmung)
- Ähnliche Duplikate finden (Name gleich, Details unterschiedlich)
- Duplikate zusammenführen (Master behält alle Daten)
- Duplikate löschen (Master wird automatisch geschützt)
- Protokoll aller Duplikat-Aktionen einsehen

## Technische Details

### Systemanforderungen
- Dolibarr 21.0+
- PHP 7.4+
- MySQL/MariaDB

### Datenbanktabellen
- `llx_contactimport_sessions`: Import-Sitzungen
- `llx_contactimport_logs`: Detaillierte Import-Logs  
- `llx_contactimport_templates`: Wiederverwendbare Templates
- `llx_contactimport_duplicate_logs`: Duplikat-Aktionen Protokoll
- `llx_contactimport_ftp_config`: FTP-Konfiguration (geplant)

### Berechtigungen
- `contactimport:read`: Zugriff auf Import-Historie
- `contactimport:write`: CSV-Upload und Import durchführen
- `contactimport:admin`: Modul-Administration

## Changelog

### Version 1.1.0 (2025-10-19)
- **Neu**: Automatische Duplikat-Erkennung beim Import
  - Prüfung auf bestehende Firmen (Name, PLZ, Ort, E-Mail, SIREN)
  - Prüfung auf bestehende Kontakte (E-Mail, Name + Firma, Mobilnummer)
  - Duplikate werden übersprungen und in Logs gekennzeichnet
  - Separate Statistik für übersprungene Duplikate
- **Neu**: Duplikat-Verwaltung in Admin-Bereich
  - Identische und ähnliche Duplikate erkennen
  - Firmen und Kontakte zusammenführen oder löschen
  - Protokollierung aller Duplikat-Aktionen
- **Neu**: Master-Schutz - ältester Datensatz wird als Master markiert
- **Verbessert**: UTF-8 Encoding-Konvertierung für CSV-Import
- **Verbessert**: Auto-Generierung von Firmennamen aus Kontaktdaten
- **Bugfix**: FTP Auto-Import Boolean-Check korrigiert
- **Bugfix**: SQL-Queries in Logs korrigiert
- **Bugfix**: Array-Index Mapping für CSV-Spalten

### Version 1.0.0 (2025-01-15)
- Erste Veröffentlichung
- CSV-Upload-Funktionalität
- Flexibles Feldmapping
- Import von Firmen und Kontakten
- Mehrsprachiger Support (DE/EN)
- Detaillierte Import-Protokollierung



