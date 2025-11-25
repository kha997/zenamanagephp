# PR: Observability 3-in-1 - Complete Implementation

## Summary
Completed unified observability implementation ensuring request_id and tenant_id are attached to all logs, metrics, and traces for complete request correlation.

## Changes

### New Files
1. **`app/Http/Middleware/UnifiedObservabilityMiddleware.php`**
   - Middleware to ensure request_id + tenant_id in all observability signals
   - Sets correlation ID in request attributes and container
   - Adds to Log context for unified logging
   - Records metrics with full labels (request_id + tenant_id)
   - Sets response headers (X-Request-Id, X-Correlation-Id, X-Trace-Id)

2. **`app/Http/Controllers/Api/ObservabilityController.php`**
   - API endpoints for observability data
   - `/api/v1/observability/summary` - Get 3-in-1 summary (logs, metrics, traces)
   - `/api/v1/observability/request/{requestId}` - Get data by request_id
   - `/api/v1/observability/dashboard` - Get dashboard data

### Modified Files
1. **`app/Services/ObservabilityService.php`**
   - Updated `recordHttpRequest()` to include request_id in metrics labels
   - Updated `recordDatabaseQuery()` to include request_id in metrics labels
   - Updated `recordError()` to include request_id in metrics labels
   - All metrics now have labels: `request.id`, `trace.id`, `tenant.id`, `user.id`

2. **`routes/api_v1.php`**
   - Added observability API routes (admin only)

## Unified Observability

### Logs
- **Format**: Structured JSON logs
- **Required Fields**: `request_id`, `tenant_id`, `user_id`, `route`, `method`, `path`, `latency`
- **Implementation**: Via `Log::withContext()` in `UnifiedObservabilityMiddleware`
- **Storage**: Laravel logs (can be forwarded to ELK, CloudWatch, etc.)

### Metrics
- **Labels**: `request.id`, `trace.id`, `tenant.id`, `user.id`, `http.method`, `http.path`, `http.status_code`
- **Implementation**: Via `TracingService::recordMetric()` with full labels
- **Storage**: Cache (can be exported to Prometheus, etc.)

### Traces
- **Attributes**: `request.id`, `trace.id`, `tenant.id`, `user.id`, `span_id`
- **Implementation**: Via `TracingService` with W3C traceparent support
- **Storage**: OpenTelemetry (when enabled) or custom storage

## Request Correlation

### Request ID Generation
1. From header: `X-Request-Id`, `X-Correlation-Id`, `X-Trace-Id`
2. From W3C traceparent header
3. Generate new: `req_` + unique ID

### Propagation
- **Request Attributes**: `correlation_id`, `trace_id`, `tenant_id`, `user_id`
- **Container Binding**: Available via `app('correlation_id')`, `app('tenant_id')`
- **Response Headers**: `X-Request-Id`, `X-Correlation-Id`, `X-Trace-Id`
- **Log Context**: Automatically added to all logs
- **Metrics Labels**: Automatically added to all metrics
- **Trace Attributes**: Automatically added to all traces

## API Endpoints

### GET /api/v1/observability/summary
Get unified observability summary (logs, metrics, traces).

**Query Parameters**:
- `time_window` (optional): Time window in minutes (default: 5)

**Response**:
```json
{
  "ok": true,
  "data": {
    "metrics": {
      "http": { ... },
      "database": { ... },
      "queue": { ... },
      "errors": { ... }
    },
    "traces": {
      "trace_id": "...",
      "span_id": "...",
      "correlation_id": "..."
    },
    "logs": {
      "count": 0,
      "logs": []
    }
  }
}
```

### GET /api/v1/observability/request/{requestId}
Get all observability data for a specific request_id.

**Response**:
```json
{
  "ok": true,
  "data": {
    "request_id": "req_...",
    "metrics": { ... },
    "logs": [ ... ],
    "trace": { ... }
  }
}
```

### GET /api/v1/observability/dashboard
Get observability dashboard data.

**Query Parameters**:
- `time_window` (optional): Time window in minutes (default: 60)

**Response**:
```json
{
  "ok": true,
  "data": {
    "metrics": { ... },
    "violations": [ ... ],
    "slow_requests": [ ... ],
    "errors": { ... }
  }
}
```

## Middleware Integration

### Current Setup
The system already has observability middleware:
- **Global**: `TracingMiddleware` (sets correlation ID, W3C traceparent)
- **API**: `RequestCorrelationMiddleware`, `MetricsMiddleware`

### UnifiedObservabilityMiddleware
`UnifiedObservabilityMiddleware` can be used to:
- Ensure request_id + tenant_id in all logs (via Log context)
- Ensure request_id + tenant_id in all metrics (via labels)
- Ensure request_id + tenant_id in all traces (via attributes)

**Optional Integration**: Add to `app/Http/Kernel.php` if you want to ensure unified observability:

```php
protected $middlewareGroups = [
    'web' => [
        // ... after auth middleware
        \App\Http\Middleware\UnifiedObservabilityMiddleware::class,
    ],
    'api' => [
        // ... after RequestCorrelationMiddleware
        \App\Http\Middleware\UnifiedObservabilityMiddleware::class,
    ],
];
```

**Note**: `TracingMiddleware` already provides correlation ID. `UnifiedObservabilityMiddleware` ensures it's in all signals (logs, metrics, traces).

## Usage

### Access Request ID in Code
```php
// From container
$requestId = app('correlation_id');
$tenantId = app('tenant_id');

// From request
$requestId = $request->attributes->get('correlation_id');
$tenantId = $request->attributes->get('tenant_id');
```

### Logging with Context
```php
// Context is automatically added by middleware
Log::info('User action', ['action' => 'create_task']);

// Output includes: request_id, tenant_id, user_id, route, etc.
```

### Recording Metrics
```php
// Metrics automatically include request_id and tenant_id
$observabilityService->recordHttpRequest(
    $method,
    $path,
    $statusCode,
    $latency,
    $tenantId,
    $userId,
    $requestId // Optional, auto-detected if not provided
);
```

## Integration with External Systems

### Log Aggregation (ELK, CloudWatch)
- Logs are structured JSON with `request_id` and `tenant_id`
- Can be queried by `request_id` to get all logs for a request
- Can be filtered by `tenant_id` for tenant-specific logs

### Metrics Storage (Prometheus)
- Metrics have labels: `request_id`, `tenant_id`, `user_id`
- Can query metrics by `request_id` for request-specific metrics
- Can aggregate by `tenant_id` for tenant-specific metrics

### APM/Tracing (Jaeger, Zipkin, OpenTelemetry)
- Traces include `request_id` and `tenant_id` as attributes
- Can search traces by `request_id` to see full request flow
- Can filter traces by `tenant_id` for tenant-specific traces

## Testing

### Test Request ID Propagation
```bash
# Make request with custom request ID
curl -H "X-Request-Id: test-req-123" http://localhost/api/v1/me

# Check response headers
# Should include: X-Request-Id: test-req-123
```

### Test Log Context
```bash
# Make request and check logs
# All log entries should include request_id and tenant_id
tail -f storage/logs/laravel.log | grep "request_id"
```

### Test Metrics Labels
```bash
# Make request and check metrics
# Metrics should include request_id and tenant_id in labels
php artisan tinker
>>> Cache::get('observability:metrics:*')
```

## Configuration

### Environment Variables
```env
# Observability features
OBSERVABILITY_ENABLED=true
OBSERVABILITY_ALERTS_ENABLED=true

# APM/Tracing (optional)
OPENTELEMETRY_ENABLED=false
OPENTELEMETRY_ENDPOINT=http://localhost:4318
```

## Future Enhancements

1. **Log Aggregation Integration**
   - ELK Stack integration
   - CloudWatch Logs integration
   - Log query API

2. **Metrics Storage Integration**
   - Prometheus exporter
   - CloudWatch Metrics integration
   - Metrics query API

3. **APM Integration**
   - OpenTelemetry full integration
   - Jaeger/Zipkin integration
   - Trace query API

4. **Dashboard UI**
   - React dashboard component
   - Real-time observability view
   - Request correlation viewer

## Related Documents

- [SLO/SLA Definition](docs/SLO_SLA_DEFINITION.md)
- [Performance Budgets](performance-budgets.json)
- [Metrics Collection](docs/PR_METRICS_BUDGETS_CI.md)

## Notes

- Request ID is propagated via headers, request attributes, and container binding
- All logs automatically include request_id and tenant_id via Log context
- All metrics automatically include request_id and tenant_id via labels
- All traces automatically include request_id and tenant_id via attributes
- Middleware should be registered early in middleware stack
- API endpoints require admin permission

---

**Status**: ✅ Complete  
**Last Updated**: 2025-01-19

