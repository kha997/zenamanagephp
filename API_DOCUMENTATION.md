# ZenaManage API Documentation

## Overview
ZenaManage is a Laravel-based multi-tenant project management system with comprehensive API endpoints for managing projects, tasks, users, and system administration.

## Base URL
```
http://localhost:8002/api
```

## Authentication
All API endpoints require authentication using Laravel Sanctum tokens.

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

## API Endpoints

### Universal Frame APIs

#### KPI Management
```http
GET /api/universal-frame/kpis
POST /api/universal-frame/kpis/preferences
POST /api/universal-frame/kpis/refresh
GET /api/universal-frame/kpis/stats
```

**Response Example:**
```json
{
  "kpis": [
        {
            "id": 1,
      "name": "Active Projects",
      "value": 12,
      "change": "+2",
      "trend": "up",
      "icon": "fas fa-folder"
    }
  ]
}
```

#### Alert Management
```http
GET /api/universal-frame/alerts
POST /api/universal-frame/alerts/resolve
POST /api/universal-frame/alerts/acknowledge
POST /api/universal-frame/alerts/mute
POST /api/universal-frame/alerts/dismiss-all
POST /api/universal-frame/alerts/create
GET /api/universal-frame/alerts/stats
```

**Response Example:**
```json
{
  "alerts": [
            {
                "id": 1,
      "type": "warning",
      "title": "Project Deadline Approaching",
      "message": "Project 'Website Redesign' is due in 3 days",
      "priority": "high",
      "created_at": "2025-09-24T10:00:00Z"
    }
  ]
}
```

#### Activity Management
```http
GET /api/universal-frame/activities
POST /api/universal-frame/activities/create
GET /api/universal-frame/activities/by-type
GET /api/universal-frame/activities/stats
POST /api/universal-frame/activities/clear-old
```

**Response Example:**
```json
{
  "activities": [
    {
      "id": 1,
      "type": "project_created",
      "description": "John created project 'Website Redesign'",
      "user": "John Doe",
      "created_at": "2025-09-24T10:00:00Z"
    }
  ]
}
```

### Smart Tools APIs

#### Search
```http
POST /api/universal-frame/search
GET /api/universal-frame/search/suggestions
GET /api/universal-frame/search/recent
POST /api/universal-frame/search/recent
```

**Request Example:**
```json
{
  "query": "website redesign",
  "context": "projects",
  "limit": 10
}
```

**Response Example:**
```json
{
  "results": [
    {
      "id": 1,
      "type": "project",
      "title": "Website Redesign",
      "description": "Complete overhaul of company website",
      "relevance": 0.95
    }
  ],
  "suggestions": ["website", "redesign", "project"]
}
```

#### Filters
```http
GET /api/universal-frame/filters/presets
GET /api/universal-frame/filters/deep
GET /api/universal-frame/filters/saved-views
POST /api/universal-frame/filters/saved-views
DELETE /api/universal-frame/filters/saved-views/{viewId}
POST /api/universal-frame/filters/apply
```

**Response Example:**
```json
{
  "presets": [
    {
      "id": 1,
      "name": "My Projects",
      "filters": {
        "status": "in_progress",
        "assignee": "current_user"
      }
    }
  ]
}
```

#### Analysis
```http
POST /api/universal-frame/analysis
GET /api/universal-frame/analysis/{context}
GET /api/universal-frame/analysis/{context}/metrics
GET /api/universal-frame/analysis/{context}/charts
GET /api/universal-frame/analysis/{context}/insights
```

**Response Example:**
```json
{
  "metrics": {
    "total_projects": 12,
    "completed_tasks": 247,
    "team_members": 8,
    "documents": 156
  },
  "charts": [
    {
      "type": "pie",
      "data": {
        "labels": ["Completed", "In Progress", "Pending"],
        "values": [45, 35, 20]
      }
    }
  ],
  "insights": [
    "Project completion rate has increased by 15% this month"
  ]
}
```

#### Export
```http
POST /api/universal-frame/export
POST /api/universal-frame/export/projects
POST /api/universal-frame/export/tasks
POST /api/universal-frame/export/documents
POST /api/universal-frame/export/users
POST /api/universal-frame/export/tenants
GET /api/universal-frame/export/history
DELETE /api/universal-frame/export/{filename}
POST /api/universal-frame/export/clean-old
```

**Request Example:**
```json
{
  "entity_type": "projects",
  "format": "csv",
  "columns": ["name", "status", "progress", "due_date"],
  "filters": {
    "status": "in_progress"
    }
}
```

### Accessibility APIs

#### Preferences
```http
GET /api/accessibility/preferences
POST /api/accessibility/preferences
POST /api/accessibility/preferences/reset
```

**Response Example:**
```json
{
  "high_contrast_mode": false,
  "reduced_motion": false,
  "font_size": "medium",
  "screen_reader_mode": false
}
```

#### Compliance & Auditing
```http
GET /api/accessibility/compliance-report
POST /api/accessibility/audit-page
GET /api/accessibility/statistics
POST /api/accessibility/check-color-contrast
POST /api/accessibility/generate-report
GET /api/accessibility/help
```

**Response Example:**
```json
{
  "target": "WCAG 2.1 AA",
  "score": 95,
  "issues_found": 3,
  "issues_resolved": 12,
  "last_audit": "2025-09-24T10:00:00Z",
  "recommendations": [
    "Review custom components for ARIA attributes"
  ]
}
```

### Performance Optimization APIs

#### Metrics & Analysis
```http
GET /api/performance/metrics
GET /api/performance/analysis
GET /api/performance/recommendations
```

**Response Example:**
```json
{
  "page_load_time": 1.25,
  "api_response_time": 0.18,
  "cache_hit_rate": 94,
  "database_query_time": 0.15,
  "memory_usage": 45.2,
  "cpu_usage": 35
}
```

#### Optimization Actions
```http
POST /api/performance/optimize-database
POST /api/performance/implement-caching
POST /api/performance/optimize-api
POST /api/performance/optimize-assets
```

**Response Example:**
```json
{
  "status": "success",
  "results": [
    {
      "optimization": "database_indexing",
      "impact": "70% faster queries",
      "applied_at": "2025-09-24T10:00:00Z"
    }
  ]
}
```

## Error Handling

### Standard Error Response
```json
{
  "error": {
    "id": "ERR_001",
    "message": "Validation failed",
    "details": {
      "field": "email",
      "rule": "required"
    },
    "timestamp": "2025-09-24T10:00:00Z"
    }
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

## Rate Limiting
- **Public APIs**: 60 requests per minute
- **Authenticated APIs**: 1000 requests per minute
- **Admin APIs**: 2000 requests per minute

## Pagination
```json
{
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5,
    "from": 1,
    "to": 20
    }
}
```

## Filtering & Sorting
```http
GET /api/universal-frame/kpis?filter[status]=active&sort=created_at&order=desc
```

## Webhooks
```json
{
  "event": "project.created",
  "data": {
    "project": {
      "id": 1,
      "name": "Website Redesign",
      "status": "planning"
    }
  },
  "timestamp": "2025-09-24T10:00:00Z"
}
```

## SDK Examples

### JavaScript/Node.js
```javascript
const ZenaManageAPI = require('zenamanage-sdk');

const client = new ZenaManageAPI({
  baseURL: 'http://localhost:8002/api',
  token: 'your-api-token'
});

// Get KPIs
const kpis = await client.kpis.list();

// Create project
const project = await client.projects.create({
  name: 'New Project',
  description: 'Project description'
});
```

### PHP
```php
use ZenaManage\Client;

$client = new Client('http://localhost:8002/api', 'your-api-token');

// Get KPIs
$kpis = $client->kpis()->list();

// Create project
$project = $client->projects()->create([
    'name' => 'New Project',
    'description' => 'Project description'
]);
```

## Testing
Use the testing suite at `/testing-suite` to validate API endpoints and functionality.

## Support
For API support and questions, contact the development team or refer to the troubleshooting guide.