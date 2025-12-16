# PR: Job Idempotency - Complete Implementation

## Summary
Implemented comprehensive job idempotency system with standardized idempotency keys, retry policies with exponential backoff, dead letter queue, and tenant-based throttling.

## Changes

### New Files
1. **`app/Queue/Middleware/JobIdempotencyMiddleware.php`**
   - Job middleware to check idempotency before execution
   - Prevents duplicate job execution
   - Format: `{tenant}_{user}_{action}_{payloadHash}`

2. **`app/Jobs/BaseIdempotentJob.php`**
   - Base class for idempotent jobs
   - Auto-generates idempotency keys
   - Integrates retry policy and throttling
   - Handles idempotency checks

3. **`app/Services/JobRetryPolicyService.php`**
   - Exponential backoff calculation
   - Retry decision logic
   - Dead letter queue management

4. **`app/Services/JobThrottlingService.php`**
   - Per-tenant job throttling
   - Per-queue job throttling
   - Throttling statistics

5. **`app/Listeners/HandleFailedJob.php`**
   - Event listener for failed jobs
   - Moves jobs to dead letter queue after max retries

6. **`database/migrations/2025_01_19_000001_create_job_idempotency_keys_table.php`**
   - Table for storing job idempotency keys
   - Tracks job status (processing, completed, failed)

7. **`database/migrations/2025_01_19_000002_create_dead_letter_queue_table.php`**
   - Table for dead letter queue
   - Stores failed jobs after max retries

8. **`tests/Unit/Queue/JobIdempotencyTest.php`**
   - Unit tests for job idempotency

### Modified Files
1. **`config/queue.php`**
   - Added throttling configuration
   - Added retry configuration

2. **`app/Providers/EventServiceProvider.php`**
   - Registered `HandleFailedJob` listener for `JobFailed` event

## Idempotency Key Format

### Standard Format
```
{tenant_id}_{user_id}_{action}_{payloadHash}
```

**Example**:
```
tenant-abc123_user-xyz789_send_email_1a2b3c4d5e6f7g8h
```

### Components
- **tenant_id**: Tenant ID (optional, can be null)
- **user_id**: User ID (optional, can be null)
- **action**: Job action name (snake_case from class name)
- **payloadHash**: SHA256 hash of job payload (first 16 chars)

## Usage

### Creating Idempotent Jobs

Extend `BaseIdempotentJob`:

```php
use App\Jobs\BaseIdempotentJob;

class SendEmailJob extends BaseIdempotentJob
{
    protected function execute(): void
    {
        // Job logic here
        // This will only execute once per idempotency key
    }
}

// Dispatch job
SendEmailJob::dispatch($tenantId, $userId, $customIdempotencyKey);
```

### Custom Idempotency Key

```php
// Provide custom idempotency key
$idempotencyKey = "custom_key_{$resourceId}_{$action}";
SendEmailJob::dispatch($tenantId, $userId, $idempotencyKey);
```

### Retry Policy

Jobs automatically use exponential backoff:

```php
// Default: 3 tries with backoff [60s, 120s, 240s]
// Configurable via config/queue.php
```

### Throttling

Throttling is automatically checked before job execution:

```php
// Per tenant: 100 jobs/minute (default)
// Per queue: 1000 jobs/minute (default)
// Configurable via config/queue.php
```

## Retry Policy

### Exponential Backoff
- **Attempt 1**: 60 seconds
- **Attempt 2**: 120 seconds (2x)
- **Attempt 3**: 240 seconds (4x)
- **Max**: 3600 seconds (1 hour)

### Configuration
```php
// config/queue.php
'retry' => [
    'max_tries' => 3,
    'initial_backoff' => 60,
    'max_backoff' => 3600,
    'multiplier' => 2.0,
],
```

## Dead Letter Queue

### Automatic Movement
Jobs that fail after max retries are automatically moved to `dead_letter_queue` table.

### Manual Retry
```php
// Get failed jobs
$failedJobs = DB::table('dead_letter_queue')
    ->where('resolved_at', null)
    ->get();

// Retry manually
foreach ($failedJobs as $job) {
    $jobClass = $job->job_class;
    $payload = json_decode($job->payload, true);
    // Dispatch job again
}
```

## Throttling

### Per-Tenant Throttling
- **Default**: 100 jobs per minute per tenant
- **Configurable**: `QUEUE_MAX_JOBS_PER_TENANT` env variable

### Per-Queue Throttling
- **Default**: 1000 jobs per minute per queue
- **Configurable**: `QUEUE_MAX_JOBS_PER_QUEUE` env variable

### Throttling Behavior
- Jobs are released back to queue with 60-second delay
- Throttling is checked before job execution
- Statistics available via `JobThrottlingService::getStats()`

## Testing

### Test Idempotency
```bash
php artisan test tests/Unit/Queue/JobIdempotencyTest.php
```

### Test Scenarios
1. Job executes once with same idempotency key
2. Job skips if already processed
3. Job marked as completed after success
4. Job marked as failed on exception (allows retry)
5. Job moved to DLQ after max retries

## Configuration

### Environment Variables
```env
# Throttling
QUEUE_MAX_JOBS_PER_TENANT=100
QUEUE_MAX_JOBS_PER_QUEUE=1000

# Retry Policy
QUEUE_MAX_TRIES=3
QUEUE_INITIAL_BACKOFF=60
QUEUE_MAX_BACKOFF=3600
QUEUE_BACKOFF_MULTIPLIER=2.0
```

## Database Tables

### job_idempotency_keys
- Stores idempotency keys and status
- Tracks job completion
- Indexed by tenant_id, action, status

### dead_letter_queue
- Stores failed jobs after max retries
- Includes exception details
- Can be manually retried

## Integration

### With Existing Jobs
To make existing jobs idempotent:

1. Extend `BaseIdempotentJob` instead of implementing `ShouldQueue` directly
2. Move job logic to `execute()` method
3. Job will automatically have idempotency, retry, and throttling

### Example Migration
```php
// Before
class SendEmailJob implements ShouldQueue
{
    public function handle() { /* ... */ }
}

// After
class SendEmailJob extends BaseIdempotentJob
{
    protected function execute() { /* ... */ }
}
```

## Monitoring

### Idempotency Statistics
```php
$stats = DB::table('job_idempotency_keys')
    ->select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();
```

### Dead Letter Queue
```php
$dlqCount = DB::table('dead_letter_queue')
    ->where('resolved_at', null)
    ->count();
```

### Throttling Statistics
```php
$throttlingService = app(JobThrottlingService::class);
$stats = $throttlingService->getStats($tenantId);
```

## Related Documents

- [Queue Implementation Guide](docs/QUEUE_IMPLEMENTATION_GUIDE.md)
- [Idempotency Middleware](app/Http/Middleware/IdempotencyMiddleware.php) (for HTTP requests)

## Notes

- Idempotency keys are stored in both cache and database for performance and persistence
- Jobs are automatically throttled per tenant and per queue
- Failed jobs are moved to dead letter queue after max retries
- Exponential backoff prevents queue overload
- Dead letter queue allows manual review and retry

---

**Status**: ✅ Complete  
**Last Updated**: 2025-01-19

