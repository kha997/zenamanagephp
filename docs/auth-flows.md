# Authentication Flows Documentation

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Complete reference for all authentication endpoints, flows, and implementation details

---

## Overview

This document provides a comprehensive guide to all authentication flows in ZenaManage, including endpoints, request/response formats, error handling, and tenancy considerations.

---

## Authentication Endpoints

### 1. Login

**Endpoint**: `POST /api/auth/login`

**Description**: Authenticates a user and returns a session token.

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "password123",
  "remember": false
}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "session_id": "session_abc123",
    "token": "1|token_xyz789",
    "token_type": "Bearer",
    "expires_in": 2592000,
    "onboarding_state": "completed",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "tenant_id": 1,
      "can_access_admin": false,
      "is_super_admin": false,
      "is_org_admin": false
    },
    "redirect_path": "/app/dashboard"
  }
}
```

**Response** (Error - 401):
```json
{
  "success": false,
  "error": {
    "id": "INVALID_CREDENTIALS",
    "message": "Invalid credentials",
    "status": 401
  }
}
```

**Middleware**: `web`, `throttle:login`, `brute.force.protection`, `input.validation`

**Notes**:
- Uses Laravel Sanctum for token generation
- Creates web session for CSRF protection
- Returns redirect path based on user permissions
- All tokens are tenant-scoped

---

### 2. Logout

**Endpoint**: `POST /api/auth/logout`

**Description**: Logs out the authenticated user and revokes all tokens.

**Request Headers**:
```
Authorization: Bearer {token}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "message": "Logged out successfully"
  }
}
```

**Middleware**: `auth:sanctum`, `security`, `validation`

**Notes**:
- Revokes all Sanctum tokens for the user
- Revokes all sessions
- No request body required

---

### 3. Get Current User (Me)

**Endpoint**: `GET /api/auth/me`

**Description**: Returns the currently authenticated user's information.

**Request Headers**:
```
Authorization: Bearer {token}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "tenant_id": 1,
      "email_verified_at": "2025-01-01T00:00:00.000000Z",
      "last_login_at": "2025-01-15T10:30:00.000000Z",
      "created_at": "2025-01-01T00:00:00.000000Z"
    }
  }
}
```

**Response** (Error - 401):
```json
{
  "success": false,
  "error": {
    "id": "AUTH_REQUIRED",
    "message": "User not authenticated",
    "status": 401
  }
}
```

**Middleware**: `auth:sanctum`, `ability:tenant`

**Notes**:
- Returns user data with tenant information
- Respects tenant isolation

---

### 4. Register / Signup

**Endpoint**: `POST /api/auth/register` (alias)  
**Endpoint**: `POST /api/public/auth/register` (primary)

**Description**: Registers a new user and creates a tenant.

**Request Body**:
```json
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "tenant_name": "Acme Corp",
  "phone": "+1234567890",
  "terms": true
}
```

**Response** (Success - 201):
```json
{
  "status": "created",
  "success": true,
  "data": {
    "message": "Registration successful. Please check your email for verification.",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "tenant_id": 1,
      "email_verified_at": null
    },
    "tenant": {
      "id": 1,
      "name": "Acme Corp",
      "slug": "acme-corp"
    },
    "verification_sent": true
  }
}
```

**Response** (Error - 403):
```json
{
  "success": false,
  "error": {
    "id": "REGISTRATION_DISABLED",
    "message": "Public registration is currently disabled. Please contact an administrator.",
    "status": 403
  }
}
```

**Middleware**: `throttle:register`

**Notes**:
- Requires feature flag: `features.auth.public_signup_enabled`
- Creates tenant and owner user
- Sends email verification (if configured)
- Password must meet policy requirements (min 8 chars, mixed case, numbers, symbols)

---

### 5. Forgot Password

**Endpoint**: `POST /api/auth/password/forgot`

**Description**: Sends a password reset link to the user's email.

**Request Body**:
```json
{
  "email": "user@example.com"
}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "message": "Password reset link sent to your email address."
  }
}
```

**Response** (Error - 422):
```json
{
  "success": false,
  "error": {
    "id": "RESET_LINK_FAILED",
    "message": "User not found.",
    "status": 422
  }
}
```

**Middleware**: `security`, `validation`, `throttle:password-reset`

**Notes**:
- Always returns success message (prevents email enumeration)
- Uses Laravel's password reset broker
- Rate limited to prevent abuse
- Token expires after 1 hour (configurable)

---

### 6. Reset Password

**Endpoint**: `POST /api/auth/password/reset`

**Description**: Resets the user's password using a token from the email.

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!",
  "token": "reset_token_from_email"
}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "message": "Password has been reset successfully."
  }
}
```

**Response** (Error - 422):
```json
{
  "success": false,
  "error": {
    "id": "PASSWORD_RESET_FAILED",
    "message": "Invalid or expired token.",
    "status": 422
  }
}
```

**Middleware**: `security`, `validation`, `throttle:password-reset`

**Notes**:
- Token is single-use (deleted after successful reset)
- Password must meet policy requirements
- Token expires after 1 hour
- Invalidates remember token

---

### 7. Change Password (Authenticated)

**Endpoint**: `POST /api/auth/password/change`

**Description**: Changes the password for an authenticated user.

**Request Headers**:
```
Authorization: Bearer {token}
```

**Request Body**:
```json
{
  "current_password": "OldPassword123!",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}
```

**Response** (Success - 200):
```json
{
  "status": "success",
  "success": true,
  "data": {
    "message": "Password changed successfully."
  }
}
```

**Response** (Error - 422):
```json
{
  "success": false,
  "error": {
    "id": "PASSWORD_POLICY_VIOLATION",
    "message": "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.",
    "status": 422
  }
}
```

**Middleware**: `auth:sanctum`, `ability:tenant`, `security`, `validation`, `rate.limit:sliding,5,1`

**Notes**:
- Requires current password verification
- Revokes all tokens after password change (user must login again)
- Password must be different from current password
- Password must meet policy requirements

---

## Frontend Routes

### Public Routes (No Authentication Required)

- `/login` - Login page
- `/register` - Registration page
- `/forgot-password` - Forgot password page
- `/reset-password?token=...&email=...` - Reset password page

### Protected Routes (Authentication Required)

- `/app/account/change-password` - Change password page (standalone)
- `/app/settings` - Settings page (includes change password in Security tab)

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_CREDENTIALS` | 401 | Invalid email or password |
| `AUTH_REQUIRED` | 401 | User not authenticated |
| `AUTH_FAILED` | 500 | Authentication process failed |
| `REGISTRATION_DISABLED` | 403 | Public registration is disabled |
| `REGISTRATION_FAILED` | 500 | Registration process failed |
| `VALIDATION_FAILED` | 422 | Request validation failed |
| `RESET_LINK_FAILED` | 422 | Failed to send reset link |
| `PASSWORD_RESET_FAILED` | 422 | Password reset failed (invalid/expired token) |
| `PASSWORD_POLICY_VIOLATION` | 422 | Password does not meet policy requirements |
| `PASSWORD_CHANGE_FAILED` | 500 | Password change process failed |
| `LOGOUT_FAILED` | 500 | Logout process failed |

---

## Password Policy

All passwords must meet the following requirements:

- Minimum 8 characters
- Maximum 128 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character
- Must not be in compromised password database (if enabled)

---

## Tenancy Considerations

### Tenant Isolation

All authentication flows respect tenant isolation:

1. **Registration**: Creates a new tenant and assigns the user to it
2. **Login**: Returns user with `tenant_id`, all subsequent API calls are tenant-scoped
3. **Password Reset**: Works across tenants (public endpoint), but tokens are user-specific
4. **Change Password**: Only works for the authenticated user's tenant

### Tenant Assignment

- New users are assigned to the tenant created during registration
- Users cannot change their tenant via authentication flows
- Admin users can access multiple tenants (via `ability:admin` or `ability:tenant`)

---

## Security Features

### Rate Limiting

- **Login**: Throttled per IP and per email
- **Register**: Throttled per IP
- **Forgot Password**: Throttled per email (prevents abuse)
- **Change Password**: Sliding window rate limit (5 requests per minute)

### Token Management

- Sanctum tokens are used for API authentication
- Tokens can have expiration times
- All tokens are revoked on password change
- Tokens are tenant-scoped

### CSRF Protection

- Web routes use Laravel's CSRF protection
- API routes use token-based authentication
- CSRF cookie is required for login endpoint

---

## Testing

### Backend Tests

- `tests/Feature/Auth/AuthenticationTest.php` - Login/logout tests
- `tests/Feature/Auth/AuthenticationModuleTest.php` - Registration tests
- `tests/Feature/Api/Auth/PasswordResetTest.php` - Password reset tests
- `tests/Feature/Auth/ChangePasswordTest.php` - Change password tests

### E2E Tests

- `tests/e2e/auth/login.spec.ts` - Login flow tests
- `tests/e2e/auth/change-password.spec.ts` - Change password flow tests
- `tests/e2e/auth/password-reset-flow.spec.ts` - Password reset flow tests
- `tests/e2e/auth/registration.spec.ts` - Registration flow tests

---

## Implementation Notes

### Backend Controllers

- `App\Http\Controllers\Api\Auth\AuthenticationController` - Login/logout/me
- `App\Http\Controllers\Api\Auth\RegistrationController` - Registration
- `App\Http\Controllers\Api\Auth\PasswordController` - Password management

### Frontend Components

- `frontend/src/features/auth/pages/LoginPage.tsx` - Login page
- `frontend/src/features/auth/pages/RegisterPage.tsx` - Registration page
- `frontend/src/features/auth/pages/ForgotPasswordPage.tsx` - Forgot password page
- `frontend/src/features/auth/pages/ResetPasswordPage.tsx` - Reset password page
- `frontend/src/features/auth/pages/ChangePasswordPage.tsx` - Change password page (standalone)

### API Client

- `frontend/src/features/auth/api.ts` - Auth API client with all endpoints

---

## Future Enhancements

- [ ] Two-factor authentication (2FA) - TOTP support
- [ ] Social login (OAuth providers)
- [ ] Account recovery via security questions
- [ ] Password history (prevent reuse of recent passwords)
- [ ] Session management UI (view/revoke active sessions)

---

## Related Documentation

- `docs/AUTH_TENANT_FLOW.md` - Detailed authentication and tenant flow
- `AUTH_SYSTEM_DOCUMENTATION.md` - Authentication system architecture

