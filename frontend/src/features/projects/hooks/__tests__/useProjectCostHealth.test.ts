import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import { useProjectCostHealth } from '../../hooks';
import { projectsApi } from '../../api';
import type { ProjectCostHealthResponse } from '../../api';

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    getProjectCostHealth: vi.fn(),
  },
}));

const mockGetProjectCostHealth = vi.mocked(projectsApi.getProjectCostHealth);

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

const mockHealthData: ProjectCostHealthResponse = {
  project_id: 'project-123',
  cost_health_status: 'ON_BUDGET',
  stats: {
    budget_total: 1000000,
    forecast_final_cost: 980000,
    variance_vs_budget: -20000,
    pending_change_orders_total: 0,
  },
};

describe('useProjectCostHealth', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches cost health data successfully', async () => {
    mockGetProjectCostHealth.mockResolvedValue({ data: mockHealthData });

    const { result } = renderHook(
      () => useProjectCostHealth('project-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.data).toEqual(mockHealthData);
    expect(mockGetProjectCostHealth).toHaveBeenCalledWith('project-123');
  });

  it('does not fetch when projectId is undefined', () => {
    const { result } = renderHook(
      () => useProjectCostHealth(undefined),
      { wrapper: createWrapper() }
    );

    expect(result.current.isFetching).toBe(false);
    expect(mockGetProjectCostHealth).not.toHaveBeenCalled();
  });

  it('handles error state', async () => {
    const error = new Error('Failed to fetch');
    mockGetProjectCostHealth.mockRejectedValue(error);

    const { result } = renderHook(
      () => useProjectCostHealth('project-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error).toBe(error);
  });
});
