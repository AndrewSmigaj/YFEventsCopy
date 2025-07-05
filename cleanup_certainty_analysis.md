# YFEvents Cleanup Certainty Analysis Report

## Executive Summary
This report analyzes the safety of removing or moving various files and directories in the YFEvents repository as part of a cleanup effort.

## Items Marked for REMOVAL

### 1. ✅ admin/ directory - **SAFE TO REMOVE WITH CAUTION**
**Certainty: 85%**

**Evidence:**
- Contains hardcoded password ('admin123') in index.php:14
- Referenced in multiple places but primarily for routing and navigation
- Admin functionality has been moved to /www/html/admin/

**References Found:**
- Routes/navigation: routes/web.php, multiple test files
- Direct links: admin/index.php references from system-debug.php, admin pages
- Module references: YFAuth, YFClaim admin panels

**Risks:**
- Some legacy links may break
- Need to update routes pointing to /admin/

**Recommendation:** 
1. Update all references to point to /www/html/admin/ first
2. Add redirect rules in .htaccess from /admin/* to /www/html/admin/*
3. Then remove the directory

---

### 2. ✅ bin/ directory - **SAFE TO REMOVE**
**Certainty: 95%**

**Evidence:**
- Directory is completely empty
- References found are mostly in scripts looking for binaries

**References Found:**
- Shell scripts: setup_github.sh, refactor.sh, various deployment scripts
- Composer.lock: references to vendor/bin/ (not this bin/)

**Risks:** None identified

**Recommendation:** Safe to remove immediately

---

### 3. ✅ var/ directory - **SAFE TO REMOVE**
**Certainty: 90%**

**Evidence:**
- Contains only empty subdirectories (cache/, logs/, sessions/)
- Alternative directories exist: storage/, logs/, www/html/var/

**References Found:**
- architecture.yaml: mentions var/ in directory structure
- Deploy scripts: some references but appear to be for server setup

**Risks:** 
- Some deployment scripts may expect this directory

**Recommendation:** 
1. Check if any runtime code writes to var/
2. Update deployment scripts if needed
3. Remove directory

---

### 4. ✅ logs/ directory - **SAFE TO REMOVE**
**Certainty: 95%**

**Evidence:**
- Directory is completely empty
- Alternative log locations exist (storage/logs/, var/logs/)

**References Found:**
- Various scrapers and error handlers reference logs/
- Config files point to logs directory

**Risks:**
- Scripts may fail if they try to write logs

**Recommendation:**
1. Update all log paths to use storage/logs/
2. Remove directory

---

### 5. ❌ refactor.sh - **NOT SAFE TO REMOVE**
**Certainty: 20%**

**Evidence:**
- Active refactoring script with substantial logic
- Creates the new clean architecture structure
- No direct references found, but appears to be an important utility

**Risks:**
- May be needed for ongoing refactoring efforts
- Contains valuable migration logic

**Recommendation:** Keep until refactoring is complete

---

### 6. ✅ setup_github.sh - **SAFE TO REMOVE**
**Certainty: 90%**

**Evidence:**
- Only one reference found in a JSON report file
- Likely a one-time setup script

**References Found:**
- codebase_verification_report_2025_06_10_104450.json

**Risks:** None identified

**Recommendation:** Safe to remove

---

### 7. ✅ codebase_verification_report_2025_06_10_104450.json - **SAFE TO REMOVE**
**Certainty: 95%**

**Evidence:**
- Old verification report from June 2025
- Referenced only by mapper scripts

**References Found:**
- codebase_mapper.php, codebase_mapper_v2.php

**Risks:** None - it's just a report file

**Recommendation:** Safe to remove

---

### 8. ⚠️ css-diagnostic.php - **REMOVE WITH UPDATES**
**Certainty: 80%**

**Evidence:**
- Diagnostic tool referenced by admin pages
- Duplicate exists at /www/html/css-diagnostic.php

**References Found:**
- admin/index.php: direct link on line 371
- admin/system-status.php, system-debug.php
- modules/yftheme/cleanup.php

**Risks:**
- Admin links will break

**Recommendation:**
1. Update all references to point to /www/html/css-diagnostic.php
2. Remove file

---

### 9. ✅ yakima_calendar_spec.md:Zone.Identifier - **SAFE TO REMOVE**
**Certainty: 100%**

**Evidence:**
- Windows Zone.Identifier file (download metadata)
- No functional purpose in the codebase

**Risks:** None

**Recommendation:** Remove immediately

---

### 10. ✅ codebase_mapper.php - **SAFE TO REMOVE**
**Certainty: 90%**

**Evidence:**
- Superseded by codebase_mapper_v2.php
- Only referenced in the old JSON report

**References Found:**
- codebase_verification_report_2025_06_10_104450.json

**Risks:** None if v2 is working

**Recommendation:** Safe to remove

---

## Items Marked for MOVING

### 1. ✅ codebase_mapper_v2.php → scripts/development/
**Certainty: 95%**

**Evidence:**
- No references found in codebase
- Development utility script

**Recommendation:** Safe to move. Create scripts/development/ directory first.

---

### 2. ✅ SQL seed files → database/seeds/
**Certainty: 95%**

**Evidence:**
- add_new_calendar_sources.sql
- add_sources_simple.sql
- Standard practice to keep seeds in database directory

**Recommendation:** Safe to move. Directory already exists.

---

### 3. ✅ create_triggers.sql → database/
**Certainty: 95%**

**Evidence:**
- Database schema file
- Belongs with other database files

**Recommendation:** Safe to move.

---

### 4. ⚠️ system-debug.php → scripts/development/
**Certainty: 70%**

**Evidence:**
- Referenced by admin/index.php line 368
- Actively used diagnostic tool

**References Found:**
- admin/index.php: direct link

**Risks:**
- Admin panel link will break

**Recommendation:**
1. Keep in root OR
2. Move and update admin/index.php reference
3. Consider keeping in public web root for easy access

---

### 5. ✅ Sample files → database/fixtures/
**Certainty: 95%**

**Evidence:**
- sample_shops.csv
- sample_shops.json
- Test data files

**Recommendation:** Safe to move. Create fixtures/ directory first.

---

## Summary Recommendations

### Safe to Remove Immediately:
1. bin/ (empty directory)
2. logs/ (empty directory) 
3. yakima_calendar_spec.md:Zone.Identifier
4. setup_github.sh
5. codebase_verification_report_2025_06_10_104450.json
6. codebase_mapper.php

### Remove After Updates:
1. admin/ (update routes and add redirects first)
2. var/ (check runtime usage first)
3. css-diagnostic.php (update references first)

### Keep for Now:
1. refactor.sh (active refactoring script)

### Safe to Move:
1. All SQL files to appropriate database directories
2. codebase_mapper_v2.php to scripts/development/
3. Sample files to database/fixtures/

### Move with Caution:
1. system-debug.php (update references or keep in web root)

## Implementation Order

1. **Phase 1**: Remove immediately safe items
2. **Phase 2**: Create new directories (scripts/development/, database/seeds/, database/fixtures/)
3. **Phase 3**: Move files to new locations
4. **Phase 4**: Update references for items requiring changes
5. **Phase 5**: Remove remaining items after verification

## Verification Steps

Before removing:
```bash
# Check for any runtime file access
grep -r "fopen\|file_get_contents\|require\|include" . | grep -E "bin/|var/|logs/"

# Check for hardcoded paths
grep -r "/admin/" --include="*.php" .

# Test admin panel after changes
curl -I http://localhost/admin/
```

After cleanup:
```bash
# Run all tests
php tests/run_all_tests.php

# Check for broken links
php scripts/test_external_links.php
```