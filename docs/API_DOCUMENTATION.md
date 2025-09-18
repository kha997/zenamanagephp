# 📚 API DOCUMENTATION - DASHBOARD SYSTEM

## 📋 OVERVIEW

Comprehensive API documentation for the ZenaManage Dashboard System.

### 🎯 **Base URL**
```
https://your-domain.com/api/v1/dashboard
```

### 🔐 **Authentication**
```http
Authorization: Bearer {your-token}
```

---

## 🏗️ **CORE DASHBOARD APIs**

### 📊 **Dashboard Management**

#### `GET /dashboard`
Get user's dashboard configuration.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "dashboard-1",
    "name": "My Dashboard",
    "layout": [],
    "preferences": {"theme": "light"},
    "is_default": true
  }
}
```

#### `GET /dashboard/widgets`
Get available widgets.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "widget-1",
      "name": "Project Overview",
      "code": "project_overview",
      "type": "card",
      "category": "overview"
    }
  ]
}
```

---

## 🔧 **WIDGET MANAGEMENT APIs**

#### `POST /dashboard/widgets`
Add widget to dashboard.

**Request:**
```json
{
  "widget_id": "widget-1",
  "config": {
    "title": "Custom Widget",
    "size": "large"
  }
}
```

#### `PUT /dashboard/widgets/{id}/config`
Update widget configuration.

#### `DELETE /dashboard/widgets/{id}`
Remove widget from dashboard.

---

## 🎭 **ROLE-BASED APIs**

#### `GET /dashboard/role-based`
Get role-based dashboard.

**Response:**
```json
{
  "success": true,
  "data": {
    "dashboard": {},
    "widgets": [],
    "metrics": [],
    "alerts": [],
    "permissions": {},
    "role_config": {},
    "project_context": {}
  }
}
```

#### `GET /dashboard/role-based/widgets`
Get role-specific widgets.

#### `GET /dashboard/role-based/metrics`
Get role-specific metrics.

#### `GET /dashboard/role-based/alerts`
Get role-specific alerts.

#### `POST /dashboard/role-based/switch-project`
Switch project context.

---

## 🎨 **CUSTOMIZATION APIs**

#### `GET /dashboard/customization/`
Get customizable dashboard.

#### `POST /dashboard/customization/widgets`
Add widget via customization.

#### `PUT /dashboard/customization/layout`
Update layout.

#### `POST /dashboard/customization/apply-template`
Apply layout template.

#### `GET /dashboard/customization/export`
Export dashboard configuration.

#### `POST /dashboard/customization/import`
Import dashboard configuration.

---

## 🔄 **REAL-TIME APIs**

#### `GET /dashboard/sse`
Server-Sent Events for real-time updates.

#### `POST /dashboard/broadcast`
Broadcast update to user.

---

## ❌ **ERROR RESPONSES**

```json
{
  "success": false,
  "message": "Error description",
  "errors": {"field": ["error message"]},
  "error_code": "ERROR_CODE"
}
```

### 📋 **Error Codes**
- `VALIDATION_ERROR` (422)
- `UNAUTHORIZED` (401)
- `FORBIDDEN` (403)
- `NOT_FOUND` (404)
- `INTERNAL_ERROR` (500)

---

## 📝 **USAGE EXAMPLES**

### 🔧 **JavaScript Example**
```javascript
const fetchDashboard = async () => {
  const response = await fetch('/api/v1/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  return data.success ? data.data : null;
};
```

### 🐍 **Python Example**
```python
import requests

def fetch_dashboard(token):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get('/api/v1/dashboard', headers=headers)
    data = response.json()
    return data['data'] if data['success'] else None
```

---

## 🔒 **SECURITY**

- Bearer token authentication required
- Role-based access control (RBAC)
- Input validation and sanitization
- Rate limiting implemented
- HTTPS required in production

---

## 📈 **PERFORMANCE**

- Response time target: < 500ms
- Caching implemented
- Database query optimization
- Pagination for large datasets
- Compression enabled

---

## 📞 **SUPPORT**

- Documentation: This document
- Support: support@zenamanage.com
- Community: community.zenamanage.com