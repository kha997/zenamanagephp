import { QueryClient } from '@tanstack/react-query';

/**
 * Cache invalidation map
 * Maps mutation actions to query keys that should be invalidated
 * 
 * This is the single source of truth for cache invalidation logic.
 * All mutation hooks should use invalidateFor() instead of manually calling invalidateQueries.
 */
export const invalidateMap = {
  // Task mutations
  // Note: ['tasks'] will invalidate all queries starting with ['tasks'], including ['tasks', 'kpis']
  'task.create': ['tasks', 'dashboard'],
  'task.update': ['tasks', 'task'],
  'task.move': ['tasks', 'task', 'dashboard'],
  'task.delete': ['tasks', 'dashboard'],
  'task.bulkDelete': ['tasks', 'dashboard'],
  'task.bulkUpdate': ['tasks'],
  'task.bulkAssign': ['tasks'],
  
  // Project mutations
  'project.create': ['projects', 'dashboard'],
  'project.update': ['project', 'projects', 'dashboard'],
  'project.delete': ['projects', 'dashboard'],
  'project.archive': ['projects', 'dashboard'],
  'project.addTeamMember': ['project', 'projects'],
  'project.removeTeamMember': ['project', 'projects'],
  'project.uploadDocument': ['projects', 'project'],
  
  // Document mutations
  'document.create': ['documents', 'dashboard'],
  'document.update': ['documents', 'document'],
  'document.delete': ['documents', 'dashboard'],
  'document.upload': ['documents'],
  
  // Client mutations (if applicable)
  'client.create': ['clients', 'dashboard'],
  'client.update': ['client', 'clients'],
  'client.delete': ['clients'],
  
  // Quote mutations (if applicable)
  'quote.create': ['quotes', 'dashboard'],
  'quote.update': ['quote', 'quotes'],
  'quote.delete': ['quotes'],
  'quote.approve': ['quote', 'quotes', 'dashboard'],
  
  // Change request mutations (if applicable)
  'changeRequest.create': ['changeRequests', 'changeRequestStats', 'dashboard'],
  'changeRequest.update': ['changeRequest', 'changeRequests', 'changeRequestStats'],
  'changeRequest.approve': ['changeRequest', 'changeRequests', 'changeRequestStats', 'dashboard'],
  'changeRequest.reject': ['changeRequest', 'changeRequests', 'changeRequestStats'],
  
  // User mutations (admin)
  'user.create': ['users', 'dashboard'],
  'user.update': ['user', 'users'],
  'user.delete': ['users'],
  'user.invite': ['users', 'dashboard'],
  
  // Template mutations (if applicable)
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
 * 
 * @example
 * ```ts
 * invalidateFor('task.update', createInvalidationContext(queryClient, {
 *   resourceId: taskId,
 *   projectId: projectId,
 * }));
 * ```
 */
export function invalidateFor(
  action: InvalidationAction,
  context: InvalidationContext
): void {
  const keys = invalidateMap[action];
  if (!keys) {
    console.warn(`[invalidateMap] No invalidation map for action: ${action}`);
    return;
  }

  const { queryClient, tenantId, resourceId, projectId } = context;

  keys.forEach(key => {
    // Base invalidation - invalidate all queries that start with this key
    queryClient.invalidateQueries({ queryKey: [key] });
    
    // Resource-specific invalidation (e.g., ['task', '123'])
    if (resourceId) {
      queryClient.invalidateQueries({ queryKey: [key, resourceId] });
    }
    
    // Project-specific invalidation (e.g., ['tasks', 'project', '456'])
    if (projectId) {
      queryClient.invalidateQueries({ queryKey: [key, 'project', projectId] });
    }
    
    // Tenant-specific invalidation (if needed in the future)
    if (tenantId) {
      queryClient.invalidateQueries({ queryKey: [key, 'tenant', tenantId] });
    }
  });
}

/**
 * Invalidate multiple actions at once
 * Useful for complex mutations that affect multiple resources
 * 
 * @param actions - Array of actions to invalidate
 * @param context - Invalidation context
 * 
 * @example
 * ```ts
 * invalidateMultiple(['task.update', 'project.update'], context);
 * ```
 */
export function invalidateMultiple(
  actions: InvalidationAction[],
  context: InvalidationContext
): void {
  actions.forEach(action => invalidateFor(action, context));
}

/**
 * Helper to create invalidation context from mutation variables
 * 
 * @param queryClient - React Query client instance
 * @param options - Optional context data (tenantId, resourceId, projectId)
 * 
 * @example
 * ```ts
 * const context = createInvalidationContext(queryClient, {
 *   resourceId: taskId,
 *   projectId: projectId,
 * });
 * invalidateFor('task.move', context);
 * ```
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

