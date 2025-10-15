# ZenaManage API Documentation

## Overview

ZenaManage provides a comprehensive RESTful API for managing construction projects, teams, documents, and more. All API endpoints are versioned and follow RESTful conventions.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All API endpoints (except public endpoints) require authentication using Laravel Sanctum tokens.

### Headers Required

```http
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

## Response Format

All API responses follow a consistent format:

### Success Response

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "message": "Operation completed successfully",
  "timestamp": "2025-01-15T10:30:00Z"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "id": "error_123",
    "message": "Error description",
    "code": "VALIDATION_ERROR",
    "details": {
      // Additional error details
    }
  },
  "timestamp": "2025-01-15T10:30:00Z"
}
```

## API Endpoints

### Dashboard API

#### Get Dashboard Statistics
```http
GET /api/v1/app/dashboard/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "kpis": {
      "active_projects": 12,
      "total_tasks": 45,
      "completed_tasks": 30,
      "team_members": 8
    }
  }
}
```

#### Get Recent Projects
```http
GET /api/v1/app/dashboard/recent-projects?limit=5
```

#### Get Recent Tasks
```http
GET /api/v1/app/dashboard/recent-tasks?limit=5
```

#### Get Recent Activity
```http
GET /api/v1/app/dashboard/recent-activity?limit=10
```

#### Get Dashboard Metrics
```http
GET /api/v1/app/dashboard/metrics?period=30d
```

**Parameters:**
- `period`: `7d`, `30d`, `90d` (default: `30d`)

### Calendar API

#### List Calendar Events
```http
GET /api/v1/app/calendar
```

**Query Parameters:**
- `start`: Start date (ISO 8601)
- `end`: End date (ISO 8601)
- `type`: Event type (`meeting`, `deadline`, `milestone`, `other`)

#### Create Calendar Event
```http
POST /api/v1/app/calendar
```

**Request Body:**
```json
{
  "title": "Project Kickoff Meeting",
  "description": "Initial project discussion",
  "start_date": "2025-01-20T09:00:00Z",
  "end_date": "2025-01-20T10:30:00Z",
  "type": "meeting",
  "project_id": "project_001",
  "attendees": ["user_001", "user_002"],
  "location": "Conference Room A"
}
```

#### Get Calendar Event
```http
GET /api/v1/app/calendar/{id}
```

#### Update Calendar Event
```http
PUT /api/v1/app/calendar/{id}
```

#### Delete Calendar Event
```http
DELETE /api/v1/app/calendar/{id}
```

#### Get Calendar Statistics
```http
GET /api/v1/app/calendar/stats
```

### Team API

#### List Team Members
```http
GET /api/v1/app/team
```

**Query Parameters:**
- `role`: Filter by role (`admin`, `pm`, `member`, `client`)
- `status`: Filter by status (`active`, `inactive`, `suspended`)
- `search`: Search by name or email

#### Create Team Member
```http
POST /api/v1/app/team
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "role": "member",
  "password": "securepassword",
  "phone": "+1234567890",
  "department": "Engineering"
}
```

#### Get Team Member
```http
GET /api/v1/app/team/{id}
```

#### Update Team Member
```http
PUT /api/v1/app/team/{id}
```

#### Delete Team Member
```http
DELETE /api/v1/app/team/{id}
```

#### Get Team Statistics
```http
GET /api/v1/app/team/stats
```

#### Invite Team Member
```http
POST /api/v1/app/team/invite
```

**Request Body:**
```json
{
  "email": "newmember@example.com",
  "role": "member",
  "message": "Welcome to our team!"
}
```

### Documents API

#### List Documents
```http
GET /api/v1/app/documents
```

**Query Parameters:**
- `type`: Filter by file type (`pdf`, `jpg`, `png`, `docx`, etc.)
- `status`: Filter by status (`active`, `archived`, `draft`)
- `project_id`: Filter by project
- `search`: Search by document name

#### Upload Document
```http
POST /api/v1/app/documents
```

**Request Body (multipart/form-data):**
- `file`: Document file
- `name`: Document name
- `description`: Document description
- `project_id`: Associated project ID
- `category`: Document category

#### Get Document
```http
GET /api/v1/app/documents/{id}
```

#### Update Document
```http
PUT /api/v1/app/documents/{id}
```

#### Delete Document
```http
DELETE /api/v1/app/documents/{id}
```

#### Download Document
```http
GET /api/v1/app/documents/{id}/download
```

#### Get Document Statistics
```http
GET /api/v1/app/documents/stats
```

### Settings API

#### Get Settings
```http
GET /api/v1/app/settings
```

**Response:**
```json
{
  "success": true,
  "data": {
    "settings": {
      "general": {
        "company_name": "Acme Construction",
        "timezone": "Asia/Ho_Chi_Minh",
        "language": "en",
        "currency": "VND"
      },
      "notifications": {
        "email_notifications": true,
        "push_notifications": true,
        "project_updates": true
      },
      "security": {
        "two_factor_auth": false,
        "session_timeout": 30
      }
    }
  }
}
```

#### Update General Settings
```http
PUT /api/v1/app/settings/general
```

**Request Body:**
```json
{
  "company_name": "New Company Name",
  "timezone": "UTC",
  "language": "vi",
  "currency": "USD",
  "date_format": "MM/DD/YYYY",
  "time_format": "12h"
}
```

#### Update Notification Settings
```http
PUT /api/v1/app/settings/notifications
```

#### Update Security Settings
```http
PUT /api/v1/app/settings/security
```

#### Update Privacy Settings
```http
PUT /api/v1/app/settings/privacy
```

#### Update Integration Settings
```http
PUT /api/v1/app/settings/integrations
```

#### Get Settings Statistics
```http
GET /api/v1/app/settings/stats
```

#### Export Data
```http
POST /api/v1/app/settings/export-data
```

#### Delete Data
```http
DELETE /api/v1/app/settings/delete-data
```

**Request Body:**
```json
{
  "confirmation": "DELETE_ALL_DATA"
}
```

### Templates API

#### List Templates
```http
GET /api/v1/app/templates
```

#### Create Template
```http
POST /api/v1/app/templates
```

#### Get Template
```http
GET /api/v1/app/templates/{id}
```

#### Update Template
```http
PUT /api/v1/app/templates/{id}
```

#### Delete Template
```http
DELETE /api/v1/app/templates/{id}
```

### Projects API

#### List Projects
```http
GET /api/v1/app/projects
```

#### Create Project
```http
POST /api/v1/app/projects
```

#### Get Project
```http
GET /api/v1/app/projects/{id}
```

#### Update Project
```http
PUT /api/v1/app/projects/{id}
```

#### Delete Project
```http
DELETE /api/v1/app/projects/{id}
```

### Clients API

#### List Clients
```http
GET /api/v1/app/clients
```

#### Create Client
```http
POST /api/v1/app/clients
```

#### Get Client
```http
GET /api/v1/app/clients/{id}
```

#### Update Client
```http
PUT /api/v1/app/clients/{id}
```

#### Delete Client
```http
DELETE /api/v1/app/clients/{id}
```

#### Get Client Statistics
```http
GET /api/v1/app/clients/{id}/stats
```

#### Update Client Lifecycle Stage
```http
PATCH /api/v1/app/clients/{id}/lifecycle-stage
```

### Quotes API

#### List Quotes
```http
GET /api/v1/app/quotes
```

#### Create Quote
```http
POST /api/v1/app/quotes
```

#### Get Quote
```http
GET /api/v1/app/quotes/{id}
```

#### Update Quote
```http
PUT /api/v1/app/quotes/{id}
```

#### Delete Quote
```http
DELETE /api/v1/app/quotes/{id}
```

#### Send Quote
```http
POST /api/v1/app/quotes/{id}/send
```

#### Accept Quote
```http
POST /api/v1/app/quotes/{id}/accept
```

#### Reject Quote
```http
POST /api/v1/app/quotes/{id}/reject
```

#### Get Quote Statistics
```http
GET /api/v1/app/quotes/stats
```

## Error Codes

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Request validation failed |
| `AUTHENTICATION_ERROR` | Invalid or missing authentication |
| `AUTHORIZATION_ERROR` | Insufficient permissions |
| `NOT_FOUND` | Resource not found |
| `CONFLICT` | Resource conflict |
| `RATE_LIMIT_EXCEEDED` | Too many requests |
| `INTERNAL_ERROR` | Server error |

## Rate Limiting

API requests are rate limited to prevent abuse:

- **Authenticated requests**: 1000 requests per hour
- **Public endpoints**: 100 requests per hour

Rate limit headers are included in responses:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Pagination

List endpoints support pagination:

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

**Response Headers:**
```http
X-Pagination-Current-Page: 1
X-Pagination-Last-Page: 10
X-Pagination-Per-Page: 15
X-Pagination-Total: 150
```

## Filtering and Sorting

Many endpoints support filtering and sorting:

**Query Parameters:**
- `filter[field]`: Filter by specific field
- `sort`: Sort field (prefix with `-` for descending)
- `search`: Global search term

**Example:**
```http
GET /api/v1/app/projects?filter[status]=active&sort=-created_at&search=construction
```

## Webhooks

ZenaManage supports webhooks for real-time notifications:

**Supported Events:**
- `project.created`
- `project.updated`
- `project.deleted`
- `task.created`
- `task.completed`
- `quote.sent`
- `quote.accepted`
- `quote.rejected`

**Webhook Payload:**
```json
{
  "event": "project.created",
  "data": {
    "id": "project_001",
    "name": "New Project",
    "status": "active"
  },
  "timestamp": "2025-01-15T10:30:00Z"
}
```

## SDKs and Libraries

Official SDKs are available for:

- **JavaScript/Node.js**: `npm install zenamanage-sdk`
- **PHP**: `composer require zenamanage/sdk`
- **Python**: `pip install zenamanage-sdk`

## Support

For API support and questions:

- **Documentation**: https://docs.zenamanage.com/api
- **Support Email**: api-support@zenamanage.com
- **Status Page**: https://status.zenamanage.com