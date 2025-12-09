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
