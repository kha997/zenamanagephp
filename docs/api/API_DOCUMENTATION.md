# ZENAMANAGE API DOCUMENTATION

## 🔌 COMPLETE API REFERENCE

**Version**: 2.0  
**Last Updated**: 2025-01-08  
**Base URL**: `https://zenamanage.com/api`

---

## 🎯 TABLE OF CONTENTS

1. [API Overview](#api-overview)
2. [Authentication](#authentication)
3. [Rate Limiting](#rate-limiting)
4. [API Versioning](#api-versioning)
5. [Error Handling](#error-handling)
6. [Endpoints](#endpoints)
7. [Request/Response Examples](#requestresponse-examples)
8. [SDKs & Libraries](#sdks--libraries)
9. [Webhooks](#webhooks)
10. [Testing](#testing)

---

## 🔌 API OVERVIEW

### Base URL
All API requests should be made to:
```
https://zenamanage.com/api
```

### API Versioning
The API uses URL-based versioning:
```
https://zenamanage.com/api/v1/
https://zenamanage.com/api/v2/
```

### Response Format
All API responses follow a consistent JSON format:

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    }
  }
}
```

### Error Format
Error responses follow this format:

```json
{
  "success": false,
  "error": {
    "id": "error-uuid",
    "message": "Error description",
    "code": "ERROR_CODE",
    "details": {
      // Additional error details
    },
    "timestamp": "2025-01-08T10:30:00Z"
  }
}
```

---

## 🔐 AUTHENTICATION

### Authentication Methods
ZenaManage API supports multiple authentication methods:

#### 1. Personal Access Tokens (Recommended)
```http
Authorization: Bearer your-personal-access-token
```

#### 2. API Keys
```http
X-API-Key: your-api-key
```

#### 3. Session Authentication
```http
Cookie: zenamanage_session=your-session-token
```

### Getting Access Tokens

#### Create Personal Access Token
```http
POST /api/auth/tokens
Content-Type: application/json

{
  "name": "My API Token",
  "abilities": ["projects:read", "tasks:write"],
  "expires_at": "2025-12-31T23:59:59Z"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|abcdef1234567890",
    "name": "My API Token",
    "abilities": ["projects:read", "tasks:write"],
    "expires_at": "2025-12-31T23:59:59Z",
    "created_at": "2025-01-08T10:30:00Z"
  }
}
```

#### Login and Get Token
```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|abcdef1234567890",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "project_manager",
      "tenant_id": 1
    }
  }
}
```

### Token Abilities
Tokens can have specific abilities:

| Ability | Description |
|---------|-------------|
| `projects:read` | Read project data |
| `projects:write` | Create/update projects |
| `projects:delete` | Delete projects |
| `tasks:read` | Read task data |
| `tasks:write` | Create/update tasks |
| `tasks:delete` | Delete tasks |
| `clients:read` | Read client data |
| `clients:write` | Create/update clients |
| `users:read` | Read user data |
| `users:write` | Create/update users |
| `admin` | Full admin access |

---

## ⚡ RATE LIMITING

### Rate Limits
API requests are rate limited per user:

| Endpoint Type | Rate Limit |
|---------------|------------|
| Authentication | 60 requests/minute |
| General API | 1000 requests/hour |
| File Upload | 100 requests/hour |
| Admin Endpoints | 500 requests/hour |

### Rate Limit Headers
Rate limit information is included in response headers:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1641648000
```

### Rate Limit Exceeded
When rate limit is exceeded:

```json
{
  "success": false,
  "error": {
    "id": "rate-limit-exceeded",
    "message": "Rate limit exceeded",
    "code": "RATE_LIMIT_EXCEEDED",
    "retry_after": 3600
  }
}
```

---

## 🔄 API VERSIONING

### Version Headers
Specify API version using headers:

```http
X-API-Version: v2
Accept: application/vnd.api.v2+json
```

### Version in URL
```http
GET /api/v2/projects
```

### Version Deprecation
Deprecated versions include warning headers:

```http
X-API-Deprecation-Date: 2025-06-01
X-API-Deprecation-Warning: API version v1 is deprecated
```

---

## ❌ ERROR HANDLING

### HTTP Status Codes
Standard HTTP status codes are used:

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limit Exceeded |
| 500 | Internal Server Error |

### Error Examples

#### Validation Error (422)
```json
{
  "success": false,
  "error": {
    "id": "validation-error-uuid",
    "message": "Validation failed",
    "code": "VALIDATION_ERROR",
    "details": {
      "name": ["The name field is required."],
      "email": ["The email must be a valid email address."]
    }
  }
}
```

#### Unauthorized (401)
```json
{
  "success": false,
  "error": {
    "id": "unauthorized-uuid",
    "message": "Unauthorized",
    "code": "UNAUTHORIZED",
    "details": {
      "reason": "Invalid or expired token"
    }
  }
}
```

#### Forbidden (403)
```json
{
  "success": false,
  "error": {
    "id": "forbidden-uuid",
    "message": "Forbidden",
    "code": "FORBIDDEN",
    "details": {
      "reason": "Insufficient permissions",
      "required_permission": "projects:write"
    }
  }
}
```

---

## 📡 ENDPOINTS

### Projects API

#### List Projects
```http
GET /api/projects
```

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page (max 100)
- `status` (string): Filter by status
- `client_id` (integer): Filter by client
- `user_id` (integer): Filter by user

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Website Redesign",
      "description": "Complete website redesign project",
      "status": "active",
      "budget_total": 50000,
      "budget_used": 25000,
      "progress_pct": 50,
      "start_date": "2025-01-01",
      "end_date": "2025-03-31",
      "client": {
        "id": 1,
        "name": "Acme Corp",
        "email": "contact@acme.com"
      },
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-08T10:30:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1
    }
  }
}
```

#### Get Project
```http
GET /api/projects/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Website Redesign",
    "description": "Complete website redesign project",
    "status": "active",
    "budget_total": 50000,
    "budget_used": 25000,
    "progress_pct": 50,
    "start_date": "2025-01-01",
    "end_date": "2025-03-31",
    "client": {
      "id": 1,
      "name": "Acme Corp",
      "email": "contact@acme.com"
    },
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "tasks": [
      {
        "id": 1,
        "name": "Design Mockups",
        "status": "completed",
        "progress_percent": 100
      }
    ],
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-08T10:30:00Z"
  }
}
```

#### Create Project
```http
POST /api/projects
Content-Type: application/json

{
  "name": "New Project",
  "description": "Project description",
  "budget_total": 30000,
  "start_date": "2025-01-15",
  "end_date": "2025-04-15",
  "client_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "New Project",
    "description": "Project description",
    "status": "planning",
    "budget_total": 30000,
    "budget_used": 0,
    "progress_pct": 0,
    "start_date": "2025-01-15",
    "end_date": "2025-04-15",
    "client_id": 1,
    "user_id": 1,
    "created_at": "2025-01-08T10:30:00Z",
    "updated_at": "2025-01-08T10:30:00Z"
  }
}
```

#### Update Project
```http
PUT /api/projects/{id}
Content-Type: application/json

{
  "name": "Updated Project Name",
  "status": "active",
  "progress_pct": 25
}
```

#### Delete Project
```http
DELETE /api/projects/{id}
```

### Tasks API

#### List Tasks
```http
GET /api/tasks
```

**Query Parameters:**
- `project_id` (integer): Filter by project
- `user_id` (integer): Filter by assignee
- `status` (string): Filter by status
- `priority` (string): Filter by priority

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Design Mockups",
      "description": "Create initial design mockups",
      "status": "in_progress",
      "priority": "high",
      "progress_percent": 75,
      "start_date": "2025-01-01",
      "end_date": "2025-01-15",
      "project": {
        "id": 1,
        "name": "Website Redesign"
      },
      "user": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com"
      },
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-08T10:30:00Z"
    }
  ]
}
```

#### Create Task
```http
POST /api/tasks
Content-Type: application/json

{
  "name": "New Task",
  "description": "Task description",
  "project_id": 1,
  "user_id": 2,
  "priority": "medium",
  "start_date": "2025-01-10",
  "end_date": "2025-01-20"
}
```

#### Update Task
```http
PUT /api/tasks/{id}
Content-Type: application/json

{
  "status": "completed",
  "progress_percent": 100,
  "completed_at": "2025-01-08T10:30:00Z"
}
```

### Clients API

#### List Clients
```http
GET /api/clients
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Acme Corp",
      "email": "contact@acme.com",
      "phone": "+1-555-123-4567",
      "company": "Acme Corporation",
      "address": "123 Main St, City, State 12345",
      "projects_count": 3,
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-08T10:30:00Z"
    }
  ]
}
```

#### Create Client
```http
POST /api/clients
Content-Type: application/json

{
  "name": "New Client",
  "email": "client@example.com",
  "phone": "+1-555-987-6543",
  "company": "Client Company",
  "address": "456 Oak Ave, City, State 54321"
}
```

### Dashboard API

#### Get Dashboard Data
```http
GET /api/dashboard
```

**Response:**
```json
{
  "success": true,
  "data": {
    "projects": {
      "total": 10,
      "active": 5,
      "completed": 3,
      "on_hold": 2
    },
    "tasks": {
      "total": 50,
      "pending": 15,
      "in_progress": 20,
      "completed": 15
    },
    "clients": {
      "total": 8,
      "active": 6
    },
    "recent_activity": [
      {
        "id": 1,
        "type": "task_completed",
        "description": "Task 'Design Mockups' completed",
        "user": "Jane Smith",
        "created_at": "2025-01-08T10:30:00Z"
      }
    ]
  }
}
```

#### Get Dashboard Stats
```http
GET /api/dashboard/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "performance": {
      "avg_response_time": 150,
      "uptime": 99.9,
      "error_rate": 0.1
    },
    "usage": {
      "api_calls_today": 1250,
      "active_users": 25,
      "storage_used": "2.5GB"
    }
  }
}
```

---

## 📝 REQUEST/RESPONSE EXAMPLES

### Complete Project Workflow

#### 1. Create Project
```http
POST /api/projects
Authorization: Bearer your-token
Content-Type: application/json

{
  "name": "E-commerce Platform",
  "description": "Build a complete e-commerce platform",
  "budget_total": 100000,
  "start_date": "2025-01-15",
  "end_date": "2025-06-15",
  "client_id": 1
}
```

#### 2. Add Tasks to Project
```http
POST /api/tasks
Authorization: Bearer your-token
Content-Type: application/json

{
  "name": "Database Design",
  "description": "Design database schema",
  "project_id": 1,
  "user_id": 2,
  "priority": "high",
  "start_date": "2025-01-15",
  "end_date": "2025-01-25"
}
```

#### 3. Update Task Progress
```http
PUT /api/tasks/1
Authorization: Bearer your-token
Content-Type: application/json

{
  "status": "in_progress",
  "progress_percent": 50
}
```

#### 4. Complete Project
```http
PUT /api/projects/1
Authorization: Bearer your-token
Content-Type: application/json

{
  "status": "completed",
  "progress_pct": 100,
  "completed_at": "2025-06-15T00:00:00Z"
}
```

### File Upload Example

#### Upload Document
```http
POST /api/documents
Authorization: Bearer your-token
Content-Type: multipart/form-data

{
  "name": "Project Requirements",
  "description": "Detailed project requirements document",
  "project_id": 1,
  "file": [binary file data]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Project Requirements",
    "description": "Detailed project requirements document",
    "file_path": "/uploads/documents/requirements.pdf",
    "file_size": 1024000,
    "mime_type": "application/pdf",
    "project_id": 1,
    "created_at": "2025-01-08T10:30:00Z"
  }
}
```

---

## 🛠️ SDKs & LIBRARIES

### JavaScript/Node.js
```bash
npm install zenamanage-api
```

```javascript
const ZenaManage = require('zenamanage-api');

const client = new ZenaManage({
  baseUrl: 'https://zenamanage.com/api',
  token: 'your-access-token'
});

// Get projects
const projects = await client.projects.list();

// Create project
const project = await client.projects.create({
  name: 'New Project',
  description: 'Project description',
  budget_total: 30000
});
```

### PHP
```bash
composer require zenamanage/api-client
```

```php
use ZenaManage\ApiClient;

$client = new ApiClient([
    'base_url' => 'https://zenamanage.com/api',
    'token' => 'your-access-token'
]);

// Get projects
$projects = $client->projects()->list();

// Create project
$project = $client->projects()->create([
    'name' => 'New Project',
    'description' => 'Project description',
    'budget_total' => 30000
]);
```

### Python
```bash
pip install zenamanage-api
```

```python
from zenamanage import ZenaManageClient

client = ZenaManageClient(
    base_url='https://zenamanage.com/api',
    token='your-access-token'
)

# Get projects
projects = client.projects.list()

# Create project
project = client.projects.create({
    'name': 'New Project',
    'description': 'Project description',
    'budget_total': 30000
})
```

---

## 🔗 WEBHOOKS

### Webhook Configuration
Configure webhooks to receive real-time notifications:

```http
POST /api/webhooks
Authorization: Bearer your-token
Content-Type: application/json

{
  "url": "https://your-app.com/webhooks/zenamanage",
  "events": ["project.created", "task.completed"],
  "secret": "your-webhook-secret"
}
```

### Webhook Events
Available webhook events:

| Event | Description |
|-------|-------------|
| `project.created` | Project created |
| `project.updated` | Project updated |
| `project.completed` | Project completed |
| `task.created` | Task created |
| `task.updated` | Task updated |
| `task.completed` | Task completed |
| `user.created` | User created |
| `user.updated` | User updated |

### Webhook Payload
```json
{
  "event": "project.created",
  "data": {
    "id": 1,
    "name": "New Project",
    "status": "planning",
    "created_at": "2025-01-08T10:30:00Z"
  },
  "timestamp": "2025-01-08T10:30:00Z"
}
```

---

## 🧪 TESTING

### API Testing
Use tools like Postman, Insomnia, or curl to test the API:

#### Test Authentication
```bash
curl -X POST https://zenamanage.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'
```

#### Test Project Creation
```bash
curl -X POST https://zenamanage.com/api/projects \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Project",
    "description": "API test project",
    "budget_total": 10000
  }'
```

### Postman Collection
Import our Postman collection for easy API testing:
[Download Postman Collection](https://zenamanage.com/api/postman-collection.json)

### API Testing Tools
- **Postman**: GUI-based API testing
- **Insomnia**: Modern API testing tool
- **curl**: Command-line API testing
- **HTTPie**: User-friendly command-line HTTP client

---

## 📞 SUPPORT

### API Support
- **Documentation**: This comprehensive guide
- **API Status**: [status.zenamanage.com](https://status.zenamanage.com)
- **Support Email**: api-support@zenamanage.com
- **Developer Forum**: [forum.zenamanage.com](https://forum.zenamanage.com)

### Rate Limits & Quotas
- **Free Tier**: 1,000 requests/month
- **Pro Tier**: 100,000 requests/month
- **Enterprise**: Unlimited requests

### API Changelog
- **v2.0**: Current version with advanced features
- **v1.5**: Previous version with basic functionality
- **v1.0**: Initial API release

---

**ZenaManage API Documentation v2.0**  
*Last Updated: January 8, 2025*  
*For API support, contact api-support@zenamanage.com or visit our developer documentation center.*