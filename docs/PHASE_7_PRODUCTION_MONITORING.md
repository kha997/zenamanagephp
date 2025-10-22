# Phase 7 Production Monitoring & Alerting Setup

**Date**: January 15, 2025  
**Status**: Production Monitoring Ready  
**Phase**: Phase 7 - UAT/Production Prep

---

## 📊 **Production Monitoring Architecture**

### **Monitoring Stack**
- **Application Monitoring**: Laravel Telescope, Custom Metrics
- **Infrastructure Monitoring**: Prometheus, Grafana
- **Log Management**: ELK Stack (Elasticsearch, Logstash, Kibana)
- **Uptime Monitoring**: Pingdom, UptimeRobot
- **Error Tracking**: Sentry, Bugsnag
- **Performance Monitoring**: New Relic, DataDog

### **Monitoring Components**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Application   │    │  Infrastructure │    │   Logging       │
│   Monitoring    │    │   Monitoring    │    │   System        │
│                 │    │                 │    │                 │
│ • Laravel       │    │ • Prometheus    │    │ • Elasticsearch │
│   Telescope     │    │ • Grafana       │    │ • Logstash      │
│ • Custom        │    │ • Node          │    │ • Kibana        │
│   Metrics       │    │   Exporter      │    │ • Filebeat      │
│ • Health        │    │ • Alertmanager  │    │ • Fluentd       │
│   Checks        │    │ • Blackbox      │    │ • Logrotate     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## 🔧 **Laravel Telescope Configuration**

### **Telescope Service Provider**
```php
<?php

namespace App\Providers;

use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;
use Illuminate\Support\Facades\Gate;

class TelescopeServiceProvider extends TelescopeServiceProvider
{
    public function register()
    {
        Telescope::night();
        
        $this->hideSensitiveRequestDetails();
        
        Telescope::filter(function ($entry) {
            if ($this->app->environment('local')) {
                return true;
            }
            
            return $entry->isReportableException() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }
    
    protected function hideSensitiveRequestDetails()
    {
        if ($this->app->environment('local')) {
            return;
        }
        
        Telescope::hideRequestParameters(['_token']);
        Telescope::hideRequestHeaders(['authorization']);
    }
    
    protected function gate()
    {
        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                'admin@zenamanage.com',
                'devops@zenamanage.com'
            ]);
        });
    }
}
```

### **Custom Metrics Collection**
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsService
{
    public function collectApplicationMetrics()
    {
        return [
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'response_time_p95' => $this->getResponseTimeP95(),
            'error_rate' => $this->getErrorRate(),
            'active_users' => $this->getActiveUsers(),
            'database_connections' => $this->getDatabaseConnections(),
            'queue_jobs_pending' => $this->getQueueJobsPending(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'memory_usage' => $this->getMemoryUsage(),
        ];
    }
    
    private function getRequestsPerMinute()
    {
        return Cache::remember('requests_per_minute', 60, function () {
            return DB::table('request_logs')
                ->where('created_at', '>=', now()->subMinute())
                ->count();
        });
    }
    
    private function getResponseTimeP95()
    {
        return Cache::remember('response_time_p95', 60, function () {
            return DB::table('request_logs')
                ->where('created_at', '>=', now()->subMinute())
                ->orderBy('response_time', 'desc')
                ->limit(5)
                ->avg('response_time');
        });
    }
    
    private function getErrorRate()
    {
        $total = DB::table('request_logs')
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        $errors = DB::table('request_logs')
            ->where('created_at', '>=', now()->subMinute())
            ->where('status_code', '>=', 400)
            ->count();
            
        return $total > 0 ? ($errors / $total) * 100 : 0;
    }
    
    private function getActiveUsers()
    {
        return DB::table('user_sessions')
            ->where('last_activity', '>=', now()->subMinutes(30))
            ->count();
    }
    
    private function getDatabaseConnections()
    {
        return DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
    }
    
    private function getQueueJobsPending()
    {
        return DB::table('jobs')->count();
    }
    
    private function getCacheHitRate()
    {
        $hits = Cache::get('cache_hits', 0);
        $misses = Cache::get('cache_misses', 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
    
    private function getMemoryUsage()
    {
        return memory_get_usage(true);
    }
}
```

---

## 📈 **Prometheus Configuration**

### **prometheus.yml**
```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - "alert_rules.yml"

alerting:
  alertmanagers:
    - static_configs:
        - targets:
          - alertmanager:9093

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'node-exporter'
    static_configs:
      - targets: ['node-exporter:9100']

  - job_name: 'laravel-app'
    static_configs:
      - targets: ['laravel-app:8000']
    metrics_path: '/metrics'
    scrape_interval: 30s

  - job_name: 'mysql-exporter'
    static_configs:
      - targets: ['mysql-exporter:9104']

  - job_name: 'redis-exporter'
    static_configs:
      - targets: ['redis-exporter:9121']

  - job_name: 'nginx-exporter'
    static_configs:
      - targets: ['nginx-exporter:9113']
```

### **Alert Rules**
```yaml
groups:
- name: laravel_app
  rules:
  - alert: HighErrorRate
    expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.1
    for: 5m
    labels:
      severity: critical
    annotations:
      summary: "High error rate detected"
      description: "Error rate is {{ $value }} errors per second"

  - alert: HighResponseTime
    expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 2
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "High response time detected"
      description: "95th percentile response time is {{ $value }} seconds"

  - alert: HighMemoryUsage
    expr: (node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes > 0.9
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "High memory usage detected"
      description: "Memory usage is {{ $value | humanizePercentage }}"

  - alert: HighCPUUsage
    expr: 100 - (avg by(instance) (rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "High CPU usage detected"
      description: "CPU usage is {{ $value }}%"

  - alert: DatabaseConnectionsHigh
    expr: mysql_global_status_threads_connected > 80
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "High database connections"
      description: "Database connections: {{ $value }}"

  - alert: QueueJobsBacklog
    expr: laravel_queue_jobs_pending > 1000
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "Queue jobs backlog"
      description: "Queue jobs pending: {{ $value }}"

  - alert: CacheHitRateLow
    expr: laravel_cache_hit_rate < 80
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "Low cache hit rate"
      description: "Cache hit rate: {{ $value }}%"
```

---

## 📊 **Grafana Dashboard Configuration**

### **Dashboard JSON**
```json
{
  "dashboard": {
    "id": null,
    "title": "ZenaManage Production Dashboard",
    "tags": ["zenamanage", "production"],
    "timezone": "UTC",
    "panels": [
      {
        "id": 1,
        "title": "Request Rate",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(http_requests_total[5m])",
            "legendFormat": "{{method}} {{endpoint}}"
          }
        ],
        "yAxes": [
          {
            "label": "Requests/sec",
            "min": 0
          }
        ]
      },
      {
        "id": 2,
        "title": "Response Time",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "95th percentile"
          },
          {
            "expr": "histogram_quantile(0.50, rate(http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "50th percentile"
          }
        ],
        "yAxes": [
          {
            "label": "Seconds",
            "min": 0
          }
        ]
      },
      {
        "id": 3,
        "title": "Error Rate",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(http_requests_total{status=~\"4..|5..\"}[5m])",
            "legendFormat": "{{status}}"
          }
        ],
        "yAxes": [
          {
            "label": "Errors/sec",
            "min": 0
          }
        ]
      },
      {
        "id": 4,
        "title": "Active Users",
        "type": "singlestat",
        "targets": [
          {
            "expr": "laravel_active_users",
            "legendFormat": "Active Users"
          }
        ],
        "valueName": "current"
      },
      {
        "id": 5,
        "title": "Queue Jobs",
        "type": "graph",
        "targets": [
          {
            "expr": "laravel_queue_jobs_pending",
            "legendFormat": "Pending"
          },
          {
            "expr": "laravel_queue_jobs_processing",
            "legendFormat": "Processing"
          },
          {
            "expr": "laravel_queue_jobs_failed",
            "legendFormat": "Failed"
          }
        ]
      },
      {
        "id": 6,
        "title": "Database Connections",
        "type": "singlestat",
        "targets": [
          {
            "expr": "mysql_global_status_threads_connected",
            "legendFormat": "Connections"
          }
        ],
        "valueName": "current"
      },
      {
        "id": 7,
        "title": "Cache Hit Rate",
        "type": "singlestat",
        "targets": [
          {
            "expr": "laravel_cache_hit_rate",
            "legendFormat": "Hit Rate %"
          }
        ],
        "valueName": "current"
      },
      {
        "id": 8,
        "title": "Memory Usage",
        "type": "graph",
        "targets": [
          {
            "expr": "node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes",
            "legendFormat": "Used Memory"
          }
        ],
        "yAxes": [
          {
            "label": "Bytes",
            "min": 0
          }
        ]
      }
    ],
    "time": {
      "from": "now-1h",
      "to": "now"
    },
    "refresh": "30s"
  }
}
```

---

## 🚨 **Alerting Configuration**

### **Alertmanager Configuration**
```yaml
global:
  smtp_smarthost: 'smtp.gmail.com:587'
  smtp_from: 'alerts@zenamanage.com'
  smtp_auth_username: 'alerts@zenamanage.com'
  smtp_auth_password: 'your-app-password'

route:
  group_by: ['alertname']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 1h
  receiver: 'web.hook'
  routes:
  - match:
      severity: critical
    receiver: 'critical-alerts'
  - match:
      severity: warning
    receiver: 'warning-alerts'

receivers:
- name: 'web.hook'
  webhook_configs:
  - url: 'http://webhook:5001/'

- name: 'critical-alerts'
  email_configs:
  - to: 'devops@zenamanage.com,admin@zenamanage.com'
    subject: 'CRITICAL: {{ .GroupLabels.alertname }}'
    body: |
      {{ range .Alerts }}
      Alert: {{ .Annotations.summary }}
      Description: {{ .Annotations.description }}
      {{ end }}
  slack_configs:
  - api_url: 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
    channel: '#alerts-critical'
    title: 'Critical Alert'
    text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'

- name: 'warning-alerts'
  email_configs:
  - to: 'devops@zenamanage.com'
    subject: 'WARNING: {{ .GroupLabels.alertname }}'
    body: |
      {{ range .Alerts }}
      Alert: {{ .Annotations.summary }}
      Description: {{ .Annotations.description }}
      {{ end }}
  slack_configs:
  - api_url: 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
    channel: '#alerts-warning'
    title: 'Warning Alert'
    text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'
```

---

## 📋 **Monitoring Checklist**

### **Pre-Production Setup**
- [ ] Prometheus configured and running
- [ ] Grafana configured with dashboards
- [ ] Alertmanager configured with alerts
- [ ] Laravel Telescope configured
- [ ] Custom metrics collection implemented
- [ ] Log aggregation configured
- [ ] Uptime monitoring configured
- [ ] Error tracking configured

### **Production Monitoring**
- [ ] Application metrics collecting
- [ ] Infrastructure metrics collecting
- [ ] Logs aggregating
- [ ] Alerts firing correctly
- [ ] Dashboards updating
- [ ] Performance benchmarks met
- [ ] Error rates within limits
- [ ] Response times within limits

### **Alert Testing**
- [ ] Critical alerts tested
- [ ] Warning alerts tested
- [ ] Email notifications working
- [ ] Slack notifications working
- [ ] Webhook notifications working
- [ ] Alert escalation working
- [ ] Alert suppression working
- [ ] Alert recovery working

---

## 🔧 **Monitoring Commands**

### **Start Monitoring Stack**
```bash
# Start Prometheus
docker-compose up -d prometheus

# Start Grafana
docker-compose up -d grafana

# Start Alertmanager
docker-compose up -d alertmanager

# Start ELK Stack
docker-compose up -d elasticsearch logstash kibana

# Check status
docker-compose ps
```

### **Test Monitoring**
```bash
# Test Prometheus
curl http://localhost:9090/api/v1/query?query=up

# Test Grafana
curl http://localhost:3000/api/health

# Test Alertmanager
curl http://localhost:9093/api/v1/alerts

# Test Laravel metrics
curl http://localhost:8000/metrics
```

---

**Last Updated**: 2025-01-15  
**Next Review**: After production deployment  
**Status**: Ready for production monitoring
