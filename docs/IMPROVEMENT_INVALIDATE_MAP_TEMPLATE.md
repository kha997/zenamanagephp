# Cache Invalidation Map - Template

## File: `frontend/src/shared/api/invalidateMap.ts`

```typescript
import { QueryClient } from '@tanstack/react-query';

/**
 * Cache invalidation map
 * Maps mutation actions to query keys that should be invalidated
 */
export const invalidateMap = {
  // Task mutations
  'task.create': ['tasks', 'tasks.kpis', 'dashboard'],
  'task.update': ['tasks', 'task', 'tasks.kpis'],
  'task.move': ['tasks', 'task', 'tasks.kpis', 'dashboard'],
  'task.delete': ['tasks', 'tasks.kpis', 'dashboard'],
  'task.bulkDelete': ['tasks', 'tasks.kpis', 'dashboard'],
  'task.bulkUpdate': ['tasks', 'tasks.kpis'],
  
  // Project mutations
  'project.create': ['projects', 'dashboard'],
  'project.update': ['project', 'projects', 'dashboard'],
  'project.delete': ['projects', 'dashboard'],
  'project.archive': ['projects', 'dashboard'],
  
  // Document mutations
  'document.create': ['documents', 'documents.kpis', 'dashboard'],
  'document.update': ['documents', 'document', 'documents.kpis'],
  'document.delete': ['documents', 'documents.kpis', 'dashboard'],
  'document.upload': ['documents', 'documents.kpis'],
  
  // Client mutations
  'client.create': ['clients', 'dashboard'],
  'client.update': ['client', 'clients'],
  'client.delete': ['clients'],
  
  // Quote mutations
  'quote.create': ['quotes', 'dashboard'],
  'quote.update': ['quote', 'quotes'],
  'quote.delete': ['quotes'],
  'quote.approve': ['quote', 'quotes', 'dashboard'],
  
  // Change request mutations
  'changeRequest.create': ['changeRequests', 'changeRequestStats', 'dashboard'],
  'changeRequest.update': ['changeRequest', 'changeRequests', 'changeRequestStats'],
  'changeRequest.approve': ['changeRequest', 'changeRequests', 'changeRequestStats', 'dashboard'],
  'changeRequest.reject': ['changeRequest', 'changeRequests', 'changeRequestStats'],
  
  // User mutations (admin)
  'user.create': ['users', 'dashboard'],
  'user.update': ['user', 'users'],
  'user.delete': ['users'],
  'user.invite': ['users', 'dashboard'],
  
  // Template mutations
  'template.create': ['templates', 'templateSets'],
  'template.update': ['template', 'templates'],
  'template.delete': ['templates'],
  'template.apply': ['tasks', 'projects', 'dashboard'],
} as const;

export type InvalidationAction = keyof typeof invalidateMap;

/**
 * Context for cache invalidation
 */
export interface InvalidationContext {
  queryClient: QueryClient;
  tenantId?: string;
  resourceId?: string | number;
  projectId?: string | number;
}

/**
 * Invalidate cache for a specific action
 * 
 * @param action - The mutation action (e.g., 'task.move')
 * @param context - Invalidation context with queryClient and optional IDs
 */
export function invalidateFor(
  action: InvalidationAction,
  context: InvalidationContext
): void {
  const keys = invalidateMap[action];
  if (!keys) {
    console.warn(`No invalidation map for action: ${action}`);
    return;
  }

  const { queryClient, tenantId, resourceId, projectId } = context;

  keys.forEach(key => {
    // Base invalidation
    queryClient.invalidateQueries({ queryKey: [key] });
    
    // Resource-specific invalidation
    if (resourceId) {
      queryClient.invalidateQueries({ queryKey: [key, resourceId] });
    }
    
    // Project-specific invalidation (for tasks, documents, etc.)
    if (projectId) {
      queryClient.invalidateQueries({ queryKey: [key, 'project', projectId] });
    }
    
    // Tenant-specific invalidation (if needed)
    if (tenantId) {
      queryClient.invalidateQueries({ queryKey: [key, 'tenant', tenantId] });
    }
  });
}

/**
 * Invalidate multiple actions at once
 * Useful for complex mutations that affect multiple resources
 */
export function invalidateMultiple(
  actions: InvalidationAction[],
  context: InvalidationContext
): void {
  actions.forEach(action => invalidateFor(action, context));
}

/**
 * Helper to create invalidation context from mutation variables
 */
export function createInvalidationContext(
  queryClient: QueryClient,
  options?: {
    tenantId?: string;
    resourceId?: string | number;
    projectId?: string | number;
  }
): InvalidationContext {
  return {
    queryClient,
    ...options,
  };
}
```

## Usage Example

### Before (Current)
```typescript
export const useUpdateTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Task> }) =>
      tasksApi.updateTask(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['task', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kpis'] });
    },
  });
};
```

### After (With invalidateMap)
```typescript
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';

export const useUpdateTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Task> }) =>
      tasksApi.updateTask(id, data),
    onSuccess: (_, variables) => {
      invalidateFor('task.update', createInvalidationContext(queryClient, {
        resourceId: variables.id,
        projectId: data.project_id,
      }));
    },
  });
};
```

### Example: Task Move (Complex)
```typescript
export const useMoveTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, status, projectId }: { id: string | number; status: string; projectId?: string | number }) =>
      tasksApi.moveTask(id, status, projectId),
    onSuccess: (_, variables) => {
      invalidateFor('task.move', createInvalidationContext(queryClient, {
        resourceId: variables.id,
        projectId: variables.projectId,
      }));
    },
  });
};
```

## Benefits

1. **Single Source of Truth**: All invalidation logic in one place
2. **Consistency**: Same invalidation pattern across all mutations
3. **Maintainability**: Easy to update invalidation rules
4. **Type Safety**: TypeScript ensures action names are correct
5. **Flexibility**: Support for resource-specific and project-specific invalidation

## Testing

```typescript
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';
import { QueryClient } from '@tanstack/react-query';

describe('invalidateFor', () => {
  it('should invalidate correct queries for task.update', () => {
    const queryClient = new QueryClient();
    const invalidateQueriesSpy = jest.spyOn(queryClient, 'invalidateQueries');
    
    invalidateFor('task.update', createInvalidationContext(queryClient, {
      resourceId: '123',
    }));
    
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks'] });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task'] });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks', 'kpis'] });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task', '123'] });
  });
});
```

## Migration Checklist

- [ ] Create `invalidateMap.ts` file
- [ ] Add all mutation actions to map
- [ ] Create helper functions
- [ ] Refactor `useCreateTask`
- [ ] Refactor `useUpdateTask`
- [ ] Refactor `useMoveTask`
- [ ] Refactor `useDeleteTask`
- [ ] Refactor project hooks
- [ ] Refactor document hooks
- [ ] Refactor other feature hooks
- [ ] Write tests
- [ ] Update documentation

