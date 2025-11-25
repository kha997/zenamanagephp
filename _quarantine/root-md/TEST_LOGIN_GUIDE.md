# Test Login Guide

## Overview

This guide explains how to use the updated login test scripts that now properly use the API authentication endpoints.

## Changes Made

### 1. Updated Test Scripts
- **test-login-simple.sh**: Now uses `/api/v1/auth/login` with JSON payload
- **test-login.sh**: Updated to use API authentication and extract Bearer tokens

### 2. Created Test User Seeder
- **TestLoginUserSeeder.php**: Ensures `test@example.com` user exists with proper tenant linkage

### 3. Fixed Authentication Flow
The old scripts incorrectly tried to POST to `/login` (web route), which only accepts GET requests.
The new scripts properly use the API endpoint `/api/v1/auth/login` with JSON authentication.

## Setup

### 1. Create Test User
```bash
# Run the seeder to create/update the test user
php artisan db:seed --class=TestLoginUserSeeder
```

This creates:
- **Email**: test@example.com
- **Password**: password
- **Tenant**: Linked to first available tenant
- **Status**: Active and email verified
- **Role**: Admin (member role)

### 2. Test the Login
The test scripts verify the entire authentication flow:
1. Login via API endpoint
2. Extract Bearer token from response
3. Use token for authenticated API request

#### Run Simple Test
```bash
./test-login-simple.sh
```

#### Run Full Test (requires jq)
```bash
./test-login.sh
```

Expected output shows:
- Login successful with token
- Dashboard API access successful

### 2. Run Test Scripts

#### Simple Test
```bash
./test-login-simple.sh
```

This script:
1. Logs in via `POST /api/v1/auth/login`
2. Extracts the Bearer token
3. Tests authenticated request to `/api/dashboard`

#### Full Test (with jq)
```bash
./test-login.sh
```

This script uses `jq` to pretty-print JSON responses.

## API Authentication Flow

The test scripts now follow the correct authentication flow:

```
1. POST /api/v1/auth/login
   Headers: Content-Type: application/json
   Body: {"email":"test@example.com","password":"password"}
   
2. Response includes Bearer token
   {"success":true,"token":"...","user":{...}}
   
3. Use token for authenticated requests
   Headers: Authorization: Bearer <token>
```

## Manual Testing

### Using curl
```bash
# Login and get token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'

# Use token for authenticated request
curl http://localhost:8000/api/dashboard \
  -H 'Authorization: Bearer <token-from-login>'
```

### Using tinker
```bash
php artisan tinker
```
```php
$user = User::where('email', 'test@example.com')->first();
$user->tenant_id; // Should have a tenant
$user->is_active; // Should be true
```

## Troubleshooting

### Script Fails with "No token"
- Verify the API is running: `curl http://localhost:8000/api/v1/auth/login -X POST`
- Check user exists: Run the seeder
- Verify password: Should be "password"

### 401 Unauthorized
- Check token is being sent correctly
- Verify token hasn't expired
- Check middleware is allowing the request

### 404 Not Found
- Verify API routes are loaded: `php artisan route:list | grep auth`
- Check that the Laravel server is running on port 8000

## API Routes

The authentication endpoint is defined in `routes/api.php`:

```165:169:routes/api.php
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'logout'])
        ->middleware(['auth:sanctum', 'security', 'validation']);
});
```

Full route: `/api/v1/auth/login`

## Implementation Notes

- The old scripts tried to POST to `/login` (web route), which only accepts GET
- The API authentication is handled by `AuthenticationService.php` which validates:
  - User exists and is active
  - Password matches
  - User has a tenant_id (required for multi-tenant isolation)
  - Email is verified
- The token is a Sanctum personal access token
- The token should be sent in the `Authorization: Bearer <token>` header

