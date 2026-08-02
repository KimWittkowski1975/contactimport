# Feature: Automatische Status-Änderung basierend auf "Letzter Auftrag (Datum)"

**Version:** 1.2.1  
**Datum:** 08.01.2026  
**Status:** ✅ Implementiert und Einsatzbereit

---

## 📋 Übersicht

Dieses Feature ermöglicht die automatische Klassifizierung von Firmen als **Kunde (Customer)** oder **Interessent (Prospect)** basierend auf dem Feld **"Letzter Auftrag (Datum)"** aus der CSV-Import-Datei.

### Trigger-Logik:

| CSV-Feld "Letzter Auftrag (Datum)" | Dolibarr Status | Client-Wert |
|-------------------------------------|-----------------|-------------|
| **Leer** oder nicht vorhanden       | Interessent     | `client=2`  |
| **Datum vorhanden** (z.B. 2025-01-05) | Kunde      | `client=1`  |

---

## 🎯 Implementierung

### 1. Extrafield in Datenbank

Das Extrafield wurde in der Dolibarr-Datenbank angelegt:

```sql
-- Extrafield Definition
Name: last_order_date
Label: Letzter Auftrag (Datum)
Type: date
Element: societe (Company/ThirdParty)
Position: 1000
Always Editable: Yes

-- Tabelle: llx_societe_extrafields
ALTER TABLE llx_societe_extrafields ADD COLUMN last_order_date DATE NULL;
```

**Prüfung:**
```bash
mysql -u dolibarr -pdolibarr dolibarrdb2 -e \
  "SELECT name, label, type FROM llx_extrafields WHERE name='last_order_date';"
```

### 2. CSV-Import Mapping

Das Feld ist im Mapping-Interface verfügbar:
- **Feldname im Code:** `options_last_order_date`
- **Label:** "Letzter Auftrag (Datum)"
- **Kategorie:** Company-Felder
- **Funktion:** `getCompanyMappingFields()` in `lib/contactimport.lib.php`

### 3. Processor-Logik

Die Status-Logik ist in `class/contactimportprocessor.class.php` implementiert:

#### Bei Neuanlage (createCompany):
```php
// Zeile 414-465: Extrafield aus CSV lesen
$last_order_date = null;
foreach ($mapping as $csv_column => $field) {
    if ($field === 'options_last_order_date') {
        $last_order_date = $value;
    }
}

// Zeile 506-512: Status basierend auf Datum setzen
if (!empty($last_order_date)) {
    $company->client = 1; // Customer
} else {
    $company->client = 2; // Prospect
}

// Zeile 519-521: Extrafield speichern
if (!empty($last_order_date)) {
    $this->setCompanyExtrafield($result, 'last_order_date', $last_order_date);
}
```

#### Bei Update (updateCompany):
```php
// Zeile 860-863: Upgrade von Prospect zu Customer
if (!empty($last_order_date) && $company->client == 2) {
    $company->client = 1; // Upgrade to Customer
}

// Zeile 869-871: Extrafield aktualisieren
if (!empty($last_order_date)) {
    $this->setCompanyExtrafield($company_id, 'last_order_date', $last_order_date);
}
```

### 4. Helper-Funktion

Extrafield-Verwaltung über `setCompanyExtrafield()`:

```php
// Zeile 903-940: Extrafield setzen
private function setCompanyExtrafield($company_id, $field_name, $value)
{
    // Datum-Validierung für last_order_date
    if ($field_name === 'last_order_date' && !empty($value)) {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }
        $value = dol_print_date($timestamp, '%Y-%m-%d', 'tzserver');
    }
    
    // SQL-Update auf llx_societe_extrafields
    $sql = "UPDATE ".MAIN_DB_PREFIX."societe_extrafields";
    $sql .= " SET ".$field_name." = '".$this->db->escape($value)."'";
    $sql .= " WHERE fk_object = ".$company_id;
    
    return $this->db->query($sql);
}
```

---

## 📝 Verwendung im CSV-Import

### Beispiel-CSV-Datei:

```csv
Firma;Vorname;Nachname;Email;Telefon;Letzter Auftrag (Datum)
Mustermann GmbH;Max;Mustermann;max@mustermann.de;+49 123 456789;2025-01-05
Test AG;Anna;Schmidt;anna@test.de;+49 234 567890;
Neukunde KG;Peter;Müller;peter@neukunde.de;+49 345 678901;
Bestandskunde GmbH;Maria;Weber;maria@bestand.de;+49 456 789012;2024-12-20
Prospect Firma;Thomas;Becker;thomas@prospect.de;+49 567 890123;
Customer Systems;Julia;Wagner;julia@customer.de;+49 678 901234;2024-11-15
```

### Import-Schritte:

1. **CSV hochladen:** `Tools → Contact Import → CSV Upload`
2. **Mapping konfigurieren:**
   - CSV-Spalte: "Letzter Auftrag (Datum)"
   - Dolibarr-Feld: `[Company] Letzter Auftrag (Datum)`
3. **Import starten**

### Erwartetes Ergebnis:

| Firma | Letzter Auftrag | Status | Client-Wert |
|-------|----------------|--------|-------------|
| Mustermann GmbH | 2025-01-05 | ✅ Kunde | 1 |
| Test AG | *(leer)* | 👤 Interessent | 2 |
| Neukunde KG | *(leer)* | 👤 Interessent | 2 |
| Bestandskunde GmbH | 2024-12-20 | ✅ Kunde | 1 |
| Prospect Firma | *(leer)* | 👤 Interessent | 2 |
| Customer Systems | 2024-11-15 | ✅ Kunde | 1 |

---

## 🧪 Test-Anleitung

### 1. Datenbank-Prüfung

```bash
# Extrafield prüfen
mysql -u dolibarr -pdolibarr dolibarrdb2 -e \
  "SELECT name, label, type, elementtype FROM llx_extrafields WHERE name='last_order_date';"

# Spalte in societe_extrafields prüfen
mysql -u dolibarr -pdolibarr dolibarrdb2 -e \
  "SHOW COLUMNS FROM llx_societe_extrafields LIKE 'last_order_date';"
```

### 2. Import-Test

1. Test-CSV verwenden: `docs/test_last_order_import.csv`
2. In Dolibarr importieren
3. Mapping konfigurieren (siehe oben)
4. Import durchführen

### 3. Ergebnis-Prüfung

```sql
-- Importierte Firmen mit Status prüfen
SELECT 
    s.rowid,
    s.nom AS firma,
    s.client,
    CASE 
        WHEN s.client = 1 THEN 'Kunde'
        WHEN s.client = 2 THEN 'Interessent'
        ELSE 'Unbekannt'
    END AS status,
    se.last_order_date
FROM llx_societe s
LEFT JOIN llx_societe_extrafields se ON se.fk_object = s.rowid
WHERE s.nom IN ('Mustermann GmbH', 'Test AG', 'Neukunde KG', 'Bestandskunde GmbH')
ORDER BY s.rowid DESC
LIMIT 10;
```

**Erwartetes SQL-Ergebnis:**
```
+-------+---------------------+--------+--------------+-----------------+
| rowid | firma               | client | status       | last_order_date |
+-------+---------------------+--------+--------------+-----------------+
| 1234  | Mustermann GmbH     |      1 | Kunde        | 2025-01-05      |
| 1235  | Test AG             |      2 | Interessent  | NULL            |
| 1236  | Neukunde KG         |      2 | Interessent  | NULL            |
| 1237  | Bestandskunde GmbH  |      1 | Kunde        | 2024-12-20      |
+-------+---------------------+--------+--------------+-----------------+
```

---

## 🔍 Debugging

### Logs prüfen:

```bash
# Import-Logs
tail -50 /var/log/apache2/error.log | grep -i "contactimport\|Fatal\|Error"

# Dolibarr-Logs
ls -lh /usr/share/dolibarr/htdocs/custom/contactimport/logs/
```

### Häufige Probleme:

1. **Feld nicht im Mapping sichtbar:**
   - Modul deaktivieren und reaktivieren
   - Browser-Cache leeren
   - Prüfen: `lib/contactimport.lib.php` Zeile 134

2. **Datum wird nicht gespeichert:**
   - Format prüfen: `YYYY-MM-DD` oder `DD.MM.YYYY`
   - setCompanyExtrafield() Logik prüfen
   - SQL-Spalte vorhanden? (siehe Datenbank-Prüfung)

3. **Status ändert sich nicht:**
   - createCompany() Logik prüfen (Zeile 506-512)
   - $last_order_date Variable prüfen
   - Debug-Output hinzufügen: `error_log("Last Order: ".$last_order_date);`

---

## 📚 Weiterführende Informationen

- **Changelog:** [changelog.md](../changelog.md) Version 1.2.1
- **README:** [README.md](../README.md)
- **Analyse:** [prospect-customer-analyse.md](../prospect-customer-analyse.md)
- **SQL-Update:** [sql/update_1.2.0_to_1.2.1.sql](../sql/update_1.2.0_to_1.2.1.sql)

---

## ✅ Checkliste Implementierung

- [x] SQL-Extrafield angelegt (`last_order_date`)
- [x] Extrafield in Mapping-Interface verfügbar
- [x] Processor-Logik implementiert (createCompany)
- [x] Processor-Logik implementiert (updateCompany)
- [x] Helper-Funktion setCompanyExtrafield()
- [x] Language-Files DE/EN aktualisiert
- [x] Test-CSV erstellt
- [x] SQL-Update-Script erstellt
- [x] Git-Commit durchgeführt
- [x] Dokumentation erstellt

**Status:** ✅ **EINSATZBEREIT**

---

**Erstellt von:** GitHub Copilot  
**Datum:** 08.01.2026  
**Version:** 1.2.1
