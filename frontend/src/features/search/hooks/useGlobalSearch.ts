import { useQuery } from '@tanstack/react-query';
import { fetchGlobalSearch, type GlobalSearchQueryParams } from '@/api/searchApi';

export function useGlobalSearch(params: GlobalSearchQueryParams, enabled: boolean) {
  const queryText = params.q?.trim() ?? '';
  const modulesKey = params.modules?.join(',') ?? '';

  return useQuery({
    queryKey: ['globalSearch', queryText, modulesKey, params.project_id, params.page, params.per_page],
    queryFn: () => fetchGlobalSearch({ ...params, q: queryText }),
    enabled: enabled && queryText.length > 0,
    keepPreviousData: true,
    staleTime: 30_000,
  });
}
