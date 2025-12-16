# Authentication "Me" Endpoints Documentation

**Version:** 1.0  
**Last Updated:** January 2025  
**Status:** Stable

## Overview

This document describes the canonical authentication endpoints for retrieving current user information, permissions, abilities, and tenant data. The system uses a single canonical endpoint (`/api/v1/me`) with a standardized response format built by `MeService`.

---

## Canonical Endpoint

### `GET /api/v1/me`

**Description:** Returns current authenticated user information with permissions, abilities, tenant summary, and onboarding state.

**Authentication:** Required (Sanctum token or session)

**Response Format:**

```json
{
  "success": true,
  "data": {
    "user": {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "name": "John Doe",
      "email": "john@example.com",
      "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
      "role": "member",
      "email_verified_at": "2025-01-15T10:30:00.000000Z",
      "last_login_at": "2025-01-20T14:22:00.000000Z",
      "created_at": "2025-01-01T08:00:00.000000Z",
      "is_active": true
    },
    "permissions": [
      "projects.view",
      "tasks.view",
      "tasks.create",
      "documents.view"
    ],
    "abilities": ["tenant"],
    "tenants_summary": {
      "count": 1,
      "items": [
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
          "name": "Acme Corp",
          "slug": "acme"
        }
      ]
    },
    "onboarding_state": "completed"
  }
}
```

**Response Fields:**

- `user`: User object with basic information
  - `id`: User ULID
  - `name`: User's full name
  - `email`: User's email address
  - `tenant_id`: Current tenant ID (may be null)
  - `role`: User's role (member, pm, admin, super_admin, etc.)
  - `email_verified_at`: ISO 8601 timestamp of email verification (null if not verified)
  - `last_login_at`: ISO 8601 timestamp of last login (null if never logged in)
  - `created_at`: ISO 8601 timestamp of account creation
  - `is_active`: Boolean indicating if account is active

- `permissions`: Array of permission strings based on user's role (from `config/permissions.php`)

- `abilities`: Array of ability flags
  - `"admin"`: User has system-wide admin access (super_admin)
  - `"tenant"`: User has tenant-scoped access

- `tenants_summary`: Lightweight tenant information
  - `count`: Number of tenants user has access to
  - `items`: Array of tenant objects with `id`, `name`, `slug`

- `onboarding_state`: Current onboarding state
  - `"email_verification"`: User needs to verify email
  - `"tenant_setup"`: User needs to be assigned to a tenant
  - `"completed"`: User has completed onboarding

**Error Responses:**

```json
{
  "ok": false,
  "code": "UNAUTHORIZED",
  "message": "User not authenticated",
  "traceId": "req_abc12345"
}
```

**Status Codes:**

- `200`: Success
- `401`: Unauthenticated

---

## Auxiliary Endpoints

### `GET /api/v1/me/tenants`

**Description:** Returns list of tenants accessible to the current user.

**Authentication:** Required (Sanctum token or session)

**Response Format:**

```json
{
  "success": true,
  "data": {
    "tenants": [
      {
        "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
        "name": "Acme Corp",
        "slug": "acme",
        "is_active": true,
        "is_current": true
      }
    ],
    "count": 1,
    "current_tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW"
  }
}
```

**Response Fields:**

- `tenants`: Array of tenant objects
  - `id`: Tenant ULID
  - `name`: Tenant name
  - `slug`: Tenant slug
  - `is_active`: Boolean indicating if tenant is active
  - `is_current`: Boolean indicating if this is the user's current tenant

- `count`: Total number of tenants

- `current_tenant_id`: ID of the user's current tenant (may be null)

**Status Codes:**

- `200`: Success
- `401`: Unauthenticated
- `500`: Server error

---

### `POST /api/v1/me/tenants/{tenantId}/select`

**Description:** Selects/switches the active tenant for the current user session.

**Authentication:** Required (Sanctum token or session)

**Parameters:**

- `tenantId` (path): ULID of the tenant to select
- `include_me` (query, optional): If `true`, includes fresh Me payload in response

**Response Format (without `include_me`):**

```json
{
  "success": true,
  "data": {
    "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
    "tenant_name": "Acme Corp",
    "message": "Tenant selected successfully"
  }
}
```

**Response Format (with `include_me=true`):**

```json
{
  "success": true,
  "data": {
    "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
    "tenant_name": "Acme Corp",
    "message": "Tenant selected successfully",
    "me": {
      "user": { ... },
      "permissions": [ ... ],
      "abilities": [ ... ],
      "tenants_summary": { ... },
      "onboarding_state": "completed"
    }
  }
}
```

**Error Responses:**

```json
{
  "ok": false,
  "code": "TENANT_NOT_FOUND",
  "message": "Tenant not found",
  "traceId": "req_abc12345"
}
```

```json
{
  "ok": false,
  "code": "TENANT_ACCESS_DENIED",
  "message": "You do not have access to this tenant",
  "traceId": "req_abc12345"
}
```

**Status Codes:**

- `200`: Success
- `401`: Unauthenticated
- `403`: Access denied (user doesn't have access to tenant)
- `404`: Tenant not found
- `500`: Server error

---

## Deprecated Endpoint

### `GET /api/auth/me` ⚠️

**Status:** Deprecated but still supported for backward compatibility

**Description:** Legacy endpoint that returns the same standardized response as `/api/v1/me`. This endpoint is maintained for backward compatibility but should not be used in new code.

**Recommendation:** Use `GET /api/v1/me` instead.

**Response Format:** Same as `/api/v1/me`

---

## Tenant Handling Flows

### No Tenant (count === 0)

When a user has no tenants assigned:

1. Frontend should display `NoTenantScreen` component
2. User sees message: "Tài khoản chưa được gán vào bất kỳ đơn vị (tenant) nào."
3. User can logout via "Đăng xuất" button
4. User should contact system administrator for access

**Example Response:**

```json
{
  "success": true,
  "data": {
    "user": { ... },
    "permissions": [ ... ],
    "abilities": [],
    "tenants_summary": {
      "count": 0,
      "items": []
    },
    "onboarding_state": "tenant_setup"
  }
}
```

### Single Tenant (count === 1)

When a user has exactly one tenant:

1. Frontend should auto-select the tenant after login
2. User is automatically redirected to dashboard
3. No tenant selector is shown

**Example Response:**

```json
{
  "success": true,
  "data": {
    "user": { ... },
    "tenants_summary": {
      "count": 1,
      "items": [
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
          "name": "Acme Corp",
          "slug": "acme"
        }
      ]
    }
  }
}
```

### Multiple Tenants (count > 1)

When a user has multiple tenants:

1. Frontend should display `TenantSelector` component
2. User selects desired tenant
3. After selection, user is redirected to dashboard
4. Selected tenant is stored in session and user state

**Example Response:**

```json
{
  "success": true,
  "data": {
    "user": { ... },
    "tenants_summary": {
      "count": 2,
      "items": [
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
          "name": "Acme Corp",
          "slug": "acme"
        },
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FAX",
          "name": "Beta Inc",
          "slug": "beta"
        }
      ]
    }
  }
}
```

---

## Frontend Usage

### React SPA

The frontend uses the canonical `/api/v1/me` endpoint via `authApi.getMe()`:

```typescript
import { authApi } from '@/features/auth/api';

// Get current user with full context
const user = await authApi.getMe();
```

The `useAuthStore` hook automatically handles:
- Fetching user data on app initialization
- Storing permissions, abilities, and tenant count
- Refreshing state after tenant selection

### Tenant Selection

```typescript
import { useAuthStore } from '@/features/auth/store';

const { selectTenant } = useAuthStore();

// Select tenant (automatically refreshes user state)
await selectTenant(tenantId);
```

---

## Implementation Details

### MeService

The `MeService` class (`app/Services/MeService.php`) is the single source of truth for building "me" responses:

```php
use App\Services\MeService;

$meService = app(MeService::class);
$meData = $meService->buildMeResponse($user);
```

**Key Methods:**

- `buildMeResponse(User $user): array` - Builds standardized response
- `getUserAbilities(User $user): array` - Computes user abilities
- `getTenantsSummary(User $user): array` - Fetches tenant information
- `getOnboardingState(User $user): string` - Determines onboarding state

### Response Standardization

All endpoints use `ApiResponse::success()` for consistent response format:

```php
use App\Support\ApiResponse;

return ApiResponse::success($meData);
```

---

## Migration Guide

### From `/api/auth/me` to `/api/v1/me`

**Before:**

```typescript
const response = await fetch('/api/auth/me');
const data = await response.json();
const user = data.user;
```

**After:**

```typescript
import { authApi } from '@/features/auth/api';

const user = await authApi.getMe();
// Or use useAuthStore hook
const { user, permissions, abilities, tenantsCount } = useAuthStore();
```

---

## Testing

### Backend Tests

- `tests/Unit/Services/MeServiceTest.php` - Unit tests for MeService
- `tests/Feature/Api/Auth/MeEndpointTest.php` - Feature tests for endpoints

### Frontend Tests

- `frontend/src/features/auth/__tests__/store.test.ts` - Auth store tests
- `frontend/src/features/auth/components/__tests__/NoTenantScreen.test.tsx` - Component tests

### E2E Tests

- `tests/E2E/core/auth/me-endpoint.spec.ts` - End-to-end tests for me endpoint
- `tests/E2E/core/auth/tenant-selection.spec.ts` - Tenant selection flow tests

---

## Changelog

### Version 1.0 (January 2025)

- Introduced canonical `/api/v1/me` endpoint
- Created `MeService` for standardized response building
- Standardized tenant endpoints (`/api/v1/me/tenants`, `/api/v1/me/tenants/{id}/select`)
- Added `tenants_summary` to me response
- Added `onboarding_state` to me response
- Deprecated `/api/auth/me` (still supported for backward compatibility)
- Improved tenant handling UX with explicit no-tenant and multi-tenant flows

---

## Support

For questions or issues, please refer to:
- Architecture documentation: `docs/ARCHITECTURE.md`
- API documentation: `docs/API_V1_CONTRACT.md`
- Project rules: `PROJECT_RULES.md`

