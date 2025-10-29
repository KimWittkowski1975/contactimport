# Automatischer FTP Import - Cron-Job Installation

## Übersicht

Das ContactImport Modul kann automatisch CSV-Dateien von einem FTP-Server herunterladen und importieren.

## Konfiguration

1. **FTP-Einstellungen** konfigurieren in: `Admin > Module ContactImport > FTP-Konfiguration`
   - FTP Host, Port, Benutzer, Passwort
   - Remote-Pfad für CSV-Dateien
   - **Synchronisationsintervall** (in Minuten)

2. **Standard-Vorlage** erstellen in: `Admin > Module ContactImport > Templates`
   - Vorlage mit "Standard = Ja" markieren
   - Diese Vorlage wird für automatische Imports verwendet

## Cron-Job Installation

### Option 1: System-Cron (Empfohlen)

Crontab bearbeiten:
```bash
sudo crontab -e
```

Füge eine der folgenden Zeilen hinzu (je nach gewünschtem Intervall):

**Stündlich:**
```bash
0 * * * * /usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php >> /var/log/contactimport_cron.log 2>&1
```

**Alle 30 Minuten:**
```bash
*/30 * * * * /usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php >> /var/log/contactimport_cron.log 2>&1
```

**Täglich um 2:00 Uhr nachts:**
```bash
0 2 * * * /usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php >> /var/log/contactimport_cron.log 2>&1
```

**Alle 15 Minuten:**
```bash
*/15 * * * * /usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php >> /var/log/contactimport_cron.log 2>&1
```

### Option 2: Dolibarr Cron

Im Dolibarr-Admin:
1. Gehe zu: `Home > Setup > Cron Jobs`
2. Klicke auf "New job"
3. Konfiguriere:
   - **Label:** ContactImport FTP Sync
   - **Command:** `php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php`
   - **Frequency:** Je nach CONTACTIMPORT_FTP_SYNC_INTERVAL
   - **Status:** Enabled

## Funktionsweise

Das Skript `cron_import.php`:

1. Liest das konfigurierte Sync-Intervall (`CONTACTIMPORT_FTP_SYNC_INTERVAL`)
2. Prüft, ob genug Zeit seit dem letzten Lauf vergangen ist
3. Lädt neue CSV-Dateien vom FTP-Server herunter
4. Importiert sie automatisch mit der Standard-Vorlage
5. Speichert den Zeitstempel des letzten Laufs

**Wichtig:** Das Skript führt nur dann einen Import durch, wenn das konfigurierte Intervall erreicht ist. Wenn du den Cron z.B. jede Stunde ausführst, aber das Sync-Intervall auf 1440 Minuten (1 Tag) eingestellt ist, wird der Import nur einmal pro Tag durchgeführt.

## Logs überprüfen

```bash
# Cron-Log anzeigen
tail -f /var/log/contactimport_cron.log

# Letzten Lauf-Zeitstempel prüfen
cat /usr/share/dolibarr/documents/contactimport/last_cron_run.txt
```

## Manueller Test

Du kannst das Skript manuell testen:

```bash
/usr/bin/php /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import.php
```

## Fehlerbehebung

### "CONTACTIMPORT_FTP_SYNC_INTERVAL not configured"
→ Konfiguriere das Sync-Intervall in `FTP-Konfiguration`

### "No files to download"
→ Keine neuen Dateien auf dem FTP-Server

### "Error: Download failed"
→ Prüfe FTP-Verbindungseinstellungen (Host, Port, Credentials)

### "Error: Import failed"
→ Prüfe, ob eine Standard-Vorlage existiert
→ Überprüfe Import-Logs in Dolibarr

## Beispiel-Konfiguration

- **Sync-Intervall:** 60 Minuten (1 Stunde)
- **Cron:** Läuft jede Stunde (0 * * * *)
- **Ergebnis:** Import wird stündlich ausgeführt

oder

- **Sync-Intervall:** 1440 Minuten (24 Stunden)  
- **Cron:** Läuft jede Stunde (0 * * * *)
- **Ergebnis:** Import wird nur einmal pro Tag ausgeführt, auch wenn Cron stündlich läuft
