# Documents Context

**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

The Documents context handles document management including uploads, versioning, approvals, and sharing.

---

## Key Components

### Services

- **`DocumentService`** (`app/Services/DocumentService.php`)
  - Document upload and storage
  - Version management
  - Approval workflows

### Controllers

- **`Api\V1\App\DocumentsController`** (`app/Http/Controllers/Api/V1/App/DocumentsController.php`)
  - API endpoints for document operations

### Models

- **`Document`** (`app/Models/Document.php`)
  - Main document model
  - Relationships: Project, Versions, Approvals

---

## Cache Invalidation

Cache invalidation via `CacheInvalidationService::forDocumentUpdate()`:

- Document cache: `document:{document_id}`
- Document list cache: `documents:project:{project_id}:*`

---

## Test Organization

```bash
# Run all document tests
php artisan test --group=documents
```

---

## References

- [Architecture Layering Guide](../ARCHITECTURE_LAYERING_GUIDE.md)

