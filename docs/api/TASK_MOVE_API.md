# Task Move API Documentation

## Overview

The Task Move API endpoint allows you to move tasks between status columns in a Kanban board with atomic status and position updates. This endpoint supports optimistic locking to prevent concurrent modification conflicts and includes comprehensive validation for status transitions.

**Version**: 1.0  
**Last Updated**: 2025-11-14  
**Base URL**: `/api/v1/app/tasks/{task}/move`

---

## Endpoint

### Move Task

```http
PATCH /api/v1/app/tasks/{task}/move
```

Moves a task to a new status column and position within that column.

#### Authentication

Requires authentication via Laravel Sanctum:
```http
Authorization: Bearer {token}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `task` | string (ULID) | Yes | The ID of the task to move |

#### Request Body

```json
{
  "to_status": "in_progress",
  "before_id": "01k5kzpfwd618xmwdwq3rej3jz",
  "after_id": null,
  "reason": "Starting work on this task",
  "version": 1
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to_status` | string | Yes | Target status: `backlog`, `in_progress`, `blocked`, `done`, `canceled` |
| `before_id` | string (ULID) | No | ID of task to place this task before (for positioning) |
| `after_id` | string (ULID) | No | ID of task to place this task after (for positioning) |
| `reason` | string | Conditional | Required when moving to `blocked` or `canceled` status (max 500 chars) |
| `version` | integer | No | Current version of task for optimistic locking (prevents conflicts) |

**Note**: Provide either `before_id` or `after_id` for positioning, but not both. If neither is provided, the task will be placed at the end of the target column.

#### Valid Status Values

- `backlog` - Task is in backlog
- `in_progress` - Task is currently being worked on
- `blocked` - Task is blocked (requires reason)
- `done` - Task is completed
- `canceled` - Task is canceled (requires reason)

---

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": "01k5kzpfwd618xmwdwq3rej3jz",
    "title": "Implement user authentication",
    "status": "in_progress",
    "progress_percent": 0.0,
    "order": 1500000.0,
    "version": 2,
    "project_id": "01k5kzpfwd618xmwdwq3rej3jz",
    "created_at": "2025-11-14T10:00:00Z",
    "updated_at": "2025-11-14T10:30:00Z"
  },
  "message": "Task moved successfully",
  "warning": null
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always `true` for successful responses |
| `data` | object | Updated task object with new status, position, and version |
| `message` | string | Success message |
| `warning` | string\|null | Optional warning message (e.g., when task has active dependents) |

### Warning Response (200 OK with Warning)

When a move is successful but has warnings:

```json
{
  "success": true,
  "data": { /* task data */ },
  "message": "Task moved successfully",
  "warning": "Task has 2 active dependent task(s). Canceling this task may affect their progress."
}
```

---

## Error Responses

### 400 Bad Request - Validation Error

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Validation failed",
    "status": 400,
    "timestamp": "2025-11-14T10:30:00Z",
    "details": {
      "to_status": ["The to status field is required."],
      "reason": ["Reason is required when moving task to 'blocked' status."]
    }
  }
}
```

### 403 Forbidden - Access Denied

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Access denied: Task belongs to different tenant",
    "status": 403,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

### 409 Conflict - Optimistic Locking Conflict

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Task has been modified by another user. Please refresh and try again.",
    "code": "CONFLICT",
    "status": 409,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

**When this occurs:**
- Another user has modified the task since you last fetched it
- The `version` field you sent doesn't match the current version in the database
- **Action**: Refresh the task data and retry the move operation

### 422 Unprocessable Entity - Business Rule Violation

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Cannot transition from 'backlog' to 'done'. Allowed transitions from 'backlog': in_progress, canceled",
    "status": 422,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

**Common 422 Errors:**

1. **Invalid Status Transition**
   - Message: "Cannot transition from '{from}' to '{to}'. Allowed transitions: ..."
   - Cause: The requested status transition is not allowed by business rules

2. **Dependencies Not Met**
   - Message: "Cannot start task: one or more dependencies are not completed. All dependent tasks must be in 'done' status before starting this task."
   - Cause: Attempting to move to `in_progress` when dependencies are not done

3. **Project Status Restriction**
   - Message: "Cannot perform this operation when project is in 'archived' status. Project must be in 'planning' or 'active' status for most task operations."
   - Cause: Project status doesn't allow task modifications

4. **Missing Reason**
   - Message: "Reason is required when moving task to 'blocked' status."
   - Cause: Moving to `blocked` or `canceled` without providing a reason

### 500 Internal Server Error

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Failed to move task: Database connection error",
    "status": 500,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

---

## Examples

### Example 1: Move Task to In Progress

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "in_progress",
  "version": 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "01k5kzpfwd618xmwdwq3rej3jz",
    "status": "in_progress",
    "progress_percent": 0.0,
    "version": 2
  },
  "message": "Task moved successfully"
}
```

### Example 2: Move Task with Positioning

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "in_progress",
  "after_id": "01k5kzpfwd618xmwdwq3rej3j4",
  "version": 2
}
```

This places the task after the task with ID `01k5kzpfwd618xmwdwq3rej3j4` in the `in_progress` column.

### Example 3: Block Task (Requires Reason)

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "blocked",
  "reason": "Waiting for client approval on design mockups",
  "version": 3
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "01k5kzpfwd618xmwdwq3rej3jz",
    "status": "blocked",
    "version": 4
  },
  "message": "Task moved successfully"
}
```

### Example 4: Cancel Task (Requires Reason)

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "canceled",
  "reason": "Client requested feature cancellation",
  "version": 5
}
```

### Example 5: Optimistic Locking Conflict

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "done",
  "version": 1
}
```

**Response (409 Conflict):**
```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Task has been modified by another user. Please refresh and try again.",
    "code": "CONFLICT",
    "status": 409,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

**Client Action:**
1. Fetch the latest task data (GET `/api/v1/app/tasks/{id}`)
2. Get the new `version` value
3. Retry the move operation with the updated `version`

### Example 6: Invalid Transition

**Request:**
```http
PATCH /api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move
Authorization: Bearer 1|abc123...
Content-Type: application/json

{
  "to_status": "done",
  "version": 2
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "message": "Cannot transition from 'backlog' to 'done'. Allowed transitions from 'backlog': in_progress, canceled",
    "status": 422,
    "timestamp": "2025-11-14T10:30:00Z"
  }
}
```

---

## Positioning Strategy

The endpoint uses a **midpoint strategy** for efficient task ordering:

1. **Between Two Tasks**: If `before_id` and `after_id` are both provided (or one is inferred), the new position is calculated as: `(before.position + after.position) / 2`

2. **At Start**: If only `after_id` is provided and it's the first task, position = `after.position - 1000000`

3. **At End**: If only `before_id` is provided and it's the last task, position = `before.position + 1000000`

4. **Default**: If no positioning is specified, task is placed at the end: `max(existing_positions) + 1000000`

This strategy minimizes the need for reindexing tasks when reordering.

---

## Optimistic Locking

The endpoint implements optimistic locking using the `version` field:

1. **Client sends version**: Include the current `version` value when making the move request
2. **Server validates**: Server checks if the task's current version matches the provided version
3. **Conflict detection**: If versions don't match, returns 409 Conflict
4. **Version increment**: On successful move, the task's version is incremented

**Best Practice:**
- Always include the `version` field in move requests
- Handle 409 responses by refreshing task data and retrying
- Display user-friendly messages when conflicts occur

---

## Status Transition Rules

See [Business Rules Documentation](../TASK_STATUS_BUSINESS_RULES.md) for complete transition matrix and business rules.

**Quick Reference:**

| From Status | Allowed To Status |
|-------------|-------------------|
| `backlog` | `in_progress`, `canceled` |
| `in_progress` | `done`, `blocked`, `canceled`, `backlog` |
| `blocked` | `in_progress`, `canceled` |
| `done` | `in_progress` (reopen) |
| `canceled` | `backlog` (reactivate) |

---

## Rate Limiting

This endpoint is subject to standard API rate limiting:
- **Authenticated users**: 60 requests per minute
- **Rate limit headers**: Included in all responses

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1634567890
```

---

## Related Endpoints

- `GET /api/v1/app/tasks/{id}` - Get task details (includes current version)
- `PUT /api/v1/app/tasks/{id}` - Update task (general updates, not for Kanban moves)
- `GET /api/v1/app/tasks` - List tasks with filtering

---

## Client Implementation Examples

### JavaScript/TypeScript (Axios)

```typescript
import axios from 'axios';

async function moveTask(
  taskId: string,
  toStatus: string,
  options?: {
    beforeId?: string;
    afterId?: string;
    reason?: string;
    version?: number;
  }
) {
  try {
    const response = await axios.patch(
      `/api/v1/app/tasks/${taskId}/move`,
      {
        to_status: toStatus,
        before_id: options?.beforeId,
        after_id: options?.afterId,
        reason: options?.reason,
        version: options?.version,
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      }
    );
    
    return response.data;
  } catch (error) {
    if (error.response?.status === 409) {
      // Handle optimistic locking conflict
      console.error('Task was modified by another user');
      // Refresh task and retry
    } else if (error.response?.status === 422) {
      // Handle validation error
      console.error('Invalid transition:', error.response.data.error.message);
    }
    throw error;
  }
}
```

### PHP (Guzzle)

```php
use GuzzleHttp\Client;

function moveTask($taskId, $toStatus, $options = []) {
    $client = new Client(['base_uri' => 'https://api.zenamanage.com']);
    
    try {
        $response = $client->patch("/api/v1/app/tasks/{$taskId}/move", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'to_status' => $toStatus,
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'reason' => $options['reason'] ?? null,
                'version' => $options['version'] ?? null,
            ],
        ]);
        
        return json_decode($response->getBody(), true);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $statusCode = $e->getResponse()->getStatusCode();
        $error = json_decode($e->getResponse()->getBody(), true);
        
        if ($statusCode === 409) {
            // Handle optimistic locking conflict
            error_log('Task was modified by another user');
        } elseif ($statusCode === 422) {
            // Handle validation error
            error_log('Invalid transition: ' . $error['error']['message']);
        }
        
        throw $e;
    }
}
```

---

## Testing

### cURL Examples

**Move task to in_progress:**
```bash
curl -X PATCH "https://api.zenamanage.com/api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move" \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "to_status": "in_progress",
    "version": 1
  }'
```

**Block task with reason:**
```bash
curl -X PATCH "https://api.zenamanage.com/api/v1/app/tasks/01k5kzpfwd618xmwdwq3rej3jz/move" \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "to_status": "blocked",
    "reason": "Waiting for client feedback",
    "version": 2
  }'
```

---

## Changelog

### Version 1.0 (2025-11-14)
- Initial release
- Support for status transitions with validation
- Optimistic locking implementation
- Midpoint positioning strategy
- Reason requirement for blocked/canceled moves

---

## Support

For questions or issues:
- **Documentation**: [Business Rules](../TASK_STATUS_BUSINESS_RULES.md)
- **Support Email**: support@zenamanage.com
- **API Status**: https://status.zenamanage.com

