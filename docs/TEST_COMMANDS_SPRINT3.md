# Test Commands - Sprint 3

## Correct Command Syntax

### PHP Tests (Laravel)

**Important**: Use quotes around filter patterns to prevent shell interpretation of pipe character.

```bash
# Unit tests for Documents and Change Requests
php artisan test --testsuite=Unit --filter="Documents|ChangeRequests"

# Integration tests for Documents and Change Requests
php artisan test --testsuite=Feature --filter="Documents|ChangeRequests"

# Tenant isolation tests
php artisan test --filter="TenantIsolation"

# Specific test file
php artisan test tests/Feature/Api/Documents/DocumentsListEndpointTest.php

# Run with coverage
php artisan test --coverage --filter="Documents|ChangeRequests"
```

### E2E Tests (Playwright)

```bash
# Navigate to frontend directory first
cd frontend

# Run E2E tests for Documents
npm run test:e2e -- documents

# Run E2E tests for Change Requests
npm run test:e2e -- change-requests

# Run all E2E tests
npm run test:e2e

# Run with UI mode
npm run test:e2e -- --ui documents change-requests

# Run with headed browser
npm run test:e2e -- --headed documents change-requests
```

## Common Issues and Solutions

### Issue 1: Pipe Character in Filter

**Error**: `bash: ChangeRequests: command not found`

**Solution**: Use quotes around the filter pattern
```bash
# Wrong
php artisan test --filter=Documents|ChangeRequests

# Correct
php artisan test --filter="Documents|ChangeRequests"
```

### Issue 2: Build Error - Import Path

**Error**: `Could not resolve "../../templates/api"`

**Solution**: Templates have been moved to `_archived/templates-2025-01/`. Update imports:
```typescript
// Wrong
import { templatesApi } from '../../templates/api';

// Correct
import { templatesApi } from '../../_archived/templates-2025-01/api';
```

### Issue 3: Test Failures - Existing Tests

**Note**: Some existing tenant isolation tests may fail due to:
- Unique constraint violations (permissions already exist)
- Auth token issues (user not found)

These are pre-existing issues not related to Sprint 3. Focus on new tests for Documents and Change Requests.

## Running Tests in Sequence

```bash
# 1. Fix build errors first
cd frontend
npm run build

# 2. Run unit tests
php artisan test --testsuite=Unit --filter="Documents|ChangeRequests"

# 3. Run integration tests
php artisan test --testsuite=Feature --filter="Documents|ChangeRequests"

# 4. Run E2E tests
cd frontend
npm run test:e2e -- documents change-requests
```

## Test File Locations

### Backend Tests
```
tests/
├── Unit/
│   ├── Services/
│   │   └── FileStorageServiceTest.php
│   └── Models/
│       ├── DocumentTest.php
│       └── ChangeRequestTest.php
├── Feature/
│   ├── Api/
│   │   ├── Documents/
│   │   └── ChangeRequests/
│   └── TenantIsolation/
│       ├── DocumentsTenantIsolationTest.php
│       └── ChangeRequestsTenantIsolationTest.php
```

### Frontend E2E Tests
```
frontend/tests/e2e/
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

## Debugging Tips

### PHP Tests
```bash
# Run with verbose output
php artisan test --filter="Documents" --verbose

# Stop on first failure
php artisan test --filter="Documents" --stop-on-failure

# Run specific test method
php artisan test --filter="test_uploads_document_successfully"
```

### Playwright Tests
```bash
# Run with debug mode
npm run test:e2e -- --debug documents

# Run with trace
npm run test:e2e -- --trace on documents

# Show browser
npm run test:e2e -- --headed documents
```

