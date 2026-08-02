# Analyse: Erweiterung ContactImport - Automatische Status-Änderung (Prospect/Customer)

**Datum:** 08.01.2026  
**Modul:** ContactImport  
**Aktuelle Version:** 1.2.0  
**Ziel-Version:** 1.2.1

---

## 📋 Anforderungsübersicht

### Neue Features
1. **Neues Extrafeld:** "Letzter Auftrag (Datum)" für Companies anlegen
2. **CSV-Feldmapping:** Spalte "Letzter Auftrag (Datum)" aus CSV importierbar machen
3. **Automatische Status-Logik:**
   - Wenn Feld leer → Status "Prospect" (client = 2)
   - Wenn Feld Datum enthält → Status "Customer" (client = 1)

---

## 🔍 Technische Analyse

### 1. ✅ **BEREITS VORHANDEN** - Customer Status Automation

**WICHTIG:** Die Funktion ist **bereits implementiert** in Version 1.2.0!

#### Bestehende Implementierung:
- **Changelog v1.2.0** beschreibt: "Customer Status Automation"
- **Feld in Templates:** `last_order_column` bereits vorhanden
- **Logik in Processor:** Status-Änderung bereits implementiert
  ```php
  // Zeile 834 in contactimportprocessor.class.php
  if ($has_last_order && $company->client == 2) $company->client = 1;
  ```

#### ⚠️ **ABER:** Feature ist für **COMPANIES** implementiert, nicht für **CONTACTS**!

---

## 🎯 Was muss angepasst werden?

### Problem-Identifikation

**AKTUELL (v1.2.0):**
- Status-Änderung erfolgt bei **Societe** (Company/ThirdParty)
- Field: `llx_societe.client` (0=nicht, 1=Customer, 2=Prospect, 3=beides)
- Extrafield "letzte Bestellung" muss bei **Societe** angelegt werden

**KUNDE MÖCHTE:**
- Status-Änderung bei **Contacts** (Socpeople)
- Aber: **Contacts haben kein `client` Feld!**
- Contacts sind immer mit einer Societe verknüpft (`fk_soc`)

### 🚨 **Architektur-Entscheidung erforderlich:**

#### Option A: Status an Company ändern (EMPFOHLEN)
**Vorteil:**
- Nutzt bestehende Infrastruktur (bereits 80% implementiert)
- `client` Status ist konzeptuell bei ThirdParty richtig
- Logik bereits vorhanden

**Aufwand:** ⭐⭐ (Gering - 1-2 Stunden)
- Extrafield bei Societe statt Contact anlegen
- Mapping-UI um Firmen-Extrafield erweitern
- Bestehende Logik testen

**Zu ändern:**
1. Extrafield erstellen für `societe` (nicht `socpeople`)
2. Template-Mapping erweitern (bereits vorbereitet)
3. UI in mapping.php erweitern
4. Testen der bestehenden Logik

#### Option B: Neues Feld bei Contact + Status bei Company
**Konzept:**
- Extrafield "letzte_bestellung" bei **Contact**
- Status-Änderung bei **verknüpfter Company**
- Logik: Wenn Contact Bestellung hat → Company wird Customer

**Aufwand:** ⭐⭐⭐⭐ (Mittel-Hoch - 3-4 Stunden)
- Extrafield bei Contact anlegen
- Contact-Mapping erweitern
- Neue Logik: Contact → Company Status Update
- Company-Objekt laden und Status ändern

**Zu ändern:**
1. Extrafield für Contact erstellen
2. Mapping-Interface erweitern
3. `createContact()` / `updateContact()` erweitern:
   - Extrafield setzen
   - Verknüpfte Company laden
   - Company-Status ändern
4. Edge-Cases behandeln (Contact ohne Company, etc.)

#### Option C: Nur Extrafield bei Contact (NICHT EMPFOHLEN)
**Problem:** 
- Contacts haben kein `client` Feld in Dolibarr
- Status-Konzept existiert nur bei ThirdParty/Societe
- Würde Dolibarr-Architektur widersprechen

**Aufwand:** ⭐⭐⭐⭐⭐ (Hoch - 6-8 Stunden)
- Neues Custom-Field bei Contact für Status
- Eigene Status-Logik außerhalb von Dolibarr-Standard
- Überall Anpassungen für Custom-Status nötig

---

## 📊 Aufwandsschätzung nach Option

### Option A (EMPFOHLEN): Status bei Company
```
Aufgabe                                  | Aufwand | Komplexität
-----------------------------------------|---------|-------------
1. Extrafield für Societe anlegen        | 15 Min  | ⭐ Einfach
2. SQL Update-Script erstellen           | 10 Min  | ⭐ Einfach
3. Mapping-UI erweitern (Company)        | 20 Min  | ⭐⭐ Mittel
4. Template-Klasse erweitern             | 10 Min  | ⭐ Einfach
5. Processor-Logik testen & anpassen     | 30 Min  | ⭐⭐ Mittel
6. Language Files erweitern              | 15 Min  | ⭐ Einfach
7. Tests durchführen                     | 30 Min  | ⭐⭐ Mittel
-----------------------------------------|---------|-------------
GESAMT                                   | 2h 10m  | ⭐⭐ GERING
```

### Option B: Extrafield bei Contact + Status bei Company
```
Aufgabe                                  | Aufwand | Komplexität
-----------------------------------------|---------|-------------
1. Extrafield für Contact anlegen        | 15 Min  | ⭐ Einfach
2. SQL Update-Script erstellen           | 10 Min  | ⭐ Einfach
3. Mapping-UI erweitern (Contact)        | 30 Min  | ⭐⭐⭐ Mittel-Hoch
4. Template-Klasse erweitern             | 15 Min  | ⭐⭐ Mittel
5. createContact() erweitern             | 45 Min  | ⭐⭐⭐⭐ Hoch
6. updateContact() erweitern             | 45 Min  | ⭐⭐⭐⭐ Hoch
7. Company-Objekt Reload-Logik           | 30 Min  | ⭐⭐⭐ Mittel-Hoch
8. Edge-Cases behandeln                  | 30 Min  | ⭐⭐⭐ Mittel-Hoch
9. Language Files erweitern              | 15 Min  | ⭐ Einfach
10. Tests durchführen                    | 45 Min  | ⭐⭐⭐ Mittel-Hoch
-----------------------------------------|---------|-------------
GESAMT                                   | 4h 0m   | ⭐⭐⭐⭐ MITTEL-HOCH
```

---

## 🗂️ Dateien die geändert werden müssen

### Option A (Company-Status)
```
Datei                                           | Änderung
------------------------------------------------|----------------------------------
sql/update_1.2.0_to_1.2.1.sql                  | NEU: Extrafield anlegen
class/contactimporttemplate.class.php           | last_order_column bereits da
class/contactimportprocessor.class.php          | Status-Logik bereits da (testen)
mapping.php                                     | UI für Company-Extrafield
langs/de_DE/contactimport.lang                  | Neue Übersetzungen
langs/en_US/contactimport.lang                  | Neue Übersetzungen
core/modules/modContactimport.class.php         | Version auf 1.2.1 erhöhen
README.md                                       | Feature dokumentieren
changelog.md                                    | Version 1.2.1 hinzufügen
```

### Option B (Contact-Extrafield + Company-Status)
```
Datei                                           | Änderung
------------------------------------------------|----------------------------------
sql/update_1.2.0_to_1.2.1.sql                  | NEU: Extrafield bei Contact
class/contactimporttemplate.class.php           | last_order_column_contact hinzu
class/contactimportprocessor.class.php          | Umfangreiche Änderungen
  - createContact()                             |   + Extrafield setzen
  - updateContact()                             |   + Extrafield setzen
  - neue Methode: updateCompanyStatusFromContact|   + Company Status Update
mapping.php                                     | UI für Contact-Extrafield
preview.php                                     | Vorschau erweitern
langs/de_DE/contactimport.lang                  | Neue Übersetzungen
langs/en_US/contactimport.lang                  | Neue Übersetzungen
core/modules/modContactimport.class.php         | Version auf 1.2.1 erhöhen
README.md                                       | Feature dokumentieren
changelog.md                                    | Version 1.2.1 hinzufügen
```

---

## 💡 Empfehlung

### ✅ **EMPFEHLUNG: Option A - Status bei Company**

**Begründung:**
1. **Dolibarr-Architektur konform:** Status gehört konzeptuell zur Company
2. **80% bereits implementiert:** Logik aus v1.2.0 nutzen
3. **Geringer Aufwand:** 2 Stunden statt 4 Stunden
4. **Wartbar:** Nutzt Standard-Dolibarr-Konzepte
5. **Testbar:** Bestehende Tests anpassen statt neue schreiben

**Prozess:**
- Contact wird importiert mit "letzte Bestellung"
- Daten werden der **Company** zugeordnet (über fk_soc Verknüpfung)
- Company bekommt Extrafield "letzte_bestellung" gesetzt
- Company-Status ändert sich automatisch (bestehende Logik)

**Nutzer-Perspektive:**
- Import funktioniert wie gewohnt
- Status wird bei der Firma gesetzt (logisch korrekt)
- Contact ist mit Customer-Firma verknüpft
- Alle Dolibarr-Features (Rechnungen, Angebote) arbeiten mit Company-Status

---

## 🔧 Implementierungs-Schritte (Option A)

### Phase 1: Datenbank (15 Min)
1. SQL-Script `sql/update_1.2.0_to_1.2.1.sql` erstellen
2. Extrafield für Societe anlegen:
   ```sql
   INSERT INTO llx_extrafields (name, label, type, elementtype, pos, enabled)
   VALUES ('last_order_date', 'Letzte Bestellung', 'date', 'societe', 1000, '1');
   
   ALTER TABLE llx_societe_extrafields 
   ADD COLUMN last_order_date DATE NULL;
   ```

### Phase 2: Backend (45 Min)
1. ✅ `contactimporttemplate.class.php` - bereits vorbereitet
2. ✅ `contactimportprocessor.class.php` - Logik bereits vorhanden, nur testen
3. `mapping.php` - UI für Extrafield-Mapping erweitern

### Phase 3: UI & Sprache (30 Min)
1. Language Files erweitern:
   - `LastOrderDate` / `Letzte Bestellung`
   - `LastOrderDateHelp` / `Datum der letzten Bestellung für automatische Status-Änderung`
2. README.md aktualisieren
3. changelog.md Version 1.2.1 hinzufügen

### Phase 4: Test (30 Min)
1. CSV mit "Letzte Bestellung" Spalte vorbereiten
2. Import durchführen
3. Prüfen: Company Status ändert sich automatisch
4. Edge-Cases testen (leeres Datum, ungültiges Datum)

---

## 📝 Beispiel-CSV

```csv
Firma;Vorname;Nachname;Email;Letzter Auftrag (Datum)
Mustermann GmbH;Max;Mustermann;max@example.com;2025-01-05
Test AG;Anna;Schmidt;anna@test.de;
Neukunde KG;Peter;Müller;peter@neu.de;
Bestandskunde;Maria;Weber;maria@bestand.de;2024-12-20
```

**Erwartetes Ergebnis:**
- Mustermann GmbH → **Customer** (client=1) - hat Datum
- Test AG → **Prospect** (client=2) - kein Datum
- Neukunde KG → **Prospect** (client=2) - kein Datum
- Bestandskunde → **Customer** (client=1) - hat Datum

---

## 🚨 Risiken & Abhängigkeiten

### Risiken (Option A)
- ⭐ **GERING:** Nutzt bewährte Patterns
- ⚠️ Bestehende Logik muss getestet werden (v1.2.0 Feature)

### Risiken (Option B)
- ⭐⭐⭐ **MITTEL:** Komplexere Logik
- ⚠️ Edge-Cases: Contact ohne Company
- ⚠️ Performance: Zusätzliche Company-Loads

### Abhängigkeiten
- Dolibarr 21.0+ für Extrafield-API
- MySQL für Date-Type Feld
- Version 1.2.0 als Basis (wichtig!)

---

## 📈 Zeitplan (Option A)

```
Tag 1 (2-3 Stunden):
├─ 09:00-09:30  SQL-Script + Extrafield erstellen
├─ 09:30-10:30  Backend-Code anpassen (Mapping UI)
├─ 10:30-11:00  Pause / Code Review
├─ 11:00-11:30  Language Files + Doku
└─ 11:30-12:00  Tests durchführen

FERTIG: v1.2.1 einsatzbereit
```

---

## ✅ Fazit

### Aufwand: ⭐⭐ GERING (2 Stunden)
Die Erweiterung ist **technisch unkompliziert**, da:
- Grundstruktur bereits vorhanden (v1.2.0)
- Nur kleine Anpassungen nötig
- Standard-Dolibarr-Konzepte genutzt werden

### Empfehlung: Option A (Company-Status)
- Schnell umsetzbar
- Wartbar & Standard-konform
- Nutzt bestehende v1.2.0 Features

### Nächste Schritte:
1. ✅ Analyse abgeschlossen
2. ⏳ User-Freigabe einholen
3. ⏳ Implementierung starten (Option A)
4. ⏳ Tests durchführen
5. ⏳ Deployment v1.2.1

---

**Erstellt von:** GitHub Copilot  
**Analyst:** AI Assistant  
**Status:** ✅ Bereit für Implementierung
