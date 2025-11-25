# PR #4: OpenAPI → Types

## Summary
Implemented automatic TypeScript type generation from OpenAPI specification, ensuring type safety and contract consistency between frontend and backend.

## Changes

### New Files
1. **`frontend/scripts/gen-api-types.ts`**
   - TypeScript script to generate types from OpenAPI spec
   - Validates OpenAPI spec before generation
   - Adds helper types and utilities
   - Validates generated types compile correctly

2. **`frontend/src/shared/api/types.ts`**
   - Type helpers for extracting types from OpenAPI-generated paths
   - Re-exported component types (Task, Project, User, etc.)
   - Endpoint-specific type aliases (TasksListResponse, TaskCreateRequest, etc.)

3. **`scripts/validate-openapi.sh`**
   - CI script to validate OpenAPI spec
   - Ensures types can be generated successfully

### Modified Files
1. **`docs/api/openapi.yaml`**
   - Added missing task endpoints: PUT, PATCH, DELETE for `/app/tasks/{id}`
   - Added `/app/tasks/{id}/move` endpoint
   - Added bulk action endpoints: `/app/tasks/bulk-delete`, `/app/tasks/bulk-status`, `/app/tasks/bulk-assign`
   - Added missing schemas: `TaskUpdate`, `ErrorResponse`
   - Added `IdempotencyKey` parameter definition

2. **`frontend/package.json`**
   - Added `gen:api` script
   - Updated `prebuild` to run `gen:api` automatically

3. **`frontend/src/features/tasks/api.ts`**
   - Refactored to use generated types from OpenAPI
   - Added idempotency key generation for write operations
   - Type-safe request/response types

4. **`frontend/src/shared/types/api.d.ts`**
   - Auto-generated from OpenAPI spec (updated)
   - Includes helper types and utilities

## Features

### Type Generation
- Automatic TypeScript type generation from OpenAPI spec
- Type-safe API client methods
- Request/response type extraction
- Component schema types

### Idempotency Support
- Automatic idempotency key generation for write operations
- Format: `{resource}_{action}_{timestamp}_{nonce}`
- Helper function: `generateIdempotencyKey(resource, action)`

### Type Safety
- All API methods use generated types
- Compile-time type checking
- IntelliSense support in IDE

## Usage

### Generate Types
```bash
cd frontend
npm run gen:api
```

### Use Generated Types
```typescript
import type {
  Task,
  TasksListResponse,
  TaskCreateRequest,
  TaskCreateResponse,
} from '@/shared/api/types';

// Type-safe API call
const response: TasksListResponse = await tasksApi.getTasks();
const task: Task = response.data[0];
```

### Extract Types from Paths
```typescript
import type { ApiResponse, ApiRequest } from '@/shared/api/types';

// Extract response type
type TaskResponse = ApiResponse<'/app/tasks/{id}', 'get'>;

// Extract request type
type TaskCreateRequest = ApiRequest<'/app/tasks', 'post'>;
```

## CI/CD Integration

### Validation Script
```bash
./scripts/validate-openapi.sh
```

### Pre-build Hook
Types are automatically generated before build:
```json
{
  "prebuild": "npm run gen:api"
}
```

## Migration Guide

### Before (Manual Types)
```typescript
export interface Task {
  id: string | number;
  title: string;
  status: string;
  // ... manual definition
}

export const tasksApi = {
  async getTasks(): Promise<{ data: Task[] }> {
    // ...
  }
};
```

### After (Generated Types)
```typescript
import type { Task, TasksListResponse } from '@/shared/api/types';

export const tasksApi = {
  async getTasks(): Promise<TasksListResponse> {
    // ...
  }
};
```

## Benefits

1. **Single Source of Truth**: OpenAPI spec is the authoritative source
2. **Type Safety**: Compile-time checking prevents runtime errors
3. **Automatic Updates**: Types update when OpenAPI spec changes
4. **Better DX**: IntelliSense and autocomplete in IDE
5. **Contract Validation**: Ensures frontend/backend contract matches

## Future Improvements

1. Generate Zod schemas from OpenAPI for runtime validation
2. Generate API client code (not just types)
3. Add OpenAPI spec validation in CI
4. Generate mock data from OpenAPI examples
5. Add API documentation generation from OpenAPI

## Testing

### Validate OpenAPI Spec
```bash
./scripts/validate-openapi.sh
```

### Type Check
```bash
cd frontend
npm run type-check
```

### Build (includes type generation)
```bash
cd frontend
npm run build
```

