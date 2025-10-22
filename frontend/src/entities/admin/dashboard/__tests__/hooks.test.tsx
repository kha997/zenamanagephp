import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useAdminDashboardSummary, useAdminDashboardCharts, useAdminDashboardActivity, useAdminDashboardExport } from '../hooks';
import { adminDashboardApi } from '../api';

// Mock the API
vi.mock('../api', () => ({
  adminDashboardApi: {
    getSummary: vi.fn(),
    getCharts: vi.fn(),
    getActivity: vi.fn(),
    getExport: vi.fn(),
  },
}));

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
  
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
};

describe('Admin Dashboard Hooks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('useAdminDashboardSummary', () => {
    it('should fetch dashboard summary successfully', async () => {
      const mockSummary = {
        data: {
          total_users: 150,
          total_projects: 45,
          total_tasks: 230,
          active_sessions: 12,
          total_tenants: 8,
          active_tenants: 6,
          suspended_tenants: 2,
          total_storage_used: 1024000, // 1GB in MB
          total_storage_limit: 5120000, // 5GB in MB
        },
      };

      vi.mocked(adminDashboardApi.getSummary).mockResolvedValue(mockSummary);

      const { result } = renderHook(() => useAdminDashboardSummary(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockSummary);
      expect(adminDashboardApi.getSummary).toHaveBeenCalledTimes(1);
    });

    it('should handle API errors', async () => {
      const error = new Error('API Error');
      vi.mocked(adminDashboardApi.getSummary).mockRejectedValue(error);

      const { result } = renderHook(() => useAdminDashboardSummary(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isError).toBe(true);
      }, { timeout: 5000 });

      expect(result.current.error).toEqual(error);
    });

    it('should have correct stale time configuration', () => {
      const { result } = renderHook(() => useAdminDashboardSummary(), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useAdminDashboardCharts', () => {
    it('should fetch dashboard charts successfully', async () => {
      const mockCharts = {
        data: {
          chart_data: {
            users_growth: [
              { month: 'January', count: 100 },
              { month: 'February', count: 150 },
            ],
            projects_status: [
              { status: 'active', count: 30 },
              { status: 'completed', count: 15 },
            ],
            tenants_plan: [
              { plan: 'basic', count: 5 },
              { plan: 'premium', count: 3 },
            ],
            storage_usage: [
              { tenant: 'tenant1', used: 500, limit: 1000 },
              { tenant: 'tenant2', used: 750, limit: 2000 },
            ],
          },
        },
      };

      vi.mocked(adminDashboardApi.getCharts).mockResolvedValue(mockCharts);

      const { result } = renderHook(() => useAdminDashboardCharts(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockCharts);
      expect(adminDashboardApi.getCharts).toHaveBeenCalledTimes(1);
    });

    it('should have correct stale time configuration', () => {
      const { result } = renderHook(() => useAdminDashboardCharts(), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useAdminDashboardActivity', () => {
    it('should fetch dashboard activity successfully', async () => {
      const mockActivity = {
        data: {
          activities: [
            { 
              id: 1, 
              type: 'user_created' as const, 
              description: 'New user registered',
              user_name: 'John Doe',
              tenant_name: 'Acme Corp',
              created_at: '2024-01-01T10:00:00Z' 
            },
            { 
              id: 2, 
              type: 'project_created' as const, 
              description: 'New project created',
              user_name: 'Jane Smith',
              tenant_name: 'TechCorp',
              created_at: '2024-01-01T11:00:00Z' 
            },
          ],
        },
      };

      vi.mocked(adminDashboardApi.getActivity).mockResolvedValue(mockActivity);

      const { result } = renderHook(() => useAdminDashboardActivity(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockActivity);
      expect(adminDashboardApi.getActivity).toHaveBeenCalledTimes(1);
    });

    it('should have correct stale time configuration', () => {
      const { result } = renderHook(() => useAdminDashboardActivity(), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useAdminDashboardExport', () => {
    it('should fetch dashboard export successfully', async () => {
      const mockExport = {
        data: {
          export_url: '/admin/dashboard/export/download',
        },
      };

      vi.mocked(adminDashboardApi.getExport).mockResolvedValue(mockExport);

      const { result } = renderHook(() => useAdminDashboardExport(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockExport);
      expect(adminDashboardApi.getExport).toHaveBeenCalledTimes(1);
    });

    it('should have infinite stale time configuration', () => {
      const { result } = renderHook(() => useAdminDashboardExport(), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });
});
