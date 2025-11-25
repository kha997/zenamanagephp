# Documents Domain Helper Guide

**For:** Future Agent (Builder)  
**Purpose:** Comprehensive implementation guide for Documents Domain test organization  
**Reference:** `docs/work-packages/documents-domain.md` (main work package)  
**Audit:** `docs/work-packages/documents-domain-audit.md` (file inventory)

---

## Overview

This guide will help you implement the Documents Domain test organization work package. The goal is to:

1. Add `@group documents` annotations to all documents-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedDocumentsDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `45678` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `documents-unit`, `documents-feature`, `documents-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/documents-domain-audit.md` for file inventory

---

## File Inventory

### Files to Add @group Annotations (11 files)

**Feature Tests (9 files):**
1. `tests/Feature/DocumentApiTest.php`
2. `tests/Feature/Api/Documents/DocumentsContractTest.php`
3. `tests/Feature/Api/DocumentManagementTest.php`
4. `tests/Feature/DocumentVersioningTest.php`
5. `tests/Feature/DocumentVersioningSimpleTest.php`
6. `tests/Feature/DocumentVersioningNoFKTest.php`
7. `tests/Feature/DocumentVersioningDebugTest.php`
8. `tests/Feature/Unit/Policies/DocumentPolicyTest.php`
9. `tests/Feature/Unit/Policies/DocumentPolicySimpleTest.php`

**Unit Tests (1 file):**
1. `tests/Unit/DocumentPolicyTest.php`

**Browser Tests (1 file):**
1. `tests/Browser/DocumentManagementTest.php`

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group documents` annotation to all documents test files.

#### Example: Adding Annotation

**Before:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests cho Document API endpoints
 */
class DocumentApiTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @group documents
 * Feature tests cho Document API endpoints
 */
class DocumentApiTest extends TestCase
{
    // ...
}
```

#### Verification

After adding annotations, verify:
```bash
grep -r "@group documents" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected: All 11 files should appear.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Verification Commands

```bash
php artisan test --testsuite=documents-unit
php artisan test --testsuite=documents-feature
php artisan test --testsuite=documents-integration
php artisan test --group=documents --seed=45678
```

---

### Phase 3: Implement seedDocumentsDomain Method

**Goal:** Add `seedDocumentsDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed documents domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete documents domain test setup including:
 * - Tenant
 * - Users (project manager, team members)
 * - Projects (for documents to belong to)
 * - Documents with different statuses and visibility
 * - Document versions
 * 
 * @param int $seed Fixed seed value (default: 45678)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     projects: \App\Models\Project[],
 *     documents: \App\Models\Document[],
 *     document_versions: \App\Models\DocumentVersion[]
 * }
 */
public static function seedDocumentsDomain(int $seed = 45678): array
```

#### Implementation Template

```php
public static function seedDocumentsDomain(int $seed = 45678): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Documents Test Tenant',
        'slug' => 'documents-test-tenant',
        'status' => 'active',
    ]);
    
    // Create users
    $users = [];
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Documents PM User',
        'email' => 'pm@documents-test.test',
        'password' => 'password',
    ]);
    
    $users['team_member'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Documents Team Member',
        'email' => 'member@documents-test.test',
        'password' => 'password',
    ]);
    
    // Create a project for documents
    $project = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'DOC-PROJ-001',
        'name' => 'Documents Test Project',
        'description' => 'Project for documents domain testing',
        'status' => 'active',
        'owner_id' => $users['project_manager']->id,
        'budget_total' => 30000.00,
        'start_date' => now(),
        'end_date' => now()->addMonths(2),
    ]);
    
    // Create documents with different statuses and visibility
    $documents = [];
    
    // Internal document
    $documents['internal'] = \App\Models\Document::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Internal Test Document',
        'original_name' => 'internal-doc.pdf',
        'file_path' => 'documents/test/internal-doc.pdf',
        'file_type' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 102400, // 100 KB
        'category' => 'internal',
        'description' => 'An internal document',
        'status' => 'active',
        'visibility' => 'internal',
        'is_public' => false,
        'uploaded_by' => $users['project_manager']->id,
        'created_by' => $users['project_manager']->id,
    ]);
    
    // Client-visible document
    $documents['client'] = \App\Models\Document::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Client Test Document',
        'original_name' => 'client-doc.pdf',
        'file_path' => 'documents/test/client-doc.pdf',
        'file_type' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 204800, // 200 KB
        'category' => 'client',
        'description' => 'A client-visible document',
        'status' => 'active',
        'visibility' => 'client',
        'is_public' => true,
        'requires_approval' => true,
        'uploaded_by' => $users['project_manager']->id,
        'created_by' => $users['project_manager']->id,
    ]);
    
    // Document with versions
    $documents['versioned'] = \App\Models\Document::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Versioned Test Document',
        'original_name' => 'versioned-doc.pdf',
        'file_path' => 'documents/test/versioned-doc-v1.pdf',
        'file_type' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 153600, // 150 KB
        'category' => 'internal',
        'description' => 'A document with multiple versions',
        'status' => 'active',
        'visibility' => 'internal',
        'is_public' => false,
        'version' => 1,
        'is_current_version' => true,
        'uploaded_by' => $users['team_member']->id,
        'created_by' => $users['team_member']->id,
    ]);
    
    // Create document versions
    $documentVersions = [];
    
    // Version 1 (already created as document)
    $documentVersions[] = \App\Models\DocumentVersion::create([
        'document_id' => $documents['versioned']->id,
        'version_number' => 1,
        'file_path' => 'documents/test/versioned-doc-v1.pdf',
        'storage_driver' => 'local',
        'comment' => 'Initial version',
        'created_by' => $users['team_member']->id,
    ]);
    
    // Version 2
    $documentVersions[] = \App\Models\DocumentVersion::create([
        'document_id' => $documents['versioned']->id,
        'version_number' => 2,
        'file_path' => 'documents/test/versioned-doc-v2.pdf',
        'storage_driver' => 'local',
        'comment' => 'Updated version',
        'created_by' => $users['project_manager']->id,
    ]);
    
    // Update document to point to latest version
    $documents['versioned']->update([
        'file_path' => 'documents/test/versioned-doc-v2.pdf',
        'version' => 2,
    ]);
    
    return [
        'tenant' => $tenant,
        'users' => array_values($users),
        'projects' => [$project],
        'documents' => array_values($documents),
        'document_versions' => $documentVersions,
    ];
}
```

#### Key Points

- Use `mt_srand($seed)` at the start for reproducibility
- Create documents with different visibility: internal, client
- Create documents with different statuses: active, archived, draft
- Create document versions for versioning tests
- Documents must belong to a project
- Set proper file paths, mime types, and file sizes
- Return structured array with all created entities

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/documents/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 45678,
  "domain": "documents",
  "document_visibility": ["internal", "client"],
  "document_statuses": ["active", "archived", "draft"],
  "documents": [
    {
      "name": "Internal Test Document",
      "visibility": "internal",
      "status": "active"
    },
    {
      "name": "Client Test Document",
      "visibility": "client",
      "status": "active"
    },
    {
      "name": "Versioned Test Document",
      "visibility": "internal",
      "status": "active",
      "versions": 2
    }
  ]
}
```

---

### Phase 5: Playwright Projects (Optional)

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package.

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running documents tests.

#### Scripts to Add

```json
{
  "scripts": {
    "test:documents": "php artisan test --group=documents",
    "test:documents:unit": "php artisan test --testsuite=documents-unit",
    "test:documents:feature": "php artisan test --testsuite=documents-feature",
    "test:documents:integration": "php artisan test --testsuite=documents-integration",
    "test:documents:e2e": "playwright test --project=documents-e2e-chromium"
  }
}
```

---

## Common Pitfalls

### 1. Document Visibility Values

**Problem:** Using invalid visibility values.

**Solution:** Use valid visibility: `internal`, `client`

### 2. Missing Project Reference

**Problem:** Documents must belong to a project.

**Solution:** Always set `project_id` when creating documents:
```php
$document = Document::create([
    'tenant_id' => $tenant->id,
    'project_id' => $project->id, // Required
    'name' => 'Document Name',
    // ...
]);
```

### 3. Document Versions

**Problem:** Document versions not properly linked to documents.

**Solution:** Ensure `document_id` is set when creating versions:
```php
DocumentVersion::create([
    'document_id' => $document->id, // Required
    'version_number' => 1,
    'file_path' => 'path/to/file.pdf',
    // ...
]);
```

### 4. File Storage

**Problem:** File paths or storage not properly configured in tests.

**Solution:** Use mock storage or ensure test storage is configured:
```php
Storage::fake('documents');
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group documents" tests/Feature/ tests/Unit/ ...`
2. **Run test suites:** `php artisan test --testsuite=documents-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedDocumentsDomain:** `php artisan test --group=documents --seed=45678`

---

## Completion Checklist

- [ ] All 11 files have `@group documents` annotation
- [ ] Test suites run successfully
- [ ] `seedDocumentsDomain()` method exists and works correctly
- [ ] Fixtures file created
- [ ] NPM scripts added (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `45678`

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/documents-domain.md`
- **File Audit:** `docs/work-packages/documents-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Future Agent (Builder)

