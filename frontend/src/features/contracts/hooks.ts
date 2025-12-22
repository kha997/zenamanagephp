import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { contractsApi } from './api';
import type { Contract, ContractPayment, ContractFilters, CreatePaymentData, UpdatePaymentData } from './types';
import { useAuthStore } from '../../auth/store';

/**
 * Get tenantId from auth store
 * Returns null if not available (for safety)
 */
const useTenantId = (): string | number | null => {
  const { user, selectedTenantId } = useAuthStore();
  return user?.tenant_id || selectedTenantId || null;
};

export const useContractsList = (
  filters?: ContractFilters,
  pagination?: { page?: number; per_page?: number }
) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: ['contracts', tenantId, filters, pagination],
    queryFn: () => contractsApi.getContracts(filters, pagination),
    enabled: tenantId !== null,
  });
};

export const useContractDetail = (id: string | number | undefined) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: ['contract', tenantId, id],
    queryFn: () => contractsApi.getContract(id!),
    enabled: !!id && tenantId !== null,
  });
};

export const useContractPayments = (
  contractId: string | number | undefined,
  pagination?: { page?: number; per_page?: number }
) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: ['contract-payments', tenantId, contractId, pagination],
    queryFn: () => contractsApi.getContractPayments(contractId!, pagination),
    enabled: !!contractId && tenantId !== null,
  });
};

export const useCreateContractPayment = () => {
  const queryClient = useQueryClient();
  const tenantId = useTenantId();
  
  return useMutation({
    mutationFn: ({ contractId, data }: { contractId: string | number; data: CreatePaymentData }) =>
      contractsApi.createPayment(contractId, data),
    onSuccess: (_, variables) => {
      // Invalidate contract payments list (with tenantId)
      queryClient.invalidateQueries({ queryKey: ['contract-payments', tenantId, variables.contractId] });
      // Also invalidate contract detail to refresh any payment summary
      queryClient.invalidateQueries({ queryKey: ['contract', tenantId, variables.contractId] });
      // Invalidate contracts list to refresh any summary data
      queryClient.invalidateQueries({ queryKey: ['contracts', tenantId] });
    },
  });
};

export const useUpdateContractPayment = () => {
  const queryClient = useQueryClient();
  const tenantId = useTenantId();
  
  return useMutation({
    mutationFn: ({
      contractId,
      paymentId,
      data,
    }: {
      contractId: string | number;
      paymentId: string | number;
      data: UpdatePaymentData;
    }) => contractsApi.updatePayment(contractId, paymentId, data),
    onSuccess: (_, variables) => {
      // Invalidate contract payments list (with tenantId)
      queryClient.invalidateQueries({ queryKey: ['contract-payments', tenantId, variables.contractId] });
      // Also invalidate contract detail
      queryClient.invalidateQueries({ queryKey: ['contract', tenantId, variables.contractId] });
      // Invalidate contracts list to refresh any summary data
      queryClient.invalidateQueries({ queryKey: ['contracts', tenantId] });
    },
  });
};

export const useDeleteContractPayment = () => {
  const queryClient = useQueryClient();
  const tenantId = useTenantId();
  
  return useMutation({
    mutationFn: ({ contractId, paymentId }: { contractId: string | number; paymentId: string | number }) =>
      contractsApi.deletePayment(contractId, paymentId),
    onSuccess: (_, variables) => {
      // Invalidate contract payments list (with tenantId)
      queryClient.invalidateQueries({ queryKey: ['contract-payments', tenantId, variables.contractId] });
      // Also invalidate contract detail
      queryClient.invalidateQueries({ queryKey: ['contract', tenantId, variables.contractId] });
      // Invalidate contracts list to refresh any summary data
      queryClient.invalidateQueries({ queryKey: ['contracts', tenantId] });
    },
  });
};

/**
 * Hook to fetch contract cost summary
 * 
 * Round 45: Contract Cost Control - Cost Summary
 * 
 * Returns budget, actual, and payments summary for a single contract.
 */
export const useContractCostSummary = (contractId?: string | number) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: ['contract-cost-summary', tenantId, contractId],
    queryFn: () => contractsApi.getContractCostSummary(contractId!),
    enabled: !!tenantId && !!contractId,
    staleTime: 30 * 1000, // 30 seconds - cost summary can be cached briefly
    gcTime: 2 * 60 * 1000, // 2 minutes
  });
};

/**
 * Hook to fetch contract cost overruns
 * 
 * Round 47: Cost Overruns Dashboard
 */
export const useContractCostOverruns = (filters?: {
  status?: string;
  search?: string;
  min_budget_diff?: number;
  min_actual_diff?: number;
  limit?: number;
}) => {
  const tenantId = useTenantId();
  
  return useQuery({
    queryKey: ['contract-cost-overruns', tenantId, filters],
    queryFn: () => contractsApi.getContractCostOverruns(filters),
    enabled: !!tenantId,
    staleTime: 60 * 1000, // 1 minute - cost overruns can be cached briefly
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

