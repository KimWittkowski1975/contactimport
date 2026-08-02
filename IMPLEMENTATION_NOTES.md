# ContactImport v1.3.0 - Implementation Notes

## Dual-Mapping System Implementation

### Changes Made:

1. **Mapping UI (mapping.php)**
   - ✅ Two separate tables: Company Mapping + Contact Mapping
   - ✅ Each CSV column can be mapped to BOTH Company AND Contact fields
   - ✅ Beautiful gradient styles for visual separation
   - ✅ Sample data preview for each column
   - ✅ Collapsible full CSV preview

2. **Language Strings**
   - ✅ Added new translations (DE + EN):
     - AlwaysImportBothCompanyAndContact
     - EachCSVRowCreatesCompanyAndLinkedContact
     - CompanyMapping / ContactMapping
     - MapToCompanyField / MapToContactField
     - SampleData
     - And more...

3. **About Page**
   - ✅ Updated version to 1.3.0
   - ✅ Added changelog entry for Dual-Mapping system

4. **Processor Logic** - **PENDING**
   - ⚠️ Current: Duplicates are SKIPPED (return -1)
   - ⚠️ Required: Duplicates should be UPDATED
   - ⚠️ Methods exist: `updateCompany()` and `updateContact()`
   - ⚠️ Need to call them when `-1` is returned

### TODO for Processor Update:

In `processImport()` method (around line 337-375):

**Current behavior:**
```php
elseif ($company_id === -1) {
    // Duplicate found - skip this line
    $result['duplicates_skipped']++;
    $this->logError($session->id, $line_count, 'company', implode(', ', $this->errors), 'skipped');
}
```

**Required behavior:**
```php
elseif ($company_id === -1) {
    // Duplicate found - extract ID from error message
    if (preg_match('/ID:\s*(\d+)/', implode(' ', $this->errors), $matches)) {
        $duplicate_id = (int)$matches[1];
        $this->errors = array(); // Clear duplicate error
        
        // Build temp company object with new data
        $temp_company = new Societe($this->db);
        // Map all CSV data to temp object
        $last_order_date = null;
        foreach ($mapping_config['company'] as $csv_idx => $field) {
            $value = isset($csv_line[$csv_idx]) ? trim($csv_line[$csv_idx]) : '';
            if ($field === 'options_last_order_date' && !empty($value)) {
                $last_order_date = $value;
            }
            // ... map other fields ...
        }
        
        // Update existing company
        $update_result = $this->updateCompany($duplicate_id, $temp_company, $user, $last_order_date);
        if ($update_result > 0) {
            $company_id = $duplicate_id;
            $result['companies_updated']++;
        }
    }
}
```

Same logic for contacts.

### Testing Checklist:

- [ ] Upload CSV with new data → Creates Company + Contact
- [ ] Upload same CSV again → Updates existing (fills empty fields)
- [ ] Upload CSV with "Last Order Date" filled → Company becomes Customer
- [ ] Upload CSV with "Last Order Date" empty → Company stays Prospect
- [ ] Upload CSV with updated "Last Order Date" → Prospect becomes Customer
- [ ] Check logs for errors
- [ ] Verify dual-mapping UI works correctly
- [ ] Test with German and English locales

### Files Modified:

1. `/custom/contactimport/mapping.php` - Dual-Mapping UI
2. `/custom/contactimport/admin/about.php` - Version + Changelog
3. `/custom/contactimport/langs/de_DE/contactimport.lang` - German strings
4. `/custom/contactimport/langs/en_US/contactimport.lang` - English strings
5. `/custom/contactimport/class/contactimportprocessor.class.php` - **PENDING UPDATE**

### Next Steps:

1. Complete Processor update logic
2. Test full import cycle
3. Update version in `core/modules/modContactimport.class.php`
4. Update `changelog.md`
5. Git commit
6. Update README.md

### Known Issues:

- Processor still skips duplicates instead of updating them
- Need to add proper extraction of duplicate_id from error messages
- Result array needs `companies_updated` and `contacts_updated` counters

