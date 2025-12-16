# PR: Metrics Collection + Performance Budgets Enforcement in CI

## Summary
Implemented comprehensive performance metrics collection and budget enforcement in CI to ensure performance standards are maintained.

## Changes

### New Files
1. **`performance-budgets.json`**
   - Performance budgets configuration file
   - Defines budgets for API, pages, WebSocket, cache, database, and memory
   - Includes enforcement settings for CI

2. **`scripts/check-performance-budgets.sh`**
   - Shell script to validate performance metrics against budgets
   - Integrates with CI workflows

3. **`scripts/check-performance-budgets.js`**
   - Node.js script to check performance budgets
   - Validates metrics against budgets
   - Generates detailed violation reports

4. **`scripts/collect-performance-metrics.js`**
   - Collects performance metrics from various sources
   - Integrates with Laravel metrics export
   - Outputs metrics in JSON format

5. **`.github/workflows/performance-budgets.yml`**
   - GitHub Actions workflow for performance budget enforcement
   - Runs on push/PR
   - Comments PR with performance report

6. **`app/Console/Commands/ExportPerformanceMetrics.php`**
   - Laravel command to export performance metrics
   - Collects metrics from cache, logs, and system
   - Outputs in JSON format for CI

### Modified Files
None (new implementation)

## Performance Budgets

### API Performance (p95 latency)
- `/api/v1/app/projects`: 300ms
- `/api/v1/app/tasks`: 300ms
- `/api/v1/app/tasks/{id}/move`: 200ms
- `/api/v1/app/documents`: 300ms
- `/api/v1/app/dashboard`: 500ms
- `/api/v1/admin/*`: 500ms
- `/api/v1/me`: 200ms
- `/api/v1/me/nav`: 200ms
- Default: 300ms

### Page Performance (p95 load time)
- `/app/dashboard`: 500ms
- `/app/projects`: 500ms
- `/app/tasks`: 500ms
- `/admin/dashboard`: 600ms
- Default: 500ms

### WebSocket Performance
- Subscribe: 200ms (p95)
- Message delivery: 100ms (p95)
- Connection establishment: 500ms (p95)

### Cache Performance
- Hit rate: > 80%
- Freshness: ≤ 5s after mutation
- Invalidation latency: 50ms (p95)

### Database Performance
- Query time: 100ms (p95)
- Slow queries: < 10 (threshold: 100ms)

### Memory Performance
- Peak usage: < 80%

## Usage

### Collect Metrics
```bash
# Using Laravel command
php artisan metrics:export --output=test-results/performance-metrics.json

# Using Node.js script
node scripts/collect-performance-metrics.js test-results/performance-metrics.json
```

### Check Budgets
```bash
# Using shell script
./scripts/check-performance-budgets.sh test-results/performance-metrics.json

# Using Node.js script directly
node scripts/check-performance-budgets.js performance-budgets.json test-results/performance-metrics.json test-results/performance-budget-report.json
```

### CI Integration
The performance budgets workflow runs automatically on:
- Push to main/develop/feature branches
- Pull requests to main/develop
- Manual workflow dispatch

## CI Workflow

### Steps
1. **Collect Metrics**: Gathers performance metrics from various sources
2. **Check Budgets**: Validates metrics against budgets
3. **Upload Report**: Uploads budget report as artifact
4. **Comment PR**: Comments PR with performance report (if PR)

### Failure Conditions
- Budget violations (exceeds budget)
- Warnings (80% of budget) - non-blocking

### Report Format
```json
{
  "timestamp": "2025-01-19T10:00:00Z",
  "violations": [
    {
      "category": "api",
      "metric": "/api/v1/app/projects",
      "value": 350,
      "budget": 300,
      "overage": 50,
      "overagePercent": "16.7"
    }
  ],
  "warnings": [],
  "summary": {
    "total": 10,
    "passed": 9,
    "failed": 1,
    "warned": 0
  }
}
```

## Configuration

### Budget File
Edit `performance-budgets.json` to adjust budgets:
```json
{
  "budgets": {
    "api": {
      "endpoints": {
        "/api/v1/app/projects": {
          "p95": 300
        }
      }
    }
  },
  "enforcement": {
    "ci": {
      "enabled": true,
      "fail_on_violation": true
    }
  }
}
```

### Environment Variables
- `BASE_URL` - Base URL for metrics collection
- `CI` - CI environment flag

## Metrics Collection Sources

1. **Laravel Command** (`php artisan metrics:export`)
   - Cache-stored metrics from PerformanceMiddleware
   - Query log metrics
   - System memory metrics

2. **Test Results**
   - Playwright test results
   - Lighthouse results
   - API test results

3. **Log Files**
   - Laravel performance logs
   - Application logs

## Future Improvements

1. **Real-time Monitoring**
   - Integrate with APM (e.g., New Relic, Datadog)
   - Real-time dashboard

2. **Historical Tracking**
   - Store metrics over time
   - Trend analysis

3. **Automated Alerts**
   - Slack/email notifications on violations
   - Performance regression detection

4. **Budget Recommendations**
   - ML-based budget suggestions
   - Automatic budget adjustment

## Testing

### Test Budget Validation
```bash
# Create test metrics
echo '{"api": {"/api/v1/app/projects": {"p95": 350}}}' > test-results/performance-metrics.json

# Check budgets (should fail)
./scripts/check-performance-budgets.sh test-results/performance-metrics.json
```

### Test Metrics Collection
```bash
# Collect metrics
node scripts/collect-performance-metrics.js test-results/performance-metrics.json

# Verify output
cat test-results/performance-metrics.json
```

## Notes

- Budgets are defined in `performance-budgets.json` (single source of truth)
- CI fails on budget violations (configurable)
- Warnings are non-blocking but logged
- Metrics collection supports multiple sources (Laravel, tests, logs)
- Reports are uploaded as artifacts for review

