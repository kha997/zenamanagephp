# 📚 **ZENAMANAGE API DOCUMENTATION**

## 📋 **OVERVIEW**

This document provides comprehensive API documentation for the ZenaManage system, including all endpoints, authentication, error handling, and examples.

## 🔐 **AUTHENTICATION**

### **API Authentication**
ZenaManage uses Laravel Sanctum for API authentication with token-based authentication.

```bash
# Login to get token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Use token in requests
curl -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  http://localhost:8000/api/v1/auth/me
```

### **Authentication Endpoints**
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/logout` - User logout
- `POST /api/v1/auth/refresh` - Refresh token
- `GET /api/v1/auth/me` - Get current user
- `GET /api/v1/auth/permissions` - Get user permissions

## 📊 **DASHBOARD API**

### **Project Manager Dashboard**
- `GET /api/v1/project-manager/dashboard/stats` - Dashboard statistics
- `GET /api/v1/project-manager/dashboard/timeline` - Project timeline

### **Admin Dashboard**
- `GET /api/v1/admin/dashboard/stats` - Admin dashboard statistics
- `GET /api/v1/admin/dashboard/activities` - System activities
- `GET /api/v1/admin/dashboard/alerts` - System alerts
- `GET /api/v1/admin/dashboard/metrics` - System metrics

## 🏗️ **PROJECT MANAGEMENT API**

### **Projects**
- `GET /api/v1/projects` - List projects
- `POST /api/v1/projects` - Create project
- `GET /api/v1/projects/{id}` - Get project
- `PUT /api/v1/projects/{id}` - Update project
- `DELETE /api/v1/projects/{id}` - Delete project

### **Tasks**
- `GET /api/v1/tasks` - List tasks
- `POST /api/v1/tasks` - Create task
- `GET /api/v1/tasks/{id}` - Get task
- `PUT /api/v1/tasks/{id}` - Update task
- `DELETE /api/v1/tasks/{id}` - Delete task

### **Users**
- `GET /api/v1/users` - List users
- `POST /api/v1/users` - Create user
- `GET /api/v1/users/{id}` - Get user
- `PUT /api/v1/users/{id}` - Update user
- `DELETE /api/v1/users/{id}` - Delete user

## 🔄 **LEGACY ROUTE MONITORING API**

### **Monitoring Endpoints**
- `GET /api/v1/legacy-routes/usage` - Usage statistics
- `GET /api/v1/legacy-routes/migration-phase` - Migration phase info
- `GET /api/v1/legacy-routes/report` - Comprehensive report
- `POST /api/v1/legacy-routes/record-usage` - Record usage
- `POST /api/v1/legacy-routes/cleanup` - Clean old data

## 📈 **UNIVERSAL FRAME API**

### **KPIs**
- `GET /api/v1/universal-frame/kpis` - Get KPIs
- `GET /api/v1/universal-frame/kpis/preferences` - Get KPI preferences
- `POST /api/v1/universal-frame/kpis/preferences` - Save KPI preferences
- `POST /api/v1/universal-frame/kpis/refresh` - Refresh KPIs
- `GET /api/v1/universal-frame/kpis/stats` - KPI statistics

### **Alerts**
- `GET /api/v1/universal-frame/alerts` - Get alerts
- `POST /api/v1/universal-frame/alerts/resolve` - Resolve alert
- `POST /api/v1/universal-frame/alerts/acknowledge` - Acknowledge alert
- `POST /api/v1/universal-frame/alerts/mute` - Mute alert
- `POST /api/v1/universal-frame/alerts/dismiss-all` - Dismiss all alerts
- `POST /api/v1/universal-frame/alerts/create` - Create alert
- `GET /api/v1/universal-frame/alerts/stats` - Alert statistics

### **Activities**
- `GET /api/v1/universal-frame/activities` - Get activities
- `POST /api/v1/universal-frame/activities/create` - Create activity
- `GET /api/v1/universal-frame/activities/by-type` - Get activities by type
- `GET /api/v1/universal-frame/activities/stats` - Activity statistics
- `POST /api/v1/universal-frame/activities/clear-old` - Clear old activities

## 🔍 **SMART TOOLS API**

### **Search**
- `POST /api/v1/universal-frame/search` - Search
- `GET /api/v1/universal-frame/search/suggestions` - Get search suggestions
- `GET /api/v1/universal-frame/search/recent` - Get recent searches
- `POST /api/v1/universal-frame/search/recent` - Save recent search

### **Filters**
- `GET /api/v1/universal-frame/filters/presets` - Get filter presets
- `GET /api/v1/universal-frame/filters/deep` - Get deep filters
- `GET /api/v1/universal-frame/filters/saved-views` - Get saved views
- `POST /api/v1/universal-frame/filters/saved-views` - Save view
- `DELETE /api/v1/universal-frame/filters/saved-views/{viewId}` - Delete view
- `POST /api/v1/universal-frame/filters/apply` - Apply filters

### **Analysis**
- `POST /api/v1/universal-frame/analysis` - Run analysis
- `GET /api/v1/universal-frame/analysis/{context}` - Get context analysis
- `GET /api/v1/universal-frame/analysis/{context}/metrics` - Get context metrics
- `GET /api/v1/universal-frame/analysis/{context}/charts` - Get context charts
- `GET /api/v1/universal-frame/analysis/{context}/insights` - Get context insights

### **Export**
- `POST /api/v1/universal-frame/export` - Export data
- `POST /api/v1/universal-frame/export/projects` - Export projects
- `POST /api/v1/universal-frame/export/tasks` - Export tasks
- `POST /api/v1/universal-frame/export/documents` - Export documents
- `POST /api/v1/universal-frame/export/users` - Export users
- `POST /api/v1/universal-frame/export/tenants` - Export tenants
- `GET /api/v1/universal-frame/export/history` - Get export history
- `DELETE /api/v1/universal-frame/export/{filename}` - Delete export
- `POST /api/v1/universal-frame/export/clean-old` - Clean old exports

## ♿ **ACCESSIBILITY API**

### **Accessibility Management**
- `GET /api/v1/accessibility/preferences` - Get accessibility preferences
- `POST /api/v1/accessibility/preferences` - Save accessibility preferences
- `POST /api/v1/accessibility/preferences/reset` - Reset preferences
- `GET /api/v1/accessibility/compliance-report` - Get compliance report
- `POST /api/v1/accessibility/audit-page` - Audit page accessibility
- `GET /api/v1/accessibility/statistics` - Get accessibility statistics
- `POST /api/v1/accessibility/check-color-contrast` - Check color contrast
- `POST /api/v1/accessibility/generate-report` - Generate accessibility report
- `GET /api/v1/accessibility/help` - Get accessibility help

## ⚡ **PERFORMANCE API**

### **Performance Optimization**
- `GET /api/v1/performance/metrics` - Get performance metrics
- `GET /api/v1/performance/analysis` - Get performance analysis
- `POST /api/v1/performance/optimize-database` - Optimize database
- `POST /api/v1/performance/implement-caching` - Implement caching
- `POST /api/v1/performance/optimize-api` - Optimize API
- `POST /api/v1/performance/optimize-assets` - Optimize assets
- `GET /api/v1/performance/recommendations` - Get performance recommendations

## 🚀 **FINAL INTEGRATION API**

### **Launch Management**
- `GET /api/v1/final-integration/launch-status` - Get launch status
- `POST /api/v1/final-integration/system-integration-checks` - Run system checks
- `POST /api/v1/final-integration/production-readiness-checks` - Run readiness checks
- `POST /api/v1/final-integration/launch-preparation-tasks` - Run preparation tasks
- `GET /api/v1/final-integration/go-live-checklist` - Get go-live checklist
- `POST /api/v1/final-integration/pre-launch-actions` - Execute pre-launch actions
- `POST /api/v1/final-integration/launch-actions` - Execute launch actions
- `POST /api/v1/final-integration/validate-integration` - Validate integration
- `POST /api/v1/final-integration/run-production-check` - Run production check
- `POST /api/v1/final-integration/complete-launch-task` - Complete launch task
- `POST /api/v1/final-integration/toggle-checklist-item` - Toggle checklist item
- `POST /api/v1/final-integration/execute-action` - Execute action
- `GET /api/v1/final-integration/launch-metrics` - Get launch metrics
- `GET /api/v1/final-integration/launch-report` - Generate launch report

## 🔧 **CACHE MANAGEMENT API**

### **Cache Operations**
- `GET /api/v1/cache/stats` - Get cache statistics
- `GET /api/v1/cache/config` - Get cache configuration
- `POST /api/v1/cache/invalidate/key` - Invalidate cache by key
- `POST /api/v1/cache/invalidate/tags` - Invalidate cache by tags
- `POST /api/v1/cache/invalidate/pattern` - Invalidate cache by pattern
- `POST /api/v1/cache/warmup` - Warm up cache
- `POST /api/v1/cache/clear` - Clear all cache

## 🌐 **WEBSOCKET API**

### **WebSocket Management**
- `GET /api/v1/websocket/info` - Get WebSocket info
- `GET /api/v1/websocket/stats` - Get WebSocket statistics
- `GET /api/v1/websocket/channels` - Get WebSocket channels
- `POST /api/v1/websocket/test` - Test WebSocket connection
- `POST /api/v1/websocket/online` - Set user online
- `POST /api/v1/websocket/offline` - Set user offline
- `GET /api/v1/websocket/activity` - Get WebSocket activity
- `POST /api/v1/websocket/broadcast` - Broadcast message
- `POST /api/v1/websocket/notification` - Send notification

## 📝 **ERROR HANDLING**

### **Error Envelope Format**
All API responses follow a standardized error envelope format:

```json
{
  "error": {
    "id": "req_12345678",
    "code": "E422.VALIDATION",
    "message": "Validation failed",
    "details": {
      "validation": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
      }
    }
  }
}
```

### **Error Codes**
- `E400.BAD_REQUEST` - Bad request
- `E401.AUTHENTICATION` - Authentication required
- `E403.AUTHORIZATION` - Authorization failed
- `E404.NOT_FOUND` - Resource not found
- `E409.CONFLICT` - Resource conflict
- `E422.VALIDATION` - Validation failed
- `E429.RATE_LIMIT` - Rate limit exceeded
- `E500.SERVER_ERROR` - Internal server error
- `E503.SERVICE_UNAVAILABLE` - Service unavailable

### **HTTP Status Codes**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `422` - Unprocessable Entity
- `429` - Too Many Requests
- `500` - Internal Server Error
- `503` - Service Unavailable

## 🔒 **RATE LIMITING**

### **Rate Limits**
- **Authentication endpoints:** 5 requests per minute
- **API endpoints:** 60 requests per minute
- **Public endpoints:** 30 requests per minute

### **Rate Limit Headers**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
X-RateLimit-Window: 60
X-RateLimit-Burst: false
```

## 🏢 **MULTI-TENANT ISOLATION**

### **Tenant Scoping**
All API endpoints automatically scope data by `tenant_id` to ensure multi-tenant isolation.

### **Tenant Headers**
```
X-Tenant-ID: tenant_123
X-Tenant-Name: Acme Corp
```

## 📊 **PERFORMANCE BUDGETS**

### **API Performance Targets**
- **Dashboard Stats API:** < 300ms
- **Project Timeline API:** < 300ms
- **Authentication Check:** < 50ms
- **Error Responses:** < 100ms

### **Response Size Limits**
- **List endpoints:** < 1MB
- **Detail endpoints:** < 500KB
- **Export endpoints:** < 10MB

## 🔍 **TESTING**

### **API Testing**
```bash
# Test authentication
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password"}'

# Test dashboard stats
curl -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  http://localhost:8000/api/v1/project-manager/dashboard/stats

# Test error handling
curl -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  http://localhost:8000/api/v1/nonexistent-endpoint
```

### **Performance Testing**
```bash
# Test API performance
curl -w "@curl-format.txt" -o /dev/null -s \
  http://localhost:8000/api/v1/project-manager/dashboard/stats
```

## 📚 **EXAMPLES**

### **Project Creation**
```bash
curl -X POST http://localhost:8000/api/v1/projects \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "New Project",
    "description": "Project description",
    "budget_planned": 50000,
    "start_date": "2024-01-01",
    "end_date": "2024-06-30"
  }'
```

### **Task Update**
```bash
curl -X PUT http://localhost:8000/api/v1/tasks/123 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Updated Task",
    "status": "in_progress",
    "priority": "high"
  }'
```

### **Legacy Route Monitoring**
```bash
# Get usage statistics
curl -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  http://localhost:8000/api/v1/legacy-routes/usage

# Generate report
curl -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  http://localhost:8000/api/v1/legacy-routes/report
```

## 🔗 **RESOURCES**

### **Documentation**
- [API Reference](../../docs/api/README.md)
- [Authentication Guide](../../docs/auth/README.md)
- [Error Handling](../../docs/errors/README.md)
- [Rate Limiting](../../docs/rate-limiting/README.md)

### **Tools**
- **API Testing:** Postman, Insomnia, curl
- **Documentation:** Swagger UI, OpenAPI
- **Monitoring:** New Relic, DataDog

### **Support**
- **Email:** api-support@zenamanage.com
- **Slack:** #api-support
- **Documentation:** /docs/api

---

**Last Updated:** December 19, 2024  
**Version:** 1.0  
**Maintainer:** ZenaManage Development Team
