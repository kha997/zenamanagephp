import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import {
  useDashboardStats,
  useRecentProjects,
  useDashboardAlerts,
  useMarkAlertAsRead,
  useMarkAllAlertsAsRead,
} from '../hooks';
import { dashboardApi } from '../api';
import type { DashboardStats, RecentProject, DashboardAlert } from '../types';

// Mock the API
vi.mock('../api', () => ({
  dashboardApi: {
    getStats: vi.fn(),
    getRecentProjects: vi.fn(),
    getAlerts: vi.fn(),
    markAlertAsRead: vi.fn(),
    markAllAlertsAsRead: vi.fn(),
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

  return ({ children }: { children: React.ReactNode }) => {
    return React.createElement(QueryClientProvider, { client: queryClient }, children);
  };
};

describe('Dashboard Hooks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('useDashboardStats', () => {
    it('should fetch dashboard stats successfully', async () => {
      const mockStats: DashboardStats = {
        projects: { total: 10, active: 5, completed: 5 },
        tasks: { total: 20, completed: 10, in_progress: 5, overdue: 2 },
        users: { total: 8, active: 6 },
      };

      vi.mocked(dashboardApi.getStats).mockResolvedValue(mockStats);

      const { result } = renderHook(() => useDashboardStats(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual(mockStats);
      expect(dashboardApi.getStats).toHaveBeenCalledTimes(1);
    });

    it('should handle error state', async () => {
      const error = new Error('Failed to fetch stats');
      vi.mocked(dashboardApi.getStats).mockRejectedValue(error);

      const { result } = renderHook(() => useDashboardStats(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isError).toBe(true));

      expect(result.current.error).toBeDefined();
    });

    it('should show loading state initially', () => {
      vi.mocked(dashboardApi.getStats).mockImplementation(
        () => new Promise(() => {}) // Never resolves
      );

      const { result } = renderHook(() => useDashboardStats(), {
        wrapper: createWrapper(),
      });

      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useRecentProjects', () => {
    it('should fetch recent projects successfully', async () => {
      const mockProjects: RecentProject[] = [
        {
          id: '1',
          name: 'Project Alpha',
          status: 'active',
          progress: 75,
          updated_at: new Date().toISOString(),
        },
      ];

      vi.mocked(dashboardApi.getRecentProjects).mockResolvedValue(mockProjects);

      const { result } = renderHook(() => useRecentProjects(5), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual(mockProjects);
      expect(dashboardApi.getRecentProjects).toHaveBeenCalledWith(5);
    });

    it('should handle empty projects list', async () => {
      vi.mocked(dashboardApi.getRecentProjects).mockResolvedValue([]);

      const { result } = renderHook(() => useRecentProjects(5), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual([]);
    });
  });

  describe('useDashboardAlerts', () => {
    it('should fetch alerts successfully', async () => {
      const mockAlerts: DashboardAlert[] = [
        {
          id: '1',
          type: 'warning',
          message: '3 tasks are overdue',
          created_at: new Date().toISOString(),
        },
      ];

      vi.mocked(dashboardApi.getAlerts).mockResolvedValue(mockAlerts);

      const { result } = renderHook(() => useDashboardAlerts(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual(mockAlerts);
    });

    it('should handle empty alerts list', async () => {
      vi.mocked(dashboardApi.getAlerts).mockResolvedValue([]);

      const { result } = renderHook(() => useDashboardAlerts(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual([]);
    });
  });

  describe('useMarkAlertAsRead', () => {
    it('should mark alert as read and invalidate queries', async () => {
      vi.mocked(dashboardApi.markAlertAsRead).mockResolvedValue();

      const { result } = renderHook(() => useMarkAlertAsRead(), {
        wrapper: createWrapper(),
      });

      result.current.mutate('1');

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(dashboardApi.markAlertAsRead).toHaveBeenCalledWith('1');
    });

    it('should handle error when marking alert as read', async () => {
      const error = new Error('Failed to mark as read');
      vi.mocked(dashboardApi.markAlertAsRead).mockRejectedValue(error);

      const { result } = renderHook(() => useMarkAlertAsRead(), {
        wrapper: createWrapper(),
      });

      result.current.mutate('1');

      await waitFor(() => expect(result.current.isError).toBe(true));

      expect(result.current.error).toBeDefined();
    });
  });

  describe('useMarkAllAlertsAsRead', () => {
    it('should mark all alerts as read and invalidate queries', async () => {
      vi.mocked(dashboardApi.markAllAlertsAsRead).mockResolvedValue();

      const { result } = renderHook(() => useMarkAllAlertsAsRead(), {
        wrapper: createWrapper(),
      });

      result.current.mutate();

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(dashboardApi.markAllAlertsAsRead).toHaveBeenCalled();
    });
  });
});

