# 🔌 **ZENAMANAGE DASHBOARD SYSTEM - API DOCUMENTATION**

## **API Overview**

**Base URL:** `https://api.zenamanage.com/v1`  
**API Version:** 1.0.0  
**Authentication:** Bearer Token (Laravel Sanctum)  
**Rate Limiting:** 1000 requests per hour  
**Response Format:** JSON  

---

## 📋 **TABLE OF CONTENTS**

1. [Authentication](#authentication)
2. [Dashboard API](#dashboard-api)
3. [Widget API](#widget-api)
4. [User API](#user-api)
5. [Support API](#support-api)
6. [System API](#system-api)
7. [WebSocket API](#websocket-api)
8. [Error Handling](#error-handling)
9. [Rate Limiting](#rate-limiting)
10. [SDKs & Examples](#sdks--examples)

---

## 🔐 **AUTHENTICATION**

### **Getting Access Token**

#### **Login Endpoint**
```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "user"
        },
        "token": "1|abcdef1234567890",
        "expires_at": "2025-01-18T00:00:00Z"
    }
}
```

#### **Register Endpoint**
```http
POST /api/auth/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "user@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "user"
        },
        "token": "1|abcdef1234567890",
        "expires_at": "2025-01-18T00:00:00Z"
    }
}
```

### **Using Access Token**

Include the token in the Authorization header:

```http
Authorization: Bearer 1|abcdef1234567890
```

### **Token Refresh**

```http
POST /api/auth/refresh
Authorization: Bearer 1|abcdef1234567890
```

---

## 📊 **DASHBOARD API**

### **Dashboard Endpoints**

#### **Get All Dashboards**
```http
GET /api/dashboards
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)
- `search` (optional): Search term
- `category` (optional): Filter by category
- `sort_by` (optional): Sort field (name, created_at, updated_at)
- `sort_order` (optional): Sort direction (asc, desc)

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Sales Dashboard",
            "description": "Monthly sales overview",
            "layout": "grid",
            "is_public": false,
            "user_id": 1,
            "created_at": "2025-01-17T10:00:00Z",
            "updated_at": "2025-01-17T10:00:00Z",
            "widgets_count": 5
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 1,
        "last_page": 1
    }
}
```

#### **Get Single Dashboard**
```http
GET /api/dashboards/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Sales Dashboard",
        "description": "Monthly sales overview",
        "layout": "grid",
        "is_public": false,
        "user_id": 1,
        "created_at": "2025-01-17T10:00:00Z",
        "updated_at": "2025-01-17T10:00:00Z",
        "widgets": [
            {
                "id": 1,
                "type": "chart",
                "title": "Sales Chart",
                "config": {
                    "chart_type": "line",
                    "data_source": "sales_data"
                },
                "position": {
                    "x": 0,
                    "y": 0,
                    "w": 6,
                    "h": 4
                }
            }
        ]
    }
}
```

#### **Create Dashboard**
```http
POST /api/dashboards
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "New Dashboard",
    "description": "Dashboard description",
    "layout": "grid",
    "is_public": false
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "name": "New Dashboard",
        "description": "Dashboard description",
        "layout": "grid",
        "is_public": false,
        "user_id": 1,
        "created_at": "2025-01-17T10:00:00Z",
        "updated_at": "2025-01-17T10:00:00Z"
    }
}
```

#### **Update Dashboard**
```http
PUT /api/dashboards/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated Dashboard",
    "description": "Updated description"
}
```

#### **Delete Dashboard**
```http
DELETE /api/dashboards/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "message": "Dashboard deleted successfully"
}
```

---

## 🧩 **WIDGET API**

### **Widget Endpoints**

#### **Get All Widgets**
```http
GET /api/widgets
Authorization: Bearer {token}
```

**Query Parameters:**
- `dashboard_id` (optional): Filter by dashboard
- `type` (optional): Filter by widget type
- `page` (optional): Page number
- `per_page` (optional): Items per page

#### **Get Single Widget**
```http
GET /api/widgets/{id}
Authorization: Bearer {token}
```

#### **Create Widget**
```http
POST /api/widgets
Authorization: Bearer {token}
Content-Type: application/json

{
    "dashboard_id": 1,
    "type": "chart",
    "title": "Sales Chart",
    "config": {
        "chart_type": "line",
        "data_source": "sales_data"
    },
    "position": {
        "x": 0,
        "y": 0,
        "w": 6,
        "h": 4
    }
}
```

#### **Update Widget**
```http
PUT /api/widgets/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Updated Chart",
    "config": {
        "chart_type": "bar"
    }
}
```

#### **Delete Widget**
```http
DELETE /api/widgets/{id}
Authorization: Bearer {token}
```

### **Widget Types**

#### **Chart Widget**
```json
{
    "type": "chart",
    "config": {
        "chart_type": "line|bar|pie|area",
        "data_source": "api_url|database|static",
        "x_axis": "field_name",
        "y_axis": "field_name",
        "colors": ["#ff0000", "#00ff00"],
        "animation": true
    }
}
```

#### **Data Table Widget**
```json
{
    "type": "table",
    "config": {
        "data_source": "api_url|database|static",
        "columns": [
            {"field": "name", "title": "Name"},
            {"field": "value", "title": "Value"}
        ],
        "pagination": true,
        "search": true,
        "sorting": true
    }
}
```

#### **Metric Widget**
```json
{
    "type": "metric",
    "config": {
        "data_source": "api_url|database|static",
        "value_field": "total_sales",
        "format": "currency|number|percentage",
        "trend": true,
        "comparison_period": "previous_month"
    }
}
```

---

## 👤 **USER API**

### **User Endpoints**

#### **Get User Profile**
```http
GET /api/user/profile
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "role": "user",
        "avatar": "https://example.com/avatar.jpg",
        "created_at": "2025-01-17T10:00:00Z",
        "updated_at": "2025-01-17T10:00:00Z"
    }
}
```

#### **Update User Profile**
```http
PUT /api/user/profile
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "John Smith",
    "avatar": "base64_encoded_image"
}
```

#### **Change Password**
```http
POST /api/user/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
    "current_password": "oldpassword",
    "new_password": "newpassword",
    "new_password_confirmation": "newpassword"
}
```

#### **Get User Dashboards**
```http
GET /api/user/dashboards
Authorization: Bearer {token}
```

#### **Get User Statistics**
```http
GET /api/user/statistics
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "dashboards_count": 5,
        "widgets_count": 25,
        "total_views": 150,
        "last_login": "2025-01-17T10:00:00Z"
    }
}
```

---

## 🎫 **SUPPORT API**

### **Support Ticket Endpoints**

#### **Get Support Tickets**
```http
GET /api/support/tickets
Authorization: Bearer {token}
```

#### **Create Support Ticket**
```http
POST /api/support/tickets
Authorization: Bearer {token}
Content-Type: application/json

{
    "subject": "Need help with dashboard",
    "description": "I'm having trouble creating a new dashboard",
    "category": "technical",
    "priority": "medium"
}
```

#### **Get Support Ticket**
```http
GET /api/support/tickets/{id}
Authorization: Bearer {token}
```

#### **Add Message to Ticket**
```http
POST /api/support/tickets/{id}/messages
Authorization: Bearer {token}
Content-Type: application/json

{
    "message": "Additional information about the issue",
    "is_internal": false
}
```

### **Documentation Endpoints**

#### **Get Documentation**
```http
GET /api/support/documentation
Authorization: Bearer {token}
```

#### **Search Documentation**
```http
GET /api/support/documentation/search?q=search_term
Authorization: Bearer {token}
```

---

## 🔧 **SYSTEM API**

### **System Health Endpoints**

#### **Get System Health**
```http
GET /api/health
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "overall_status": "healthy",
        "timestamp": "2025-01-17T10:00:00Z",
        "services": {
            "database": {
                "status": "healthy",
                "response_time_ms": 15
            },
            "redis": {
                "status": "healthy",
                "response_time_ms": 2
            }
        },
        "metrics": {
            "memory": {
                "usage_percentage": 45.2
            },
            "cpu": {
                "load_average_1min": 0.5
            }
        }
    }
}
```

#### **Get Performance Metrics**
```http
GET /api/health/performance
Authorization: Bearer {token}
```

### **Maintenance Endpoints**

#### **Get Maintenance Status**
```http
GET /api/maintenance/status
Authorization: Bearer {token}
```

#### **Run Maintenance Task**
```http
POST /api/maintenance/run
Authorization: Bearer {token}
Content-Type: application/json

{
    "task": "cache_clear|database_optimize|log_cleanup"
}
```

---

## 🔌 **WEBSOCKET API**

### **WebSocket Connection**

#### **Authentication**
```javascript
// Get WebSocket token
const response = await fetch('/api/websocket/auth', {
    headers: {
        'Authorization': 'Bearer ' + token
    }
});
const { token: wsToken, socket_id, channel } = await response.json();

// Connect to WebSocket
const socket = new WebSocket(`wss://ws.zenamanage.com:6001?token=${wsToken}`);
```

### **WebSocket Events**

#### **Dashboard Updates**
```javascript
socket.on('dashboard.updated', (data) => {
    console.log('Dashboard updated:', data);
    // Update dashboard in UI
});

socket.on('dashboard.deleted', (data) => {
    console.log('Dashboard deleted:', data);
    // Remove dashboard from UI
});
```

#### **Widget Updates**
```javascript
socket.on('widget.updated', (data) => {
    console.log('Widget updated:', data);
    // Update widget in UI
});

socket.on('widget.deleted', (data) => {
    console.log('Widget deleted:', data);
    // Remove widget from UI
});
```

#### **Real-time Data**
```javascript
socket.on('data.updated', (data) => {
    console.log('Data updated:', data);
    // Update widget data
});
```

#### **User Presence**
```javascript
socket.on('user.joined', (data) => {
    console.log('User joined:', data);
    // Show user presence indicator
});

socket.on('user.left', (data) => {
    console.log('User left:', data);
    // Hide user presence indicator
});
```

---

## ❌ **ERROR HANDLING**

### **Error Response Format**

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "name": ["The name field is required."],
            "email": ["The email field must be a valid email address."]
        }
    }
}
```

### **HTTP Status Codes**

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

### **Error Codes**

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Input validation failed |
| `AUTHENTICATION_ERROR` | Authentication failed |
| `AUTHORIZATION_ERROR` | Insufficient permissions |
| `NOT_FOUND` | Resource not found |
| `RATE_LIMIT_EXCEEDED` | Rate limit exceeded |
| `INTERNAL_ERROR` | Internal server error |

---

## ⚡ **RATE LIMITING**

### **Rate Limits**

| Endpoint | Limit |
|----------|-------|
| Authentication | 5 requests per minute |
| API Endpoints | 1000 requests per hour |
| File Upload | 20 requests per minute |
| WebSocket | 100 connections per user |

### **Rate Limit Headers**

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

### **Rate Limit Exceeded Response**

```json
{
    "success": false,
    "error": {
        "code": "RATE_LIMIT_EXCEEDED",
        "message": "Too many requests. Please try again later.",
        "retry_after": 3600
    }
}
```

---

## 💻 **SDKS & EXAMPLES**

### **JavaScript SDK**

```javascript
import { ZenaManageAPI } from 'zenamanage-sdk';

const api = new ZenaManageAPI({
    baseURL: 'https://api.zenamanage.com/v1',
    token: 'your-access-token'
});

// Get dashboards
const dashboards = await api.dashboards.list();

// Create dashboard
const dashboard = await api.dashboards.create({
    name: 'My Dashboard',
    description: 'Dashboard description',
    layout: 'grid'
});

// Update dashboard
await api.dashboards.update(dashboard.id, {
    name: 'Updated Dashboard'
});

// Delete dashboard
await api.dashboards.delete(dashboard.id);
```

### **PHP SDK**

```php
use ZenaManage\ZenaManageAPI;

$api = new ZenaManageAPI([
    'base_url' => 'https://api.zenamanage.com/v1',
    'token' => 'your-access-token'
]);

// Get dashboards
$dashboards = $api->dashboards()->list();

// Create dashboard
$dashboard = $api->dashboards()->create([
    'name' => 'My Dashboard',
    'description' => 'Dashboard description',
    'layout' => 'grid'
]);

// Update dashboard
$api->dashboards()->update($dashboard['id'], [
    'name' => 'Updated Dashboard'
]);

// Delete dashboard
$api->dashboards()->delete($dashboard['id']);
```

### **Python SDK**

```python
from zenamanage import ZenaManageAPI

api = ZenaManageAPI(
    base_url='https://api.zenamanage.com/v1',
    token='your-access-token'
)

# Get dashboards
dashboards = api.dashboards.list()

# Create dashboard
dashboard = api.dashboards.create({
    'name': 'My Dashboard',
    'description': 'Dashboard description',
    'layout': 'grid'
})

# Update dashboard
api.dashboards.update(dashboard['id'], {
    'name': 'Updated Dashboard'
})

# Delete dashboard
api.dashboards.delete(dashboard['id'])
```

### **cURL Examples**

#### **Create Dashboard**
```bash
curl -X POST https://api.zenamanage.com/v1/dashboards \
  -H "Authorization: Bearer your-access-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Dashboard",
    "description": "Dashboard description",
    "layout": "grid"
  }'
```

#### **Get Dashboards**
```bash
curl -X GET https://api.zenamanage.com/v1/dashboards \
  -H "Authorization: Bearer your-access-token"
```

#### **Update Dashboard**
```bash
curl -X PUT https://api.zenamanage.com/v1/dashboards/1 \
  -H "Authorization: Bearer your-access-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Dashboard"
  }'
```

#### **Delete Dashboard**
```bash
curl -X DELETE https://api.zenamanage.com/v1/dashboards/1 \
  -H "Authorization: Bearer your-access-token"
```

---

## 📝 **POSTMAN COLLECTION**

A complete Postman collection is available for testing the API:

**Download:** [ZenaManage API Collection](https://api.zenamanage.com/postman/collection.json)

**Environment Variables:**
- `base_url`: https://api.zenamanage.com/v1
- `token`: Your access token
- `user_id`: Your user ID

---

*API Documentation generated on: January 17, 2025*  
*Version: 1.0.0*  
*Last Updated: January 17, 2025*
