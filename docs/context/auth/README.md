# Auth Context

**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

The Auth context handles authentication, authorization, user sessions, and permissions.

---

## Key Components

### Services

- **`PermissionService`** (`app/Services/PermissionService.php`)
  - Permission management
  - Role-based access control

- **`AbilityMatrixService`** (`app/Services/AbilityMatrixService.php`)
  - Ability matrix for RBAC
  - Permission checks

### Controllers

- **`Api\Auth\AuthenticationController`** (`app/Http/Controllers/Api/Auth/AuthenticationController.php`)
  - Login/logout endpoints
  - Token management

### Models

- **`User`** (`app/Models/User.php`)
  - User model with tenant relationship
- **`Role`** (`app/Models/Role.php`)
  - Role model
- **`Permission`** (`app/Models/Permission.php`)
  - Permission model

---

## API Endpoints

- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/v1/me` - Get current user
- `GET /api/v1/me/permissions` - Get user permissions

---

## Test Organization

```bash
# Run all auth tests
php artisan test --group=auth

# Run auth feature tests
php artisan test --testsuite=auth-feature
```

---

## References

- [Architecture Layering Guide](../ARCHITECTURE_LAYERING_GUIDE.md)
- [Security Review](../SECURITY_REVIEW.md)

