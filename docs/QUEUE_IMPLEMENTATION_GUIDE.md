# Queue & Background Jobs Implementation Guide
**ZenaManage Project Management System**

**Version:** 1.0  
**Last Updated:** October 25, 2025  
**Status:** ✅ **IMPLEMENTED**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Queue Configuration](#queue-configuration)
3. [Background Jobs](#background-jobs)
4. [Queue Management](#queue-management)
5. [Monitoring & Metrics](#monitoring--metrics)
6. [Retry Mechanism](#retry-mechanism)
7. [Testing](#testing)
8. [Production Deployment](#production-deployment)

---

## 1. Overview

This guide documents the queue and background job implementation in ZenaManage, covering queue monitoring, retry mechanisms, background job processing, and performance monitoring.

### **Queue Features**
- ✅ **Queue Monitoring**: Real-time queue statistics and metrics
- ✅ **Retry Mechanism**: Automatic retry with exponential backoff
- ✅ **Background Jobs**: Document processing and email sending
- ✅ **Performance Monitoring**: Queue performance metrics
- ✅ **Dead Letter Queue**: Failed job handling

---

## 2. Queue Configuration

### **Queue Connections**

File: `config/queue.php`

```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],

    'emails-high' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'emails-high',
        'retry_after' => 60,
        'after_commit' => false,
    ],

    'emails-medium' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'emails-medium',
        'retry_after' => 90,
        'after_commit' => false,
    ],

    'emails-low' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'emails-low',
        'retry_after' => 120,
        'after_commit' => false,
    ],

    'documents' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'documents',
        'retry_after' => 300,
        'after_commit' => false,
    ],
],
```

### **Environment Configuration**

```env
# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# Redis Queue (Optional)
REDIS_QUEUE=default
```

---

## 3. Background Jobs

### **ProcessDocumentJob**

File: `app/Jobs/ProcessDocumentJob.php`

**Features:**
- ✅ **Timeout**: 5 minutes
- ✅ **Retry Attempts**: 3 attempts
- ✅ **Exponential Backoff**: 1min, 5min, 15min
- ✅ **Error Handling**: Failed job logging
- ✅ **Status Updates**: Document status tracking

```php
class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function handle(): void
    {
        // Process document logic
        $this->processDocument();
    }

    public function failed(\Throwable $exception): void
    {
        // Handle permanent failure
        Log::error('Document processing failed', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### **SendEmailJob**

File: `app/Jobs/SendEmailJob.php`

**Features:**
- ✅ **Timeout**: 2 minutes
- ✅ **Retry Attempts**: 3 attempts
- ✅ **Exponential Backoff**: 30sec, 2min, 5min
- ✅ **Failed Email Storage**: Store failed emails for manual retry
- ✅ **Template Support**: Dynamic email templates

```php
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;
    public $backoff = [30, 120, 300]; // 30sec, 2min, 5min

    public function handle(): void
    {
        // Send email logic
        Mail::send($this->template, $this->data, function ($message) {
            $message->to($this->to)->subject($this->subject);
        });
    }

    public function failed(\Throwable $exception): void
    {
        // Store failed email for manual retry
        DB::table('failed_emails')->insert([
            'to' => $this->to,
            'subject' => $this->subject,
            'template' => $this->template,
            'data' => json_encode($this->data),
            'error_message' => $exception->getMessage(),
            'created_at' => now()
        ]);
    }
}
```

---

## 4. Queue Management

### **QueueManagementService**

File: `app/Services/QueueManagementService.php`

**Features:**
- ✅ **Queue Statistics**: Real-time queue stats
- ✅ **Queue Metrics**: Prometheus-compatible metrics
- ✅ **Worker Management**: Active worker tracking
- ✅ **Health Monitoring**: Queue health status
- ✅ **Retry Operations**: Retry failed jobs
- ✅ **Clear Operations**: Clear failed jobs

#### **Key Methods:**

```php
// Get queue statistics
$stats = $queueService->getQueueStats();

// Get queue metrics for monitoring
$metrics = $queueService->getQueueMetrics();

// Retry failed jobs
$result = $queueService->retryAllFailedJobs('default');

// Clear failed jobs
$result = $queueService->clearFailedJobs('default');

// Get active workers
$workers = $queueService->getActiveWorkers();

// Get health status
$health = $queueService->getHealthStatus();
```

### **QueueController**

File: `app/Http/Controllers/QueueController.php`

**API Endpoints:**
- `GET /api/admin/queue/stats` - Queue statistics
- `GET /api/admin/queue/metrics` - Queue metrics
- `GET /api/admin/queue/workers` - Active workers
- `POST /api/admin/queue/retry` - Retry failed jobs
- `POST /api/admin/queue/clear` - Clear failed jobs

---

## 5. Monitoring & Metrics

### **Queue Metrics**

The system provides Prometheus-compatible metrics:

```php
$metrics = [
    'queue_jobs_total' => 150,
    'queue_jobs_failed_total' => 5,
    'queue_jobs_processing' => 10,
    'queue_workers_active' => 3,
    'queue_health_status' => 'healthy',
    'queue_emails-high_pending' => 25,
    'queue_emails-high_failed' => 2,
    'queue_documents_pending' => 8,
    'queue_documents_failed' => 1,
];
```

### **Health Monitoring**

Queue health status includes:

- **Status Levels**: `healthy`, `warning`, `critical`
- **Issues Detection**: High pending jobs, failed jobs, no workers
- **Recommendations**: Actionable suggestions for optimization

```php
$health = [
    'status' => 'healthy',
    'issues' => [],
    'recommendations' => []
];
```

### **Performance Thresholds**

- **High Pending Jobs**: > 1000 jobs
- **High Failed Jobs**: > 100 failed jobs
- **No Active Workers**: Critical status
- **Queue Processing Time**: p95 < 500ms

---

## 6. Retry Mechanism

### **Exponential Backoff**

Jobs implement exponential backoff for retry attempts:

```php
// ProcessDocumentJob
public $backoff = [60, 300, 900]; // 1min, 5min, 15min

// SendEmailJob  
public $backoff = [30, 120, 300]; // 30sec, 2min, 5min
```

### **Retry Limits**

- **Max Attempts**: 3 attempts per job
- **Timeout**: Job-specific timeouts
- **Dead Letter Queue**: Failed jobs stored for manual review

### **Retry Operations**

```php
// Retry specific job
$result = $queueService->retryJob('job-uuid-123');

// Retry all failed jobs in queue
$result = $queueService->retryAllFailedJobs('default');

// Clear failed jobs
$result = $queueService->clearFailedJobs('default');
```

---

## 7. Testing

### **Unit Tests**

File: `tests/Unit/Jobs/QueueJobTest.php`

**Test Coverage:**
- ✅ Job dispatching
- ✅ Queue statistics
- ✅ Queue metrics
- ✅ Health status
- ✅ Retry mechanism
- ✅ Exponential backoff
- ✅ Failed job handling

### **Test Commands**

```bash
# Run queue tests
php artisan test --testsuite=Unit --filter=Queue

# Run job tests
php artisan test --testsuite=Unit --filter=Job

# Run specific test
php artisan test tests/Unit/Jobs/QueueJobTest.php
```

### **Test Examples**

```php
public function test_process_document_job_can_be_dispatched(): void
{
    Queue::fake();

    ProcessDocumentJob::dispatch('doc-123', 'user-123', 'tenant-123');

    Queue::assertPushed(ProcessDocumentJob::class);
}

public function test_queue_statistics(): void
{
    $stats = $this->queueService->getQueueStats();

    $this->assertIsArray($stats);
    $this->assertArrayHasKey('connection', $stats);
    $this->assertArrayHasKey('total_jobs', $stats);
}
```

---

## 8. Production Deployment

### **Queue Workers**

#### **Development**
```bash
# Start queue worker
php artisan queue:work --queue=default,emails-high,emails-medium,emails-low,documents --tries=3 --timeout=60
```

#### **Production**
```bash
# Using Supervisor
php artisan queue:work --queue=default,emails-high,emails-medium,emails-low,documents --tries=3 --timeout=60 --daemon

# Using Laravel Horizon (Recommended)
php artisan horizon
```

### **Database Migrations**

Ensure required tables exist:

```bash
# Create jobs table
php artisan queue:table

# Create failed jobs table  
php artisan queue:failed-table

# Create job batches table
php artisan queue:batches-table

# Run migrations
php artisan migrate
```

### **Monitoring Setup**

#### **Queue Monitoring Dashboard**
- Access: `/api/admin/queue/stats`
- Real-time statistics
- Worker status
- Health monitoring

#### **Prometheus Metrics**
- Endpoint: `/api/admin/queue/metrics`
- Format: Prometheus-compatible
- Integration: Grafana dashboards

### **Environment Variables**

```env
# Production Queue Configuration
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# Redis Queue (Optional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default

# Queue Worker Configuration
QUEUE_WORKER_TIMEOUT=60
QUEUE_WORKER_TRIES=3
QUEUE_WORKER_SLEEP=3
QUEUE_WORKER_MAX_JOBS=1000
QUEUE_WORKER_MAX_TIME=3600
```

### **Supervisor Configuration**

File: `/etc/supervisor/conf.d/zenamanage-worker.conf`

```ini
[program:zenamanage-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/zenamanage/artisan queue:work --queue=default,emails-high,emails-medium,emails-low,documents --tries=3 --timeout=60 --sleep=3 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/zenamanage/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 📊 Summary

### **Implementation Status**

| Feature | Status | Tests | Documentation |
|---------|--------|-------|---------------|
| Queue Monitoring | ✅ Complete | ✅ Complete | ✅ Complete |
| Retry Mechanism | ✅ Complete | ✅ Complete | ✅ Complete |
| Background Jobs | ✅ Complete | ✅ Complete | ✅ Complete |
| Performance Monitoring | ✅ Complete | ✅ Complete | ✅ Complete |
| Dead Letter Queue | ✅ Complete | ✅ Complete | ✅ Complete |

### **Queue Metrics**
- ✅ **Real-time Statistics**: Queue depth, processing time, worker status
- ✅ **Health Monitoring**: Automatic issue detection and recommendations
- ✅ **Retry Management**: Exponential backoff with configurable limits
- ✅ **Performance Tracking**: p95 < 500ms processing time
- ✅ **Failed Job Handling**: Dead letter queue with manual retry

### **Background Jobs**
- ✅ **Document Processing**: 5-minute timeout with 3 retry attempts
- ✅ **Email Sending**: 2-minute timeout with failed email storage
- ✅ **Exponential Backoff**: Configurable retry intervals
- ✅ **Error Handling**: Comprehensive logging and failure tracking

---

**Document Version:** 1.0  
**Last Review:** October 25, 2025  
**Next Review:** November 25, 2025  
**Maintained by:** ZenaManage DevOps Team
