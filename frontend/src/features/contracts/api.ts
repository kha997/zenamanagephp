import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  Contract,
  ContractPayment,
  ContractFilters,
  ContractsResponse,
  ContractPaymentsResponse,
  CreatePaymentData,
  UpdatePaymentData,
} from './types';

const apiClient = createApiClient();

/**
 * Contracts API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/contracts/*
 * Route prefix: Route::prefix('v1/app') -> Route::prefix('contracts') under auth:sanctum + ability:tenant middleware
 */
export const contractsApi = {
  async getContracts(
    filters?: ContractFilters,
    pagination?: { page?: number; per_page?: number }
  ): Promise<ContractsResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.status) params.append('status', filters.status);
      if (filters?.client_id) params.append('client_id', String(filters.client_id));
      if (filters?.project_id) params.append('project_id', String(filters.project_id));
      if (filters?.signed_from) params.append('signed_from', filters.signed_from);
      if (filters?.signed_to) params.append('signed_to', filters.signed_to);
      if (filters?.sort_by) params.append('sort_by', filters.sort_by);
      if (filters?.sort_direction) params.append('sort_direction', filters.sort_direction);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<{ success?: boolean; data?: Contract[]; meta?: any } | ContractsResponse>(
        `/v1/app/contracts?${params.toString()}`
      );
      
      // Handle both response formats: { success: true, data: [...], meta: {...} } or { data: [...], meta: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: Contract[]; meta?: any };
        return {
          data: apiResponse.data || [],
          meta: apiResponse.meta,
        };
      }
      return response.data as ContractsResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getContract(id: string | number): Promise<{ data: Contract }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data: Contract }>(`/v1/app/contracts/${id}`);
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return response.data as { data: Contract };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getContractPayments(
    contractId: string | number,
    pagination?: { page?: number; per_page?: number }
  ): Promise<ContractPaymentsResponse> {
    try {
      const params = new URLSearchParams();
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const queryString = params.toString();
      const url = `/v1/app/contracts/${contractId}/payments${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ success?: boolean; data?: ContractPayment[]; meta?: any } | ContractPaymentsResponse>(url);
      
      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: ContractPayment[]; meta?: any };
        return {
          data: apiResponse.data || [],
          meta: apiResponse.meta,
        };
      }
      return response.data as ContractPaymentsResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createPayment(contractId: string | number, data: CreatePaymentData): Promise<{ data: ContractPayment }> {
    try {
      const response = await apiClient.post<{ data: ContractPayment }>(
        `/v1/app/contracts/${contractId}/payments`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updatePayment(
    contractId: string | number,
    paymentId: string | number,
    data: UpdatePaymentData
  ): Promise<{ data: ContractPayment }> {
    try {
      const response = await apiClient.patch<{ data: ContractPayment }>(
        `/v1/app/contracts/${contractId}/payments/${paymentId}`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deletePayment(contractId: string | number, paymentId: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/contracts/${contractId}/payments/${paymentId}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get cost summary for a contract
   * 
   * Round 45: Contract Cost Control - Cost Summary
   */
  async getContractCostSummary(contractId: string | number): Promise<{
    summary: {
      contract_value: number | null;
      budget_total: number;
      actual_total: number;
      payments_scheduled_total: number;
      payments_paid_total: number;
      remaining_to_schedule: number | null;
      remaining_to_pay: number;
      budget_vs_contract_diff: number | null;
      contract_vs_actual_diff: number | null;
      overdue_payments_count: number;
      overdue_payments_total: number;
    };
  }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: { summary: any } }>(
        `/v1/app/contracts/${contractId}/cost-summary`
      );
      // Handle both response formats: { success: true, data: { summary: {...} } } or { data: { summary: {...} } }
      if (response.data.success !== undefined) {
        return { summary: (response.data as any).data.summary };
      }
      return response.data as { summary: any };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract cost overruns
   * 
   * Round 47: Cost Overruns Dashboard
   */
  async getContractCostOverruns(params?: {
    status?: string;
    search?: string;
    min_budget_diff?: number;
    min_actual_diff?: number;
    limit?: number;
  }): Promise<{
    overBudgetContracts: Array<{
      id: string;
      code: string;
      name: string;
      client_name?: string;
      project_name?: string;
      status: string;
      currency: string;
      contract_value: number;
      budget_total: number;
      budget_vs_contract_diff: number;
    }>;
    overrunContracts: Array<{
      id: string;
      code: string;
      name: string;
      client_name?: string;
      project_name?: string;
      status: string;
      currency: string;
      contract_value: number;
      actual_total: number;
      contract_vs_actual_diff: number;
    }>;
  }> {
    try {
      const queryParams = new URLSearchParams();
      if (params?.status) queryParams.append('status', params.status);
      if (params?.search) queryParams.append('search', params.search);
      if (params?.min_budget_diff !== undefined) queryParams.append('min_budget_diff', String(params.min_budget_diff));
      if (params?.min_actual_diff !== undefined) queryParams.append('min_actual_diff', String(params.min_actual_diff));
      if (params?.limit) queryParams.append('limit', String(params.limit));

      const queryString = queryParams.toString();
      const url = `/v1/app/reports/contracts/cost-overruns${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ success?: boolean; data?: any }>(url);
      
      const data = response.data?.data ?? response.data;
      return {
        overBudgetContracts: data?.over_budget_contracts ?? [],
        overrunContracts: data?.overrun_contracts ?? [],
      };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export contracts list to CSV
   * 
   * Round 47: Cost Overruns Dashboard + Export
   */
  async exportContracts(params?: Record<string, any>): Promise<Blob> {
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
      const url = `/v1/app/contracts/export${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url, {
        responseType: 'blob',
      });
      
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export contract cost schedule to CSV
   * 
   * Round 47: Cost Overruns Dashboard + Export
   */
  async exportContractCostSchedule(contractId: string | number): Promise<Blob> {
    try {
      const response = await apiClient.get(`/v1/app/contracts/${contractId}/cost-export`, {
        responseType: 'blob',
      });
      
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

