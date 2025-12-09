import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import {
  useProjectContracts,
  useContractDetail,
  useContractChangeOrders,
  useChangeOrderDetail,
  useContractPaymentCertificates,
  useContractPayments,
} from '../../hooks';
import { projectsApi } from '../../api';

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    getProjectContracts: vi.fn(),
    getContractDetail: vi.fn(),
    getContractChangeOrders: vi.fn(),
    getChangeOrderDetail: vi.fn(),
    getContractPaymentCertificates: vi.fn(),
    getContractPayments: vi.fn(),
  },
}));

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        cacheTime: 0,
        staleTime: 0,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
};

describe('Contract Hooks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('useProjectContracts', () => {
    it('fetches contracts successfully', async () => {
      const mockContracts = [
        {
          id: 'contract-1',
          project_id: 'project-123',
          code: 'CT-001',
          name: 'Main Contract',
          type: 'construction',
          party_name: 'ABC Construction',
          currency: 'VND',
          base_amount: 1000000,
          current_amount: 1050000,
          total_certified_amount: 500000,
          total_paid_amount: 450000,
          outstanding_amount: 50000,
          status: 'active',
          start_date: '2024-01-01',
          end_date: '2024-12-31',
          signed_at: '2024-01-01T00:00:00Z',
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z',
        },
      ];

      vi.mocked(projectsApi.getProjectContracts).mockResolvedValue({
        data: mockContracts,
      });

      const { result } = renderHook(() => useProjectContracts('project-123'), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockContracts);
    });

    it('does not fetch when projectId is undefined', () => {
      const { result } = renderHook(() => useProjectContracts(undefined), {
        wrapper: createWrapper(),
      });

      expect(result.current.isLoading).toBe(false);
      expect(result.current.data).toBeUndefined();
    });
  });

  describe('useContractDetail', () => {
    it('fetches contract detail successfully', async () => {
      const mockContract = {
        id: 'contract-1',
        project_id: 'project-123',
        code: 'CT-001',
        name: 'Main Contract',
        type: 'construction',
        party_name: 'ABC Construction',
        currency: 'VND',
        base_amount: 1000000,
        current_amount: 1050000,
        total_certified_amount: 500000,
        total_paid_amount: 450000,
        outstanding_amount: 50000,
        status: 'active',
        start_date: '2024-01-01',
        end_date: '2024-12-31',
        signed_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        vat_percent: null,
        total_amount_with_vat: null,
        retention_percent: null,
        notes: null,
        metadata: null,
        lines: [],
      };

      vi.mocked(projectsApi.getContractDetail).mockResolvedValue({
        data: mockContract,
      });

      const { result } = renderHook(
        () => useContractDetail('project-123', 'contract-1'),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockContract);
    });
  });

  describe('useContractChangeOrders', () => {
    it('fetches change orders successfully', async () => {
      const mockChangeOrders = [
        {
          id: 'co-1',
          tenant_id: 'tenant-1',
          project_id: 'project-123',
          contract_id: 'contract-1',
          code: 'CO-001',
          title: 'Change Order 1',
          reason: 'Additional work',
          status: 'approved' as const,
          amount_delta: 50000,
          effective_date: '2024-06-01',
          metadata: null,
          created_at: '2024-05-01T00:00:00Z',
          updated_at: '2024-05-01T00:00:00Z',
        },
      ];

      vi.mocked(projectsApi.getContractChangeOrders).mockResolvedValue({
        data: mockChangeOrders,
      });

      const { result } = renderHook(
        () => useContractChangeOrders('project-123', 'contract-1'),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockChangeOrders);
    });
  });

  describe('useChangeOrderDetail', () => {
    it('fetches change order detail successfully', async () => {
      const mockChangeOrder = {
        id: 'co-1',
        tenant_id: 'tenant-1',
        project_id: 'project-123',
        contract_id: 'contract-1',
        code: 'CO-001',
        title: 'Change Order 1',
        reason: 'Additional work',
        status: 'approved' as const,
        amount_delta: 50000,
        effective_date: '2024-06-01',
        metadata: null,
        created_at: '2024-05-01T00:00:00Z',
        updated_at: '2024-05-01T00:00:00Z',
        lines: [],
      };

      vi.mocked(projectsApi.getChangeOrderDetail).mockResolvedValue({
        data: mockChangeOrder,
      });

      const { result } = renderHook(
        () => useChangeOrderDetail('project-123', 'contract-1', 'co-1'),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockChangeOrder);
    });
  });

  describe('useContractPaymentCertificates', () => {
    it('fetches payment certificates successfully', async () => {
      const mockCertificates = [
        {
          id: 'cert-1',
          tenant_id: 'tenant-1',
          project_id: 'project-123',
          contract_id: 'contract-1',
          code: 'PC-001',
          title: 'Payment Certificate 1',
          status: 'approved',
          period_start: '2024-01-01',
          period_end: '2024-01-31',
          amount_before_retention: 100000,
          retention_percent_override: null,
          retention_amount: 5000,
          amount_payable: 95000,
          metadata: null,
          created_at: '2024-01-15T00:00:00Z',
          updated_at: '2024-01-15T00:00:00Z',
        },
      ];

      vi.mocked(projectsApi.getContractPaymentCertificates).mockResolvedValue({
        data: mockCertificates,
      });

      const { result } = renderHook(
        () => useContractPaymentCertificates('project-123', 'contract-1'),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockCertificates);
    });
  });

  describe('useContractPayments', () => {
    it('fetches payments successfully', async () => {
      const mockPayments = [
        {
          id: 'payment-1',
          tenant_id: 'tenant-1',
          project_id: 'project-123',
          contract_id: 'contract-1',
          certificate_id: 'cert-1',
          paid_date: '2024-01-20',
          amount_paid: 95000,
          currency: 'VND',
          payment_method: 'bank_transfer',
          reference_no: 'REF-001',
          metadata: null,
          created_at: '2024-01-20T00:00:00Z',
          updated_at: '2024-01-20T00:00:00Z',
        },
      ];

      vi.mocked(projectsApi.getContractPayments).mockResolvedValue({
        data: mockPayments,
      });

      const { result } = renderHook(
        () => useContractPayments('project-123', 'contract-1'),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data?.data).toEqual(mockPayments);
    });
  });
});
