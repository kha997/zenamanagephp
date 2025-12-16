# Error Envelope Contract

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Stable  
**Purpose**: Defines the standardized error response format for ZenaManage API

---

## Overview

All API error responses follow a standardized envelope format to ensure consistency, enable proper error handling, and support correlation for debugging.

---

## Standard Error Format

### JSON Structure

```json
{
  "ok": false,
  "code": "TASK_NOT_FOUND",
  "message": "Task with ID 123 not found",
  "traceId": "req_abc12345",
  "details": {
    "resource_id": "123",
    "resource_type": "task"
  }
}
```

### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `ok` | boolean | Yes | Always `false` for errors |
| `code` | string | Yes | Error code (see Error Codes section) |
| `message` | string | Yes | Human-readable error message (i18n-supported) |
| `traceId` | string | Yes | Request correlation ID for debugging |
| `details` | object | No | Additional error context (resource IDs, validation errors, etc.) |

---

## HTTP Status Code Mapping

| HTTP Status | Error Code Prefix | Description | Retry-After |
|-------------|-------------------|-------------|-------------|
| 400 | `BAD_REQUEST` | Invalid request format | No |
| 401 | `UNAUTHORIZED` | Authentication required | No |
| 403 | `FORBIDDEN` | Insufficient permissions | No |
| 404 | `NOT_FOUND` | Resource not found | No |
| 409 | `CONFLICT` | Resource conflict | No |
| 422 | `VALIDATION_FAILED` | Validation failed | No |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests | **Yes** |
| 500 | `SERVER_ERROR` | Internal server error | No |
| 503 | `SERVICE_UNAVAILABLE` | Service unavailable | **Yes** |

---

## Error Code Convention

Error codes follow the pattern: `{DOMAIN}_{ERROR_TYPE}` or `{GENERIC_ERROR}`

### Format Rules

1. **UPPERCASE** with underscores
2. **Domain Prefix** for domain-specific errors: `PROJECT_*`, `TASK_*`, `AUTH_*`
3. **Generic Codes** for common errors: `NOT_FOUND`, `VALIDATION_FAILED`, `UNAUTHORIZED`

### Examples

- `TASK_NOT_FOUND` - Task-specific not found
- `PROJECT_NOT_FOUND` - Project-specific not found
- `NOT_FOUND` - Generic not found
- `VALIDATION_FAILED` - Generic validation error
- `AUTH_REQUIRED` - Authentication required

---

## Error Codes Reference

### Authentication & Authorization

| Code | HTTP Status | Description | Example Message |
|------|-------------|-------------|-----------------|
| `UNAUTHORIZED` | 401 | Authentication required or failed | "Authentication required" |
| `AUTH_REQUIRED` | 401 | Authentication token missing | "Authentication token is required" |
| `AUTH_TOKEN_EXPIRED` | 401 | Token has expired | "Authentication token has expired" |
| `AUTH_TOKEN_INVALID` | 401 | Token is invalid | "Authentication token is invalid" |
| `FORBIDDEN` | 403 | Insufficient permissions | "You do not have permission to perform this action" |
| `INSUFFICIENT_PERMISSIONS` | 403 | Missing required permission | "You do not have permission to delete projects" |

### Validation Errors

| Code | HTTP Status | Description | Example Message |
|------|-------------|-------------|-----------------|
| `VALIDATION_FAILED` | 422 | General validation error | "Validation failed" |
| `BAD_REQUEST` | 400 | Invalid request format | "Invalid request format" |
| `MISSING_REQUIRED_FIELD` | 422 | Required field missing | "The name field is required" |
| `INVALID_FIELD_VALUE` | 422 | Field value is invalid | "The email field must be a valid email address" |

### Not Found Errors

| Code | HTTP Status | Description | Example Message |
|------|-------------|-------------|-----------------|
| `NOT_FOUND` | 404 | Generic resource not found | "Resource not found" |
| `PROJECT_NOT_FOUND` | 404 | Project not found | "Project with ID 123 not found" |
| `TASK_NOT_FOUND` | 404 | Task not found | "Task with ID 456 not found" |
| `DOCUMENT_NOT_FOUND` | 404 | Document not found | "Document with ID 789 not found" |
| `USER_NOT_FOUND` | 404 | User not found | "User with ID abc not found" |
| `CLIENT_NOT_FOUND` | 404 | Client not found | "Client with ID xyz not found" |
| `QUOTE_NOT_FOUND` | 404 | Quote not found | "Quote with ID 123 not found" |

### Conflict Errors

| Code | HTTP Status | Description | Example Message |
|------|-------------|-------------|-----------------|
| `CONFLICT` | 409 | Generic resource conflict | "Resource conflict" |
| `PROJECT_ALREADY_EXISTS` | 409 | Project with same code exists | "A project with this code already exists" |
| `IDEMPOTENCY_KEY_CONFLICT` | 409 | Idempotency key conflict | "This request was already processed with different data" |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Idempotency key missing | "Idempotency-Key header is required for this operation" |
| `RESOURCE_IN_USE` | 409 | Resource is in use | "Project cannot be deleted because it has active tasks" |

### Domain-Specific Errors

#### Project Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `PROJECT_NOT_FOUND` | 404 | Project not found |
| `PROJECT_ALREADY_EXISTS` | 409 | Project code/name already exists |
| `PROJECT_CANNOT_BE_DELETED` | 409 | Project has dependencies |
| `PROJECT_STATUS_INVALID` | 422 | Invalid project status transition |

#### Task Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `TASK_NOT_FOUND` | 404 | Task not found |
| `TASK_CANNOT_BE_MOVED` | 422 | Task cannot be moved to requested status |
| `TASK_STATUS_INVALID` | 422 | Invalid task status transition |
| `TASK_ASSIGNMENT_FAILED` | 422 | Task assignment failed |

#### Tenant Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `TENANT_NOT_FOUND` | 404 | Tenant not found |
| `TENANT_ISOLATION_VIOLATION` | 403 | Cross-tenant access attempt |
| `TENANT_QUOTA_EXCEEDED` | 403 | Tenant quota exceeded |
| `NO_TENANT_ACCESS` | 403 | User has no tenant access |

#### Document Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `DOCUMENT_NOT_FOUND` | 404 | Document not found |
| `FILE_TOO_LARGE` | 413 | File size exceeds maximum |
| `INVALID_FILE_TYPE` | 422 | File type not allowed |
| `STORAGE_QUOTA_EXCEEDED` | 403 | Storage quota exceeded |

### Rate Limiting & Service Errors

| Code | HTTP Status | Description | Retry-After |
|------|-------------|-------------|-------------|
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests | Yes |
| `SERVER_ERROR` | 500 | Internal server error | No |
| `SERVICE_UNAVAILABLE` | 503 | Service temporarily unavailable | Yes |
| `DATABASE_ERROR` | 500 | Database operation failed | No |
| `QUEUE_ERROR` | 500 | Queue operation failed | No |

---

## Error Response Examples

### Validation Error

```json
{
  "ok": false,
  "code": "VALIDATION_FAILED",
  "message": "Validation failed",
  "traceId": "req_abc12345",
  "details": {
    "validation": {
      "name": ["The name field is required"],
      "email": ["The email must be a valid email address"]
    }
  }
}
```

**HTTP Status**: 422

---

### Not Found Error

```json
{
  "ok": false,
  "code": "TASK_NOT_FOUND",
  "message": "Task with ID 123 not found",
  "traceId": "req_abc12345",
  "details": {
    "task_id": "123",
    "tenant_id": "tenant_xyz"
  }
}
```

**HTTP Status**: 404

---

### Authorization Error

```json
{
  "ok": false,
  "code": "FORBIDDEN",
  "message": "You do not have permission to delete projects",
  "traceId": "req_abc12345",
  "details": {
    "required_permission": "projects.delete",
    "user_permissions": ["projects.view", "projects.create"]
  }
}
```

**HTTP Status**: 403

---

### Rate Limit Error

```json
{
  "ok": false,
  "code": "RATE_LIMIT_EXCEEDED",
  "message": "Rate limit exceeded",
  "traceId": "req_abc12345",
  "details": {
    "retry_after": 60,
    "limit": 60,
    "remaining": 0
  }
}
```

**HTTP Status**: 429  
**Headers**:
```
Retry-After: 60
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1705123500
```

---

### Conflict Error (Idempotency)

```json
{
  "ok": false,
  "code": "IDEMPOTENCY_KEY_CONFLICT",
  "message": "This request was already processed with different data",
  "traceId": "req_abc12345",
  "details": {
    "idempotency_key": "project_create_1705123456789_abc123xyz",
    "original_request_id": "req_xyz67890",
    "conflict_fields": ["name", "description"]
  }
}
```

**HTTP Status**: 409

---

### Server Error

```json
{
  "ok": false,
  "code": "SERVER_ERROR",
  "message": "Internal server error",
  "traceId": "req_abc12345",
  "details": {
    "error_id": "err_xyz789"
  }
}
```

**HTTP Status**: 500

**Note**: In production, `details` should not expose sensitive information or stack traces.

---

## Trace ID (Correlation ID)

### Purpose

The `traceId` field enables:
- **Request Correlation**: Link errors to specific requests
- **Debugging**: Find related logs across services
- **Support**: Help users report issues with trace ID

### Format

```
req_{random8chars}
```

**Example**: `req_abc12345`

### Sources (Priority Order)

1. **Request Attributes**: Set by `TracingMiddleware` or `RequestCorrelationMiddleware`
2. **Headers**: `X-Request-Id`, `X-Correlation-ID`, `X-Correlation-Id`
3. **App Container**: `trace_id` or `correlation_id` binding
4. **Generated**: Auto-generated if not present

### Response Header

All error responses include:
```
X-Request-Id: req_abc12345
```

---

## Internationalization (i18n)

Error messages support i18n through Laravel's translation system.

### Translation Keys

Error messages can be translation keys:
- `errors.TASK_NOT_FOUND` → Translated message
- `errors.VALIDATION_FAILED` → Translated message

### Language Files

- `lang/en/errors.php` - English translations
- `lang/vi/errors.php` - Vietnamese translations

### Example

```php
// Error code
'code' => 'TASK_NOT_FOUND'

// Message (translated)
'message' => 'Task with ID 123 not found' // EN
'message' => 'Không tìm thấy task với ID 123' // VI
```

---

## Usage in Controllers

### Using ErrorEnvelopeService

```php
use App\Services\ErrorEnvelopeService;

// Not found
return ErrorEnvelopeService::notFoundError(
    "Task with ID {$id} not found",
    $traceId
);

// Validation error
return ErrorEnvelopeService::validationError(
    $validator->errors()->toArray(),
    $traceId
);

// Authorization error
return ErrorEnvelopeService::authorizationError(
    "You do not have permission to delete projects",
    $traceId
);

// Custom error
return ErrorEnvelopeService::error(
    'TASK_CANNOT_BE_MOVED',
    'Task cannot be moved to requested status',
    ['current_status' => 'completed', 'requested_status' => 'in_progress'],
    422,
    $traceId
);
```

### Using Exception Handler

The `app/Exceptions/Handler.php` automatically wraps exceptions:

```php
// Thrown exception
throw new ModelNotFoundException("Task not found");

// Automatically wrapped to:
{
  "ok": false,
  "code": "NOT_FOUND",
  "message": "Task not found",
  "traceId": "req_abc12345",
  "details": {}
}
```

---

## Frontend Error Handling

### TypeScript Interface

```typescript
interface ApiError {
  ok: false;
  code: string;
  message: string;
  traceId: string;
  details?: Record<string, any>;
}
```

### Error Handler Example

```typescript
import { ERROR_I18N_KEYS, DEFAULT_ERROR_MESSAGES } from '@/shared/api/errorCodes';

function handleApiError(error: ApiError) {
  // Get i18n key
  const i18nKey = ERROR_I18N_KEYS[error.code] || 'errors.generic';
  
  // Get translated message
  const message = t(i18nKey) || DEFAULT_ERROR_MESSAGES[error.code] || error.message;
  
  // Log with traceId for correlation
  console.error(`[${error.traceId}] ${error.code}: ${message}`, error.details);
  
  // Show user-friendly message
  showNotification(message, 'error');
  
  // Send to error tracking (Sentry, etc.)
  captureException(error, { traceId: error.traceId });
}
```

---

## Best Practices

1. **Always Use ErrorEnvelopeService**: Never return raw exceptions or custom error formats
2. **Include Trace ID**: Always include traceId for correlation
3. **Use Specific Error Codes**: Prefer domain-specific codes (`TASK_NOT_FOUND`) over generic (`NOT_FOUND`)
4. **Include Context**: Add relevant details in `details` object
5. **Don't Expose Sensitive Data**: Never include passwords, tokens, or PII in error messages or details
6. **Support i18n**: Use translation keys for error messages
7. **Log Errors**: Log all errors with traceId for debugging

---

## Error Code Naming Guidelines

### Domain Prefixes

- `AUTH_*` - Authentication errors
- `PROJECT_*` - Project-related errors
- `TASK_*` - Task-related errors
- `DOCUMENT_*` - Document-related errors
- `CLIENT_*` - Client-related errors
- `QUOTE_*` - Quote-related errors
- `TENANT_*` - Tenant-related errors
- `USER_*` - User-related errors

### Generic Codes

- `NOT_FOUND` - Generic not found
- `VALIDATION_FAILED` - Generic validation error
- `UNAUTHORIZED` - Authentication required
- `FORBIDDEN` - Authorization failed
- `CONFLICT` - Generic conflict
- `SERVER_ERROR` - Generic server error

---

## References

- [ErrorEnvelopeService](../../app/Services/ErrorEnvelopeService.php)
- [Error Envelope Middleware](../../app/Http/Middleware/ErrorEnvelopeMiddleware.php)
- [API v1 Contract](API_V1_CONTRACT.md)
- [Frontend Error Codes](../../frontend/src/shared/api/errorCodes.ts)

---

*This contract is binding. All API errors must follow this format.*

