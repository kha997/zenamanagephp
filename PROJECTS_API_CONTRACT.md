# 📋 PROJECTS MODULE - API CONTRACT & DTO

## 🎯 OVERVIEW
API contract cho Projects module - rebuild with Smart Filters, Quick Actions, and unified structure.

---

## 🔌 ENDPOINTS

### 1. GET /api/v1/projects
**Purpose**: List all projects with smart filters and pagination

**Query Parameters**:
```json
{
  "q": "string",              // Search by name or description
  "status": "string",          // Filter by status (active, archived, completed)
  "owner_id": "string",        // Filter by owner (ulid)
  "sort_by": "string",         // Sort field (name, created_at, updated_at, progress)
  "sort_dir": "string",        // Sort direction (asc, desc)
  "page": "integer",           // Page number
  "per_page": "integer"        // Items per page (max 100)
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "data": {
    "data": [
      {
        "id": "01HXXXX...",
        "name": "ZenaManage Refactor",
        "description": "Rebuild the core modules for better performance.",
        "status": "active",
        "progress": 75,
        "owner": {
          "id": "01HWWWW...",
          "name": "Alex Doe",
          "email": "alex@example.com",
          "avatar_url": "https://example.com/avatar.png"
        },
        "team_members_count": 5,
        "open_tasks_count": 12,
        "completed_tasks_count": 36,
        "total_budget": 50000,
        "spent_budget": 37500,
        "created_at": "2023-11-18T10:00:00Z",
        "updated_at": "2023-11-18T12:30:00Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 10,
      "per_page": 15,
      "total": 150,
      "from": 1,
      "to": 15
    }
  }
}
```

**Error Response** (400 Bad Request):
```json
{
  "status": "error",
  "error": {
    "id": "validation-error",
    "message": "Invalid query parameters",
    "details": {
      "status": ["Status must be one of: active, archived, completed"],
      "sort_by": ["Sort field must be one of: name, created_at, updated_at, progress"]
    }
  }
}
```

---

### 2. POST /api/v1/projects
**Purpose**: Create new project

**Request Body**:
```json
{
  "name": "string (required)",
  "description": "string",
  "owner_id": "string (ulid, optional)",
  "status": "string (default: planning)",
  "start_date": "datetime (ISO 8601)",
  "end_date": "datetime (ISO 8601)",
  "budget": "decimal",
  "client_id": "string (ulid, optional)"
}
```

**Response** (201 Created):
```json
{
  "status": "success",
  "data": {
    "id": "01HYYYY...",
    "name": "New Project",
    "description": "Project description",
    "status": "planning",
    "owner": {
      "id": "01HWWWW...",
      "name": "Current User",
      "avatar_url": "https://..."
    },
    "created_at": "2023-11-20T10:00:00Z",
    "updated_at": "2023-11-20T10:00:00Z"
  }
}
```

---

### 3. GET /api/v1/projects/{id}
**Purpose**: Get project details

**Response** (200 OK):
```json
{
  "status": "success",
  "data": {
    "id": "01HXXXX...",
    "name": "Project Name",
    "description": "Full project description",
    "status": "active",
    "progress": 75,
    "owner": {...},
    "team_members": [...],
    "tasks": {
      "total": 48,
      "completed": 36,
      "in_progress": 8,
      "pending": 4
    },
    "budget": {...},
    "timeline": {...},
    "documents": {...},
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

### 4. PATCH /api/v1/projects/{id}
**Purpose**: Update project

**Request Body**: (same as POST, all fields optional)

**Response**: (200 OK) - updated project object

---

### 5. DELETE /api/v1/projects/{id}
**Purpose**: Delete (soft delete) project

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Project deleted successfully"
}
```

---

### 6. POST /api/v1/projects/{id}/archive
**Purpose**: Archive/unarchive project

**Response** (200 OK):
```json
{
  "status": "success",
  "data": {
    "id": "...",
    "status": "archived",
    "archived_at": "2023-11-20T10:00:00Z"
  }
}
```

---

## 📊 DATA TRANSFER OBJECTS (DTOs)

### ProjectDTO
```typescript
interface ProjectDTO {
  id: string;                    // ULID
  name: string;
  description: string | null;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'archived';
  progress: number;              // 0-100
  owner: UserDTO;
  team_members_count: number;
  open_tasks_count: number;
  completed_tasks_count: number;
  total_budget: number | null;
  spent_budget: number | null;
  client?: ClientDTO;
  start_date: string | null;     // ISO 8601
  end_date: string | null;       // ISO 8601
  created_at: string;           // ISO 8601
  updated_at: string;           // ISO 8601
}
```

### UserDTO
```typescript
interface UserDTO {
  id: string;
  name: string;
  email: string;
  avatar_url: string | null;
  role: string;
}
```

### ProjectMetaDTO (Pagination)
```typescript
interface ProjectMetaDTO {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}
```

---

## 🎯 SMART FILTERS MAPPING

### Filter Presets
```json
{
  "presets": [
    {
      "id": "my_projects",
      "name": "My Projects",
      "icon": "fas fa-user",
      "description": "Projects I own",
      "filters": {
        "owner_id": "current_user_id"
      }
    },
    {
      "id": "active_projects",
      "name": "Active Projects",
      "icon": "fas fa-play-circle",
      "description": "Currently active projects",
      "filters": {
        "status": "active"
      }
    },
    {
      "id": "completed_projects",
      "name": "Completed",
      "icon": "fas fa-check-circle",
      "description": "Completed projects",
      "filters": {
        "status": "completed"
      }
    }
  ]
}
```

### Deep Filters
```json
{
  "deep_filters": [
    {
      "key": "status",
      "label": "Status",
      "type": "select",
      "options": [
        {"value": "all", "label": "All Statuses"},
        {"value": "planning", "label": "Planning"},
        {"value": "active", "label": "Active"},
        {"value": "on_hold", "label": "On Hold"},
        {"value": "completed", "label": "Completed"},
        {"value": "archived", "label": "Archived"}
      ]
    },
    {
      "key": "progress",
      "label": "Progress",
      "type": "range",
      "min": 0,
      "max": 100,
      "step": 5
    },
    {
      "key": "created_at",
      "label": "Created Date",
      "type": "date_range"
    }
  ]
}
```

---

## ⚡ QUICK ACTIONS MAPPING

### Available Quick Actions
```typescript
interface QuickAction {
  id: string;
  label: string;
  icon: string;
  action: 'create' | 'archive' | 'delete' | 'export';
  endpoint: string;
  method: 'POST' | 'DELETE';
  confirm: boolean;
}

const quickActions: QuickAction[] = [
  {
    id: 'create_project',
    label: 'New Project',
    icon: 'fas fa-plus',
    action: 'create',
    endpoint: '/api/v1/projects',
    method: 'POST',
    confirm: false
  },
  {
    id: 'export_projects',
    label: 'Export Projects',
    icon: 'fas fa-download',
    action: 'export',
    endpoint: '/api/v1/projects/export',
    method: 'GET',
    confirm: false
  }
];
```

---

## 🧪 TESTING REQUIREMENTS

### Backend Tests
- [ ] Test GET /api/v1/projects with filters
- [ ] Test GET /api/v1/projects with pagination
- [ ] Test POST /api/v1/projects (create)
- [ ] Test PATCH /api/v1/projects/{id} (update)
- [ ] Test DELETE /api/v1/projects/{id} (soft delete)
- [ ] Test archive/unarchive functionality
- [ ] Test RBAC (only own projects)
- [ ] Test multi-tenant isolation

### Frontend Tests
- [ ] E2E: Load projects list
- [ ] E2E: Apply smart filters
- [ ] E2E: Create new project
- [ ] E2E: Edit project
- [ ] E2E: Archive project
- [ ] E2E: Search projects
- [ ] E2E: Pagination

---

## ✅ ACCEPTANCE CRITERIA

### Functional
- ✅ Can list projects with pagination
- ✅ Can filter by status, owner, search query
- ✅ Can sort by name, created_at, updated_at, progress
- ✅ Can create new project
- ✅ Can edit project details
- ✅ Can archive/unarchive project
- ✅ Can delete project (soft delete)
- ✅ Can export projects to CSV

### Non-Functional
- ✅ API response time < 300ms (p95)
- ✅ Supports 100+ projects per page
- ✅ Multi-tenant isolation enforced
- ✅ RBAC enforced (PM can see all, Member sees assigned)
- ✅ Audit log for all actions

---

**Status**: 📋 Ready for Implementation

