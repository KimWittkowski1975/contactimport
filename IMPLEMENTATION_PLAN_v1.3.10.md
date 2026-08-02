# ContactImport Module - Implementierungsplan v1.3.10
**Datum:** 30. Mai 2026  
**Erstellt von:** AI Assistant  
**Betroffene Module:** contactimport

---

## 📋 ZUSAMMENFASSUNG

Zwei Verbesserungen für das ContactImport Modul:

1. **Template Standard-Logik**: Nur ein Template kann als Standard markiert sein
2. **Intelligente Name/Firma Pflichtfeld-Logik**: Automatische Fallbacks zwischen Name und Firma

---

## 📊 AUFWANDSANALYSE

### Feature 1: Template Standard-Logik
**Komplexität:** 🟢 NIEDRIG  
**Geschätzter Aufwand:** 30-45 Minuten  
**Risiko:** NIEDRIG  
**Dateien betroffen:** 1 Datei

### Feature 2: Name/Firma Intelligente Logik  
**Komplexität:** 🟡 MITTEL  
**Geschätzter Aufwand:** 1-1.5 Stunden  
**Risiko:** NIEDRIG  
**Dateien betroffen:** 1 Datei

### **GESAMT-AUFWAND: 1.5 - 2 Stunden**

---

## 🔍 DETAILLIERTE ANALYSE

### Feature 1: Template Standard-Logik

#### Aktuelle Situation
- ✅ Feld `is_default` existiert in `ContactImportTemplate`
- ✅ Methode `getDefaultTemplate()` existiert
- ❌ **PROBLEM:** Mehrere Templates können gleichzeitig als Standard markiert sein
- ❌ Beim Setzen eines neuen Standards wird das alte nicht zurückgesetzt

#### Gewünschtes Verhalten
```
WENN neues Template als Standard gesetzt wird:
  1. Alle anderen Templates: is_default = 0
  2. Neues Template: is_default = 1
  
ERGEBNIS: Nur EIN Standard-Template pro Entity
```

#### Betroffene Dateien
- `/custom/contactimport/class/contactimporttemplate.class.php`
  - Neue Methode: `setAsDefault()`
  - Änderung: `create()` Methode
  - Änderung: `update()` Methode

#### Implementierungs-Schritte

**Schritt 1.1: Neue Methode `setAsDefault()` erstellen**
```php
/**
 * Set this template as default and unset all other defaults
 *
 * @return int >0 if OK, <0 if KO
 */
public function setAsDefault()
{
    global $conf;
    
    $this->db->begin();
    
    // Reset all other templates to non-default
    $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET is_default = 0";
    $sql .= " WHERE entity IN (0, ".($conf->entity > 0 ? $conf->entity : 1).")";
    
    $resql = $this->db->query($sql);
    if (!$resql) {
        $this->db->rollback();
        return -1;
    }
    
    // Set this template as default
    $this->is_default = 1;
    
    $sql2 = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql2 .= " SET is_default = 1";
    $sql2 .= " WHERE rowid = ".$this->id;
    
    $resql2 = $this->db->query($sql2);
    if (!$resql2) {
        $this->db->rollback();
        return -1;
    }
    
    $this->db->commit();
    return 1;
}
```

**Schritt 1.2: Integration in `create()` Methode**
```php
public function create(User $user, $notrigger = false)
{
    // Wenn als Standard markiert, andere zurücksetzen
    if (!empty($this->is_default) && $this->is_default == 1) {
        $this->resetOtherDefaults();
    }
    
    $resultcreate = $this->createCommon($user, $notrigger);

    return $resultcreate;
}

// Helper Methode
private function resetOtherDefaults()
{
    global $conf;
    
    $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET is_default = 0";
    $sql .= " WHERE entity IN (0, ".($conf->entity > 0 ? $conf->entity : 1).")";
    
    $this->db->query($sql);
}
```

**Schritt 1.3: Integration in `update()` Methode**
```php
public function update(User $user, $notrigger = false)
{
    // Wenn als Standard markiert, andere zurücksetzen
    if (!empty($this->is_default) && $this->is_default == 1) {
        $this->resetOtherDefaults();
    }
    
    return $this->updateCommon($user, $notrigger);
}
```

#### Test-Szenarien für Feature 1
1. ✅ Template A als Standard erstellen → Erfolg
2. ✅ Template B als Standard erstellen → A wird zurückgesetzt
3. ✅ Template C ohne Standard erstellen → A und B bleiben
4. ✅ Template B wieder als Standard setzen → Nur B ist Standard
5. ✅ Template löschen das Standard ist → Kein Standard mehr (OK)

---

### Feature 2: Intelligente Name/Firma Pflichtfeld-Logik

#### Aktuelle Situation
- ✅ `createCompany()`: Wenn `nom` leer → versucht aus Contact-Daten zu generieren → sonst "XX-AUTO"
- ✅ `createContact()`: Wenn `lastname` leer → "XX-AUTO"
- ❌ **PROBLEM:** Unidirektional - Contact nutzt nicht Firma-Daten als Fallback
- ❌ Wenig intelligent - beide Richtungen sollten unterstützt werden

#### Gewünschtes Verhalten

**Für FIRMA (Company nom):**
```
1. Wenn nom VORHANDEN → nom verwenden ✅
2. Wenn nom LEER:
   a. Wenn lastname VORHANDEN → lastname als nom ✅  
   b. Wenn firstname VORHANDEN → lastname + firstname als nom ✅
   c. Sonst → "XX-AUTO" als nom ⚠️
```

**Für KONTAKT (Contact lastname):**
```
1. Wenn lastname VORHANDEN → lastname verwenden ✅
2. Wenn lastname LEER:
   a. Wenn nom VORHANDEN → nom als lastname ✅ (NEU!)
   b. Sonst → "XX-AUTO" als lastname ⚠️
```

#### Betroffene Dateien
- `/custom/contactimport/class/contactimportprocessor.class.php`
  - Änderung: `createCompany()` Methode (Zeile ~425-535)
  - Änderung: `createContact()` Methode (Zeile ~565-680)

#### Implementierungs-Schritte

**Schritt 2.1: Erweiterte Logik in `createCompany()`**

**Aktueller Code (Zeile ~422-429):**
```php
// Validate required fields - if no company name, try to generate from contact data
if (empty($company->name) && !empty($contact_mapping)) {
    $firstname = '';
    $lastname = '';
    
    // Extract firstname and lastname from contact mapping
    foreach ($contact_mapping as $csv_column => $field) {
        $csv_column = (int)$csv_column;
        $value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
        if ($field === 'firstname' && !empty($value)) {
            $firstname = $value;
        } elseif ($field === 'lastname' && !empty($value)) {
            $lastname = $value;
        }
    }
    
    // Generate company name from contact name
    if (!empty($lastname)) {
        $company->name = trim($lastname . ($firstname ? ', ' . $firstname : ''));
    }
}

// Final validation - Auto-fill empty required fields
if (empty($company->name)) {
    $company->name = 'XX-AUTO';
    $this->errors[] = 'Warning: Company name was empty - auto-filled with "XX-AUTO"';
}
```

**Neuer Code (verbesserte Logik):**
```php
// Validate required fields - if no company name, try to generate from contact data
if (empty($company->name) && !empty($contact_mapping)) {
    $firstname = '';
    $lastname = '';
    
    // Extract firstname and lastname from contact mapping
    foreach ($contact_mapping as $csv_column => $field) {
        $csv_column = (int)$csv_column;
        $value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
        if ($field === 'firstname' && !empty($value)) {
            $firstname = $value;
        } elseif ($field === 'lastname' && !empty($value)) {
            $lastname = $value;
        }
    }
    
    // Generate company name from contact name
    if (!empty($lastname) || !empty($firstname)) {
        if (!empty($lastname) && !empty($firstname)) {
            // Both available: "Lastname, Firstname"
            $company->name = trim($lastname . ', ' . $firstname);
            $this->errors[] = 'Info: Company name generated from contact name (Lastname, Firstname)';
        } elseif (!empty($lastname)) {
            // Only lastname available
            $company->name = $lastname;
            $this->errors[] = 'Info: Company name copied from contact lastname';
        } else {
            // Only firstname available
            $company->name = $firstname;
            $this->errors[] = 'Info: Company name copied from contact firstname';
        }
    }
}

// Final validation - Auto-fill empty required fields
if (empty($company->name)) {
    $company->name = 'XX-AUTO';
    $this->errors[] = 'Warning: Company name was empty - auto-filled with "XX-AUTO"';
}
```

**Schritt 2.2: Erweiterte Logik in `createContact()`**

**Aktueller Code (Zeile ~631-635):**
```php
// Validate required fields - Auto-fill empty required fields
if (empty($contact->lastname)) {
    $contact->lastname = 'XX-AUTO';
    $this->errors[] = 'Warning: Contact lastname was empty - auto-filled with "XX-AUTO"';
}
```

**Neuer Code (mit Firma-Fallback):**
```php
// Validate required fields - if no lastname, try to get from company data
if (empty($contact->lastname)) {
    // Try to extract company name from the same CSV line
    $company_name = '';
    
    // We need access to company mapping to extract company name
    // Pass company_mapping as parameter or extract from global mapping_config
    // For now, we'll use a helper method to extract company name
    foreach ($mapping as $csv_column => $field) {
        $csv_column = (int)$csv_column;
        $value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
        // This won't work - we need company mapping here!
    }
    
    // If we have a company ID, we could fetch company name
    // But that's an extra DB query - not optimal
    
    // Alternative: Pass company_mapping to createContact()
}

// Final validation - Auto-fill empty required fields
if (empty($contact->lastname)) {
    $contact->lastname = 'XX-AUTO';
    $this->errors[] = 'Warning: Contact lastname was empty - auto-filled with "XX-AUTO"';
}
```

**⚠️ PROBLEM ERKANNT:** Die `createContact()` Methode hat keinen Zugriff auf `company_mapping`!

**LÖSUNG:** Methoden-Signatur erweitern:
```php
// ALT:
private function createContact($csv_line, $mapping, User $user, $company_id = 0)

// NEU:  
private function createContact($csv_line, $mapping, User $user, $company_id = 0, $company_mapping = array())
```

**Schritt 2.3: Finale Implementierung `createContact()`**

```php
/**
 * Create a contact from CSV data
 *
 * @param array $csv_line CSV line data
 * @param array $mapping Field mapping (contact fields)
 * @param User $user User creating the contact
 * @param int $company_id Company ID to link contact to
 * @param array $company_mapping Company field mapping for fallback (NEW)
 * @return int Contact ID if successful, 0 if error, -1 if duplicate found
 */
private function createContact($csv_line, $mapping, User $user, $company_id = 0, $company_mapping = array())
{
    global $conf;
    $contact = new Contact($this->db);

    // Map CSV data to contact fields
    foreach ($mapping as $csv_column => $field) {
        // ... existing code ...
    }

    // Validate required fields - if no lastname, try to get from company data
    if (empty($contact->lastname) && !empty($company_mapping)) {
        // Extract company name from CSV using company mapping
        foreach ($company_mapping as $csv_column => $field) {
            $csv_column = (int)$csv_column;
            $value = isset($csv_line[$csv_column]) ? trim($csv_line[$csv_column]) : '';
            if ($field === 'nom' && !empty($value)) {
                $contact->lastname = $value;
                $this->errors[] = 'Info: Contact lastname copied from company name';
                break;
            }
        }
    }

    // Final validation - Auto-fill empty required fields
    if (empty($contact->lastname)) {
        $contact->lastname = 'XX-AUTO';
        $this->errors[] = 'Warning: Contact lastname was empty - auto-filled with "XX-AUTO"';
    }

    // ... rest of existing code ...
}
```

**Schritt 2.4: Aufruf-Stellen aktualisieren**

In `processImport()` Methode (Zeile ~369):
```php
// ALT:
$contact_id = $this->createContact($csv_line, $mapping_config['contact'], $user, $company_id);

// NEU:
$contact_id = $this->createContact($csv_line, $mapping_config['contact'], $user, $company_id, $mapping_config['company']);
```

#### Test-Szenarien für Feature 2

**Szenario 1: Nur Firma vorhanden**
```csv
Firma,Email,Telefon
Mustermann GmbH,info@test.de,0123456
```
**Erwartet:**
- Company: nom = "Mustermann GmbH" ✅
- Contact: lastname = "Mustermann GmbH" ✅ (aus Company kopiert)

**Szenario 2: Nur Name vorhanden**
```csv
Name,Email,Telefon
Max Mustermann,max@test.de,0123456
```
**Erwartet:**
- Company: nom = "Max Mustermann" ✅ (aus Contact kopiert)
- Contact: lastname = "Max Mustermann" ✅

**Szenario 3: Beides vorhanden**
```csv
Name,Firma,Email
Max Mustermann,Mustermann GmbH,info@test.de
```
**Erwartet:**
- Company: nom = "Mustermann GmbH" ✅
- Contact: lastname = "Max Mustermann" ✅

**Szenario 4: Nichts vorhanden**
```csv
Email,Telefon
info@test.de,0123456
```
**Erwartet:**
- Company: nom = "XX-AUTO" ⚠️
- Contact: lastname = "XX-AUTO" ⚠️

**Szenario 5: Firstname + Lastname getrennt**
```csv
Vorname,Nachname,Email
Max,Mustermann,max@test.de
```
**Erwartet:**
- Company: nom = "Mustermann, Max" ✅ (aus Contact generiert)
- Contact: lastname = "Mustermann" ✅

---

## 📝 IMPLEMENTIERUNGS-REIHENFOLGE

### Phase 1: Feature 1 - Template Standard-Logik (30-45 Min)
1. ✅ Analyse abgeschlossen
2. ⏳ Methode `resetOtherDefaults()` erstellen
3. ⏳ `create()` Methode erweitern
4. ⏳ `update()` Methode erweitern
5. ⏳ Tests durchführen (siehe Test-Szenarien)
6. ✅ Code Review

### Phase 2: Feature 2 - Name/Firma Intelligente Logik (1-1.5 Std)
1. ✅ Analyse abgeschlossen
2. ⏳ `createCompany()` Logik erweitern
3. ⏳ `createContact()` Signatur & Logik erweitern
4. ⏳ Aufruf-Stellen aktualisieren
5. ⏳ Umfangreiche Tests mit CSV-Varianten
6. ✅ Code Review

### Phase 3: Finalisierung (15-20 Min)
1. ⏳ Changelog aktualisieren (v1.3.10)
2. ⏳ Version erhöhen in Descriptor
3. ⏳ README aktualisieren (neue Features)
4. ⏳ Finale Verifikation (Error Logs prüfen!)
5. ✅ User Abnahme

---

## 🎯 ERFOLGS-KRITERIEN

### Feature 1: Template Standard-Logik
- ✅ Nur EIN Template kann als Standard markiert sein
- ✅ Beim Setzen eines neuen Standards werden alle anderen zurückgesetzt
- ✅ Funktioniert über alle Test-Szenarien
- ✅ Keine Fehler in Error Logs

### Feature 2: Name/Firma Intelligente Logik
- ✅ Firma nutzt Contact-Daten als Fallback
- ✅ Contact nutzt Firma-Daten als Fallback
- ✅ Alle 5 Test-Szenarien funktionieren
- ✅ Keine falschen Zuordnungen
- ✅ Warning-Meldungen sind informativ
- ✅ Keine Fehler in Error Logs

---

## ⚠️ RISIKEN & ABHÄNGIGKEITEN

### Risiken: NIEDRIG
- ✅ Alle Änderungen in Custom-Modul (keine Core-Änderungen)
- ✅ Bestehende Funktionalität bleibt erhalten
- ✅ Rückwärtskompatibel (bestehende Templates/Imports funktionieren weiter)

### Abhängigkeiten: KEINE
- ✅ Keine externen Module betroffen
- ✅ Keine Datenbank-Schema-Änderungen nötig
- ✅ Keine Core-Funktionen modifiziert

---

## 📊 TESTDATEN-VORBEREITUNG

### CSV Test-Dateien erstellen:
1. `test_only_company.csv` - Nur Firma, kein Name
2. `test_only_name.csv` - Nur Name, keine Firma
3. `test_both.csv` - Beide vorhanden
4. `test_neither.csv` - Weder noch
5. `test_firstname_lastname.csv` - Firstname + Lastname getrennt

---

## 🎨 BEST PRACTICE VORSCHLÄGE

### 1. Logging verbessern
- Statt `'Info: ...'` und `'Warning: ...'` → Sprachschlüssel verwenden
- In Sprachdateien: `InfoCompanyNameGenerated`, `WarningAutoFilledCompany` etc.

### 2. Konfigurierbarkeit erhöhen
- Admin-Option: Auto-Fill-Modus wählen (XX-AUTO vs. Copy from other field)
- Admin-Option: Template-Standard-Verhalten (strikt vs. mehrere erlaubt)

### 3. UI-Verbesserung
- In Template-Liste: Standard-Template visuell hervorheben (Badge "Standard")
- Bei Template-Bearbeitung: Hinweis "Wird aktuelles Standard-Template ersetzen"

### 4. Validierung erweitern
- Bei CSV-Upload: Preview zeigen welche Felder leer sind
- Warnung wenn kritische Felder fehlen

---

## 📋 CHANGELOG-EINTRAG (Vorabversion)

```markdown
## Version 1.3.10 (2026-05-30)

### 🔧 Verbesserungen

#### Template-Verwaltung
- **NEU:** Automatische Standard-Template-Verwaltung
  - Nur ein Template kann als Standard markiert sein
  - Beim Setzen eines neuen Standards werden alle anderen automatisch zurückgesetzt
  - Verhindert Konflikte bei automatischen Importen

#### Import-Logik
- **VERBESSERT:** Intelligente Pflichtfeld-Behandlung für Name und Firma
  - Wenn nur Firma vorhanden: Wird auch als Kontakt-Name verwendet
  - Wenn nur Name vorhanden: Wird auch als Firmenname verwendet
  - Wenn beides vorhanden: Beide Werte werden korrekt übernommen
  - Reduziert "XX-AUTO" Platzhalter auf absolute Notfälle
  - Bessere Fehlermeldungen und Warnungen

### 🐛 Bugfixes
- Verhindert mehrere Standard-Templates gleichzeitig

### 📚 Dokumentation
- Implementierungsplan dokumentiert
- Test-Szenarien definiert
```

---

## ✅ ABSCHLUSS-CHECKLISTE

- [ ] Feature 1 implementiert
- [ ] Feature 1 getestet (alle Test-Szenarien)
- [ ] Feature 2 implementiert
- [ ] Feature 2 getestet (alle CSV-Varianten)
- [ ] Error Logs geprüft (keine Fatal/Error)
- [ ] Changelog aktualisiert
- [ ] Version erhöht (1.3.9 → 1.3.10)
- [ ] README aktualisiert
- [ ] User-Tests durchgeführt
- [ ] User-Abnahme erhalten
- [ ] Git Commit erstellt

---

**Ende Implementierungsplan**
