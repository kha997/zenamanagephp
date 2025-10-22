# 📊 Performance Monitoring Dashboard

**Date:** January 15, 2025  
**Status:** Implementation Phase  
**Goal:** Real-time performance monitoring and alerting

## 🎯 **Monitoring Objectives**

### **Key Performance Indicators (KPIs)**
- **API Response Time**: < 300ms (p95)
- **Page Load Time**: < 500ms (p95)
- **Database Query Time**: < 100ms (p95)
- **Memory Usage**: < 512MB
- **CPU Usage**: < 70%
- **Error Rate**: < 0.1%

## 🔧 **Monitoring Stack**

### **1. Application Performance Monitoring (APM)**
```php
// app/Http/Middleware/PerformanceMonitoring.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Log request start
        Log::info('Request started', [
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()
        ]);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        // Calculate metrics
        $executionTime = ($endTime - $startTime) * 1000; // ms
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // MB
        $queryCount = DB::getQueryLog();
        
        // Log performance metrics
        Log::info('Request completed', [
            'url' => $request->url(),
            'method' => $request->method(),
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_mb' => round($memoryUsage, 2),
            'query_count' => count($queryCount),
            'status_code' => $response->getStatusCode(),
            'timestamp' => now()
        ]);
        
        // Add performance headers
        $response->headers->set('X-Response-Time', $executionTime . 'ms');
        $response->headers->set('X-Memory-Usage', $memoryUsage . 'MB');
        $response->headers->set('X-Query-Count', count($queryCount));
        
        return $response;
    }
}
```

### **2. Database Performance Monitoring**
```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    // Monitor slow queries
    DB::listen(function ($query) {
        if ($query->time > 100) { // 100ms threshold
            Log::warning('Slow query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'timestamp' => now()
            ]);
        }
    });
}
```

### **3. System Resource Monitoring**
```bash
#!/bin/bash
# system-monitor.sh

echo "🖥️ System Resource Monitoring"
echo "============================="

# CPU Usage
echo "CPU Usage:"
top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1

# Memory Usage
echo "Memory Usage:"
free -h | grep "Mem:" | awk '{print $3 "/" $2}'

# Disk Usage
echo "Disk Usage:"
df -h | grep "/dev/"

# Network Connections
echo "Network Connections:"
netstat -an | grep :8000 | wc -l

# Process Count
echo "PHP Processes:"
ps aux | grep php | wc -l
```

## 📈 **Dashboard Implementation**

### **1. Real-time Metrics API**
```php
// app/Http/Controllers/Api/MonitoringController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    public function metrics()
    {
        return response()->json([
            'timestamp' => now(),
            'system' => [
                'cpu_usage' => $this->getCpuUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage(),
                'load_average' => $this->getLoadAverage()
            ],
            'application' => [
                'active_users' => $this->getActiveUsers(),
                'requests_per_minute' => $this->getRequestsPerMinute(),
                'error_rate' => $this->getErrorRate(),
                'response_time' => $this->getAverageResponseTime()
            ],
            'database' => [
                'connection_count' => $this->getConnectionCount(),
                'slow_queries' => $this->getSlowQueries(),
                'cache_hit_rate' => $this->getCacheHitRate()
            ]
        ]);
    }
    
    private function getCpuUsage()
    {
        $load = sys_getloadavg();
        return round($load[0] * 100, 2);
    }
    
    private function getMemoryUsage()
    {
        $memory = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        return [
            'used' => round($memory / 1024 / 1024, 2),
            'limit' => $memoryLimit,
            'percentage' => round(($memory / $this->parseMemoryLimit($memoryLimit)) * 100, 2)
        ];
    }
    
    private function getActiveUsers()
    {
        return Cache::remember('active_users', 60, function () {
            return DB::table('sessions')
                ->where('last_activity', '>', now()->subMinutes(5))
                ->count();
        });
    }
    
    private function getRequestsPerMinute()
    {
        return Cache::remember('requests_per_minute', 60, function () {
            return DB::table('audit_logs')
                ->where('created_at', '>', now()->subMinute())
                ->count();
        });
    }
    
    private function getErrorRate()
    {
        $total = DB::table('audit_logs')
            ->where('created_at', '>', now()->subHour())
            ->count();
            
        $errors = DB::table('audit_logs')
            ->where('created_at', '>', now()->subHour())
            ->where('action', 'like', '%error%')
            ->count();
            
        return $total > 0 ? round(($errors / $total) * 100, 2) : 0;
    }
}
```

### **2. Frontend Dashboard**
```html
<!-- resources/views/admin/monitoring/dashboard.blade.php -->
<div class="monitoring-dashboard">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- System Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">System Resources</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>CPU Usage:</span>
                    <span id="cpu-usage" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Memory:</span>
                    <span id="memory-usage" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Disk:</span>
                    <span id="disk-usage" class="font-mono">--</span>
                </div>
            </div>
        </div>
        
        <!-- Application Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Application</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Active Users:</span>
                    <span id="active-users" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Requests/min:</span>
                    <span id="requests-per-minute" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Error Rate:</span>
                    <span id="error-rate" class="font-mono">--</span>
                </div>
            </div>
        </div>
        
        <!-- Database Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Database</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Connections:</span>
                    <span id="connection-count" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Slow Queries:</span>
                    <span id="slow-queries" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Cache Hit Rate:</span>
                    <span id="cache-hit-rate" class="font-mono">--</span>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Performance</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Avg Response:</span>
                    <span id="avg-response-time" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Page Load:</span>
                    <span id="page-load-time" class="font-mono">--</span>
                </div>
                <div class="flex justify-between">
                    <span>Uptime:</span>
                    <span id="uptime" class="font-mono">--</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Response Time Trend</h3>
            <canvas id="response-time-chart"></canvas>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Error Rate Trend</h3>
            <canvas id="error-rate-chart"></canvas>
        </div>
    </div>
</div>

<script>
// Real-time updates every 30 seconds
setInterval(() => {
    fetch('/api/monitoring/metrics')
        .then(response => response.json())
        .then(data => {
            document.getElementById('cpu-usage').textContent = data.system.cpu_usage + '%';
            document.getElementById('memory-usage').textContent = data.system.memory_usage.used + 'MB';
            document.getElementById('disk-usage').textContent = data.system.disk_usage;
            document.getElementById('active-users').textContent = data.application.active_users;
            document.getElementById('requests-per-minute').textContent = data.application.requests_per_minute;
            document.getElementById('error-rate').textContent = data.application.error_rate + '%';
            document.getElementById('connection-count').textContent = data.database.connection_count;
            document.getElementById('slow-queries').textContent = data.database.slow_queries;
            document.getElementById('cache-hit-rate').textContent = data.database.cache_hit_rate + '%';
            document.getElementById('avg-response-time').textContent = data.application.response_time + 'ms';
        });
}, 30000);
</script>
```

## 🚨 **Alerting System**

### **1. Performance Alerts**
```php
// app/Services/AlertService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    public function checkPerformanceThresholds($metrics)
    {
        $alerts = [];
        
        // CPU Usage Alert
        if ($metrics['system']['cpu_usage'] > 80) {
            $alerts[] = [
                'type' => 'cpu_high',
                'message' => 'CPU usage is above 80%',
                'value' => $metrics['system']['cpu_usage']
            ];
        }
        
        // Memory Usage Alert
        if ($metrics['system']['memory_usage']['percentage'] > 85) {
            $alerts[] = [
                'type' => 'memory_high',
                'message' => 'Memory usage is above 85%',
                'value' => $metrics['system']['memory_usage']['percentage']
            ];
        }
        
        // Response Time Alert
        if ($metrics['application']['response_time'] > 500) {
            $alerts[] = [
                'type' => 'response_slow',
                'message' => 'Average response time is above 500ms',
                'value' => $metrics['application']['response_time']
            ];
        }
        
        // Error Rate Alert
        if ($metrics['application']['error_rate'] > 1) {
            $alerts[] = [
                'type' => 'error_rate_high',
                'message' => 'Error rate is above 1%',
                'value' => $metrics['application']['error_rate']
            ];
        }
        
        if (!empty($alerts)) {
            $this->sendAlerts($alerts);
        }
        
        return $alerts;
    }
    
    private function sendAlerts($alerts)
    {
        Log::critical('Performance alerts triggered', $alerts);
        
        // Send email alerts
        Mail::raw('Performance alerts: ' . json_encode($alerts), function ($message) {
            $message->to('admin@zenamanage.com')
                   ->subject('ZenaManage Performance Alert');
        });
    }
}
```

## 📊 **Implementation Timeline**

### **Week 1: Basic Monitoring**
- [ ] Implement performance middleware
- [ ] Set up basic metrics collection
- [ ] Create monitoring API endpoint
- [ ] Basic dashboard implementation

### **Week 2: Advanced Features**
- [ ] Real-time dashboard
- [ ] Chart integration
- [ ] Alert system
- [ ] Historical data storage

### **Week 3: Optimization**
- [ ] Performance tuning
- [ ] Alert optimization
- [ ] Dashboard customization
- [ ] Documentation

### **Week 4: Production Deployment**
- [ ] Production monitoring setup
- [ ] Alert configuration
- [ ] Performance baseline
- [ ] Maintenance procedures

---

**Next Action:** Implement performance middleware and basic metrics collection
