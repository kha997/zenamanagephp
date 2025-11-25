# WebSocket Architecture

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

ZenaManage uses WebSocket for real-time dashboard updates, notifications, and live data synchronization. This document describes the WebSocket architecture, entrypoints, and security requirements.

---

## Entrypoints

### ✅ Valid Entrypoints

#### 1. websocket_server.php

**Location**: `websocket_server.php` (root)

**Purpose**: Standalone WebSocket server entrypoint

**Usage**:
```bash
php websocket_server.php
```

**Features**:
- Uses `ZenaWebSocketServer` implementation
- Runs on port 8080 (configurable)
- Handles authentication, project channels, notifications

#### 2. WebSocketServerCommand (Artisan)

**Location**: `app/Console/Commands/WebSocketServerCommand.php`

**Purpose**: Artisan command for running WebSocket server

**Usage**:
```bash
php artisan websocket:serve --host=0.0.0.0 --port=8080 --workers=1
```

**Features**:
- Uses `DashboardWebSocketHandler` (proper auth/security)
- Configurable host, port, workers
- Production-ready with authentication guards

**Handler**: `app/WebSocket/DashboardWebSocketHandler.php`

---

## Deprecated Entrypoints

### ❌ simple_websocket_server.php

**Status**: `@deprecated since 2025-01-XX`

**Reason**: 
- No authentication
- No security guards
- No tenant isolation
- Basic implementation only

**DO NOT USE IN PRODUCTION**

**Migration**: Use `websocket_server.php` or `php artisan websocket:serve`

### ❌ websocket_test.php.disabled

**Status**: Disabled test file

**Purpose**: Was used for testing, now disabled

**Note**: Should not be enabled or used in production

---

## WebSocket Handler

### DashboardWebSocketHandler

**Location**: `app/WebSocket/DashboardWebSocketHandler.php`

**Features**:
- **Authentication**: Uses `AuthGuard` to validate Sanctum tokens
- **Rate Limiting**: Uses `RateLimitGuard` to prevent abuse
- **Tenant Isolation**: Ensures tenant-scoped connections
- **RBAC**: Validates user permissions via `AbilityMatrixService`

**Message Types**:
- `authenticate`: Authenticate connection with token
- `subscribe`: Subscribe to channels (dashboard, alerts, project, etc.)
- `unsubscribe`: Unsubscribe from channels
- `ping`: Heartbeat/ping message

**Example**:
```json
{
  "type": "authenticate",
  "token": "sanctum_token_here"
}
```

---

## Security Requirements

### Authentication

WebSocket connections **MUST** authenticate using Sanctum tokens, same as REST API:

1. Client sends `authenticate` message with token
2. `AuthGuard` validates token via Sanctum
3. Connection is associated with authenticated user
4. Tenant context is set from user's tenant_id

### Tenant Isolation

WebSocket connections **MUST** respect tenant boundaries:

1. User can only subscribe to channels for their tenant
2. Messages are filtered by tenant_id
3. Cross-tenant access is blocked

### RBAC

WebSocket subscriptions **MUST** respect RBAC permissions:

1. User can only subscribe to channels they have permission for
2. Permission checks use same `AbilityMatrixService` as REST API
3. If REST API returns 403 for resource, WebSocket subscription also fails

### Rate Limiting

WebSocket connections **MUST** respect rate limits:

1. Per-connection rate limiting via `RateLimitGuard`
2. Per-tenant rate limiting
3. Message rate limits to prevent abuse

---

## Contract: WebSocket = REST

WebSocket security and permissions **MUST** match REST API behavior:

### Rule 1: Authentication
- If REST `/api/v1/app/tasks/:id` requires auth, WS `subscribe task:id` also requires auth
- Same Sanctum token validation

### Rule 2: Authorization
- If REST `/api/v1/app/tasks/:id` returns 403, WS `subscribe task:id` also returns 403
- Same permission checks via `AbilityMatrixService`

### Rule 3: Tenant Isolation
- If REST API filters by tenant_id, WS also filters by tenant_id
- Same tenant scope middleware logic

### Testing

See `tests/Feature/WebSocket/WebSocketRestContractTest.php` for contract validation tests.

---

## Configuration

### config/websocket.php

```php
'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
'port' => env('WEBSOCKET_PORT', 8080),
'workers' => env('WEBSOCKET_WORKERS', 1),

'auth' => [
    'guard' => 'sanctum',
    'token_header' => 'Authorization',
    'token_prefix' => 'Bearer ',
],

'channels' => [
    'dashboard' => 'dashboard.{user_id}',
    'alerts' => 'alerts.{user_id}',
    'metrics' => 'metrics.{tenant_id}',
    'notifications' => 'notifications.{user_id}',
    'project' => 'project.{project_id}',
    'system' => 'system.{tenant_id}',
],

'heartbeat' => [
    'interval' => 30, // seconds
    'timeout' => 60, // seconds
],
```

---

## Metrics & Monitoring

### Endpoint: GET /api/metrics/websocket

**Purpose**: WebSocket server metrics

**Response**:
```json
{
  "connections": {
    "total": 150,
    "per_tenant": {
      "tenant_1": 50,
      "tenant_2": 100
    }
  },
  "messages": {
    "per_second": 25.5,
    "total_today": 125000
  },
  "errors": {
    "per_second": 0.1,
    "total_today": 500
  },
  "dropped": {
    "per_second": 0,
    "total_today": 0
  }
}
```

**Service**: `app/Services/WebSocketMetricsService.php`

---

## Development

### Running Locally

```bash
# Option 1: Standalone server
php websocket_server.php

# Option 2: Artisan command (recommended)
php artisan websocket:serve --host=127.0.0.1 --port=8080
```

### Testing

```bash
# Run WebSocket tests
php artisan test tests/Feature/WebSocket/

# Run contract tests
php artisan test tests/Feature/WebSocket/WebSocketRestContractTest.php
```

---

## Production Deployment

### Requirements

1. **Use Artisan Command**: `php artisan websocket:serve`
2. **Use DashboardWebSocketHandler**: Has proper auth/security
3. **Enable Rate Limiting**: Via `RateLimitGuard`
4. **Monitor Metrics**: Use `/api/metrics/websocket`
5. **Set Up Process Manager**: Use Supervisor or systemd

### Supervisor Configuration

```ini
[program:zenamanage-websocket]
command=php /path/to/artisan websocket:serve --host=0.0.0.0 --port=8080
directory=/path/to/zenamanage
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/zenamanage/websocket.log
```

---

## Troubleshooting

### Connection Issues

1. Check WebSocket server is running: `ps aux | grep websocket`
2. Check port is open: `netstat -tuln | grep 8080`
3. Check firewall rules
4. Verify authentication token is valid

### Permission Issues

1. Verify user has required permissions
2. Check tenant isolation is working
3. Review RBAC matrix
4. Check WebSocketRestContractTest for contract violations

---

## References

- [WebSocket Security Tests](tests/Feature/Security/WebSocketSecurityTest.php)
- [WebSocket Auth Tests](tests/Feature/WebSocketAuthTest.php)
- [Dashboard WebSocket Handler](app/WebSocket/DashboardWebSocketHandler.php)
- [ADR-002: Blade Deprecation Plan](architecture/decisions/002-blade-deprecation.md)

