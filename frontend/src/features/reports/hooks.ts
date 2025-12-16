import { useQuery, useMutation } from '@tanstack/react-query';
import { reportsApi, type ProjectHealthPortfolioItem, type ProjectHealthPortfolioHistoryItem } from './api';
import { useAuthStore } from '../../auth/store';
import type { ApiError } from '../../shared/api/client';

/**
 * Get tenantId from auth store
 * Returns null if not available (for safety)
 */
const useTenantId = (): string | number | null => {
  const { user, selectedTenantId } = useAuthStore();
  return user?.tenant_id || selectedTenantId || null;
};

/**
 * Reports Query Keys
 */
export const reportsKeys = {
  all: ['reports'] as const,
  kpis: (tenantId: string | number | null, period?: string) =>
    ['reports', 'kpis', tenantId, period] as const,
  costOverrunsTable: (
    tenantId: string | number | null,
    filters?: Record<string, any>,
    pagination?: Record<string, any>,
    sort?: Record<string, any>
  ) => ['reports', 'contracts', 'cost-overruns-table', tenantId, filters, pagination, sort] as const,
  projectCostPortfolio: (
    tenantId: string | number | null,
    filters?: Record<string, any>,
    pagination?: Record<string, any>,
    sort?: Record<string, any>
  ) => ['reports', 'portfolio', 'projects', tenantId, filters, pagination, sort] as const,
  clientCostPortfolio: (
    tenantId: string | number | null,
    filters?: Record<string, any>,
    pagination?: Record<string, any>,
    sort?: Record<string, any>
  ) => ['reports', 'portfolio', 'clients', tenantId, filters, pagination, sort] as const,
  projectHealthPortfolio: (tenantId: string | number | null) =>
    ['reports', 'projects', 'health', tenantId] as const,
  projectHealthPortfolioHistory: (tenantId: string | number | null, days?: number) =>
    ['reports', 'projects', 'health', 'history', tenantId, days] as const,
};

/**
 * Hook to fetch Reports KPIs
 * 
 * Returns KPIs data including contracts metrics
 * Query key includes tenantId to ensure tenant-aware caching
 * 
 * @param period Optional period filter ('week' | 'month')
 * @param options Optional query options (enabled, etc.)
 */
export const useReportsKpis = (
  period?: string,
  options?: { enabled?: boolean }
) => {
  const tenantId = useTenantId();
  const { enabled = true } = options ?? {};

  return useQuery({
    queryKey: reportsKeys.kpis(tenantId, period),
    queryFn: async () => {
      if (!tenantId) {
        throw new Error('No active tenant');
      }
      const response = await reportsApi.getKpis(period);
      // Return data.contracts or the full data structure
      return response.data || response;
    },
    enabled: enabled && tenantId !== null,
    staleTime: 60 * 1000, // 60 seconds - KPIs can be cached
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Hook to fetch contract cost overruns table
 * 
 * Round 49: Full-page Cost Overruns Table
 * 
 * @param filters Optional filters (search, status, client_id, project_id, min_overrun_amount, type)
 * @param pagination Optional pagination (page, per_page)
 * @param sort Optional sort (sort_by, sort_direction)
 */
export const useContractCostOverrunsTable = (
  filters?: {
    search?: string;
    status?: string;
    client_id?: string;
    project_id?: string;
    min_overrun_amount?: number;
    type?: 'budget' | 'actual' | 'both';
  },
  pagination?: {
    page?: number;
    per_page?: number;
  },
  sort?: {
    sort_by?: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
    sort_direction?: 'asc' | 'desc';
  }
) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: reportsKeys.costOverrunsTable(tenantId, filters, pagination, sort),
    queryFn: () => reportsApi.getContractCostOverrunsTable({
      ...filters,
      ...pagination,
      ...sort,
    }),
    enabled: !!tenantId,
    staleTime: 60 * 1000, // 1 minute
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Hook to export contract cost overruns to CSV
 * 
 * Round 49: Full-page Cost Overruns Export
 * Round 51: Added sort support to match table sorting
 * 
 * @returns Mutation function to trigger export
 */
export const useExportContractCostOverruns = () => {
  return useMutation({
    mutationFn: (params?: {
      search?: string;
      status?: string;
      client_id?: string;
      project_id?: string;
      min_overrun_amount?: number;
      type?: 'budget' | 'actual' | 'both';
      sort_by?: 'code' | 'overrun_amount' | 'budget_vs_contract_diff';
      sort_direction?: 'asc' | 'desc';
    }) => reportsApi.exportContractCostOverruns(params),
  });
};

/**
 * Hook to fetch project cost portfolio
 * 
 * Round 51: Project Cost Portfolio
 * 
 * @param filters Optional filters (search, client_id, status, min_overrun_amount)
 * @param pagination Optional pagination (page, per_page)
 * @param sort Optional sort (sort_by, sort_direction)
 */
export const useProjectCostPortfolio = (
  filters?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
  },
  pagination?: {
    page?: number;
    per_page?: number;
  },
  sort?: {
    sort_by?: 'project_code' | 'project_name' | 'contracts_value_total' | 'overrun_amount_total';
    sort_direction?: 'asc' | 'desc';
  }
) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: reportsKeys.projectCostPortfolio(tenantId, filters, pagination, sort),
    queryFn: () => reportsApi.getProjectCostPortfolio({
      ...filters,
      ...pagination,
      ...sort,
    }),
    enabled: !!tenantId,
    staleTime: 60 * 1000, // 1 minute
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Hook to fetch client cost portfolio
 * 
 * Round 53: Client Cost Portfolio
 * 
 * @param filters Optional filters (search, client_id, status, min_overrun_amount)
 * @param pagination Optional pagination (page, per_page)
 * @param sort Optional sort (sort_by, sort_direction)
 */
export const useClientCostPortfolio = (
  filters?: {
    search?: string;
    client_id?: string;
    status?: string;
    min_overrun_amount?: number;
  },
  pagination?: {
    page?: number;
    per_page?: number;
  },
  sort?: {
    sort_by?: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
    sort_direction?: 'asc' | 'desc';
  }
) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: reportsKeys.clientCostPortfolio(tenantId, filters, pagination, sort),
    queryFn: () => reportsApi.getClientCostPortfolio({
      ...filters,
      ...pagination,
      ...sort,
    }),
    enabled: !!tenantId,
    staleTime: 60 * 1000, // 1 minute
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Hook to export project cost portfolio to CSV
 * 
 * Round 66: Project Cost Portfolio Export
 * 
 * @returns Mutation function to trigger export
 */
export const useExportProjectCostPortfolio = () => {
  return useMutation({
    mutationFn: (params?: {
      search?: string;
      client_id?: string;
      status?: string;
      min_overrun_amount?: number;
      sort_by?: 'project_code' | 'project_name' | 'contracts_value_total' | 'overrun_amount_total';
      sort_direction?: 'asc' | 'desc';
    }) => reportsApi.exportProjectCostPortfolio(params),
  });
};

/**
 * Hook to export client cost portfolio to CSV
 * 
 * Round 66: Client Cost Portfolio Export
 * 
 * @returns Mutation function to trigger export
 */
export const useExportClientCostPortfolio = () => {
  return useMutation({
    mutationFn: (params?: {
      search?: string;
      client_id?: string;
      status?: string;
      min_overrun_amount?: number;
      sort_by?: 'client_name' | 'contracts_value_total' | 'overrun_amount_total' | 'contracts_count';
      sort_direction?: 'asc' | 'desc';
    }) => reportsApi.exportClientCostPortfolio(params),
  });
};

/**
 * Hook to fetch project health portfolio
 * 
 * Round 75: Project Health Portfolio
 * Round 77: Add enabled option to prevent API calls when user lacks permission
 * 
 * Returns list of all projects with their health status
 * 
 * @param options Optional configuration
 * @param options.enabled If false, the query will not be executed (default: true when tenantId exists)
 */
export type UseProjectHealthPortfolioOptions = {
  enabled?: boolean;
};

export const useProjectHealthPortfolio = (
  options?: UseProjectHealthPortfolioOptions
) => {
  const tenantId = useTenantId();
  
  const enabled = !!tenantId && (options?.enabled ?? true);
  
  return useQuery<ProjectHealthPortfolioItem[], ApiError>({
    queryKey: reportsKeys.projectHealthPortfolio(tenantId),
    queryFn: () => reportsApi.getProjectHealthPortfolio(),
    enabled,
    staleTime: 60 * 1000, // 1 minute
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Hook to fetch project health portfolio history
 * 
 * Round 92: Project Health Portfolio Trend Card
 * 
 * Returns array of daily aggregated counts for current tenant
 * 
 * @param options Optional configuration
 * @param options.days Number of days to fetch (default: 30)
 * @param options.enabled If false, the query will not be executed (default: true when tenantId exists)
 */
export interface UseProjectHealthPortfolioHistoryOptions {
  days?: number;
  enabled?: boolean;
}

export const useProjectHealthPortfolioHistory = (
  options: UseProjectHealthPortfolioHistoryOptions = {}
) => {
  const tenantId = useTenantId();
  const days = options.days ?? 30;
  const enabled = (options.enabled ?? true) && !!tenantId;

  return useQuery<ProjectHealthPortfolioHistoryItem[], ApiError>({
    queryKey: reportsKeys.projectHealthPortfolioHistory(tenantId, days),
    queryFn: ({ signal }) =>
      reportsApi.getProjectHealthPortfolioHistory({ days, signal }),
    enabled,
    staleTime: 60 * 1000, // 1 minute
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

