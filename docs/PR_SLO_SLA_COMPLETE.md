# PR: SLO/SLA nội bộ - Complete Implementation

## Summary
Completed SLO/SLA tracking implementation with alerting, dashboard freshness tracking, and comprehensive SLO definition document.

## Changes

### New Files
1. **`docs/SLO_SLA_DEFINITION.md`**
   - Complete SLO definition document
   - SLO targets for all categories (API, Pages, WebSocket, Cache, Database, Error Rate, Availability)
   - Alerting rules and severity levels
   - Measurement methodology
   - SLO violation response procedures

2. **`app/Services/SLOAlertingService.php`**
   - SLO compliance checking service
   - Violation detection and alerting
   - Multi-channel alerting (Email, Slack, In-App)
   - Alert cooldown management
   - Severity calculation

3. **`app/Services/DashboardFreshnessTracker.php`**
   - Tracks cache freshness after mutations
   - Records mutation → invalidation → refresh timestamps
   - Calculates freshness metrics (p50, p95, p99)
   - Detects freshness violations

4. **`app/Console/Commands/CheckSLOCompliance.php`**
   - Scheduled command to check SLO compliance
   - Runs every 5 minutes
   - Displays violations and metrics
   - Supports freshness-only check

5. **`app/Mail/SLOAlertEmail.php`**
   - Email mailable for SLO violation alerts
   - HTML email template with violation details

6. **`config/slo.php`**
   - SLO configuration file
   - Alert recipients
   - Slack webhook URL
   - Cooldown periods
   - SLO targets (optional overrides)

7. **`resources/views/emails/slo-alert.blade.php`**
   - HTML email template for SLO alerts
   - Color-coded by severity
   - Displays all violation details

### Modified Files
1. **`app/Console/Kernel.php`**
   - Added scheduled command `slo:check` (every 5 minutes)

## SLO Targets

### API Performance
- Standard endpoints: p95 < 300ms
- Critical endpoints: p95 < 200ms
- Admin endpoints: p95 < 500ms

### Page Performance
- App pages: p95 < 500ms
- Admin pages: p95 < 600ms

### WebSocket Performance
- Subscribe: p95 < 200ms
- Message delivery: p95 < 100ms
- Connection establishment: p95 < 500ms

### Cache Performance
- Hit rate: > 80%
- Freshness: ≤ 5s after mutation
- Invalidation latency: p95 < 50ms

### Database Performance
- Query time: p95 < 100ms
- Slow queries: < 10 per hour

### Error Rate
- 4xx errors: < 1%
- 5xx errors: < 0.1%

### Availability
- Uptime: > 99.9%

## Alerting

### Severity Levels
1. **Critical**: > 100% of target (immediate action)
2. **Warning**: > 80% of target (attention needed)
3. **Info**: > 60% of target (monitoring)

### Alert Channels
1. **Email**: Critical alerts only
2. **Slack**: All alerts
3. **In-App**: Warning and Info alerts

### Cooldown Periods
- Critical: No cooldown
- Warning: 15 minutes
- Info: 1 hour

## Dashboard Freshness Tracking

### Target
- Dashboard updates ≤ 5 seconds after mutation

### Tracked Mutations
- Task create/update/delete/move
- Project create/update/delete
- Document upload/update/delete
- Team member add/remove

### Metrics
- p50, p95, p99 freshness
- Average freshness
- Violation detection

## Usage

### Check SLO Compliance
```bash
# Check all SLOs
php artisan slo:check

# Check freshness only
php artisan slo:check --freshness

# Check without sending alerts
php artisan slo:check --no-alerts
```

### Scheduled Execution
The command runs automatically every 5 minutes via Laravel scheduler.

### Configuration
Set environment variables:
```env
SLO_ALERT_RECIPIENTS=devops@example.com,oncall@example.com
SLO_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
SLO_SLACK_CHANNEL=#alerts-zenamanage
```

## Integration

### Dashboard Freshness Tracking
To track freshness in your code:

```php
use App\Services\DashboardFreshnessTracker;

// After mutation
$tracker = app(DashboardFreshnessTracker::class);
$tracker->recordMutation('task.create', $taskId, $tenantId);

// After cache invalidation
$tracker->recordInvalidation('task.create', $taskId, $tenantId);

// After dashboard refresh (in frontend or API)
$freshness = $tracker->recordRefresh('task.create', $taskId, $tenantId);
```

### SLO Alerting
The service automatically checks SLO compliance every 5 minutes. To check manually:

```php
use App\Services\SLOAlertingService;

$sloService = app(SLOAlertingService::class);
$violations = $sloService->checkSLOCompliance();
```

## Testing

### Test SLO Compliance Check
```bash
php artisan slo:check
```

### Test Freshness Tracking
```bash
php artisan slo:check --freshness
```

### Test Alerting
1. Temporarily lower SLO targets in `config/slo.php`
2. Run `php artisan slo:check`
3. Verify alerts are sent (check logs, email, Slack)

## Monitoring

### Metrics Available
- SLO violations by category
- Freshness metrics by mutation type
- Alert history (last 100 alerts in cache)

### Dashboard Integration
Alerts are stored in cache and can be displayed in admin dashboard:
- Key: `slo_alerts` (last 100 alerts)
- Key: `freshness_metrics` (freshness statistics)

## Related Documents

- [SLO/SLA Definition](docs/SLO_SLA_DEFINITION.md)
- [Performance Budgets](performance-budgets.json)
- [Metrics Collection](docs/PR_METRICS_BUDGETS_CI.md)

## Notes

- SLO targets are defined in `performance-budgets.json` (single source of truth)
- Alert cooldown prevents alert spam
- Freshness tracking requires integration in mutation handlers
- Slack webhook URL must be configured for Slack alerts
- Email alerts require mail configuration

---

**Status**: ✅ Complete  
**Last Updated**: 2025-01-19

