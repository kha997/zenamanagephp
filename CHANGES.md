## 🐞 **E2E Workflow Database Connection & Migration Fixes**
**Date**: 2024-11-20 (Updated 2025-01-27)
**Builder**: AI Assistant
**Status**: ✅ COMPLETE - Fixed database connection mismatch and migration compatibility issues.

### Summary:
- **Issue 1**: E2E Smoke Tests Debug workflow was failing with SQLite errors even though MySQL was configured. Migration `2025_10_07_021725_add_created_by_updated_by_to_documents_table` was attempting to query `information_schema.KEY_COLUMN_USAGE` which doesn't exist in SQLite.
- **Issue 2**: Playwright global setup was overriding MySQL config with SQLite defaults, causing migrations to run with SQLite instead of MySQL.
- **Root Cause**: 
  1. Migration was querying `information_schema` (MySQL-specific) regardless of database driver
  2. Global setup only read `.env.e2e` file (doesn't exist in CI) and defaulted to SQLite
  3. Laravel config cache wasn't cleared after setting DB config in workflow

### Resolution:
1. **Migration Fix**: Wrapped foreign key constraints in `if (DBDriver::isMysql())` check to prevent execution on SQLite
2. **Global Setup Fix**: Modified `tests/E2E/setup/global-setup.ts` to:
   - Read both `.env` (workflow created) and `.env.e2e` (local tests)
   - Check `process.env` for DB config (CI/workflow)
   - Only default to SQLite if no DB config is found
   - Skip migrations in CI since workflow already runs them
3. **Workflow Improvements**: Added steps to:
   - Clear Laravel config cache before migrations
   - Verify database configuration before running migrations
   - Validate DB driver is MySQL before executing migrations

### Files Modified:
* `database/migrations/2025_10_07_021725_add_created_by_updated_by_to_documents_table.php`: 
  - Wrapped foreign key constraints in `if (DBDriver::isMysql())` conditional check
  - Prevents querying `information_schema` when using SQLite
  
* `tests/E2E/setup/global-setup.ts`: 
  - Updated `buildArtisanEnv()` to read `.env` file (workflow created)
  - Added logic to merge `.env` and `.env.e2e` configs
  - Only defaults to SQLite if no DB config is found
  - Skip migrations in CI environments
  
* `.github/workflows/e2e-smoke-debug.yml`: 
  - Added "Clear Laravel config cache" step
  - Added "Verify database configuration" step
  - Enhanced "Create database" step with DB driver verification
  - Improved error reporting for migration failures

### Technical Details:
The migration uses `SqliteCompatibleMigration` trait which provides `addForeignKeyConstraint()` method. However, Laravel's Blueprint automatically checks for existing foreign keys by querying `information_schema.KEY_COLUMN_USAGE`. By wrapping the foreign key creation in a MySQL check, we ensure it only executes when using MySQL.

### Testing:
- ✅ Migration now works with both MySQL (CI/workflow) and SQLite (local tests)
- ✅ Workflow correctly uses MySQL for migrations
- ✅ Local tests can still use SQLite for faster execution

---

## 🐞 **PHP Version Investigation**
**Date**: 2024-11-19
**Builder**: AI Assistant
**Status**: ✅ COMPLETE - Investigated PHP version mismatch. Recommended solution is to use `php artisan serve`.

### Summary:
- **Issue**: XAMPP's Apache was using PHP 8.0.28, while the project requires PHP >= 8.2.0. The CLI was correctly using 8.2.29.
- **Root Cause**: XAMPP requires a specific build of PHP modules for its Apache server. Simply copying or symlinking the module from another PHP installation (like Homebrew's) does not work due to architectural mismatches.
- **Attempted Fixes**:
  - Symlinking Homebrew's PHP 8.2 to XAMPP's `bin` directory. This updates the CLI version for XAMPP but does not affect the version used by Apache.
  - Attempting to modify Apache's configuration (`httpd.conf`) was not possible due to file system limitations and security restrictions in the current environment.
- **Resolution**: The most reliable and recommended approach for local development is to use Laravel's built-in web server.

### How to Run the Application:
1.  **Start the server**:

