# Invitation System Documentation

## Overview

The Invitation System allows administrators to invite users to join their organization (tenant) via email or invitation link. The system supports single and bulk invitations, with idempotent handling to prevent duplicate invitations.

## Table of Contents

1. [User Flows](#user-flows)
2. [API Endpoints](#api-endpoints)
3. [RBAC Rules](#rbac-rules)
4. [Email Configuration](#email-configuration)
5. [Security Features](#security-features)
6. [Error Handling](#error-handling)

## User Flows

### Flow 1: Super Admin Invites User

1. Super Admin navigates to `/admin/users`
2. Clicks "Invite User" button
3. Selects tenant (can choose any tenant)
4. Enters email address and selects role
5. Optionally adds message and note
6. Clicks "Send Invitation"
7. System creates invitation and sends email (if configured)
8. Invitation link is displayed for manual sharing

### Flow 2: Org Admin Invites User

1. Org Admin navigates to `/admin/users`
2. Clicks "Invite User" button
3. Tenant is auto-selected (cannot change)
4. Enters email address and selects role
5. Optionally adds message and note
6. Clicks "Send Invitation"
7. System creates invitation and sends email (if configured)

### Flow 3: Bulk Invitation

1. Admin navigates to `/admin/users`
2. Clicks "Invite User" button
3. Switches to "Bulk" mode
4. Enters multiple email addresses (one per line or comma-separated)
5. Selects role (applies to all)
6. Clicks "Send Invitations"
7. System processes each email:
   - Creates invitation if new
   - Returns existing invitation if pending
   - Reports error if already member
8. Summary displayed with counts

### Flow 4: User Accepts Invitation (New User)

1. User receives invitation email with link `/invite/{token}`
2. Clicks link (or copies manually if email not configured)
3. System validates token
4. User sees invitation details (tenant, role, message)
5. User fills registration form:
   - Name (required)
   - Password (required, min 8 chars)
   - Confirm Password (required)
   - Optional: First Name, Last Name, Phone, Job Title
6. Clicks "Create Account & Accept"
7. System creates user account
8. User is logged in and redirected to dashboard

### Flow 5: User Accepts Invitation (Existing User)

1. Existing user receives invitation email
2. Clicks link `/invite/{token}`
3. If not logged in, redirected to login
4. After login, redirected back to invitation page
5. User sees invitation details
6. Clicks "Accept Invitation"
7. System adds user to tenant (or updates role)
8. User redirected to dashboard

## API Endpoints

### Admin Endpoints (Requires `auth:sanctum` + `ability:admin`)

#### Create Single Invitation

```http
POST /api/admin/invitations
Content-Type: application/json
Authorization: Bearer {token}

{
  "email": "user@example.com",
  "role": "member",
  "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "first_name": "John",
  "last_name": "Doe",
  "message": "Welcome to our team!",
  "note": "Internal note",
  "send_email": true,
  "expires_in_days": 7
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "status": "created",
    "invitation": {
      "id": 1,
      "email": "user@example.com",
      "token": "550e8400-e29b-41d4-a716-446655440000",
      "link": "https://app.example.com/invite/550e8400-e29b-41d4-a716-446655440000",
      "expires_at": "2025-01-23T12:00:00Z",
      "status": "pending"
    },
    "email_sent": true
  }
}
```

**Response (409 Conflict - Already Member):**
```json
{
  "success": false,
  "error": {
    "id": "err_xxx",
    "message": "User is already a member of this tenant",
    "status": 409
  }
}
```

**Response (200 OK - Pending Invitation Exists):**
```json
{
  "success": true,
  "data": {
    "status": "pending_invitation",
    "invitation": {
      "id": 1,
      "email": "user@example.com",
      "token": "550e8400-e29b-41d4-a716-446655440000",
      "link": "https://app.example.com/invite/550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

#### Create Bulk Invitations

```http
POST /api/admin/invitations/bulk
Content-Type: application/json
Authorization: Bearer {token}

{
  "emails": [
    "user1@example.com",
    "user2@example.com",
    "user3@example.com"
  ],
  "role": "member",
  "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "message": "Welcome!",
  "send_email": true,
  "expires_in_days": 7
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "created": [
      {
        "email": "user1@example.com",
        "invitation": { ... },
        "link": "https://app.example.com/invite/xxx"
      }
    ],
    "already_member": [
      {
        "email": "user2@example.com",
        "user": { ... }
      }
    ],
    "pending": [
      {
        "email": "user3@example.com",
        "invitation": { ... }
      }
    ],
    "errors": [
      {
        "email": "invalid-email",
        "error": "The email must be a valid email address."
      }
    ],
    "summary": {
      "total": 3,
      "created": 1,
      "already_member": 1,
      "pending": 1,
      "errors": 0
    },
    "email_sent": true
  }
}
```

#### List Invitations

```http
GET /api/admin/invitations?status=pending&tenant_id=xxx&page=1&per_page=15
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "role": "member",
        "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
        "tenant_name": "Acme Corp",
        "status": "pending",
        "expires_at": "2025-01-23T12:00:00Z",
        "accepted_at": null,
        "link": "https://app.example.com/invite/xxx"
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 1,
      "last_page": 1
    },
    "links": { ... }
  }
}
```

#### Resend Invitation

```http
POST /api/admin/invitations/{id}/resend
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "invitation": {
      "id": 1,
      "email": "user@example.com",
      "token": "new-token-xxx",
      "link": "https://app.example.com/invite/new-token-xxx",
      "expires_at": "2025-01-30T12:00:00Z",
      "status": "pending"
    },
    "email_sent": true
  }
}
```

### Public Endpoints (No Authentication Required)

#### Validate Invitation Token

```http
GET /api/invitations/{token}/validate
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "email": "user@example.com",
    "tenant_name": "Acme Corp",
    "role": "member",
    "expires_at": "2025-01-23T12:00:00Z",
    "message": "Welcome to our team!",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

**Response (410 Gone - Expired):**
```json
{
  "success": false,
  "error": {
    "id": "err_xxx",
    "message": "Invitation has expired",
    "status": 410
  }
}
```

#### Accept Invitation

```http
POST /api/invitations/{token}/accept
Content-Type: application/json

{
  "name": "John Doe",
  "password": "password123",
  "password_confirmation": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+1234567890",
  "job_title": "Software Engineer"
}
```

**For Existing User (Logged In):**
```http
POST /api/invitations/{token}/accept
Authorization: Bearer {token}

{
  "user_id": 123
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 123,
      "name": "John Doe",
      "email": "user@example.com",
      "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "role": "member"
    },
    "message": "Invitation accepted successfully"
  }
}
```

## RBAC Rules

### Super Admin (`admin.access`)

- ✅ Can invite users to **any tenant**
- ✅ Can view all invitations across all tenants
- ✅ Can resend any invitation
- ✅ Can cancel any invitation

### Org Admin (`admin.access.tenant`)

- ✅ Can invite users to **their own tenant only**
- ✅ Can view invitations for their tenant only
- ✅ Can resend invitations for their tenant
- ✅ Can cancel invitations for their tenant
- ❌ Cannot invite to other tenants
- ❌ Cannot view other tenants' invitations

### Inviter

- ✅ Can view their own invitations
- ✅ Can resend their own invitations (if not accepted)
- ✅ Can cancel their own invitations (if not accepted)

### Invitee

- ✅ Can view invitation details via token
- ✅ Can accept invitation via token

## Email Configuration

### Setup

1. Configure mail driver in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

2. If email is not configured (`MAIL_MAILER=array`), the system will:
   - Still create invitations
   - Return invitation links in API response
   - Allow manual link sharing
   - Log warning that email was not sent

### Email Template

The invitation email includes:
- Tenant name
- Inviter name
- Role
- Expiration date
- Personal message (if provided)
- Accept button/link

### Queue Configuration

If queue is configured, emails are queued for async processing:
```php
// In InvitationService
if ($mailable->shouldQueue()) {
    Mail::to($invitation->email)->queue($mailable);
} else {
    Mail::to($invitation->email)->send($mailable);
}
```

## Security Features

### Rate Limiting

- **Admin Endpoints:**
  - List: 60 requests/minute
  - Create: 10 requests/minute
  - Bulk: 5 requests/minute
  - Resend: 10 requests/minute

- **Public Endpoints:**
  - Validate: 10 requests/minute per IP
  - Accept: 5 requests/5 minutes per IP, 3 requests/10 minutes per email

### Token Security

- UUID v4 tokens (36 characters)
- Single-use tokens (marked as `used_at` after acceptance)
- Expiration date (default 7 days, configurable)
- Token validation before acceptance

### Audit Logging

All invitation operations are logged with:
- User ID (inviter)
- Tenant ID
- Email address
- IP address
- Timestamp
- Action (create, accept, resend, cancel)

## Error Handling

### Common Error Codes

- **400 Bad Request:** Invalid input data
- **401 Unauthorized:** Missing or invalid authentication
- **403 Forbidden:** Insufficient permissions (e.g., Org Admin trying to invite to other tenant)
- **404 Not Found:** Invitation not found
- **409 Conflict:** User already member of tenant
- **410 Gone:** Invitation expired or already used
- **422 Unprocessable Entity:** Validation errors
- **429 Too Many Requests:** Rate limit exceeded
- **500 Internal Server Error:** Server error

### Error Response Format

```json
{
  "success": false,
  "error": {
    "id": "err_1234567890",
    "message": "User is already a member of this tenant",
    "status": 409,
    "timestamp": "2025-01-16T12:00:00Z"
  }
}
```

### Validation Errors

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email must be a valid email address."
    ],
    "tenant_id": [
      "The tenant id field is required."
    ]
  }
}
```

## Database Schema

### invitations table

```sql
CREATE TABLE invitations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id CHAR(26) NOT NULL,
    email VARCHAR(191) NOT NULL,
    role VARCHAR(64) NOT NULL,
    token CHAR(36) UNIQUE NOT NULL,
    inviter_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    status VARCHAR(20) DEFAULT 'pending',
    message TEXT NULL,
    note TEXT NULL,
    first_name VARCHAR(191) NULL,
    last_name VARCHAR(191) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (inviter_id) REFERENCES users(id),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_tenant_email (tenant_id, email),
    INDEX idx_tenant_expires (tenant_id, expires_at)
);
```

## Testing

### Running Tests

```bash
# Feature tests
php artisan test --group=invitations

# Unit tests
php artisan test tests/Unit/Services/InvitationServiceTest.php

# All invitation tests
php artisan test --filter=Invitation
```

### Test Coverage

- ✅ Super Admin can invite to any tenant
- ✅ Org Admin can only invite to own tenant
- ✅ Bulk invitation creation
- ✅ Idempotent invitation logic
- ✅ Token validation
- ✅ Expired token handling
- ✅ Single-use token enforcement
- ✅ Existing user acceptance
- ✅ New user registration
- ✅ Tenant isolation
- ✅ Rate limiting

## Troubleshooting

### Invitation email not sent

1. Check `MAIL_MAILER` in `.env` (should not be `array`)
2. Check mail queue is running: `php artisan queue:work`
3. Check logs: `storage/logs/laravel.log`
4. Invitation links are always returned in API response for manual sharing

### Invitation link not working

1. Check token is valid: `GET /api/invitations/{token}/validate`
2. Check expiration date
3. Check if already used (`used_at` is not null)
4. Check rate limiting (429 error)

### User cannot accept invitation

1. Verify user email matches invitation email
2. Check invitation status is `pending`
3. Check expiration date
4. Verify token is correct
5. Check rate limiting

## Support

For issues or questions, contact the development team or refer to the main project documentation.

