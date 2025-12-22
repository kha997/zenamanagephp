# WebSocket Security Checklist

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Deployment checklist and troubleshooting guide for WebSocket security

---

## Overview

WebSocket connections in ZenaManage use the same authentication and authorization mechanisms as REST API endpoints. This checklist ensures WebSocket security is properly configured and maintained.

---

## Security Requirements

### 1. Authentication

**Requirement**: All WebSocket connections MUST authenticate using Sanctum tokens.

**Implementation**:
- Client sends `authenticate` message with Sanctum token
- `AuthGuard::verifyToken()` validates token via `PersonalAccessToken::findToken()`
- Token expiration is checked
- User active status is verified

**Checklist**:
- [ ] `AuthGuard` is used for all authentication
- [ ] Token validation checks expiration
- [ ] User active status is verified
- [ ] Failed authentication attempts are logged
- [ ] Invalid tokens are rejected immediately

**Example**:
```json
{
  "type": "authenticate",
  "token": "sanctum_token_here"
}
```

---

### 2. Tenant Isolation

**Requirement**: WebSocket connections MUST respect tenant boundaries.

**Implementation**:
- Channel format: `tenant:{tenant_id}:{resource}:{resource_id}`
- `AuthGuard::canSubscribe()` enforces tenant isolation
- Cross-tenant subscription attempts are blocked
- All messages are filtered by `tenant_id`

**Checklist**:
- [ ] Channel format includes tenant ID
- [ ] `canSubscribe()` checks tenant ID matches user's tenant
- [ ] Cross-tenant access attempts are logged and blocked
- [ ] Messages are filtered by tenant_id before sending
- [ ] Connection metadata includes tenant_id

**Channel Format**:
```
tenant:{tenant_id}:{resource}:{resource_id}
Examples:
- tenant:abc123:tasks:xyz789 (specific task)
- tenant:abc123:tasks (all tasks for tenant)
- tenant:abc123:projects:def456 (specific project)
```

---

### 3. Authorization (RBAC)

**Requirement**: WebSocket subscriptions MUST respect RBAC permissions.

**Implementation**:
- Permission checks use same `AbilityMatrixService` as REST API
- Resource-specific permissions are checked (e.g., `tasks.view`, `projects.view`)
- Gate/Policy checks are used when available
- Fallback to tenant-based access if Gate fails

**Checklist**:
- [ ] Permission checks match REST API behavior
- [ ] Resource-specific permissions are verified
- [ ] Gate/Policy checks are used for resources
- [ ] Permission failures are logged
- [ ] Users without permission cannot subscribe

**Permission Mapping**:
- `tasks` → `tasks.view` permission
- `projects` → `projects.view` permission
- `documents` → `documents.view` permission

---

### 4. Rate Limiting

**Requirement**: WebSocket connections MUST respect rate limits.

**Implementation**:
- Per-connection rate limiting: 10 messages/second
- Per-tenant rate limiting: 500 messages/second
- Connection limit per tenant: 50 connections
- Sliding window algorithm

**Checklist**:
- [ ] `RateLimitGuard` is used for all rate limiting
- [ ] Per-connection limits are enforced
- [ ] Per-tenant limits are enforced
- [ ] Connection limits per tenant are enforced
- [ ] Rate limit violations are logged
- [ ] Rate limit errors are sent to client

**Limits**:
```php
MAX_MESSAGES_PER_SECOND_PER_CONNECTION = 10
MAX_MESSAGES_PER_SECOND_PER_TENANT = 500
MAX_CONNECTIONS_PER_TENANT = 50
```

---

### 5. Logging

**Requirement**: All WebSocket security events MUST be logged.

**Log Events**:
- Connection opened/closed
- Authentication success/failure
- Subscription attempts (success/failure)
- Rate limit violations
- Cross-tenant access attempts
- Permission check failures

**Log Format**:
```json
{
  "timestamp": "2025-01-15T10:30:00Z",
  "level": "INFO|WARNING|ERROR",
  "event": "websocket_authentication|websocket_subscription|websocket_rate_limit",
  "user_id": "user_123",
  "tenant_id": "tenant_abc",
  "connection_id": 456,
  "channel": "tenant:abc123:tasks:xyz789",
  "result": "success|failure",
  "reason": "permission_denied|rate_limit_exceeded|cross_tenant_access"
}
```

**Checklist**:
- [ ] All authentication events are logged
- [ ] All subscription events are logged
- [ ] Rate limit violations are logged
- [ ] Cross-tenant attempts are logged
- [ ] Logs include correlation IDs (traceId)
- [ ] PII is redacted in logs

---

## Deployment Checklist

### Pre-Deployment

- [ ] **Authentication**: Verify `AuthGuard` is properly configured
- [ ] **Tenant Isolation**: Verify `canSubscribe()` enforces tenant boundaries
- [ ] **Rate Limiting**: Verify `RateLimitGuard` limits are appropriate
- [ ] **Logging**: Verify all security events are logged
- [ ] **Testing**: Run WebSocket security tests
- [ ] **Documentation**: Update WebSocket documentation

### Production Deployment

- [ ] **Environment Variables**: Set `WEBSOCKET_ENABLED=true`
- [ ] **Rate Limits**: Verify production rate limits are stricter than dev
- [ ] **Logging**: Verify production logging includes all security events
- [ ] **Monitoring**: Set up alerts for WebSocket security violations
- [ ] **Health Check**: Verify `/api/v1/ws/health` endpoint works

### Post-Deployment

- [ ] **Monitor Logs**: Check for authentication failures
- [ ] **Monitor Rate Limits**: Check for rate limit violations
- [ ] **Monitor Connections**: Check connection counts per tenant
- [ ] **Verify Isolation**: Test cross-tenant access is blocked
- [ ] **Verify Permissions**: Test permission-based subscriptions

---

## Troubleshooting Guide

### Issue: "WebSocket authentication fails"

**Symptoms**:
- Client receives "Invalid authentication token" error
- Logs show "WebSocket authentication failed"

**Diagnosis**:
1. Check token is valid Sanctum token
2. Check token is not expired
3. Check user is active
4. Check `AuthGuard::verifyToken()` logs

**Solution**:
```php
// Verify token manually
$token = PersonalAccessToken::findToken($tokenString);
if (!$token) {
    // Token not found
}
if ($token->expires_at && $token->expires_at->isPast()) {
    // Token expired
}
$user = $token->tokenable;
if (!$user || !$user->is_active) {
    // User disabled
}
```

---

### Issue: "Cannot subscribe to channel"

**Symptoms**:
- Client receives "Subscription denied" error
- Logs show "WebSocket cross-tenant subscription attempt blocked"

**Diagnosis**:
1. Check channel format is correct: `tenant:{tenant_id}:{resource}:{resource_id}`
2. Check user's tenant_id matches channel tenant_id
3. Check user has required permission
4. Check resource exists and belongs to tenant

**Solution**:
```php
// Verify subscription manually
$user = Auth::user();
$channel = "tenant:{$user->tenant_id}:tasks:task_123";
$canSubscribe = $authGuard->canSubscribe($user, $user->tenant_id, $channel);
```

---

### Issue: "Rate limit exceeded"

**Symptoms**:
- Client receives "Rate limit exceeded" error
- Logs show "WebSocket rate limit exceeded"

**Diagnosis**:
1. Check per-connection rate limit (10 msg/sec)
2. Check per-tenant rate limit (500 msg/sec)
3. Check connection count per tenant (50 max)

**Solution**:
```php
// Check rate limit stats
$stats = $rateLimitGuard->getStats();
// Review connections_per_tenant and messages_per_tenant_per_second
```

---

### Issue: "WebSocket connection limit exceeded"

**Symptoms**:
- Client receives "Connection limit exceeded for your organization"
- Logs show "WebSocket connection limit exceeded for tenant"

**Diagnosis**:
1. Check tenant has > 50 active connections
2. Check for connection leaks (connections not properly closed)
3. Check for multiple browser tabs/windows

**Solution**:
```php
// Check connection count
$stats = $rateLimitGuard->getStats();
$connections = $stats['connections_per_tenant'][$tenantId] ?? 0;
if ($connections >= 50) {
    // Limit exceeded - need to close old connections
}
```

---

### Issue: "WebSocket not receiving messages"

**Symptoms**:
- Connection established but no messages received
- Messages sent but not delivered

**Diagnosis**:
1. Check subscription was successful
2. Check tenant isolation (messages filtered by tenant_id)
3. Check permission (user has permission to receive messages)
4. Check message queue (backpressure)

**Solution**:
```php
// Verify subscription
$subscribedChannels = $conn->subscribedChannels ?? [];
// Check if channel is in subscribed list
// Check if messages are being filtered by tenant_id
```

---

## Security Testing

### Test Authentication

```php
// Test: Valid token should authenticate
$user = User::factory()->create();
$token = $user->createToken('test')->plainTextToken;
$authenticatedUser = $authGuard->verifyToken($token);
$this->assertNotNull($authenticatedUser);
$this->assertEquals($user->id, $authenticatedUser->id);

// Test: Invalid token should fail
$invalidUser = $authGuard->verifyToken('invalid_token');
$this->assertNull($invalidUser);
```

### Test Tenant Isolation

```php
// Test: User cannot subscribe to other tenant's channel
$userA = User::factory()->create(['tenant_id' => 'tenant_a']);
$userB = User::factory()->create(['tenant_id' => 'tenant_b']);
$channel = 'tenant:tenant_b:tasks:task_123';

$canSubscribe = $authGuard->canSubscribe($userA, 'tenant_a', $channel);
$this->assertFalse($canSubscribe); // Should be blocked
```

### Test Rate Limiting

```php
// Test: Rate limit should block after limit exceeded
$conn = new MockConnection();
for ($i = 0; $i < 10; $i++) {
    $this->assertTrue($rateLimitGuard->canSendMessage($conn));
}
$this->assertFalse($rateLimitGuard->canSendMessage($conn)); // Should be blocked
```

---

## Monitoring & Alerts

### Key Metrics

1. **Authentication Failure Rate**: Should be < 1%
2. **Rate Limit Violations**: Should be < 0.1%
3. **Cross-Tenant Attempts**: Should be 0 (all blocked)
4. **Connection Count**: Monitor per tenant
5. **Message Rate**: Monitor per tenant

### Alerts

Set up alerts for:
- Authentication failure rate > 5%
- Rate limit violations > 1%
- Cross-tenant access attempts > 0
- Connection count > 80% of limit
- WebSocket service down

---

## Best Practices

1. **Always Authenticate**: Never allow unauthenticated connections
2. **Enforce Tenant Isolation**: Always check tenant_id matches
3. **Check Permissions**: Always verify user has required permission
4. **Rate Limit**: Always enforce rate limits
5. **Log Everything**: Log all security events
6. **Monitor**: Set up monitoring and alerts
7. **Test**: Run security tests before deployment

---

## WebSocket = REST API Contract

WebSocket security MUST match REST API behavior:

### Rule 1: Authentication
- If REST `/api/v1/app/tasks/:id` requires auth, WS `subscribe task:id` also requires auth
- Same Sanctum token validation

### Rule 2: Authorization
- If REST `/api/v1/app/tasks/:id` returns 403, WS `subscribe task:id` also returns 403
- Same permission checks

### Rule 3: Tenant Isolation
- If REST API filters by tenant_id, WS also filters by tenant_id
- Same tenant scope logic

---

## References

- [WebSocket Architecture](WEBSOCKET_ARCHITECTURE.md)
- [AuthGuard](../../app/WebSocket/AuthGuard.php)
- [RateLimitGuard](../../app/WebSocket/RateLimitGuard.php)
- [DashboardWebSocketHandler](../../app/WebSocket/DashboardWebSocketHandler.php)
- [Security Environment Matrix](SECURITY_ENVIRONMENT_MATRIX.md)

---

*This checklist must be verified before every WebSocket deployment.*

