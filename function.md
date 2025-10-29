# ContactImport Modul - Funktionsbeschreibung und Ablauf

## Übersicht
Das ContactImport-Modul ermöglicht den automatischen Import von Firmen- und Kontaktdaten aus CSV-Dateien in Dolibarr. Es unterstützt sowohl manuelle Uploads als auch automatische FTP-Downloads mit zeitgesteuerter Verarbeitung.

---

## Ablauf des Imports

### 1. Setup FTP-Server (Optional)
**Navigation:** Tools → Contact Import → FTP-Konfiguration

#### Zweck
Automatischer Download von CSV-Dateien von einem FTP/SFTP-Server zu festgelegten Zeiten.

#### Konfigurationsschritte
1. **FTP-Verbindung einrichten:**
   - Host/IP-Adresse des FTP-Servers
   - Port (Standard: 21 für FTP, 22 für SFTP)
   - Protokoll wählen (FTP oder SFTP)
   - Benutzername und Passwort

2. **Download-Verzeichnis festlegen:**
   - Remote-Verzeichnis auf dem FTP-Server
   - Lokales Zielverzeichnis (Standard: `/documents/contactimport/temp/`)

3. **Zeitsteuerung konfigurieren:**
   - Auto-Download aktivieren
   - Intervall festlegen (z.B. stündlich, täglich)
   - Cron-Job einrichten für automatische Ausführung

4. **Import-Template auswählen:**
   - Wähle das Template, das für heruntergeladene Dateien verwendet werden soll
   - Auto-Import aktivieren (importiert Dateien automatisch nach Download)

#### Funktionsweise
- Der Cron-Job prüft in festgelegten Intervallen den FTP-Server
- Neue CSV-Dateien werden heruntergeladen
- Bei aktiviertem Auto-Import werden Dateien sofort verarbeitet
- Verarbeitete Dateien können optional auf dem FTP-Server gelöscht werden

---

### 2. Erstellen eines Templates
**Navigation:** Tools → Contact Import → Templates

#### Zweck
Wiederverwendbare Mapping-Konfigurationen für unterschiedliche CSV-Formate erstellen.

#### Schritte zur Template-Erstellung

##### 2.1 CSV-Beispieldatei hochladen
1. Klicke auf "Neues Template erstellen"
2. Gib Template-Name und Beschreibung ein
3. Lade eine **Beispiel-CSV-Datei** hoch
   - Sollte die gleiche Struktur wie spätere Import-Dateien haben
   - Header-Zeile wird automatisch erkannt

##### 2.2 CSV-Parameter festlegen
- **CSV-Trennzeichen:** `;` (Semikolon), `,` (Komma), `|` (Pipe), Tab
- **CSV-Anführungszeichen:** `"` (Standard), `'` (Apostroph), keine
- **Header-Zeile vorhanden:** Ja/Nein
- **Standard-Template:** Als Standardvorlage markieren

##### 2.3 Import-Modus wählen
Wähle, was importiert werden soll:

**Option 1: Firmen und Kontakte** (Standard)
- Erstellt sowohl Firmen als auch zugehörige Kontakte
- **Pflichtfelder:** 
  - Firmenname (nom)
  - Kontakt-Nachname (lastname)

**Option 2: Nur Firmen**
- Importiert ausschließlich Firmendaten
- **Pflichtfeld:** Firmenname (nom)
- Kontaktfelder werden ignoriert

**Option 3: Nur Kontakte**
- Importiert Kontakte mit automatischer Firmenerstellung
- **Pflichtfelder:** 
  - Firmenname (nom) - wird aus Kontakt-Nachname generiert wenn leer
  - Kontakt-Nachname (lastname)
- **Wichtig:** Firma ist IMMER erforderlich in Dolibarr
- Auto-Generierung: "Nachname, Vorname" als Firmenname

##### 2.4 Feldmapping konfigurieren
**Zwei-Spalten-Layout:**

**Linke Spalte: CSV-Spalten**
- Zeigt alle Spalten der hochgeladenen CSV-Datei
- Mit Spaltennummer und Header-Name

**Rechte Spalte: Dolibarr-Felder**
- Wähle für jedes Dolibarr-Feld die passende CSV-Spalte
- **Pflichtfelder** sind rot markiert mit Sternchen (*)
- Nicht benötigte Felder können auf "Ignorieren" gesetzt werden

**Verfügbare Firmenfelder:**
- ✅ **Firmenname*** (nom) - PFLICHT
- Firmenalias (name_alias)
- Adresse (address)
- PLZ (zip)
- Ort (town)
- Land (country)
- Telefon (phone)
- Fax (fax)
- E-Mail (email)
- Website (url)
- SIREN/SIRET (siren/siret)
- USt-IdNr. (tva_intra)
- Notizen (note_public/note_private)

**Verfügbare Kontaktfelder:**
- ✅ **Nachname*** (lastname) - PFLICHT bei Kontakt-Import
- Vorname (firstname)
- Anrede (civility)
- Position (poste)
- Adresse (address)
- PLZ (zip)
- Ort (town)
- Land (country)
- Telefon Büro (phone)
- Telefon Privat (phone_perso)
- Mobiltelefon (phone_mobile)
- Fax (fax)
- E-Mail (email)
- Geburtstag (birthday)
- Notizen (note_public/note_private)

##### 2.5 Template speichern
- Überprüfe die Mapping-Konfiguration
- Klicke auf "Speichern"
- Template ist nun für Imports verfügbar

---

### 3. CSV-Datei hochladen (Manueller Import)
**Navigation:** Tools → Contact Import → CSV Upload

#### Schritte

##### 3.1 Datei auswählen
1. Klicke auf "Datei auswählen"
2. Wähle CSV-Datei von deinem Computer
   - Max. Dateigröße: 10 MB (konfigurierbar)
   - Unterstützte Formate: `.csv`, `.txt`

##### 3.2 Session-Informationen eingeben
- **Beschreibung:** Kurze Beschreibung des Imports (z.B. "JTL Kundenstammdaten 2025-10")
- **CSV-Parameter:**
  - Trennzeichen
  - Anführungszeichen
  - Header vorhanden: Ja/Nein

##### 3.3 Upload durchführen
- Klicke auf "Hochladen"
- Datei wird in `/documents/contactimport/uploads/` gespeichert
- Weiterleitung zum Mapping-Interface

---

### 4. Feldmapping durchführen
**Navigation:** Automatisch nach Upload ODER manuell über Import-Historie

#### Ablauf

##### 4.1 Template auswählen (optional)
- Wähle ein vorhandenes Template
- Mapping wird automatisch geladen
- Bei Bedarf kann Mapping angepasst werden

##### 4.2 Import-Modus festlegen
Siehe Template-Erstellung (Punkt 2.3)

##### 4.3 Felder zuordnen
- Drag & Drop ODER Dropdown-Auswahl
- Pflichtfelder müssen gemappt werden
- System prüft automatisch auf fehlende Pflichtfelder

##### 4.4 Datenvorschau
- Zeigt erste 10 Zeilen der zu importierenden Daten
- Prüfe auf Formatierungsfehler
- Validierung:
  - E-Mail-Formate
  - Telefonnummern
  - Geburtsdaten
  - Potenzielle Duplikate

##### 4.5 Mapping speichern (optional)
- Speichere als neues Template
- Für zukünftige Imports mit gleichem Format

---

### 5. Import durchführen
**Navigation:** Nach Mapping-Konfiguration

#### Importvorgang

##### 5.1 Import starten
- Klicke auf "Import starten"
- System beginnt mit Verarbeitung
- Fortschrittsanzeige (optional)

##### 5.2 Duplikat-Erkennung (AUTOMATISCH)
Das System prüft **vor jedem Insert** auf Duplikate:

**Firmen-Duplikatsprüfung:**
1. **Primär:** Name + PLZ + Ort (exakte Übereinstimmung)
2. **Sekundär:** E-Mail-Adresse (falls vorhanden)
3. **Tertiär:** SIREN/SIRET (für französische Firmen)

**Kontakt-Duplikatsprüfung:**
1. **Primär:** E-Mail-Adresse (höchste Priorität)
2. **Sekundär:** Nachname + Vorname + Firma
3. **Tertiär:** Mobilnummer (falls vorhanden)

**Verhalten bei Duplikaten:**
- Duplikat wird **übersprungen** (nicht importiert)
- Im Log wird die vorhandene Duplikat-ID angezeigt
- Status: `skipped` (nicht `error`)
- Zählt zur Statistik "Übersprungene Zeilen"

##### 5.3 Datenverarbeitung
**Für jede CSV-Zeile:**

1. **Encoding-Konvertierung:**
   - Automatische Erkennung (UTF-8, Windows-1252, ISO-8859-1)
   - Konvertierung zu UTF-8

2. **Firmen erstellen (wenn aktiviert):**
   - Mapping-Daten auslesen
   - Duplikatsprüfung durchführen
   - Bei Neueintrag: Firma erstellen
   - Bei Duplikat: Überspringen und loggen
   - Firmen-ID speichern

3. **Kontakte erstellen (wenn aktiviert):**
   - Mapping-Daten auslesen
   - Duplikatsprüfung durchführen
   - Verknüpfung mit Firma herstellen
   - Bei Neueintrag: Kontakt erstellen
   - Bei Duplikat: Überspringen und loggen

4. **Auto-Generierung Firmenname:**
   - Falls Firmenname leer UND Kontaktdaten vorhanden
   - Format: "Nachname, Vorname"
   - Nur bei "Nur Kontakte" oder "Firmen und Kontakte" Modus

5. **Logging:**
   - Erfolgreiche Imports → Status `success`
   - Duplikate → Status `skipped` mit Duplikat-ID
   - Fehler → Status `error` mit Fehlermeldung
   - Zeile, Typ (company/contact/both), IDs

##### 5.4 Import-Ergebnis
Nach Abschluss wird angezeigt:
- **Gesamtzeilen:** Anzahl verarbeiteter CSV-Zeilen
- **Erfolgreiche Zeilen:** Neu erstellte Datensätze
- **Fehlerhafte Zeilen:** Zeilen mit Fehlern
- **Übersprungene Zeilen:** Erkannte Duplikate
- **Erfolgsrate:** Prozentsatz erfolgreicher Imports

---

### 6. Protokolle / Logs verwalten
**Navigation:** Tools → Contact Import → Protokolle & Dateien

#### Funktionen

##### 6.1 Heruntergeladene Dateien
**Anzeige:**
- Dateiname
- Dateigröße
- Download-Datum
- Aktionen: Download, Löschen

**Verwaltung:**
- **Download:** Datei erneut herunterladen
- **Einzelne Datei löschen:** Mit Bestätigung
- **Alle Dateien löschen:** Alle CSV-Dateien im Temp-Verzeichnis

##### 6.2 Import-Statistiken
**Übersicht:**
- Gesamte Imports
- Abgeschlossene Imports
- Fehlgeschlagene Imports
- Verarbeitete Zeilen gesamt
- Erfolgreiche Zeilen
- Fehlerhafte Zeilen
- Erfolgsrate (%)

##### 6.3 Import-Verlauf
**Session-Liste:**
- Referenznummer (AUTO_timestamp oder UPLOAD_timestamp)
- Dateiname
- Status (Abgeschlossen, Fehler, In Bearbeitung)
- Datum
- Zeilen (Verarbeitet / Gesamt)
- Erfolg/Fehler-Anzahl
- Aktion: Details anzeigen

**Detail-Ansicht pro Session:**
- Vollständige Session-Informationen
- Mapping-Konfiguration
- Zeilen-für-Zeilen Protokoll
- Fehlermeldungen mit Zeilennummer
- Import-Typ (company/contact/both)
- Status (success/error/skipped)

##### 6.4 Protokolle löschen
**Optionen:**
- **Alle Protokolle löschen:** Alle Einträge entfernen
- **Protokolle älter als 30 Tage:** Nur alte Einträge
- **Protokolle älter als 90 Tage:** Nur sehr alte Einträge

**Sicherheit:**
- JavaScript-Bestätigungsdialog
- Token-basierte CSRF-Schutz
- Nur Administratoren

---

### 7. Duplikate verwalten
**Navigation:** Tools → Contact Import → Duplikate Verwalten

#### Funktionen

##### 7.1 Duplikate analysieren
**Firmen analysieren:**
- Klicke auf "Firmenduplikate analysieren"
- System sucht nach:
  - **Identische Duplikate:** 100% Übereinstimmung (Name, Adresse, PLZ, Ort, E-Mail, Telefon)
  - **Ähnliche Duplikate:** Gleicher Name, unterschiedliche Details

**Kontakte analysieren:**
- Klicke auf "Kontaktduplikate analysieren"
- System sucht nach:
  - **Identische Duplikate:** 100% Übereinstimmung (E-Mail, Telefone, Firma)
  - **Ähnliche Duplikate:** Gleicher Name, unterschiedliche Details

##### 7.2 Duplikate anzeigen
**Zwei-Spalten-Layout:**

**Linke Spalte: Identische Duplikate**
- Exakte Übereinstimmungen
- Master-Eintrag wird angezeigt (ältester Datensatz)
- Duplikate mit IDs und Checkboxen
- **Master:** Gekennzeichnet mit "KeepThis" - KEINE Checkbox
- **Duplikate:** Rot markiert mit "WillBeDeleted" - MIT Checkbox

**Rechte Spalte: Ähnliche Duplikate**
- Gleiches Layout wie identische Duplikate
- Nur Name stimmt überein
- Details sind unterschiedlich

##### 7.3 Duplikate löschen
1. Wähle Duplikate per Checkbox (Master ist NICHT auswählbar)
2. Klicke auf "Ausgewählte löschen"
3. Bestätige die Aktion
4. System löscht nur die ausgewählten Duplikate
5. **Master bleibt erhalten**
6. Aktion wird in Protokoll gespeichert

##### 7.4 Duplikate zusammenführen
1. Wähle Master-Eintrag (ältester ist vorausgewählt)
2. Wähle Duplikate, die zusammengeführt werden sollen
3. Klicke auf "Ausgewählte zusammenführen"
4. System führt Merge durch:
   - Leere Felder im Master werden mit Duplikat-Daten gefüllt
   - Kontakte werden auf Master übertragen
   - Duplikate werden gelöscht
5. Aktion wird in Protokoll gespeichert

##### 7.5 Duplikat-Aktionen-Protokoll
**Anzeige:**
- Datum und Uhrzeit
- Typ (Company/Contact)
- Aktion (Delete/Merge)
- Quell-ID (gelöschtes/zusammengeführtes Element)
- Ziel-ID (Master bei Merge)
- Benutzer

**Protokoll löschen:**
- **Alle Duplikat-Protokolle löschen**
- **Protokolle älter als 30 Tage**
- **Protokolle älter als 90 Tage**

---

## Zeitgesteuerte Automatisierung

### Cron-Job Konfiguration
**Für automatische FTP-Downloads und Imports:**

1. **Dolibarr Cron-Modul aktivieren**
   - Home → Setup → Module/Applications → Cron
   
2. **Cron-Job erstellen:**
   ```
   Befehl: php /pfad/zu/dolibarr/htdocs/custom/contactimport/scripts/ftp_import.php
   Frequenz: Stündlich / Täglich (je nach Bedarf)
   Aktiv: Ja
   ```

3. **System-Cron (Linux):**
   ```bash
   # Jede Stunde
   0 * * * * cd /usr/share/dolibarr/htdocs/custom/contactimport/scripts && php ftp_import.php
   
   # Täglich um 2 Uhr nachts
   0 2 * * * cd /usr/share/dolibarr/htdocs/custom/contactimport/scripts && php ftp_import.php
   ```

---

## Best Practices

### Template-Management
- Erstelle Templates für verschiedene Datenquellen
- Teste Templates mit Beispieldaten
- Dokumentiere Mapping-Entscheidungen in Template-Beschreibung

### Import-Durchführung
- Prüfe CSV-Dateien auf Formatierung
- Nutze Datenvorschau vor Import
- Starte mit kleineren Test-Imports
- Überprüfe Protokolle nach jedem Import

### Duplikat-Vermeidung
- Nutze aussagekräftige E-Mail-Adressen
- Pflege Firmennamen einheitlich
- Prüfe regelmäßig auf Duplikate
- Führe Duplikate zeitnah zusammen

### Wartung
- Lösche alte Protokolle regelmäßig (90-Tage-Regel)
- Archiviere verarbeitete CSV-Dateien
- Überprüfe FTP-Verbindung monatlich
- Aktualisiere Templates bei Format-Änderungen

---

## Fehlerbehandlung

### Häufige Fehler

**"Company name is required"**
- Ursache: Firmenname-Feld nicht gemappt
- Lösung: Mappe CSV-Spalte auf Dolibarr-Feld "nom"
- Bei "Nur Kontakte": Mappe Nachname auf Firmenname

**"Contact lastname is required"**
- Ursache: Kontakt-Nachname nicht gemappt bei Kontakt-Import
- Lösung: Mappe CSV-Spalte auf "lastname"

**"Invalid email format"**
- Ursache: Ungültige E-Mail-Adresse in CSV
- Lösung: Korrigiere E-Mail in Quelldatei oder ignoriere Feld

**"Company already exists (ID: XXX)"**
- Ursache: Duplikat erkannt
- Verhalten: Zeile wird übersprungen (kein Fehler)
- Lösung: Normal - Duplikatsprüfung funktioniert

**Encoding-Probleme (Umlaute falsch dargestellt)**
- Ursache: CSV nicht in UTF-8
- Lösung: System konvertiert automatisch von Windows-1252/ISO-8859-1
- Falls Probleme: CSV in UTF-8 speichern

---

## Technische Details

### Datenbank-Tabellen
- `llx_contactimport_sessions` - Import-Sessions
- `llx_contactimport_logs` - Detaillierte Import-Logs
- `llx_contactimport_templates` - Wiederverwendbare Templates
- `llx_contactimport_ftp_config` - FTP-Konfiguration
- `llx_contactimport_duplicate_logs` - Duplikat-Aktionen Protokoll

### Verzeichnisstruktur
```
/documents/contactimport/
├── temp/          # FTP-Downloads
├── uploads/       # Manuelle Uploads
└── samples/       # Template-Beispieldateien
```

### Berechtigungen
- **contactimport:read** - Zugriff auf Import-Historie
- **contactimport:write** - CSV-Upload und Import durchführen
- **contactimport:admin** - Modul-Administration, Template-Verwaltung

---

## Version & Support

**Modul-Version:** 1.1.0  
**Dolibarr-Kompatibilität:** 21.0+  
**Entwickler:** Kim Wittkowski  
**E-Mail:** kim.wittkowski@gmx.de  
**Lizenz:** GNU GPL v3.0

---

## Changelog

### Version 1.1.0 (2025-10-19)
- ✅ Automatische Duplikat-Erkennung beim Import
- ✅ Duplikat-Verwaltung im Admin-Bereich
- ✅ Master-Schutz bei Duplikat-Löschung
- ✅ Separate Statistik für übersprungene Duplikate
- ✅ Auto-Generierung von Firmennamen aus Kontaktdaten
- ✅ UTF-8 Encoding-Konvertierung
- ✅ Dynamische Pflichtfeld-Anpassung je nach Import-Modus
- ✅ Protokoll-Löschfunktion für Duplikat-Logs
- 🐛 FTP Auto-Import Boolean-Check korrigiert
- 🐛 SQL-Queries in logs.php korrigiert

### Version 1.0.0 (2025-01-15)
- Erste Veröffentlichung
- CSV-Upload-Funktionalität
- Flexibles Feldmapping
- Import von Firmen und Kontakten
- Mehrsprachiger Support (DE/EN)
