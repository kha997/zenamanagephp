# ZENAMANAGE v2.0 - API REFERENCE
## Complete API Documentation and Examples

**Version**: 2.0  
**Last Updated**: October 5, 2025  
**Status**: Production Ready ✅

---

## 📋 **API OVERVIEW**

### **Base URLs**
- **Production**: `https://api.zenamanage.com/v1`
- **Development**: `http://localhost:8000/api/v1`

### **Authentication**
- **Type**: Bearer Token (Laravel Sanctum)
- **Header**: `Authorization: Bearer {token}`
- **Scopes**: `admin` or `tenant`

---

## 🔗 **CROSS-REFERENCES**

- **[📄 Complete System Documentation](../COMPLETE_SYSTEM_DOCUMENTATION.md)** - Main documentation
- **[📋 OpenAPI Specification](../api/openapi.json)** - Complete API spec
- **[📮 Postman Collection](../api/postman-collection.json)** - API testing
- **[💡 API Examples](../api/api-examples.md)** - Usage examples
- **[🏗️ Architecture Guide](architecture.md)** - System architecture
- **[🔒 Security Guide](security-guide.md)** - Security implementation

---

## 🚀 **QUICK START**

### **1. Authentication**
```bash
# Login to get token
curl -X POST https://api.zenamanage.com/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Response
{
  "success": true,
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": "01k5kzpfwd618xmwdwq3rej3jz",
      "name": "John Doe",
      "email": "user@example.com",
      "role": "pm",
      "tenant_id": "01k5kzpfwd618xmwdwq3rej3jz"
    }
  }
}
```

### **2. Using the Token**
```bash
# Make authenticated requests
curl -X GET https://api.zenamanage.com/v1/projects \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json"
```

---

## 📡 **API ENDPOINTS**

### **🏥 Health Endpoints**

#### **Basic Health Check**
```http
GET /health
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": {
    "status": "healthy",
    "timestamp": "2025-10-05T10:30:00Z",
    "version": "2.0.0"
  }
}
```

#### **Detailed Health Check**
```http
GET /health/detailed
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": {
    "status": "healthy",
    "timestamp": "2025-10-05T10:30:00Z",
    "version": "2.0.0",
    "environment": "production",
    "metrics": {
      "uptime": 86400,
      "memory": {
        "current_mb": 256.5,
        "peak_mb": 512.0,
        "limit_mb": 1024
      },
      "database": {
        "status": "connected",
        "driver": "mysql",
        "version": "8.0.25"
      },
      "cache": {
        "status": "connected",
        "driver": "Redis"
      }
    }
  }
}
```

---

### **📊 Projects API**

#### **List Projects**
```http
GET /projects?page=1&per_page=15&status=active&search=project
```

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15, max: 100)
- `status` (string): Filter by status (`planning`, `active`, `on_hold`, `completed`, `cancelled`)
- `priority` (string): Filter by priority (`low`, `medium`, `high`)
- `search` (string): Search in project name and description
- `sort` (string): Sort by field (`name`, `created_at`, `due_date`)

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": [
    {
      "id": "01k5kzpfwd618xmwdwq3rej3jz",
      "name": "Website Redesign",
      "description": "Complete redesign of company website",
      "status": "active",
      "priority": "high",
      "progress": 75,
      "team_size": 5,
      "start_date": "2025-09-01",
      "due_date": "2025-11-30",
      "created_at": "2025-09-01T10:00:00Z",
      "updated_at": "2025-10-05T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 25,
    "last_page": 2
  }
}
```

#### **Create Project**
```http
POST /projects
```

**Request Body:**
```json
{
  "name": "New Project",
  "description": "Project description",
  "status": "planning",
  "priority": "medium",
  "progress": 0,
  "team_size": 3,
  "start_date": "2025-10-06",
  "due_date": "2025-12-31"
}
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": {
    "id": "01k5kzpfwd618xmwdwq3rej3jz",
    "name": "New Project",
    "description": "Project description",
    "status": "planning",
    "priority": "medium",
    "progress": 0,
    "team_size": 3,
    "start_date": "2025-10-06",
    "due_date": "2025-12-31",
    "created_at": "2025-10-05T10:30:00Z",
    "updated_at": "2025-10-05T10:30:00Z"
  }
}
```

#### **Get Project**
```http
GET /projects/{id}
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": {
    "id": "01k5kzpfwd618xmwdwq3rej3jz",
    "name": "Website Redesign",
    "description": "Complete redesign of company website",
    "status": "active",
    "priority": "high",
    "progress": 75,
    "team_size": 5,
    "start_date": "2025-09-01",
    "due_date": "2025-11-30",
    "created_at": "2025-09-01T10:00:00Z",
    "updated_at": "2025-10-05T10:30:00Z",
    "tasks": [
      {
        "id": "01k5kzpfwd618xmwdwq3rej3jz",
        "title": "Design mockups",
        "status": "completed",
        "assignee": "John Doe"
      }
    ]
  }
}
```

#### **Update Project**
```http
PUT /projects/{id}
```

**Request Body:**
```json
{
  "name": "Updated Project Name",
  "status": "active",
  "progress": 50
}
```

#### **Delete Project**
```http
DELETE /projects/{id}
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": null
}
```

---

### **📋 Tasks API**

#### **List Tasks**
```http
GET /tasks?page=1&per_page=15&status=pending&project_id=01k5kzpfwd618xmwdwq3rej3jz
```

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15, max: 100)
- `status` (string): Filter by status (`pending`, `in_progress`, `on_hold`, `completed`, `cancelled`)
- `priority` (string): Filter by priority (`low`, `normal`, `medium`, `high`, `urgent`)
- `project_id` (string): Filter by project ID
- `assignee_id` (string): Filter by assignee ID
- `search` (string): Search in task title and description

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": [
    {
      "id": "01k5kzpfwd618xmwdwq3rej3jz",
      "title": "Design mockups",
      "description": "Create initial design mockups",
      "status": "in_progress",
      "priority": "high",
      "progress": 60,
      "project_id": "01k5kzpfwd618xmwdwq3rej3jz",
      "assignee_id": "01k5kzpfwd618xmwdwq3rej3jz",
      "due_date": "2025-10-15",
      "estimated_hours": 8,
      "created_at": "2025-10-01T10:00:00Z",
      "updated_at": "2025-10-05T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 50,
    "last_page": 4
  }
}
```

#### **Create Task**
```http
POST /tasks
```

**Request Body:**
```json
{
  "title": "New Task",
  "description": "Task description",
  "status": "pending",
  "priority": "medium",
  "project_id": "01k5kzpfwd618xmwdwq3rej3jz",
  "assignee_id": "01k5kzpfwd618xmwdwq3rej3jz",
  "due_date": "2025-10-20",
  "estimated_hours": 4,
  "progress": 0
}
```

---

### **📅 Calendar Events API**

#### **List Calendar Events**
```http
GET /calendar-events?start_date=2025-10-01&end_date=2025-10-31
```

**Query Parameters:**
- `start_date` (date): Start date filter (YYYY-MM-DD)
- `end_date` (date): End date filter (YYYY-MM-DD)
- `type` (string): Filter by event type (`meeting`, `deadline`, `milestone`, `reminder`)

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": [
    {
      "id": "01k5kzpfwd618xmwdwq3rej3jz",
      "title": "Project Review Meeting",
      "description": "Weekly project review",
      "start_date": "2025-10-10T14:00:00Z",
      "end_date": "2025-10-10T15:00:00Z",
      "type": "meeting",
      "project_id": "01k5kzpfwd618xmwdwq3rej3jz",
      "created_at": "2025-10-01T10:00:00Z",
      "updated_at": "2025-10-05T10:30:00Z"
    }
  ]
}
```

---

### **📄 Templates API**

#### **List Templates**
```http
GET /templates?category=project&type=standard
```

**Query Parameters:**
- `category` (string): Filter by category (`project`, `task`, `document`)
- `type` (string): Filter by type (`standard`, `custom`, `shared`)

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "data": [
    {
      "id": "01k5kzpfwd618xmwdwq3rej3jz",
      "name": "Website Development Template",
      "description": "Standard template for website development projects",
      "category": "project",
      "type": "standard",
      "content": {
        "phases": ["Planning", "Design", "Development", "Testing", "Launch"],
        "tasks": ["Create wireframes", "Design mockups", "Develop frontend", "Develop backend", "Test functionality"]
      },
      "is_active": true,
      "created_at": "2025-09-01T10:00:00Z",
      "updated_at": "2025-10-05T10:30:00Z"
    }
  ]
}
```

---

## 🚨 **ERROR RESPONSES**

### **Standard Error Format**
```json
{
  "success": false,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "error": {
    "id": "err_7f1a2b3c",
    "code": "E422.VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "fields": {
        "name": ["The name field is required"],
        "email": ["The email must be a valid email address"]
      }
    }
  },
  "retry_after": null
}
```

### **Error Codes**
| Code | HTTP Status | Description |
|------|-------------|-------------|
| `E400.VALIDATION_ERROR` | 400 | Validation failed |
| `E401.UNAUTHORIZED` | 401 | Authentication required |
| `E403.FORBIDDEN` | 403 | Access forbidden |
| `E404.NOT_FOUND` | 404 | Resource not found |
| `E409.CONFLICT` | 409 | Resource conflict |
| `E422.UNPROCESSABLE_ENTITY` | 422 | Business logic error |
| `E429.RATE_LIMITED` | 429 | Too many requests |
| `E500.INTERNAL_ERROR` | 500 | Server error |
| `E503.SERVICE_UNAVAILABLE` | 503 | Service down |

---

## 🔒 **AUTHENTICATION & AUTHORIZATION**

### **Token Authentication**
```bash
# Get token
curl -X POST https://api.zenamanage.com/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Use token
curl -X GET https://api.zenamanage.com/v1/projects \
  -H "Authorization: Bearer 1|abc123..."
```

### **Role-Based Access**
- **super_admin**: Full access to all endpoints
- **pm**: Access to projects, tasks, team, documents, templates, calendar, reports
- **member**: Access to assigned projects and tasks
- **client**: Read-only access to assigned projects

---

## 📊 **RATE LIMITING**

### **Limits**
- **API Default**: 60 requests per minute per user
- **API Strict**: 30 requests per minute per user
- **API Public**: 100 requests per minute per IP
- **Login**: 5 attempts per minute per IP

### **Rate Limit Headers**
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1633434000
```

### **Rate Limit Exceeded**
```json
{
  "success": false,
  "timestamp": "2025-10-05T10:30:00Z",
  "request_id": "req_7f1a2b3c",
  "error": {
    "id": "err_7f1a2b3c",
    "code": "E429.RATE_LIMITED",
    "message": "Too many requests",
    "details": null
  },
  "retry_after": 60
}
```

---

## 🛠️ **API TESTING**

### **Postman Collection**
Import the Postman collection from `docs/api/postman-collection.json` for easy API testing.

### **OpenAPI Visualization**
```bash
# Install redoc-cli
npm install -g redoc-cli

# Serve OpenAPI documentation
npx redoc-cli serve docs/api/openapi.json

# Open in browser: http://localhost:8080
```

### **cURL Examples**
```bash
# List projects
curl -X GET "https://api.zenamanage.com/v1/projects" \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json"

# Create project
curl -X POST "https://api.zenamanage.com/v1/projects" \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Project",
    "description": "Project description",
    "status": "planning",
    "priority": "medium"
  }'
```

---

## 🎯 **BEST PRACTICES**

### **1. Error Handling**
- Always check the `success` field in responses
- Handle different error codes appropriately
- Implement retry logic for 429/503 errors
- Log error IDs for debugging

### **2. Pagination**
- Use `page` and `per_page` parameters
- Check `meta` object for pagination info
- Implement infinite scroll or page navigation

### **3. Caching**
- Cache responses appropriately
- Use ETags for conditional requests
- Implement cache invalidation

### **4. Security**
- Store tokens securely
- Use HTTPS in production
- Implement proper error handling
- Validate all inputs

---

## 🚀 **NEXT STEPS**

1. **[📋 OpenAPI Specification](../api/openapi.json)** - Complete API spec
2. **[📮 Postman Collection](../api/postman-collection.json)** - Import for testing
3. **[💡 API Examples](../api/api-examples.md)** - More usage examples
4. **[🔒 Security Guide](security-guide.md)** - Security implementation

---

*This API reference provides comprehensive documentation for all ZenaManage API endpoints.*
