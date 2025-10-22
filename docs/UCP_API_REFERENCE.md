# UCP API Reference

## Overview

This document provides a comprehensive reference for all UCP (Universal Component Protocol) APIs, interfaces, and data structures used in the ZenaManage system.

## Core Interfaces

### UCPRequest<T>

```typescript
interface UCPRequest<T> {
  id: string;           // Unique request identifier
  type: string;         // Request type identifier
  payload: T;           // Request payload data
  timestamp: number;    // Unix timestamp
  version: string;      // UCP version
  metadata?: {          // Optional metadata
    tenantId?: string;
    userId?: string;
    correlationId?: string;
  };
}
```

### UCPResponse<T>

```typescript
interface UCPResponse<T> {
  id: string;           // Response identifier (matches request)
  success: boolean;     // Success status
  data?: T;            // Response data (if successful)
  error?: UCPError;    // Error information (if failed)
  timestamp: number;   // Unix timestamp
  metadata?: {          // Optional metadata
    processingTime?: number;
    cacheHit?: boolean;
  };
}
```

### UCPEvent<T>

```typescript
interface UCPEvent<T> {
  id: string;           // Unique event identifier
  type: string;         // Event type identifier
  payload: T;           // Event payload data
  timestamp: number;    // Unix timestamp
  source: string;       // Event source component
  version: string;      // UCP version
  metadata?: {          // Optional metadata
    tenantId?: string;
    userId?: string;
    correlationId?: string;
  };
}
```

### UCPError

```typescript
interface UCPError {
  code: string;                    // Error code
  message: string;                 // Human-readable message
  details?: Record<string, any>;  // Additional error details
  stack?: string;                 // Stack trace (development only)
  timestamp: number;              // Unix timestamp
  metadata?: {                    // Optional metadata
    requestId?: string;
    componentId?: string;
  };
}
```

## Component Interfaces

### Task Component

```typescript
interface TaskComponentProps {
  taskId: string;
  onUpdate: (task: Task) => void;
  onError: (error: UCPError) => void;
  onStatusChange?: (status: TaskStatus) => void;
  onAssigneeChange?: (assigneeId: string) => void;
}

interface Task {
  id: string;
  title: string;
  description: string;
  status: TaskStatus;
  priority: TaskPriority;
  assigneeId?: string;
  projectId: string;
  createdAt: string;
  updatedAt: string;
  dueDate?: string;
  tags?: string[];
}

type TaskStatus = 'backlog' | 'in_progress' | 'blocked' | 'done' | 'cancelled';
type TaskPriority = 'low' | 'normal' | 'high' | 'urgent';
```

### Project Component

```typescript
interface ProjectComponentProps {
  projectId: string;
  onUpdate: (project: Project) => void;
  onError: (error: UCPError) => void;
  onTaskCreate?: (task: Task) => void;
  onTaskUpdate?: (task: Task) => void;
}

interface Project {
  id: string;
  name: string;
  description: string;
  status: ProjectStatus;
  priority: ProjectPriority;
  ownerId: string;
  teamIds: string[];
  clientId?: string;
  budget?: number;
  startDate: string;
  endDate?: string;
  createdAt: string;
  updatedAt: string;
}

type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
type ProjectPriority = 'low' | 'normal' | 'high' | 'urgent';
```

### User Component

```typescript
interface UserComponentProps {
  userId: string;
  onUpdate: (user: User) => void;
  onError: (error: UCPError) => void;
  onRoleChange?: (role: UserRole) => void;
}

interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  status: UserStatus;
  tenantId: string;
  createdAt: string;
  updatedAt: string;
  lastLoginAt?: string;
  preferences?: UserPreferences;
}

type UserRole = 'super_admin' | 'admin' | 'pm' | 'member' | 'client';
type UserStatus = 'active' | 'inactive' | 'suspended';

interface UserPreferences {
  theme: 'light' | 'dark' | 'auto';
  language: 'en' | 'vi';
  timezone: string;
  notifications: NotificationSettings;
}

interface NotificationSettings {
  email: boolean;
  inApp: boolean;
  sms: boolean;
  types: string[];
}
```

## API Endpoints

### Task API

```typescript
// Get task by ID
GET /api/tasks/{taskId}
Response: UCPResponse<Task>

// Create new task
POST /api/tasks
Request: UCPRequest<CreateTaskRequest>
Response: UCPResponse<Task>

// Update task
PUT /api/tasks/{taskId}
Request: UCPRequest<UpdateTaskRequest>
Response: UCPResponse<Task>

// Delete task
DELETE /api/tasks/{taskId}
Response: UCPResponse<void>

// Get tasks by project
GET /api/tasks/project/{projectId}
Response: UCPResponse<Task[]>

// Bulk update tasks
POST /api/tasks/bulk-update
Request: UCPRequest<BulkUpdateTasksRequest>
Response: UCPResponse<BulkUpdateResult>
```

### Project API

```typescript
// Get project by ID
GET /api/projects/{projectId}
Response: UCPResponse<Project>

// Create new project
POST /api/projects
Request: UCPRequest<CreateProjectRequest>
Response: UCPResponse<Project>

// Update project
PUT /api/projects/{projectId}
Request: UCPRequest<UpdateProjectRequest>
Response: UCPResponse<Project>

// Delete project
DELETE /api/projects/{projectId}
Response: UCPResponse<void>

// Get projects list
GET /api/projects
Query: { status?, priority?, clientId?, page?, limit? }
Response: UCPResponse<PaginatedResponse<Project>>
```

### User API

```typescript
// Get user by ID
GET /api/users/{userId}
Response: UCPResponse<User>

// Create new user
POST /api/users
Request: UCPRequest<CreateUserRequest>
Response: UCPResponse<User>

// Update user
PUT /api/users/{userId}
Request: UCPRequest<UpdateUserRequest>
Response: UCPResponse<User>

// Delete user
DELETE /api/users/{userId}
Response: UCPResponse<void>

// Get users list
GET /api/users
Query: { role?, status?, tenantId?, page?, limit? }
Response: UCPResponse<PaginatedResponse<User>>
```

## Request/Response Types

### CreateTaskRequest

```typescript
interface CreateTaskRequest {
  title: string;
  description?: string;
  projectId: string;
  assigneeId?: string;
  priority: TaskPriority;
  dueDate?: string;
  tags?: string[];
}
```

### UpdateTaskRequest

```typescript
interface UpdateTaskRequest {
  title?: string;
  description?: string;
  status?: TaskStatus;
  priority?: TaskPriority;
  assigneeId?: string;
  dueDate?: string;
  tags?: string[];
}
```

### BulkUpdateTasksRequest

```typescript
interface BulkUpdateTasksRequest {
  taskIds: string[];
  updates: Partial<UpdateTaskRequest>;
}
```

### CreateProjectRequest

```typescript
interface CreateProjectRequest {
  name: string;
  description?: string;
  ownerId: string;
  teamIds: string[];
  clientId?: string;
  budget?: number;
  startDate: string;
  endDate?: string;
  priority: ProjectPriority;
}
```

### UpdateProjectRequest

```typescript
interface UpdateProjectRequest {
  name?: string;
  description?: string;
  status?: ProjectStatus;
  priority?: ProjectPriority;
  teamIds?: string[];
  clientId?: string;
  budget?: number;
  endDate?: string;
}
```

### CreateUserRequest

```typescript
interface CreateUserRequest {
  name: string;
  email: string;
  role: UserRole;
  tenantId: string;
  password: string;
}
```

### UpdateUserRequest

```typescript
interface UpdateUserRequest {
  name?: string;
  email?: string;
  role?: UserRole;
  status?: UserStatus;
  preferences?: UserPreferences;
}
```

## Utility Types

### PaginatedResponse<T>

```typescript
interface PaginatedResponse<T> {
  data: T[];
  pagination: {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
    hasMore: boolean;
  };
}
```

### BulkUpdateResult

```typescript
interface BulkUpdateResult {
  updated: number;
  failed: number;
  errors: Array<{
    id: string;
    error: UCPError;
  }>;
}
```

## Error Codes

### Task Errors

- `TASK_NOT_FOUND`: Task with specified ID not found
- `TASK_UPDATE_FAILED`: Failed to update task
- `TASK_DELETE_FAILED`: Failed to delete task
- `TASK_CREATE_FAILED`: Failed to create task
- `TASK_VALIDATION_ERROR`: Task validation failed
- `TASK_PERMISSION_DENIED`: Insufficient permissions for task operation

### Project Errors

- `PROJECT_NOT_FOUND`: Project with specified ID not found
- `PROJECT_UPDATE_FAILED`: Failed to update project
- `PROJECT_DELETE_FAILED`: Failed to delete project
- `PROJECT_CREATE_FAILED`: Failed to create project
- `PROJECT_VALIDATION_ERROR`: Project validation failed
- `PROJECT_PERMISSION_DENIED`: Insufficient permissions for project operation

### User Errors

- `USER_NOT_FOUND`: User with specified ID not found
- `USER_UPDATE_FAILED`: Failed to update user
- `USER_DELETE_FAILED`: Failed to delete user
- `USER_CREATE_FAILED`: Failed to create user
- `USER_VALIDATION_ERROR`: User validation failed
- `USER_PERMISSION_DENIED`: Insufficient permissions for user operation

### System Errors

- `SYSTEM_ERROR`: Internal system error
- `VALIDATION_ERROR`: Input validation failed
- `AUTHENTICATION_ERROR`: Authentication failed
- `AUTHORIZATION_ERROR`: Authorization failed
- `RATE_LIMIT_EXCEEDED`: Rate limit exceeded
- `SERVICE_UNAVAILABLE`: Service temporarily unavailable

## Event Types

### Task Events

```typescript
// Task created
interface TaskCreatedEvent {
  type: 'task.created';
  payload: {
    task: Task;
    projectId: string;
    userId: string;
  };
}

// Task updated
interface TaskUpdatedEvent {
  type: 'task.updated';
  payload: {
    task: Task;
    changes: Partial<Task>;
    userId: string;
  };
}

// Task status changed
interface TaskStatusChangedEvent {
  type: 'task.status_changed';
  payload: {
    task: Task;
    oldStatus: TaskStatus;
    newStatus: TaskStatus;
    userId: string;
  };
}
```

### Project Events

```typescript
// Project created
interface ProjectCreatedEvent {
  type: 'project.created';
  payload: {
    project: Project;
    userId: string;
  };
}

// Project updated
interface ProjectUpdatedEvent {
  type: 'project.updated';
  payload: {
    project: Project;
    changes: Partial<Project>;
    userId: string;
  };
}
```

### User Events

```typescript
// User created
interface UserCreatedEvent {
  type: 'user.created';
  payload: {
    user: User;
    createdBy: string;
  };
}

// User updated
interface UserUpdatedEvent {
  type: 'user.updated';
  payload: {
    user: User;
    changes: Partial<User>;
    updatedBy: string;
  };
}
```

## Constants

### UCP Versions

```typescript
const UCP_VERSIONS = {
  CURRENT: '1.0.0',
  SUPPORTED: ['1.0.0'],
  DEPRECATED: []
} as const;
```

### Component Types

```typescript
const COMPONENT_TYPES = {
  TASK: 'task',
  PROJECT: 'project',
  USER: 'user',
  DASHBOARD: 'dashboard',
  NOTIFICATION: 'notification'
} as const;
```

### Event Types

```typescript
const EVENT_TYPES = {
  CREATED: 'created',
  UPDATED: 'updated',
  DELETED: 'deleted',
  STATUS_CHANGED: 'status_changed',
  ASSIGNED: 'assigned',
  UNASSIGNED: 'unassigned'
} as const;
```

---

*This API reference is part of the UCP documentation suite. For implementation details, see UCP_IMPLEMENTATION_GUIDE.md.*
