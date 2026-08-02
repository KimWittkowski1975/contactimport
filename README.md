# ContactImport Module für Dolibarr

**Version 1.4.0** - siehe [changelog.md](changelog.md) für die vollständige Versionshistorie

## Beschreibung

Das ContactImport-Modul ermöglicht den Import von Kontaktdaten aus CSV-Dateien und erstellt automatisch **sowohl eine Firma (Company) als auch einen zugehörigen Kontakt (Contact)** aus jeder CSV-Zeile. Dies löst das häufige Problem, dass Webformular-Kontakte als vollständige Firmen-Kontakt-Paare in Dolibarr benötigt werden.

## Hauptfunktionen (v1.3.0)

### 🎯 Dual-Mapping System
- **Ein CSV-Datensatz = Firma + Kontakt**: Jede Zeile erstellt automatisch beides
- **Doppelte Zuordnung**: Gleiche CSV-Spalte kann gleichzeitig Firmen- UND Kontaktfeld befüllen
- **Visuelle Mapping-Oberfläche**: Zwei übersichtliche Tabellen für Company und Contact Mapping
- **Beispieldaten-Vorschau**: Sehen Sie sofort, welche Daten wo landen

### 🔄 Intelligente Duplikat-Behandlung
- **Update statt Überspringen**: Bestehende Datensätze werden aktualisiert
- **Nur leere Felder füllen**: Vorhandene Daten werden nie überschrieben
- **Automatisches Status-Upgrade**: Prospect → Customer bei gefülltem "Letzter Auftrag" Datum

### 📊 Automatische Status-Verwaltung
- **Letzter Auftrag (Datum)** als ExtraField für Firmen
- **Prospect** (Interessent): Wenn Feld leer  
- **Customer** (Kunde): Wenn Datum vorhanden
- **Automatisches Upgrade**: Beim Import einer aktualisierten CSV

## Features

- **CSV Upload**: Upload CSV files with customizable separators and encoding
- **Flexible Field Mapping**: Map CSV columns to Dolibarr company and contact fields
- **Automatic Status Detection**: Set customer status based on "Last Order Date" field
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
3. **FTP Configuration**: FTP-Server für automatische Downloads

## Verwendung

### 🚀 Schnellstart

Das ContactImport-Modul erstellt automatisch **Firma + Kontakt Paare** aus jeder CSV-Zeile. Perfect für Webformular-Kontakte!

**Beispiel CSV:**
```csv
Name,Email,Telefon,Letzter Auftrag
Max Mustermann,max@example.com,0123456789,2025-01-15
Anna Schmidt,anna@test.de,0987654321,
```

**Resultat in Dolibarr:**
- Firma "Max Mustermann" (Customer) mit Kontakt "Max Mustermann"
- Firma "Anna Schmidt" (Prospect) mit Kontakt "Anna Schmidt"

### 1. CSV-Datei hochladen

1. Gehen Sie zu **Tools → Contact Import → CSV Upload**
2. Wählen Sie Ihre CSV-Datei aus
3. Konfigurieren Sie CSV-Parameter (Trennzeichen, Anführungszeichen)
4. Geben Sie eine Beschreibung ein
5. Klicken Sie auf "Upload"

### 2. Dual-Mapping konfigurieren (NEU in v1.3.0)

Nach dem Upload sehen Sie zwei Mapping-Tabellen:

#### Company Mapping (oben)
Ordnen Sie CSV-Spalten den Firmen-Feldern zu:
- Name → Company Name
- Email → Company Email
- Telefon → Company Phone
- Letzter Auftrag → Last Order Date

#### Contact Mapping (unten)
Ordnen Sie die GLEICHEN CSV-Spalten den Kontakt-Feldern zu:
- Name → Contact Lastname
- Email → Contact Email
- Telefon → Contact Mobile

**Wichtig:** Die gleiche CSV-Spalte kann BEIDE Tabellen befüllen!

### 3. Import starten

1. Überprüfen Sie die Datenvorschau
2. Klicken Sie "Save Mapping"
3. Bestätigen Sie den Import
4. Fertig! Bei erneutem Import der gleichen Daten werden nur leere Felder aktualisiert.

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
- **Letzte Bestellung** (Extrafeld) - Automatische Kundenstatus-Erkennung:
  - Wenn leer: Status = "Interessent" (Prospect)
  - Wenn Datum vorhanden: Status = "Kunde" (Customer)
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
- Bestehende Datensätze werden **aktualisiert statt übersprungen** (nur leere Felder werden befüllt, vorhandene Daten bleiben unverändert)
- Im Import-Log wird die Duplikat-ID angezeigt
- Separate Statistik zeigt Anzahl aktualisierter Datensätze
- Status: `updated` statt `error` im Protokoll

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

Die FTP-Konfiguration wird über `llx_const` (Konstanten `CONTACTIMPORT_FTP_*`) gespeichert, nicht über eine eigene Tabelle.

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

### Geplante Features
- Export von Import-Ergebnissen

FTP-Integration, Import-Templates und zeitgesteuerte Imports per (Pseudo-)Cron sind bereits implementiert, siehe [changelog.md](changelog.md) und [docs/PSEUDO_CRON.md](docs/PSEUDO_CRON.md).

## Support

Bei Fragen oder Problemen wenden Sie sich an:
- **Entwickler**: Kim Wittkowski
- **E-Mail**: kim@nexor.de
- **Website**: https://www.nexor.de

## Lizenz

Dieses Modul steht unter der GNU General Public License v3.0.

## Beitrag

Beiträge zum Projekt sind willkommen! Bitte erstellen Sie Issues oder Pull Requests auf unserem Repository.

---

**Modulnummer**: 918399  
**Kategorie**: Interface  
**Kompatibilität**: Dolibarr 21.0+