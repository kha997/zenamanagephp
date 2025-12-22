import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type { ProjectOverviewHealth } from '../projects/api';

const apiClient = createApiClient();

/**
 * Project Health Portfolio Item
 * 
 * Round 75: Project Health Portfolio
 */
export type ProjectHealthPortfolioItem = {
  project: {
    id: string;
    code: string | null;
    name: string;
    status?: string | null;
    client_name?: string | null;
  };
  health: ProjectOverviewHealth;
};

/**
 * Project Health Portfolio History Item
 * 
 * Round 91: Project Health Portfolio History Endpoint
 * Round 92: Frontend integration
 */
export interface ProjectHealthPortfolioHistoryItem {
  snapshot_date: string; // 'YYYY-MM-DD'
  good: number;
  warning: number;
  critical: number;
  total: number;
}

/**
 * Reports API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/reports/*
 * Route prefix: Route::prefix('v1/app') -> Route::prefix('reports') under auth:sanctum + ability:tenant middleware
 */
export const reportsApi = {
  /**
   * Get Reports KPIs
   * Returns KPIs including contracts data
   * 
   * Response structure:
   * {
   *   success: true,
   *   data: {
   *     total_reports: number,
   *     recent_reports: number,
   *     by_type: Record<string, number>,
   *     downloads: number,
   *     trends: {...},
   *     period: string,
   *     contracts: {
   *       total_count: number,
   *       active_count: number,
   *       completed_count: number,
   *       cancelled_count: number,
   *       total_value: number,
   *       payments: {
   *         scheduled_total: number,
   *         paid_total: number,
   *         overdue_total: number,
   *         overdue_count: number,
   *         remaining_to_schedule: number,
   *         remaining_to_pay: number,
   *       }
   *     }
   *   }
   * }
   */
  async getKpis(period?: string): Promise<{
    success?: boolean;
    data?: {
      total_reports?: number;
      recent_reports?: number;
      by_type?: Record<string, number>;
      downloads?: number;
      trends?: any;
      period?: string;
      contracts?: {
        total_count: number;
        active_count: number;
        completed_count: number;
        cancelled_count: number;
        total_value: number;
        payments: {
          scheduled_total: number;
          paid_total: number;
          overdue_total: number;
          overdue_count: number;
          remaining_to_schedule: number;
          remaining_to_pay: number;
        };
      };
    };
  }> {
    try {
      const params = new URLSearchParams();
      if (period) params.append('period', period);

      const response = await apiClient.get<{
        success?: boolean;
        data?: any;
      }>(`/v1/app/reports/kpis${params.toString() ? `?${params.toString()}` : ''}`);

      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as any;
      }
      return { data: response.data } as any;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract cost overruns table
   * 
   * Round 49: Full-page Cost Overruns Table
   */
  async getContractCostOverrunsTable(params?: {
    search?: string;
    status?: string;
    client_id?: string;
    project_id?: string;
    min_overrun_amount?: number;
    type?: 'budget' | 'actual' | 'both';
    page?: number;
    per_page?: number;
    sort_by?: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
    sort_direction?: 'asc' | 'desc';
  }): Promise<{
    ok: boolean;
    data: {
      items: Array<{
        id: string;
        code: string;
        name: string;
        status: string;
        client: { id: string; name: string } | null;
        project: { id: string; name: string } | null;
        currency: string; // Round 50: Add currency field
        contract_value: number;
        budget_total: number;
        actual_total: number;
        budget_vs_contract_diff: number;
        contract_vs_actual_diff: number;
        overrun_amount: number;
      }>;
      pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
      };
    };
  }> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/contracts/cost-overruns/table${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ ok: boolean; data: any }>(url);
      
      return response.data as any;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export contract cost overruns to CSV
   * 
   * Round 49: Full-page Cost Overruns Export
   * Round 51: Added sort support to match table sorting
   */
  async exportContractCostOverruns(params?: {
    search?: string;
    status?: string;
    client_id?: string;
    project_id?: string;
    min_overrun_amount?: number;
    type?: 'budget' | 'actual' | 'both';
    sort_by?: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
    sort_direction?: 'asc' | 'desc';
  }): Promise<Blob> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/contracts/cost-overruns/export${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url, {
        responseType: 'blob',
      });
      
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project cost portfolio
   * 
   * Round 51: Project Cost Portfolio
   */
  async getProjectCostPortfolio(params?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
    page?: number;
    per_page?: number;
    sort_by?: 'project_code' | 'project_name' | 'contracts_value_total' | 'overrun_amount_total';
    sort_direction?: 'asc' | 'desc';
  }): Promise<{
    ok: boolean;
    data: {
      items: Array<{
        project_id: string;
        project_code: string;
        project_name: string;
        client: { id: string; name: string } | null;
        contracts_count: number;
        contracts_value_total: number | null;
        budget_total: number;
        actual_total: number;
        overrun_amount_total: number;
        over_budget_contracts_count: number;
        overrun_contracts_count: number;
        currency: string;
      }>;
      pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
      };
    };
  }> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/portfolio/projects${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ ok: boolean; data: any }>(url);
      
      return response.data as any;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get client cost portfolio
   * 
   * Round 53: Client Cost Portfolio
   */
  async getClientCostPortfolio(params?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
    page?: number;
    per_page?: number;
    sort_by?: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
    sort_direction?: 'asc' | 'desc';
  }): Promise<{
    ok: boolean;
    data: {
      items: Array<{
        client_id: string;
        client_code: string | null;
        client_name: string;
        projects_count: number;
        contracts_count: number;
        contracts_value_total: number | null;
        budget_total: number;
        actual_total: number;
        overrun_amount_total: number;
        over_budget_contracts_count: number;
        overrun_contracts_count: number;
        currency: string;
      }>;
      pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
      };
    };
  }> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/portfolio/clients${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ ok: boolean; data: any }>(url);
      
      return response.data as any;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export project cost portfolio to CSV
   * 
   * Round 66: Project Cost Portfolio Export
   */
  async exportProjectCostPortfolio(params?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
    sort_by?: 'project_code' | 'project_name' | 'contracts_value_total' | 'overrun_amount_total';
    sort_direction?: 'asc' | 'desc';
  }): Promise<Blob> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/portfolio/projects/export${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url, {
        responseType: 'blob',
      });
      
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export client cost portfolio to CSV
   * 
   * Round 66: Client Cost Portfolio Export
   */
  async exportClientCostPortfolio(params?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
    sort_by?: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
    sort_direction?: 'asc' | 'desc';
  }): Promise<Blob> {
    try {
      const queryParams = new URLSearchParams();
      if (params) {
        Object.entries(params).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, String(value));
          }
        });
      }

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/portfolio/clients/export${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url, {
        responseType: 'blob',
      });
      
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project health portfolio
   * 
   * Round 75: Project Health Portfolio
   * 
   * Returns list of all projects with their health status
   */
  async getProjectHealthPortfolio(): Promise<ProjectHealthPortfolioItem[]> {
    try {
      const response = await apiClient.get<{ ok: boolean; data: ProjectHealthPortfolioItem[] }>('/v1/app/reports/projects/health');
      const raw = response.data;
      
      const items = raw?.data ?? raw ?? [];
      return items as ProjectHealthPortfolioItem[];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project health portfolio history
   * 
   * Round 91: Project Health Portfolio History Endpoint
   * Round 92: Frontend integration
   * 
   * Returns array of daily aggregated counts for current tenant
   */
  async getProjectHealthPortfolioHistory({
    days,
    signal,
  }: {
    days?: number;
    signal?: AbortSignal;
  }): Promise<ProjectHealthPortfolioHistoryItem[]> {
    try {
      const params = new URLSearchParams();
      if (days !== undefined) {
        params.append('days', String(days));
      }

      const response = await apiClient.get<ProjectHealthPortfolioHistoryItem[]>(
        `/v1/app/reports/projects/health/history${params.toString() ? `?${params.toString()}` : ''}`,
        { signal }
      );
      
      const raw = response.data;
      return Array.isArray(raw) ? raw : [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

