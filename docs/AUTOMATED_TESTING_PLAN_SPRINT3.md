# Automated Testing Plan - Sprint 3: Documents & Change-Requests

**Date:** 2025-01-17  
**Status:** Ready for Implementation

---

## Overview

This document outlines the automated testing strategy for Sprint 3 modules (Documents & Change-Requests). Tests are organized into three layers:

1. **Unit Tests** - Fast, isolated tests for services, mappers, validators
2. **Integration Tests** - API endpoints with database, auth, RBAC, multi-tenant
3. **E2E Tests** - Critical user paths using Playwright

---

## Test Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── FileStorageServiceTest.php
│   │   └── ChangeRequestServiceTest.php (if service exists)
│   └── Models/
│       ├── DocumentTest.php
│       └── ChangeRequestTest.php
├── Feature/
│   ├── Api/
│   │   ├── Documents/
│   │   │   ├── DocumentsListEndpointTest.php
│   │   │   ├── DocumentDetailEndpointTest.php
│   │   │   ├── DocumentUploadEndpointTest.php
│   │   │   ├── DocumentUpdateEndpointTest.php
│   │   │   ├── DocumentDeleteEndpointTest.php
│   │   │   ├── DocumentsKpisEndpointTest.php
│   │   │   ├── DocumentsAlertsEndpointTest.php
│   │   │   └── DocumentsActivityEndpointTest.php
│   │   └── ChangeRequests/
│   │       ├── ChangeRequestsListEndpointTest.php
│   │       ├── ChangeRequestDetailEndpointTest.php
│   │       ├── ChangeRequestCreateEndpointTest.php
│   │       ├── ChangeRequestUpdateEndpointTest.php
│   │       ├── ChangeRequestDeleteEndpointTest.php
│   │       ├── ChangeRequestSubmitEndpointTest.php
│   │       ├── ChangeRequestApproveEndpointTest.php
│   │       ├── ChangeRequestRejectEndpointTest.php
│   │       ├── ChangeRequestsKpisEndpointTest.php
│   │       ├── ChangeRequestsAlertsEndpointTest.php
│   │       └── ChangeRequestsActivityEndpointTest.php
│   └── TenantIsolation/
│       ├── DocumentsTenantIsolationTest.php
│       └── ChangeRequestsTenantIsolationTest.php
└── E2E/
    ├── documents/
    │   ├── documents-list.spec.ts
    │   ├── document-upload.spec.ts
    │   ├── document-detail.spec.ts
    │   └── document-approvals.spec.ts
    └── change-requests/
        ├── change-requests-list.spec.ts
        ├── change-request-create.spec.ts
        ├── change-request-detail.spec.ts
        ├── change-request-submit.spec.ts
        ├── change-request-approve.spec.ts
        └── change-request-reject.spec.ts
```

---

## Unit Tests

### 1. FileStorageServiceTest.php

**Purpose:** Test file storage lifecycle, virus scanning, TTL links

**Test Cases:**

```php
class FileStorageServiceTest extends TestCase
{
    // File Upload
    public function test_can_upload_document_with_valid_file()
    public function test_rejects_file_exceeding_size_limit()
    public function test_rejects_invalid_file_type()
    public function test_stores_file_with_correct_path()
    public function test_generates_file_hash()
    
    // Virus Scanning
    public function test_queues_virus_scan_for_large_files()
    public function test_scans_small_files_immediately()
    public function test_marks_file_as_infected_on_virus_detection()
    
    // TTL Links
    public function test_generates_ttl_link_with_expiration()
    public function test_ttl_link_expires_after_timeout()
    public function test_ttl_link_invalid_after_expiration()
    
    // Lifecycle
    public function test_transitions_document_from_draft_to_active()
    public function test_archives_old_documents()
    public function test_cleans_up_orphaned_files()
}
```

### 2. DocumentTest.php

**Purpose:** Test Document model relationships and scopes

**Test Cases:**

```php
class DocumentTest extends TestCase
{
    public function test_belongs_to_tenant()
    public function test_belongs_to_project()
    public function test_belongs_to_uploader()
    public function test_has_many_versions()
    public function test_scope_filters_by_tenant()
    public function test_soft_deletes()
}
```

### 3. ChangeRequestTest.php

**Purpose:** Test ChangeRequest model relationships, status transitions

**Test Cases:**

```php
class ChangeRequestTest extends TestCase
{
    // Relationships
    public function test_belongs_to_tenant()
    public function test_belongs_to_project()
    public function test_belongs_to_requester()
    public function test_has_many_approvals()
    public function test_has_many_comments()
    
    // Status Transitions
    public function test_can_transition_from_draft_to_awaiting_approval()
    public function test_can_transition_from_awaiting_approval_to_approved()
    public function test_can_transition_from_awaiting_approval_to_rejected()
    public function test_cannot_transition_from_approved_to_draft()
    public function test_can_transition_from_rejected_to_draft()
    
    // Scopes
    public function test_scope_filters_by_tenant()
    public function test_soft_deletes()
}
```

---

## Integration Tests - Documents API

### DocumentsListEndpointTest.php

**Purpose:** Test GET /api/v1/app/documents

**Test Cases:**

```php
class DocumentsListEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_paginated_documents()
    public function test_filters_by_search_term()
    public function test_filters_by_project_id()
    public function test_filters_by_status()
    public function test_filters_by_mime_type()
    public function test_sorts_by_created_at_desc()
    public function test_respects_per_page_parameter()
    public function test_requires_authentication()
    public function test_only_returns_tenant_documents()
    public function test_returns_empty_array_when_no_documents()
}
```

### DocumentDetailEndpointTest.php

**Purpose:** Test GET /api/v1/app/documents/{id}

**Test Cases:**

```php
class DocumentDetailEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_document_details()
    public function test_includes_project_relationship()
    public function test_includes_uploader_relationship()
    public function test_returns_404_for_nonexistent_document()
    public function test_returns_403_for_different_tenant_document()
    public function test_requires_authentication()
}
```

### DocumentUploadEndpointTest.php

**Purpose:** Test POST /api/v1/app/documents

**Test Cases:**

```php
class DocumentUploadEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_uploads_document_successfully()
    public function test_requires_file()
    public function test_validates_file_size()
    public function test_validates_file_type()
    public function test_saves_document_metadata()
    public function test_assigns_to_tenant()
    public function test_sets_uploader()
    public function test_queues_virus_scan_for_large_files()
    public function test_requires_upload_permission()
    public function test_creates_audit_log_entry()
}
```

### DocumentUpdateEndpointTest.php

**Purpose:** Test PUT /api/v1/app/documents/{id}

**Test Cases:**

```php
class DocumentUpdateEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_updates_document_metadata()
    public function test_updates_description()
    public function test_updates_tags()
    public function test_returns_404_for_nonexistent_document()
    public function test_returns_403_for_different_tenant_document()
    public function test_requires_update_permission()
    public function test_creates_audit_log_entry()
}
```

### DocumentDeleteEndpointTest.php

**Purpose:** Test DELETE /api/v1/app/documents/{id}

**Test Cases:**

```php
class DocumentDeleteEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_deletes_document()
    public function test_soft_deletes_document()
    public function test_returns_404_for_nonexistent_document()
    public function test_returns_403_for_different_tenant_document()
    public function test_requires_delete_permission()
    public function test_creates_audit_log_entry()
}
```

### DocumentsKpisEndpointTest.php

**Purpose:** Test GET /api/v1/app/documents/kpis

**Test Cases:**

```php
class DocumentsKpisEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_kpi_data()
    public function test_includes_total_count()
    public function test_includes_pending_count()
    public function test_includes_approved_count()
    public function test_filters_by_period()
    public function test_only_includes_tenant_documents()
    public function test_requires_authentication()
}
```

### DocumentsAlertsEndpointTest.php

**Purpose:** Test GET /api/v1/app/documents/alerts

**Test Cases:**

```php
class DocumentsAlertsEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_alerts()
    public function test_includes_overdue_documents()
    public function test_includes_pending_approvals()
    public function test_only_includes_tenant_alerts()
    public function test_requires_authentication()
}
```

### DocumentsActivityEndpointTest.php

**Purpose:** Test GET /api/v1/app/documents/activity

**Test Cases:**

```php
class DocumentsActivityEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_activity_feed()
    public function test_respects_limit_parameter()
    public function test_orders_by_most_recent()
    public function test_only_includes_tenant_activity()
    public function test_requires_authentication()
}
```

---

## Integration Tests - Change Requests API

### ChangeRequestsListEndpointTest.php

**Purpose:** Test GET /api/v1/app/change-requests

**Test Cases:**

```php
class ChangeRequestsListEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_paginated_change_requests()
    public function test_filters_by_search_term()
    public function test_filters_by_status()
    public function test_filters_by_priority()
    public function test_filters_by_project_id()
    public function test_filters_by_change_type()
    public function test_sorts_by_created_at_desc()
    public function test_respects_per_page_parameter()
    public function test_requires_authentication()
    public function test_only_returns_tenant_change_requests()
    public function test_returns_empty_array_when_no_change_requests()
}
```

### ChangeRequestDetailEndpointTest.php

**Purpose:** Test GET /api/v1/app/change-requests/{id}

**Test Cases:**

```php
class ChangeRequestDetailEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_change_request_details()
    public function test_includes_project_relationship()
    public function test_includes_requester_relationship()
    public function test_includes_approvals()
    public function test_includes_comments()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_authentication()
}
```

### ChangeRequestCreateEndpointTest.php

**Purpose:** Test POST /api/v1/app/change-requests

**Test Cases:**

```php
class ChangeRequestCreateEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_creates_change_request_successfully()
    public function test_requires_title()
    public function test_requires_description()
    public function test_requires_project_id()
    public function test_auto_generates_change_number()
    public function test_sets_status_to_draft()
    public function test_assigns_to_tenant()
    public function test_sets_requester()
    public function test_validates_change_type()
    public function test_validates_priority()
    public function test_requires_create_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestUpdateEndpointTest.php

**Purpose:** Test PUT /api/v1/app/change-requests/{id}

**Test Cases:**

```php
class ChangeRequestUpdateEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_updates_change_request()
    public function test_only_allows_update_when_draft()
    public function test_returns_403_when_not_draft()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_update_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestDeleteEndpointTest.php

**Purpose:** Test DELETE /api/v1/app/change-requests/{id}

**Test Cases:**

```php
class ChangeRequestDeleteEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_deletes_change_request()
    public function test_only_allows_delete_when_draft()
    public function test_returns_403_when_not_draft()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_delete_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestSubmitEndpointTest.php

**Purpose:** Test POST /api/v1/app/change-requests/{id}/submit

**Test Cases:**

```php
class ChangeRequestSubmitEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_submits_change_request_for_approval()
    public function test_only_allows_submit_when_draft()
    public function test_returns_403_when_not_draft()
    public function test_changes_status_to_awaiting_approval()
    public function test_sets_requested_at()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_submit_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestApproveEndpointTest.php

**Purpose:** Test POST /api/v1/app/change-requests/{id}/approve

**Test Cases:**

```php
class ChangeRequestApproveEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_approves_change_request()
    public function test_only_allows_approve_when_awaiting_approval()
    public function test_returns_403_when_not_awaiting_approval()
    public function test_changes_status_to_approved()
    public function test_sets_approved_by()
    public function test_sets_approved_at()
    public function test_saves_approval_notes()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_approve_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestRejectEndpointTest.php

**Purpose:** Test POST /api/v1/app/change-requests/{id}/reject

**Test Cases:**

```php
class ChangeRequestRejectEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_rejects_change_request()
    public function test_only_allows_reject_when_awaiting_approval()
    public function test_returns_403_when_not_awaiting_approval()
    public function test_requires_rejection_reason()
    public function test_changes_status_to_rejected()
    public function test_sets_rejected_by()
    public function test_sets_rejected_at()
    public function test_saves_rejection_reason()
    public function test_returns_404_for_nonexistent_change_request()
    public function test_returns_403_for_different_tenant_change_request()
    public function test_requires_reject_permission()
    public function test_creates_audit_log_entry()
}
```

### ChangeRequestsKpisEndpointTest.php

**Purpose:** Test GET /api/v1/app/change-requests/kpis

**Test Cases:**

```php
class ChangeRequestsKpisEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_kpi_data()
    public function test_includes_total_count()
    public function test_includes_pending_count()
    public function test_includes_approved_count()
    public function test_includes_rejected_count()
    public function test_filters_by_period()
    public function test_only_includes_tenant_change_requests()
    public function test_requires_authentication()
}
```

### ChangeRequestsAlertsEndpointTest.php

**Purpose:** Test GET /api/v1/app/change-requests/alerts

**Test Cases:**

```php
class ChangeRequestsAlertsEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_alerts()
    public function test_includes_overdue_change_requests()
    public function test_only_includes_tenant_alerts()
    public function test_requires_authentication()
}
```

### ChangeRequestsActivityEndpointTest.php

**Purpose:** Test GET /api/v1/app/change-requests/activity

**Test Cases:**

```php
class ChangeRequestsActivityEndpointTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_returns_activity_feed()
    public function test_respects_limit_parameter()
    public function test_orders_by_most_recent()
    public function test_only_includes_tenant_activity()
    public function test_requires_authentication()
}
```

---

## Tenant Isolation Tests

### DocumentsTenantIsolationTest.php

**Purpose:** Prove tenant A cannot access tenant B's documents

**Test Cases:**

```php
class DocumentsTenantIsolationTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_tenant_a_cannot_list_tenant_b_documents()
    public function test_tenant_a_cannot_view_tenant_b_document()
    public function test_tenant_a_cannot_update_tenant_b_document()
    public function test_tenant_a_cannot_delete_tenant_b_document()
    public function test_tenant_a_cannot_download_tenant_b_document()
    public function test_kpis_only_include_tenant_documents()
    public function test_alerts_only_include_tenant_alerts()
    public function test_activity_only_includes_tenant_activity()
}
```

### ChangeRequestsTenantIsolationTest.php

**Purpose:** Prove tenant A cannot access tenant B's change requests

**Test Cases:**

```php
class ChangeRequestsTenantIsolationTest extends TestCase
{
    use DomainTestIsolation;
    
    public function test_tenant_a_cannot_list_tenant_b_change_requests()
    public function test_tenant_a_cannot_view_tenant_b_change_request()
    public function test_tenant_a_cannot_update_tenant_b_change_request()
    public function test_tenant_a_cannot_delete_tenant_b_change_request()
    public function test_tenant_a_cannot_submit_tenant_b_change_request()
    public function test_tenant_a_cannot_approve_tenant_b_change_request()
    public function test_tenant_a_cannot_reject_tenant_b_change_request()
    public function test_kpis_only_include_tenant_change_requests()
    public function test_alerts_only_include_tenant_alerts()
    public function test_activity_only_includes_tenant_activity()
}
```

---

## E2E Tests (Playwright)

### Documents E2E Tests

#### documents-list.spec.ts

**Purpose:** Test documents list page

**Test Cases:**

```typescript
test.describe('Documents List Page', () => {
  test('displays documents list', async ({ page }) => {
    // Navigate to /app/documents
    // Verify KpiStrip displays
    // Verify AlertBar displays
    // Verify documents list displays
    // Verify ActivityFeed displays
  });
  
  test('searches documents', async ({ page }) => {
    // Type in search box
    // Verify results filter
  });
  
  test('filters documents by project', async ({ page }) => {
    // Select project filter
    // Verify results filter
  });
  
  test('uploads document', async ({ page }) => {
    // Click upload button
    // Select file
    // Fill form
    // Submit
    // Verify document appears in list
  });
  
  test('navigates to document detail', async ({ page }) => {
    // Click on document
    // Verify navigates to detail page
  });
});
```

#### document-upload.spec.ts

**Purpose:** Test document upload flow

**Test Cases:**

```typescript
test.describe('Document Upload', () => {
  test('uploads valid document', async ({ page }) => {
    // Navigate to create page
    // Select valid file
    // Fill form
    // Submit
    // Verify success message
    // Verify redirect to detail page
  });
  
  test('rejects file exceeding size limit', async ({ page }) => {
    // Select file > 10MB
    // Verify error message
  });
  
  test('rejects invalid file type', async ({ page }) => {
    // Select invalid file type
    // Verify error message
  });
  
  test('validates required fields', async ({ page }) => {
    // Submit without file
    // Verify validation errors
  });
});
```

#### document-detail.spec.ts

**Purpose:** Test document detail page

**Test Cases:**

```typescript
test.describe('Document Detail Page', () => {
  test('displays document details', async ({ page }) => {
    // Navigate to detail page
    // Verify document info displays
    // Verify KpiStrip displays
    // Verify AlertBar displays
  });
  
  test('switches tabs', async ({ page }) => {
    // Click Overview tab
    // Verify Overview content
    // Click Activity tab
    // Verify Activity content
  });
  
  test('downloads document', async ({ page }) => {
    // Click download button
    // Verify file downloads
  });
  
  test('deletes document', async ({ page }) => {
    // Click delete button
    // Confirm deletion
    // Verify document deleted
    // Verify redirect to list
  });
});
```

#### document-approvals.spec.ts

**Purpose:** Test document approvals page

**Test Cases:**

```typescript
test.describe('Document Approvals', () => {
  test('displays pending approvals', async ({ page }) => {
    // Navigate to approvals page
    // Verify pending documents display
  });
  
  test('approves document', async ({ page }) => {
    // Click approve button
    // Verify approval succeeds
  });
  
  test('rejects document', async ({ page }) => {
    // Click reject button
    // Enter reason
    // Submit
    // Verify rejection succeeds
  });
});
```

### Change Requests E2E Tests

#### change-requests-list.spec.ts

**Purpose:** Test change requests list page

**Test Cases:**

```typescript
test.describe('Change Requests List Page', () => {
  test('displays change requests list', async ({ page }) => {
    // Navigate to /app/change-requests
    // Verify KpiStrip displays
    // Verify AlertBar displays
    // Verify change requests list displays
    // Verify ActivityFeed displays
  });
  
  test('searches change requests', async ({ page }) => {
    // Type in search box
    // Verify results filter
  });
  
  test('filters by status', async ({ page }) => {
    // Select status filter
    // Verify results filter
  });
  
  test('navigates to change request detail', async ({ page }) => {
    // Click on change request
    // Verify navigates to detail page
  });
});
```

#### change-request-create.spec.ts

**Purpose:** Test change request creation

**Test Cases:**

```typescript
test.describe('Create Change Request', () => {
  test('creates change request', async ({ page }) => {
    // Navigate to create page
    // Fill form
    // Submit
    // Verify success
    // Verify redirect to detail page
  });
  
  test('validates required fields', async ({ page }) => {
    // Submit without title
    // Verify validation error
    // Submit without description
    // Verify validation error
    // Submit without project
    // Verify validation error
  });
  
  test('auto-generates change number', async ({ page }) => {
    // Create change request
    // Verify change number generated
  });
});
```

#### change-request-detail.spec.ts

**Purpose:** Test change request detail page

**Test Cases:**

```typescript
test.describe('Change Request Detail Page', () => {
  test('displays change request details', async ({ page }) => {
    // Navigate to detail page
    // Verify change request info displays
    // Verify KpiStrip displays
    // Verify AlertBar displays
  });
  
  test('switches tabs', async ({ page }) => {
    // Click Overview tab
    // Verify Overview content
    // Click Timeline tab
    // Verify Timeline content
    // Click Activity tab
    // Verify Activity content
  });
  
  test('displays timeline correctly', async ({ page }) => {
    // Navigate to Timeline tab
    // Verify timeline events display
    // Verify dates display correctly
  });
});
```

#### change-request-submit.spec.ts

**Purpose:** Test change request submission

**Test Cases:**

```typescript
test.describe('Submit Change Request', () => {
  test('submits draft change request', async ({ page }) => {
    // Navigate to draft change request
    // Click submit button
    // Verify status changes to awaiting_approval
  });
  
  test('cannot submit non-draft change request', async ({ page }) => {
    // Navigate to approved change request
    // Verify submit button not visible
  });
});
```

#### change-request-approve.spec.ts

**Purpose:** Test change request approval

**Test Cases:**

```typescript
test.describe('Approve Change Request', () => {
  test('approves change request', async ({ page }) => {
    // Navigate to awaiting_approval change request
    // Click approve button
    // Enter approval notes
    // Submit
    // Verify status changes to approved
    // Verify approval notes saved
  });
  
  test('cannot approve non-awaiting_approval change request', async ({ page }) => {
    // Navigate to draft change request
    // Verify approve button not visible
  });
});
```

#### change-request-reject.spec.ts

**Purpose:** Test change request rejection

**Test Cases:**

```typescript
test.describe('Reject Change Request', () => {
  test('rejects change request', async ({ page }) => {
    // Navigate to awaiting_approval change request
    // Click reject button
    // Enter rejection reason
    // Submit
    // Verify status changes to rejected
    // Verify rejection reason saved
  });
  
  test('requires rejection reason', async ({ page }) => {
    // Navigate to awaiting_approval change request
    // Click reject button
    // Submit without reason
    // Verify validation error
  });
  
  test('cannot reject non-awaiting_approval change request', async ({ page }) => {
    // Navigate to draft change request
    // Verify reject button not visible
  });
});
```

---

## Test Data Setup

### TestDataSeeder.php

**Purpose:** Create consistent test data for all tests

```php
class TestDataSeeder
{
    public static function seedDocumentsDomain(int $tenantId): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create(['id' => $tenantId]);
        
        // Create users
        $admin = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'admin']);
        $member = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'member']);
        
        // Create projects
        $project = Project::factory()->create(['tenant_id' => $tenantId]);
        
        // Create documents
        Document::factory()->count(10)->create([
            'tenant_id' => $tenantId,
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
        ]);
    }
    
    public static function seedChangeRequestsDomain(int $tenantId): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create(['id' => $tenantId]);
        
        // Create users
        $admin = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'admin']);
        $pm = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'pm']);
        
        // Create projects
        $project = Project::factory()->create(['tenant_id' => $tenantId]);
        
        // Create change requests with different statuses
        ChangeRequest::factory()->count(5)->create([
            'tenant_id' => $tenantId,
            'project_id' => $project->id,
            'status' => ChangeRequest::STATUS_DRAFT,
            'requested_by' => $pm->id,
        ]);
        
        ChangeRequest::factory()->count(3)->create([
            'tenant_id' => $tenantId,
            'project_id' => $project->id,
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
            'requested_by' => $pm->id,
        ]);
        
        ChangeRequest::factory()->count(2)->create([
            'tenant_id' => $tenantId,
            'project_id' => $project->id,
            'status' => ChangeRequest::STATUS_APPROVED,
            'requested_by' => $pm->id,
            'approved_by' => $admin->id,
        ]);
    }
}
```

---

## CI/CD Integration

### GitHub Actions Workflow

```yaml
name: Test Sprint 3

on:
  push:
    paths:
      - 'app/Http/Controllers/Api/DocumentsController.php'
      - 'app/Http/Controllers/Api/ChangeRequestsController.php'
      - 'frontend/src/features/documents/**'
      - 'frontend/src/features/change-requests/**'
      - 'tests/**'
  pull_request:
    paths:
      - 'app/Http/Controllers/Api/DocumentsController.php'
      - 'app/Http/Controllers/Api/ChangeRequestsController.php'
      - 'frontend/src/features/documents/**'
      - 'frontend/src/features/change-requests/**'
      - 'tests/**'

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install Dependencies
        run: composer install
      - name: Run Unit Tests
        run: php artisan test --testsuite=Unit --filter=Documents|ChangeRequests

  integration-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Setup MySQL
        run: |
          sudo systemctl start mysql
          mysql -e "CREATE DATABASE testing;"
      - name: Install Dependencies
        run: composer install
      - name: Run Migrations
        run: php artisan migrate --env=testing
      - name: Run Integration Tests
        run: php artisan test --testsuite=Feature --filter=Documents|ChangeRequests

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install Dependencies
        run: |
          cd frontend && npm install
      - name: Install Playwright
        run: |
          cd frontend && npx playwright install --with-deps
      - name: Run E2E Tests
        run: |
          cd frontend && npm run test:e2e -- documents change-requests
```

---

## Test Execution Commands

### Run All Tests

```bash
# Unit tests
php artisan test --testsuite=Unit --filter=Documents|ChangeRequests

# Integration tests
php artisan test --testsuite=Feature --filter=Documents|ChangeRequests

# Tenant isolation tests
php artisan test --filter=TenantIsolation

# E2E tests
cd frontend && npm run test:e2e -- documents change-requests
```

### Run Specific Test Suites

```bash
# Documents API tests
php artisan test --filter=Documents

# Change Requests API tests
php artisan test --filter=ChangeRequests

# Specific test file
php artisan test tests/Feature/Api/Documents/DocumentsListEndpointTest.php
```

### Run with Coverage

```bash
php artisan test --coverage --filter=Documents|ChangeRequests
```

---

## Test Coverage Goals

- **Unit Tests**: 80%+ coverage for services and models
- **Integration Tests**: 100% endpoint coverage
- **E2E Tests**: All critical user paths covered
- **Tenant Isolation**: 100% coverage (all isolation scenarios tested)

---

## Implementation Priority

1. **Phase 1: Critical Paths** (Week 1)
   - Documents upload/download
   - Change requests create/submit/approve/reject
   - Tenant isolation tests

2. **Phase 2: Full Coverage** (Week 2)
   - All API endpoints
   - All E2E user flows
   - Error handling tests

3. **Phase 3: Edge Cases** (Week 3)
   - File validation edge cases
   - Status transition edge cases
   - Performance tests

---

## Notes

- All tests must use `DomainTestIsolation` trait
- All tests must use fixed seeds for reproducibility
- All tests must use `TestDataSeeder` for consistent data
- E2E tests must use Playwright MCP tools
- All tests must be deterministic (no random data)
- All tests must clean up after themselves

---

## Sign-off

- [ ] Test plan reviewed
- [ ] Test structure approved
- [ ] Test cases defined
- [ ] CI/CD configured
- [ ] Ready for implementation

**Created:** 2025-01-17  
**Status:** Ready for Implementation

