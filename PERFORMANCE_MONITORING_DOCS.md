# ZenaManage Performance Monitoring Documentation

## 📊 **Overview**

ZenaManage Performance Monitoring System provides comprehensive observability into application performance, system health, and user experience metrics.

## 🏗️ **Architecture**

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   ZenaManage    │───▶│   Prometheus    │───▶│     Grafana     │
│   Application   │    │   (Metrics)     │    │   (Dashboard)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Alertmanager   │    │   Node Exporter │    │   Custom Alerts │
│  (Notifications)│    │  (System Metrics)│    │   (Business)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🔧 **Components**

### **1. Metrics Collection**
- **Service**: `MetricsCollector`
- **Endpoints**: 
  - `/api/metrics` - JSON format
  - `/api/metrics/prometheus` - Prometheus format
  - `/api/metrics/health` - Health checks
- **Command**: `php artisan metrics:collect`

### **2. Prometheus**
- **Port**: 9090
- **Config**: `monitoring/prometheus.yml`
- **Rules**: `monitoring/rules/zenamanage.yml`
- **Scrape Interval**: 30s

### **3. Grafana**
- **Port**: 3000
- **Credentials**: admin/admin123
- **Dashboards**: `monitoring/grafana/dashboards/`
- **Datasources**: Prometheus

### **4. Alertmanager**
- **Port**: 9093
- **Config**: `monitoring/alertmanager.yml`
- **Notifications**: Email, Slack, Webhook

## 📈 **Key Metrics**

### **Application Metrics**
- `zenamanage_active_users` - Number of active users
- `zenamanage_requests_per_minute` - Request rate
- `zenamanage_error_rate` - Error percentage
- `zenamanage_response_time_avg` - Average response time
- `zenamanage_response_time_p95` - 95th percentile response time

### **System Metrics**
- `zenamanage_cpu_usage` - CPU usage percentage
- `zenamanage_memory_usage` - Memory usage percentage
- `zenamanage_db_connections` - Database connections
- `zenamanage_slow_queries` - Slow query count

### **Business Metrics**
- `zenamanage_projects_count` - Total projects
- `zenamanage_tasks_count` - Total tasks
- `zenamanage_documents_count` - Total documents
- `zenamanage_team_members` - Team size

## 🚨 **Alert Rules**

### **Critical Alerts (P0)**
- Service Down: `up{job="zenamanage-app"} == 0`
- Database Unavailable: `zenamanage_db_connections == 0`
- High Error Rate: `zenamanage_error_rate > 10`

### **Warning Alerts (P1)**
- High Response Time: `zenamanage_response_time_avg > 500`
- High CPU Usage: `zenamanage_cpu_usage > 80`
- High Memory Usage: `zenamanage_memory_usage > 85`
- Slow Queries: `increase(zenamanage_slow_queries[5m]) > 10`

### **Info Alerts (P2)**
- High Request Rate: `zenamanage_requests_per_minute > 1000`
- Low Cache Hit Rate: `zenamanage_cache_hit_rate < 80`

## 📊 **Dashboard Panels**

### **Overview Dashboard**
1. **Application Overview** - Key metrics at a glance
2. **Response Time** - Average and P95 response times
3. **System Resources** - CPU and Memory usage
4. **Database Metrics** - Connections and slow queries
5. **Error Rate Trend** - Error rate over time

### **Business Dashboard**
1. **User Activity** - Active users and sessions
2. **Project Metrics** - Projects, tasks, documents
3. **Performance KPIs** - Response time, throughput
4. **System Health** - Overall system status

## 🛠️ **Setup Instructions**

### **1. Start Monitoring Stack**
```bash
# Start all monitoring services
docker-compose -f docker-compose.monitoring.yml up -d

# Verify services
docker-compose -f docker-compose.monitoring.yml ps
```

### **2. Configure Application**
```bash
# Enable metrics collection
php artisan metrics:collect --format=json

# Test endpoints
curl http://localhost:8000/api/metrics
curl http://localhost:8000/api/metrics/prometheus
curl http://localhost:8000/api/metrics/health
```

### **3. Setup Cron Jobs**
```bash
# Add to crontab
crontab -e

# Metrics collection every 5 minutes
*/5 * * * * cd /path/to/zenamanage && php artisan metrics:collect --format=json --log

# Performance monitoring every minute
* * * * * cd /path/to/zenamanage && ./monitor-performance.sh monitor
```

### **4. Configure Alerts**
```bash
# Update alertmanager.yml with your notification channels
# Email: admin@zenamanage.com
# Slack: YOUR_SLACK_WEBHOOK_URL
```

## 📱 **Access URLs**

- **Grafana Dashboard**: http://localhost:3000
- **Prometheus**: http://localhost:9090
- **Alertmanager**: http://localhost:9093
- **Metrics API**: http://localhost:8000/api/metrics
- **Health Check**: http://localhost:8000/api/metrics/health

## 🔍 **Troubleshooting**

### **Common Issues**

#### **Metrics Not Appearing**
```bash
# Check Prometheus targets
curl http://localhost:9090/api/v1/targets

# Check application metrics
curl http://localhost:8000/api/metrics/prometheus

# Restart Prometheus
docker-compose -f docker-compose.monitoring.yml restart prometheus
```

#### **Grafana Not Loading**
```bash
# Check Grafana logs
docker-compose -f docker-compose.monitoring.yml logs grafana

# Restart Grafana
docker-compose -f docker-compose.monitoring.yml restart grafana
```

#### **Alerts Not Firing**
```bash
# Check Alertmanager config
curl http://localhost:9093/api/v1/status

# Check Prometheus rules
curl http://localhost:9090/api/v1/rules

# Test alert rule
curl http://localhost:9090/api/v1/query?query=zenamanage_error_rate
```

### **Performance Issues**

#### **High Memory Usage**
```bash
# Check Prometheus memory
docker stats zenamanage-prometheus

# Reduce retention period
# Edit monitoring/prometheus.yml
# --storage.tsdb.retention.time=24h
```

#### **Slow Queries**
```bash
# Check slow query log
tail -f /var/log/mysql/slow.log

# Optimize queries
php artisan optimize
```

## 📋 **Maintenance Tasks**

### **Daily**
- [ ] Check dashboard for anomalies
- [ ] Review alert notifications
- [ ] Monitor system resources
- [ ] Check log files

### **Weekly**
- [ ] Review performance trends
- [ ] Update alert thresholds
- [ ] Clean up old metrics
- [ ] Test alert notifications

### **Monthly**
- [ ] Capacity planning review
- [ ] Dashboard optimization
- [ ] Alert rule refinement
- [ ] Documentation updates

## 🔧 **Customization**

### **Adding New Metrics**
```php
// In MetricsCollector.php
private function getCustomMetric(): float
{
    // Your custom metric logic
    return $value;
}

// Add to collectApplicationMetrics()
'custom_metric' => $this->getCustomMetric(),

// Export to Prometheus
$output[] = "# HELP zenamanage_custom_metric Custom metric description";
$output[] = "# TYPE zenamanage_custom_metric gauge";
$output[] = "zenamanage_custom_metric " . $metrics['application']['custom_metric'];
```

### **Creating Custom Dashboards**
1. Go to Grafana → Dashboards → New Dashboard
2. Add panels with Prometheus queries
3. Export dashboard JSON
4. Save to `monitoring/grafana/dashboards/`

### **Adding Alert Rules**
```yaml
# In monitoring/rules/zenamanage.yml
- alert: CustomAlert
  expr: zenamanage_custom_metric > threshold
  for: 2m
  labels:
    severity: warning
  annotations:
    summary: "Custom alert triggered"
    description: "Custom metric is {{ $value }}"
```

## 📚 **Best Practices**

### **Metrics Collection**
- Collect metrics at appropriate intervals
- Use meaningful metric names
- Include relevant labels
- Avoid high-cardinality metrics

### **Alerting**
- Set appropriate thresholds
- Use proper severity levels
- Include actionable descriptions
- Test alert notifications

### **Dashboard Design**
- Keep dashboards focused
- Use appropriate visualizations
- Include time ranges
- Make dashboards actionable

### **Performance**
- Monitor monitoring system itself
- Use efficient queries
- Set appropriate retention
- Scale horizontally when needed

## 🔗 **Related Documentation**

- [Incident Response Runbook](INCIDENT_RESPONSE_RUNBOOK.md)
- [Production Deployment Checklist](PRODUCTION_DEPLOYMENT_CHECKLIST.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Security Guidelines](SECURITY_GUIDELINES.md)

---

**Last Updated**: 2025-10-15
**Version**: 1.0
**Maintainer**: DevOps Team
