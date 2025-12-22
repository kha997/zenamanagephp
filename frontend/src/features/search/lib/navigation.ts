import type { GlobalSearchResult } from '@/api/searchApi';

export interface GlobalSearchRoute {
  path: string;
  search?: string;
}

export function resolveSearchResultRoute(result: GlobalSearchResult): GlobalSearchRoute | null {
  switch (result.module) {
    case 'projects':
      return { path: `/app/projects/${result.id}` };
    case 'tasks':
      return { path: `/app/tasks/${result.id}` };
    case 'documents':
      return { path: `/app/documents/${result.id}` };
    case 'cost':
      return resolveCostRoute(result);
    case 'users':
      return { path: '/app/users' };
    default:
      return null;
  }
}

function resolveCostRoute(result: GlobalSearchResult): GlobalSearchRoute | null {
  const projectId = result.project_id;
  const contractId = result.entity?.contract_id as string | undefined;

  if (!projectId) {
    return { path: '/app/projects' };
  }

  if (result.type === 'change_order' && contractId) {
    return {
      path: `/app/projects/${projectId}/contracts/${contractId}/change-orders/${result.id}`,
    };
  }

  if (contractId) {
    return {
      path: `/app/projects/${projectId}/contracts/${contractId}`,
    };
  }

  return { path: `/app/projects/${projectId}` };
}

/**
 * Resolve secondary route for a search result (e.g., "Open in project" for tasks/documents)
 */
export function resolveSearchResultSecondaryRoute(result: GlobalSearchResult): GlobalSearchRoute | null {
  const projectId = result.project_id;

  switch (result.module) {
    case 'tasks':
      if (projectId) {
        return {
          path: `/app/projects/${projectId}`,
          search: `?tab=tasks&taskId=${result.id}`,
        };
      }
      return null;

    case 'documents':
      if (projectId) {
        return {
          path: `/app/projects/${projectId}`,
          search: `?tab=documents&docId=${result.id}`,
        };
      }
      // Fallback to document detail if no project_id
      return { path: `/app/documents/${result.id}` };

    case 'cost':
      // For change_order, secondary route is the contract detail
      if (result.type === 'change_order' && projectId) {
        const contractId = result.entity?.contract_id as string | undefined;
        if (contractId) {
          return {
            path: `/app/projects/${projectId}/contracts/${contractId}`,
          };
        }
      }
      // For other cost types, no secondary route
      return null;

    case 'projects':
    case 'users':
      // No secondary routes for these
      return null;

    default:
      return null;
  }
}
