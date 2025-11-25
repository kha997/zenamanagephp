# Project Health Monitoring Configuration

Round 84: Project Health Monitoring polish & operator controls

## Overview

The Project Health monitoring system logs portfolio generation events to provide observability into the Project Health & Reports vertical. This document explains how to configure and tune monitoring behavior.

## What Gets Logged

When a project health portfolio is generated, the system logs an event with the following structure:

**Log Key:** `project_health.portfolio_generated`

**Context:**
```json
{
    "tenant_id": 1,
    "projects": 5,
    "duration_ms": 123.45
}
```

- `tenant_id`: The tenant ID for which the portfolio was generated
- `projects`: Number of projects in the portfolio
- `duration_ms`: Time taken to generate the portfolio in milliseconds

## Configuration Variables

### `PROJECT_HEALTH_MONITORING_ENABLED`

**Type:** `boolean`  
**Default:** `true`

Controls whether monitoring is enabled. When set to `false`, no logs are generated regardless of other settings.

**Example:**
```env
PROJECT_HEALTH_MONITORING_ENABLED=false
```

### `PROJECT_HEALTH_LOG_CHANNEL`

**Type:** `string|null`  
**Default:** `null`

Specifies a custom log channel to use for project health logs. When `null`, logs are written to the default channel.

**Example:**
```env
PROJECT_HEALTH_LOG_CHANNEL=project_health
```

To use a custom channel, ensure it's configured in `config/logging.php`:
```php
'channels' => [
    'project_health' => [
        'driver' => 'daily',
        'path' => storage_path('logs/project_health.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

### `PROJECT_HEALTH_MONITORING_SAMPLE_RATE`

**Type:** `float`  
**Default:** `1.0`

Controls the sampling rate for logging:
- `1.0`: Log every portfolio generation (100% sampling)
- `0.5`: Log approximately 50% of portfolio generations
- `0.0`: Never log (even if `monitoring_enabled` is `true`)
- Any value `<= 0.0`: Treated as "no logging"

**Example:**
```env
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=0.1
```

This would log approximately 10% of portfolio generations, useful for high-traffic environments where full logging would be too noisy.

### `PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY`

**Type:** `boolean`  
**Default:** `false`

Controls whether to log portfolio generations when there are zero projects:
- `false`: Skip logging when `projectCount === 0` (default)
- `true`: Log even when there are zero projects

**Example:**
```env
PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY=true
```

This is useful for debugging or monitoring tenant activity patterns.

### `PROJECT_HEALTH_CACHE_ENABLED`

**Type:** `boolean`  
**Default:** `false`

**Round 85: Project Health Portfolio Caching**

Controls whether portfolio results are cached. When set to `true`, the portfolio is cached per tenant for the duration specified by `PROJECT_HEALTH_CACHE_TTL_SECONDS`.

**Important:** When caching is enabled, the `ProjectHealthPortfolioGenerated` event is only dispatched when the portfolio is rebuilt (cache miss), not on cache hits. This means monitoring logs reflect the actual rebuild cost, not the cheap cache retrieval.

**Example:**
```env
PROJECT_HEALTH_CACHE_ENABLED=true
```

### `PROJECT_HEALTH_CACHE_TTL_SECONDS`

**Type:** `integer`  
**Default:** `60`

**Round 85: Project Health Portfolio Caching**

Specifies the cache time-to-live in seconds. Must be a positive integer to be effective. If set to `<= 0`, caching is disabled even if `PROJECT_HEALTH_CACHE_ENABLED` is `true`.

**Example:**
```env
PROJECT_HEALTH_CACHE_TTL_SECONDS=300
```

This would cache portfolio results for 5 minutes per tenant.

## Caching Behavior

When caching is enabled:

- **Cache Key:** `project_health_portfolio:{tenant_id}` (per-tenant isolation)
- **Event Dispatch:** `ProjectHealthPortfolioGenerated` is only emitted on cache rebuilds (not on cache hits)
- **Monitoring Logs:** Reflect rebuild cost, not cache retrieval time
- **Default Behavior:** Caching is disabled by default (`cache_enabled = false`), maintaining backward compatibility

When caching is disabled (default):

- Portfolio is rebuilt on every request
- `ProjectHealthPortfolioGenerated` event is dispatched on every request
- Behavior is identical to Round 83/84

## Configuration Examples

### Example 1: Dedicated Log Channel

Log all portfolio generations to a dedicated channel:

```env
PROJECT_HEALTH_MONITORING_ENABLED=true
PROJECT_HEALTH_LOG_CHANNEL=project_health
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=1.0
PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY=false
```

### Example 2: Reduce Log Noise in Production

In high-traffic environments, reduce logging to 10% of requests and skip empty portfolios:

```env
PROJECT_HEALTH_MONITORING_ENABLED=true
PROJECT_HEALTH_LOG_CHANNEL=null
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=0.1
PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY=false
```

### Example 3: Disable Logging Completely

Turn off all project health monitoring:

```env
PROJECT_HEALTH_MONITORING_ENABLED=false
```

Or use sampling rate:

```env
PROJECT_HEALTH_MONITORING_ENABLED=true
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=0.0
```

### Example 4: Debug Empty Portfolios

Enable logging for empty portfolios to debug tenant activity:

```env
PROJECT_HEALTH_MONITORING_ENABLED=true
PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY=true
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=1.0
```

### Example 5: Enable Caching for Performance

Enable caching to reduce database load in high-traffic environments:

```env
PROJECT_HEALTH_CACHE_ENABLED=true
PROJECT_HEALTH_CACHE_TTL_SECONDS=300
PROJECT_HEALTH_MONITORING_ENABLED=true
PROJECT_HEALTH_MONITORING_SAMPLE_RATE=1.0
```

This caches portfolio results for 5 minutes per tenant. Note that monitoring logs will only reflect rebuild costs (cache misses), not cache hits.

## Behavior Summary

| `monitoring_enabled` | `sample_rate` | `log_when_empty` | `projectCount` | Result |
|---------------------|---------------|------------------|----------------|--------|
| `false` | any | any | any | No log |
| `true` | `<= 0.0` | any | any | No log |
| `true` | `> 0.0` | `false` | `0` | No log |
| `true` | `> 0.0` | `true` | `0` | Log (if sample passes) |
| `true` | `1.0` | any | `> 0` | Log |
| `true` | `0.0 < rate < 1.0` | any | `> 0` | Log probabilistically |

## Daily Snapshot Scheduling

**Round 88: Daily Project Health Snapshots (command + schedule)**

The system includes a console command that can create daily health snapshots for all projects across tenants. This enables historical tracking of project health metrics over time.

### Command: `project-health:snapshot-daily`

The `project-health:snapshot-daily` command creates or updates health snapshots for all projects in one or more tenants.

**Usage:**
```bash
# Snapshot all projects for all active tenants
php artisan project-health:snapshot-daily

# Snapshot all projects for a specific tenant
php artisan project-health:snapshot-daily --tenant=01HZ0000000000000000000000

# Dry run (show what would be done without creating snapshots)
php artisan project-health:snapshot-daily --dry-run
```

**Behavior:**
- Creates one snapshot per project per day (idempotent: running multiple times per day updates existing snapshots)
- Skips soft-deleted projects
- Processes all active tenants if `--tenant` is not specified
- Returns success (0) on completion, failure (1) on critical errors

### Configuration Variables

### `PROJECT_HEALTH_SNAPSHOT_SCHEDULE_ENABLED`

**Type:** `boolean`  
**Default:** `false`

Controls whether the daily snapshot command is scheduled automatically via Laravel's task scheduler. When set to `false` (default), snapshots must be run manually or via external cron.

**Example:**
```env
PROJECT_HEALTH_SNAPSHOT_SCHEDULE_ENABLED=true
```

### `PROJECT_HEALTH_SNAPSHOT_SCHEDULE_CRON`

**Type:** `string`  
**Default:** `"0 2 * * *"`

Specifies the cron expression for when the daily snapshot command should run. The default runs at 02:00 daily.

**Example:**
```env
PROJECT_HEALTH_SNAPSHOT_SCHEDULE_CRON="0 2 * * *"
```

To run at 3:30 AM daily:
```env
PROJECT_HEALTH_SNAPSHOT_SCHEDULE_CRON="30 3 * * *"
```

### Scheduling Setup

When `PROJECT_HEALTH_SNAPSHOT_SCHEDULE_ENABLED` is `true`, the command is automatically scheduled in `app/Console/Kernel.php`. Ensure Laravel's scheduler is running:

```bash
# Add to crontab (runs every minute, Laravel handles scheduling)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

**Important Notes:**
- By default, scheduling is **disabled** (`snapshot_schedule_enabled = false`)
- Snapshots are idempotent per day: running the command multiple times on the same day updates existing snapshots rather than creating duplicates
- The unique constraint on `(tenant_id, project_id, snapshot_date)` prevents duplicate snapshots
- Soft-deleted projects are automatically excluded from snapshots

## Portfolio History API

**Round 91: Project Health Portfolio History API (backend-only)**

The system provides an API endpoint to retrieve aggregated daily portfolio health history for a tenant. This enables the frontend to plot portfolio trend charts showing how project health statuses change over time.

### Endpoint: `GET /api/v1/app/reports/projects/health/history`

Returns per-day aggregated counts of projects by health status (good, warning, critical) for the current tenant.

**Authentication & Authorization:**
- Requires `auth:sanctum` (token authentication)
- Requires `ability:tenant` (tenant-scoped access)
- Requires `tenant.permission:tenant.view_reports` permission

**Query Parameters:**
- `days` (optional, integer, default: 30, max: 90)
  - Number of calendar days back from today (inclusive) to consider
  - Automatically clamped to range 1-90

**Response Format:**
```json
{
  "ok": true,
  "data": [
    {
      "snapshot_date": "2025-11-20",
      "good": 5,
      "warning": 2,
      "critical": 1,
      "total": 8
    },
    {
      "snapshot_date": "2025-11-21",
      "good": 4,
      "warning": 3,
      "critical": 2,
      "total": 9
    }
  ]
}
```

**Response Details:**
- Entries are ordered by `snapshot_date` ascending
- Only days that have at least one snapshot for the tenant appear in the array
- If there are no snapshots in the selected range, returns empty array `[]` with HTTP 200
- `total` equals the sum of `good + warning + critical` (does not include `no_data` status)

**Example Request:**
```bash
GET /api/v1/app/reports/projects/health/history?days=30
Authorization: Bearer {token}
```

**Implementation Notes:**
- Aggregates data from `project_health_snapshots` table
- Filters by `tenant_id` for multi-tenant isolation
- Groups by `snapshot_date` and `overall_status`
- Only includes snapshots within the specified date range
- Excludes soft-deleted snapshots (`deleted_at IS NULL`)

## Related Documentation

- `tests/e2e/README_PROJECT_HEALTH.md` - E2E test documentation for Project Health features
- `config/reports.php` - Configuration file for reports settings

