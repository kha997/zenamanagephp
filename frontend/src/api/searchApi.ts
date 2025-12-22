import { api } from '@/services/api';

/**
 * Module names understood by the search endpoint
 */
export type GlobalSearchModule = 'projects' | 'tasks' | 'documents' | 'cost' | 'users';

/**
 * Shape of individual global search results
 */
export interface GlobalSearchResult {
  id: string;
  module: GlobalSearchModule;
  type: string;
  title: string;
  subtitle?: string | null;
  description?: string | null;
  project_id?: string | null;
  project_name?: string | null;
  status?: string | null;
  entity: Record<string, unknown>;
}

/**
 * Shape of the global search response payload
 */
export interface GlobalSearchResponse {
  pagination: {
    page: number;
    per_page: number;
    total: number;
  };
  results: GlobalSearchResult[];
}

/**
 * Query parameters accepted by the global search endpoint
 */
export interface GlobalSearchQueryParams {
  q: string;
  modules?: GlobalSearchModule[];
  project_id?: string;
  page?: number;
  per_page?: number;
}

/**
 * Fetch global search results from the API
 */
export async function fetchGlobalSearch(params: GlobalSearchQueryParams): Promise<GlobalSearchResponse> {
  const response = await api.get<GlobalSearchResponse>('/v1/app/search', {
    params: {
      q: params.q,
      ...(params.modules ? { modules: params.modules } : {}),
      ...(params.project_id ? { project_id: params.project_id } : {}),
      ...(params.page ? { page: params.page } : {}),
      ...(params.per_page ? { per_page: params.per_page } : {}),
    },
  });

  return response.data;
}
