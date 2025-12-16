# SLO/SLA Definition - ZenaManage

**Version**: 1.0.0  
**Last Updated**: 2025-01-19  
**Status**: Active

## Overview

This document defines Service Level Objectives (SLOs) and Service Level Agreements (SLAs) for ZenaManage. SLOs are internal targets we aim to meet, while SLAs are commitments to users.

## SLO Targets

### API Performance

#### Response Time (p95 latency)
- **Target**: p95 < 300ms for standard endpoints
- **Critical Endpoints**: p95 < 200ms
- **Admin Endpoints**: p95 < 500ms

**Endpoints**:
- `/api/v1/app/projects`: p95 < 300ms
- `/api/v1/app/tasks`: p95 < 300ms
- `/api/v1/app/tasks/{id}/move`: p95 < 200ms (critical)
- `/api/v1/app/documents`: p95 < 300ms
- `/api/v1/app/dashboard`: p95 < 500ms
- `/api/v1/admin/*`: p95 < 500ms
- `/api/v1/me`: p95 < 200ms
- `/api/v1/me/nav`: p95 < 200ms

**Measurement**: 
- Collected via `PerformanceMiddleware`
- Aggregated over 5-minute windows
- p95 calculated from last 1000 requests per endpoint

**Alerting**:
- Warning: p95 > 80% of target (240ms for 300ms target)
- Critical: p95 > 100% of target (300ms for 300ms target)

---

### Page Load Performance

#### Load Time (p95)
- **Target**: p95 < 500ms for app pages
- **Admin Pages**: p95 < 600ms

**Pages**:
- `/app/dashboard`: p95 < 500ms
- `/app/projects`: p95 < 500ms
- `/app/tasks`: p95 < 500ms
- `/admin/dashboard`: p95 < 600ms

**Measurement**:
- Collected via Playwright E2E tests
- Lighthouse metrics (FCP, LCP, TTFB)
- Real User Monitoring (RUM) if available

**Alerting**:
- Warning: p95 > 80% of target (400ms for 500ms target)
- Critical: p95 > 100% of target (500ms for 500ms target)

---

### WebSocket Performance

#### Connection Establishment
- **Target**: p95 < 500ms
- **Measurement**: Time from connection request to authenticated

#### Subscribe Latency
- **Target**: p95 < 200ms
- **Measurement**: Time from subscribe message to confirmation

#### Message Delivery
- **Target**: p95 < 100ms
- **Measurement**: Time from server send to client receive

**Alerting**:
- Warning: p95 > 80% of target
- Critical: p95 > 100% of target

---

### Cache Performance

#### Hit Rate
- **Target**: > 80%
- **Measurement**: Cache hits / (hits + misses) over 1-hour window

#### Freshness
- **Target**: Dashboard updates ≤ 5s after mutation
- **Measurement**: Time from mutation to cache invalidation + refetch

#### Invalidation Latency
- **Target**: p95 < 50ms
- **Measurement**: Time to invalidate cache keys after mutation

**Alerting**:
- Warning: Hit rate < 70%
- Critical: Hit rate < 60% or freshness > 10s

---

### Database Performance

#### Query Time
- **Target**: p95 < 100ms
- **Measurement**: Query execution time from query log

#### Slow Queries
- **Target**: < 10 queries > 100ms per hour
- **Measurement**: Count of queries exceeding 100ms threshold

**Alerting**:
- Warning: p95 > 80ms or > 5 slow queries/hour
- Critical: p95 > 100ms or > 10 slow queries/hour

---

### Error Rate

#### 4xx Errors (Client Errors)
- **Target**: < 1% of total requests
- **Measurement**: 4xx responses / total responses over 1-hour window

#### 5xx Errors (Server Errors)
- **Target**: < 0.1% of total requests
- **Measurement**: 5xx responses / total responses over 1-hour window

**Alerting**:
- Warning: 4xx > 0.8% or 5xx > 0.08%
- Critical: 4xx > 1% or 5xx > 0.1%

---

### Availability

#### Uptime
- **Target**: 99.9% availability (8.76 hours downtime/year)
- **Measurement**: Successful requests / total requests over 1-hour window

**Alerting**:
- Warning: Availability < 99.5%
- Critical: Availability < 99%

---

## SLA Commitments

### Standard SLA
- **Uptime**: 99.9%
- **Response Time**: p95 < 300ms (standard endpoints)
- **Error Rate**: < 0.1% (5xx errors)

### Premium SLA (Future)
- **Uptime**: 99.95%
- **Response Time**: p95 < 200ms (all endpoints)
- **Error Rate**: < 0.05% (5xx errors)
- **Support Response**: < 1 hour

---

## Dashboard Freshness SLO

### Cache Freshness After Mutation
- **Target**: Dashboard updates ≤ 5 seconds after mutation
- **Measurement**: Time from API mutation response to dashboard data refresh

**Mutations Tracked**:
- Task create/update/delete/move
- Project create/update/delete
- Document upload/update/delete
- Team member add/remove

**Alerting**:
- Warning: Freshness > 8s
- Critical: Freshness > 10s

---

## Alerting Rules

### Alert Severity Levels

1. **Critical**: Immediate action required
   - SLO violation > 100% of target
   - System availability < 99%
   - 5xx error rate > 0.1%

2. **Warning**: Attention needed
   - SLO violation > 80% of target
   - System availability < 99.5%
   - Performance degradation detected

3. **Info**: Monitoring
   - SLO approaching threshold (> 60% of target)
   - Performance trends

### Alert Channels

1. **Email**: Critical alerts only
   - Recipients: DevOps team, On-call engineer
   - Format: HTML email with metrics and links

2. **Slack**: All alerts
   - Channel: `#alerts-zenamanage`
   - Format: Rich message with metrics and actions

3. **In-App**: Warning and Info alerts
   - Dashboard notification
   - Alert history page

4. **SMS** (Future): Critical alerts only
   - On-call engineer
   - PagerDuty integration

### Alert Cooldown

- **Critical**: No cooldown (send immediately)
- **Warning**: 15-minute cooldown
- **Info**: 1-hour cooldown

---

## Measurement Methodology

### Metrics Collection

1. **API Metrics**: Collected via `PerformanceMiddleware`
   - Request/response time
   - Status codes
   - Tenant ID
   - Request ID

2. **Page Metrics**: Collected via Playwright E2E tests
   - Load time
   - Lighthouse metrics
   - Time to Interactive (TTI)

3. **WebSocket Metrics**: Collected via `DashboardWebSocketHandler`
   - Connection time
   - Subscribe latency
   - Message delivery time

4. **Cache Metrics**: Collected via `CacheManagementService`
   - Hit rate
   - Invalidation latency
   - Freshness tracking

5. **Database Metrics**: Collected via query log
   - Query execution time
   - Slow query count
   - Connection pool usage

### Aggregation Windows

- **Real-time**: Last 1 minute (for immediate alerts)
- **Short-term**: Last 5 minutes (for trending)
- **Medium-term**: Last 1 hour (for SLO calculation)
- **Long-term**: Last 24 hours (for reporting)

### Percentile Calculation

- **p50**: Median (50th percentile)
- **p95**: 95th percentile (used for SLO targets)
- **p99**: 99th percentile (for extreme cases)

---

## SLO Violation Response

### Immediate Actions

1. **Critical Violation**:
   - Alert on-call engineer
   - Create incident ticket
   - Start investigation
   - Post-mortem required

2. **Warning Violation**:
   - Log violation
   - Notify team via Slack
   - Monitor trends
   - Investigate if persistent

### Escalation

1. **Level 1**: Automated alert
2. **Level 2**: Team notification (15 minutes)
3. **Level 3**: Management notification (30 minutes)
4. **Level 4**: Executive notification (1 hour)

---

## Review and Updates

### Review Schedule
- **Weekly**: Review SLO compliance
- **Monthly**: Review SLO targets and adjust if needed
- **Quarterly**: Review SLA commitments

### Update Process
1. Analyze historical data
2. Identify trends
3. Propose changes
4. Review with stakeholders
5. Update documentation
6. Update monitoring

---

## Related Documents

- [Performance Budgets](performance-budgets.json)
- [Metrics Collection](docs/PR_METRICS_BUDGETS_CI.md)
- [Observability Guide](docs/OBSERVABILITY_GUIDE.md) (if exists)

---

## Appendix

### SLO Calculation Example

```
For API endpoint /api/v1/app/tasks:
- Target: p95 < 300ms
- Last 1000 requests: [150, 200, 180, ..., 350, 320]
- Sorted: [150, 180, 200, ..., 320, 350]
- p95 index: 950 (95% of 1000)
- p95 value: 320ms
- Status: VIOLATION (320ms > 300ms)
- Alert: CRITICAL
```

### Alert Message Template

```
[CRITICAL] SLO Violation: API Performance
Endpoint: /api/v1/app/tasks
Target: p95 < 300ms
Current: p95 = 320ms (106.7% of target)
Window: Last 5 minutes
Requests: 1000
Action Required: Investigate performance degradation
```

---

**Document Owner**: DevOps Team  
**Last Reviewed**: 2025-01-19  
**Next Review**: 2025-02-19

