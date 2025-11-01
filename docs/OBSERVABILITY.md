# OBSERVABILITY GUIDE

This document outlines the observability features implemented in ZenaManage for monitoring, logging, and alerting.

## 1. Logging Infrastructure

### Structured Logging
- **JSON Format**: All logs are structured in JSON format for easy parsing
- **Correlation IDs**: Every request gets a unique correlation ID for tracing
- **PII Redaction**: Sensitive data is automatically redacted from logs
- **Multiple Channels**: Different log channels for different purposes

### Log Channels

#### Application Logs
- **`single`**: Main application log (`storage/logs/laravel.log`)
- **`structured`**: Structured JSON logs (`storage/logs/structured.log`)

#### Specialized Logs
- **`audit`**: User actions and data changes
- **`performance`**: Performance metrics and slow queries
- **`security`**: Security events and failed authentication attempts
- **`admin`**: Administrative actions
- **`data`**: Data access and modifications
- **`api`**: API requests and responses

#### External Channels
- **`slack`**: Critical alerts sent to Slack
- **`syslog`**: System-level logging integration

### Configuration
```php
// config/logging.php
'features' => [
    'structured_logging' => true,
    'request_id_propagation' => true,
    'pii_redaction' => true,
    'performance_tracking' => true,
    'audit_logging' => true,
],
```

## 2. Health Monitoring

### Health Check Endpoints
- **`/_debug/health`**: Basic health status
- **`/_debug/health-detailed`**: Detailed health with dependency checks
- **`/_debug/metrics`**: Application metrics and KPIs

### Health Checks Include
- **Database**: Connectivity and response time
- **Cache**: Read/write operations
- **Redis**: Connection status (if configured)
- **Storage**: File system operations

### Example Response
```json
{
  "status": "healthy",
  "timestamp": "2025-10-06T10:30:00.000Z",
  "checks": {
    "database": {
      "status": "healthy",
      "response_time_ms": 15.2,
      "connection": "ok"
    },
    "cache": {
      "status": "healthy",
      "response_time_ms": 2.1,
      "read_write": "ok"
    }
  }
}
```

## 3. Performance Monitoring

### Metrics Tracked
- **Memory Usage**: Current and peak memory consumption
- **Execution Time**: Request processing time
- **Database Queries**: Query count and execution time
- **Cache Hit Rate**: Cache effectiveness
- **Response Times**: API and page load times

### Performance Thresholds
```php
'performance' => [
    'page_load_threshold' => 500, // ms
    'api_response_threshold' => 300, // ms
    'database_query_threshold' => 100, // ms
    'cache_hit_threshold' => 90, // percentage
],
```

### Performance Endpoint
- **`/_debug/performance`**: Real-time performance metrics

## 4. Alerting

### Slack Integration
- **Critical Errors**: Automatically sent to Slack
- **Deployment Notifications**: Success/failure alerts
- **Performance Alerts**: When thresholds are exceeded

### Configuration
```env
SLACK_WEBHOOK=https://hooks.slack.com/services/...
SLACK_CHANNEL=#alerts
SLACK_USERNAME=ZenaManage
```

## 5. Monitoring Best Practices

### Log Levels
- **DEBUG**: Development only
- **INFO**: Normal operations
- **WARN**: Degraded performance
- **ERROR**: 4xx errors (actionable)
- **CRITICAL**: 5xx errors (immediate attention)

### Correlation IDs
Every request gets a unique correlation ID that flows through:
1. Request headers
2. Database queries
3. Log entries
4. Error responses

### PII Redaction
Automatically redacts sensitive information:
- Passwords
- Tokens
- Secrets
- API keys
- Email addresses
- Phone numbers
- SSNs
- Credit card numbers

## 6. Dusk E2E Testing

### Smoke Tests
Six critical user flows are tested:
1. **LoginFlowTest**: Authentication and logout
2. **ProjectsFlowTest**: Project CRUD operations
3. **TasksFlowTest**: Task management and progress updates
4. **ClientsFlowTest**: Client management
5. **QuotesFlowTest**: Quote creation and management
6. **NavFlowTest**: Navigation between sections

### Test Configuration
- **Headless Chrome**: Runs in CI/CD pipeline
- **Screenshots**: Captured on test failures
- **Database**: Uses in-memory SQLite for speed

## 7. CI/CD Pipeline

### Pipeline Stages
1. **Route Gate**: Early validation of route integrity
2. **Unit/Feature Tests**: Comprehensive test suite
3. **Dusk E2E Tests**: Browser automation tests
4. **Security Scan**: Vulnerability assessment
5. **Performance Tests**: Performance regression testing
6. **Deployment**: Staging and production deployment

### Quality Gates
- All tests must pass
- No security vulnerabilities
- Performance within thresholds
- Route integrity maintained

## 8. Deployment Monitoring

### Post-Deployment Checks
- **Route Smoke Tests**: Verify all routes are accessible
- **Health Checks**: Ensure all services are healthy
- **Performance Validation**: Confirm performance metrics

### Rollback Triggers
- Health check failures
- Performance degradation
- Error rate spikes
- Critical alerts

## 9. Troubleshooting

### Common Issues
- **High Memory Usage**: Check for memory leaks in logs
- **Slow Queries**: Review database query logs
- **Cache Misses**: Analyze cache hit rates
- **Error Spikes**: Check correlation IDs for patterns

### Debug Commands
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check health
curl http://localhost:8000/_debug/health

# View metrics
curl http://localhost:8000/_debug/metrics

# Performance check
curl http://localhost:8000/_debug/performance
```

## 10. Future Enhancements

### Planned Features
- **APM Integration**: New Relic, Datadog, or similar
- **Custom Dashboards**: Grafana integration
- **Real-time Alerts**: WebSocket-based notifications
- **Log Aggregation**: Centralized log management
- **Distributed Tracing**: Request flow visualization

---

*Last Updated: 2025-10-06*
