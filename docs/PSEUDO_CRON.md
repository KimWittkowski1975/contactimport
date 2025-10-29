# Pseudo-Cron Alternative für ContactImport

## Übersicht

Für Benutzer, die **keinen Zugriff auf Cron-Jobs** haben (z.B. auf Shared-Hosting), bietet das ContactImport-Modul eine **Pseudo-Cron-Alternative** an.

## Was ist Pseudo-Cron?

Pseudo-Cron ist ein System (ähnlich wie bei WordPress), das bei normalen Seitenaufrufen im Hintergrund läuft und prüft, ob ein automatischer Import fällig ist.

### Vorteile:
✅ Funktioniert ohne Server-Cron-Zugriff
✅ Automatische Ausführung bei Seitenaufrufen
✅ Nutzt das konfigurierte Sync-Intervall aus ftp.php

### Nachteile:
⚠️ Benötigt regelmäßige Seitenaufrufe
⚠️ Minimal erhöhte Last bei jedem Seitenaufruf (1% Wahrscheinlichkeit)
⚠️ Nicht so präzise wie echter Cron-Job

## Installation

### Schritt 1: Pseudo-Cron aktivieren

1. Gehe zu: **Home > Setup > Modules > ContactImport > Setup**
2. Klicke auf "Ändern"
3. Aktiviere: **"Pseudo-Cron aktivieren"** → Ja
4. Speichern

### Schritt 2: Hook in main.inc.php einfügen

Füge folgende Zeile **am Ende** von `/usr/share/dolibarr/htdocs/main.inc.php` hinzu:

```php
// ContactImport Pseudo-Cron Hook
@include_once DOL_DOCUMENT_ROOT.'/custom/contactimport/lib/pseudo_cron_hook.php';
```

**Position:** Direkt vor dem schließenden `?>` oder am Ende der Datei.

### Schritt 3: Fertig!

Der automatische Import läuft jetzt bei normalen Seitenaufrufen im Hintergrund.

## Funktionsweise

1. **Bei jedem Seitenaufruf** (mit 1% Wahrscheinlichkeit):
   - Prüft, ob Pseudo-Cron aktiviert ist
   - Prüft, ob genug Zeit seit letztem Import vergangen ist
   - Lädt ggf. neue Dateien vom FTP und importiert sie

2. **Lock-Mechanismus**:
   - Verhindert gleichzeitige Ausführungen
   - Timeout nach 5 Minuten

3. **Verwendet Sync-Intervall**:
   - Nutzt `CONTACTIMPORT_FTP_SYNC_INTERVAL` aus ftp.php
   - User kann Intervall jederzeit ändern

## Performance

- **Nur 1% der Seitenaufrufe** führen die Prüfung durch
- **Lock-File verhindert** parallele Ausführungen
- **Minimale Performance-Auswirkung** für normale User

## Vergleich: Echter Cron vs. Pseudo-Cron

| Feature | Echter Cron | Pseudo-Cron |
|---------|-------------|-------------|
| Server-Zugriff nötig | ✅ Ja | ❌ Nein |
| Präzises Timing | ✅ Ja | ⚠️ Abhängig von Traffic |
| Performance | ✅ Optimal | ⚠️ Minimal höher |
| Empfohlen für | Dedizierte Server | Shared Hosting |

## Empfehlung

**Verwende echten Cron-Job, wenn möglich!**

Pseudo-Cron ist nur für Situationen gedacht, in denen kein echter Cron verfügbar ist.

## Logs überprüfen

Pseudo-Cron-Fehler werden im PHP Error Log gespeichert:

```bash
tail -f /var/log/apache2/error.log | grep "ContactImport Pseudo-Cron"
```

## Deaktivierung

1. Gehe zu Setup und deaktiviere "Pseudo-Cron aktivieren"
2. Optional: Entferne die Zeile aus `main.inc.php`

## Troubleshooting

### "Imports laufen nicht"
→ Stelle sicher, dass deine Website regelmäßig aufgerufen wird
→ Prüfe, ob Pseudo-Cron in Setup aktiviert ist
→ Überprüfe das Error-Log

### "Zu viele Imports"
→ Erhöhe das Sync-Intervall in ftp.php
→ Prüfe, ob Lock-Files korrekt funktionieren

### "Performance-Probleme"
→ Wechsle zu echtem Cron-Job
→ Erhöhe die Wahrscheinlichkeit im Code (aktuell 1%)
