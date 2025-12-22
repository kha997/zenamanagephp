import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface UserPreferences {
  views?: {
    [page: string]: {
      mode?: 'table' | 'card' | 'list';
      density?: 'compact' | 'comfortable' | 'spacious';
      columns?: string[];
      sortBy?: string;
      sortDirection?: 'asc' | 'desc';
    };
  };
  kpi?: {
    [page: string]: string[]; // Selected KPI IDs
  };
  theme?: 'light' | 'dark' | 'auto';
  notifications?: {
    email?: boolean;
    push?: boolean;
    inApp?: boolean;
  };
}

/**
 * User Preferences API Client
 */
export const preferencesApi = {
  async getPreferences(): Promise<{ data: UserPreferences }> {
    try {
      const response = await apiClient.get<{ data: UserPreferences }>('/api/user-preferences');
      return response.data;
    } catch (error) {
      // Fallback to default preferences if API fails
      return { data: {} as UserPreferences };
    }
  },

  async updatePreferences(preferences: Partial<UserPreferences>): Promise<{ data: UserPreferences }> {
    try {
      const response = await apiClient.put<{ data: UserPreferences }>('/api/user-preferences', preferences);
      return response.data;
    } catch (error) {
      // Store in localStorage as fallback
      if (typeof window !== 'undefined') {
        const stored = localStorage.getItem('user-preferences');
        const current = stored ? JSON.parse(stored) : {};
        const updated = { ...current, ...preferences };
        localStorage.setItem('user-preferences', JSON.stringify(updated));
        return { data: updated as UserPreferences };
      }
      throw mapAxiosError(error as any);
    }
  },

  async getKpiPreferences(): Promise<{ data: any }> {
    try {
      const response = await apiClient.get('/api/kpi/preferences');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async saveKpiPreferences(preferences: { [page: string]: string[] }): Promise<void> {
    try {
      await apiClient.post('/api/kpi/preferences', preferences);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

