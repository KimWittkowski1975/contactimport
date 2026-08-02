# ContactImport Module - Installation Guide

## 🚀 Quick Installation (v1.3.9+)

### For Fresh Dolibarr Installations:

1. **Copy module to custom folder:**
   ```bash
   cp -r contactimport /path/to/dolibarr/htdocs/custom/
   ```

2. **Activate module in Dolibarr:**
   - Navigate to: **Home → Setup → Modules**
   - Search for "ContactImport"
   - Click **Activate**

3. **✅ Done! Everything is auto-configured:**
   - ✓ Database tables created
   - ✓ Default JTL template created (`JTL_MASTER_TEMPLATE`)
   - ✓ Extrafield `last_order_date` created
   - ✓ Ready to import!

---

## 🔄 Upgrade from Older Versions

### If upgrading from v1.3.8 or earlier:

**Option A: Quick Method (Recommended)**
1. Deactivate the module
2. Reactivate the module
3. New default template and extrafield will be created automatically

**Option B: Manual Method**
1. Run SQL update scripts in order:
   ```sql
   -- If upgrading from v1.2.0 or earlier:
   source /path/to/dolibarr/htdocs/custom/contactimport/sql/update_1.2.0_to_1.2.1.sql
   ```
2. Reactivate module to create new default template

---

## 📋 What Gets Installed Automatically

### 1. Database Tables:
- `llx_contactimport_sessions` - Import session tracking
- `llx_contactimport_logs` - Detailed import logs
- `llx_contactimport_templates` - Reusable mapping templates
- `llx_contactimport_ftp_config` - FTP auto-import configuration
- `llx_contactimport_duplicate_logs` - Duplicate management logs

### 2. Default Template: `JTL_MASTER_TEMPLATE`
**Optimized for JTL Kundenstammdaten Exports**

| CSV Column | Index | Maps to Company | Maps to Contact |
|------------|-------|----------------|-----------------|
| Firma | 5 | ✓ nom | - |
| Vorname | 3 | - | ✓ firstname |
| Nachname | 4 | - | ✓ lastname |
| Straße und Nr. | 7 | ✓ address | ✓ address |
| PLZ | 9 | ✓ zip | ✓ zip |
| Ort | 10 | ✓ town | ✓ town |
| Mobil | 11 | - | ✓ phone_mobile |
| Telefon | 12 | ✓ phone | ✓ phone |
| E-Mail-Adresse | 13 | ✓ email | ✓ email |
| Letzter Auftrag (Datum) | 19 | ✓ last_order_date | - |

### 3. Extrafield:
- **Name:** `last_order_date`
- **Type:** Date
- **Element:** ThirdParty/Societe
- **Purpose:** Automatic Customer/Prospect status assignment

---

## 🎯 Automatic Status Logic

The module automatically sets customer status based on "Last Order Date":

| Last Order Date | Company Status | client value |
|----------------|----------------|--------------|
| **Empty** | Prospect (Interessent) | 2 |
| **Filled** | Customer (Kunde) | 1 |

This happens automatically during import - no manual configuration needed!

---

## ✅ Verify Installation

### Check 1: Tables Created
```sql
SHOW TABLES LIKE 'llx_contactimport%';
-- Should show 5 tables
```

### Check 2: Default Template Exists
```sql
SELECT ref, label FROM llx_contactimport_templates WHERE ref = 'JTL_MASTER_TEMPLATE';
-- Should return 1 row
```

### Check 3: Extrafield Exists
```sql
SELECT name, label, elementtype FROM llx_extrafields WHERE name = 'last_order_date';
-- Should return: last_order_date | Letzter Auftrag (Datum) | societe
```

### Check 4: Extrafield Column Exists
```sql
SHOW COLUMNS FROM llx_societe_extrafields LIKE 'last_order_date';
-- Should show DATE column
```

---

## 🛠️ Manual Fixes (if needed)

### If Extrafield is Missing:

**Symptom:** Error message during import: "Extrafield column 'last_order_date' does not exist"

**Fix:**
```bash
# Option 1: Deactivate and reactivate module (easiest)
# Go to Setup → Modules → ContactImport → Deactivate → Activate

# Option 2: Run SQL manually
mysql -u dolibarr -p dolibarrdb < /path/to/custom/contactimport/sql/update_1.2.0_to_1.2.1.sql
```

### If Default Template is Missing:

**Fix:**
```bash
# Deactivate and reactivate module
# Or run data SQL manually:
mysql -u dolibarr -p dolibarrdb < /path/to/custom/contactimport/sql/llx_contactimport_data.sql
```

---

## 📞 Support

- **Module Version:** 1.3.9
- **Dolibarr Version:** 21.0+
- **Author:** Kim Wittkowski
- **Email:** kim@wittkowski-it.de

---

## 📝 Next Steps After Installation

1. Go to **Tools → Contact Import → CSV Upload**
2. Upload your JTL Kundenstammdaten CSV
3. Select template: **JTL Import Template**
4. Start import
5. Verify results:
   - Email addresses imported? ✓
   - Customer status correct? ✓
   - Last order date filled? ✓

**Happy Importing! 🎉**
