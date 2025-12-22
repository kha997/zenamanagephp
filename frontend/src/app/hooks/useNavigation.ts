import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../../shared/api/client';

export interface NavItem {
  path: string;
  label: string;
  icon?: string;
  perm?: string;
  admin?: boolean;
}

interface NavigationResponse {
  navigation: NavItem[];
  role: string;
  permissions: string[];
}

/**
 * Hook to fetch navigation items from API
 * Returns navigation items filtered by user permissions
 */
export function useNavigation() {
  return useQuery<NavItem[]>({
    queryKey: ['navigation'],
    queryFn: async () => {
      const response = await apiClient.get<NavigationResponse>('/v1/me/nav');
      return response.data.navigation;
    },
    staleTime: 60 * 1000, // 60 seconds - navigation doesn't change frequently
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
}

