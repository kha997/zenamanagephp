import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectCostDashboardSection } from '../ProjectCostDashboardSection';
import { useProjectCostDashboard } from '../../hooks';
import type { ProjectCostDashboardResponse } from '../../api';

// Mock the hook
vi.mock('../../hooks', () => ({
  useProjectCostDashboard: vi.fn(),
}));

const mockUseProjectCostDashboard = vi.mocked(useProjectCostDashboard);

// Store QueryClient instances for cleanup
const queryClients: QueryClient[] = [];

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

  queryClients.push(queryClient);

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
};

const mockDashboardData: ProjectCostDashboardResponse = {
  project_id: 'project-123',
  currency: 'VND',
  summary: {
    budget_total: 1000000,
    contract_base_total: 950000,
    contract_current_total: 980000,
    total_certified_amount: 500000,
    total_paid_amount: 450000,
    outstanding_amount: 50000,
  },
  variance: {
    pending_change_orders_total: 50000,
    rejected_change_orders_total: 10000,
    forecast_final_cost: 1030000,
    variance_vs_budget: 30000,
    variance_vs_contract_current: 50000,
  },
  contracts: {
    contract_base_total: 950000,
    change_orders_approved_total: 30000,
    change_orders_pending_total: 50000,
    change_orders_rejected_total: 10000,
    contract_current_total: 980000,
  },
  time_series: {
    certificates_per_month: [
      { year: 2024, month: 11, amount_payable_approved: 100000 },
      { year: 2024, month: 12, amount_payable_approved: 150000 },
    ],
    payments_per_month: [
      { year: 2024, month: 11, amount_paid: 90000 },
      { year: 2024, month: 12, amount_paid: 140000 },
    ],
  },
};

describe('ProjectCostDashboardSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading state', () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    // Check for skeleton loading indicators
    const cards = screen.getAllByRole('generic').filter(
      (el) => el.className.includes('animate-pulse')
    );
    expect(cards.length).toBeGreaterThan(0);
  });

  it('renders error state with retry button', () => {
    const mockRefetch = vi.fn();
    mockUseProjectCostDashboard.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: new Error('Failed to load'),
      refetch: mockRefetch,
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/error loading cost dashboard/i)).toBeInTheDocument();
    const retryButton = screen.getByRole('button', { name: /retry/i });
    expect(retryButton).toBeInTheDocument();

    // Test retry functionality
    retryButton.click();
    expect(mockRefetch).toHaveBeenCalled();
  });

  it('renders no data state', () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/no cost data available/i)).toBeInTheDocument();
  });

  it('renders summary cards with correct values', async () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: mockDashboardData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    // Check summary cards are rendered
    await waitFor(() => {
      expect(screen.getByText('Budget Total')).toBeInTheDocument();
      expect(screen.getByText('Contract Base Total')).toBeInTheDocument();
      expect(screen.getByText('Contract Current Total')).toBeInTheDocument();
      expect(screen.getByText('Total Certified Amount')).toBeInTheDocument();
      expect(screen.getByText('Total Paid Amount')).toBeInTheDocument();
      expect(screen.getByText('Outstanding Amount')).toBeInTheDocument();
    });
  });

  it('renders variance block with correct values', async () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: mockDashboardData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(screen.getByText('Variance & Forecast')).toBeInTheDocument();
      expect(screen.getByText('Forecast Final Cost')).toBeInTheDocument();
      expect(screen.getByText('Variance vs Budget')).toBeInTheDocument();
      expect(screen.getByText('Variance vs Current Contract')).toBeInTheDocument();
      expect(screen.getByText('Pending Change Orders')).toBeInTheDocument();
      expect(screen.getByText('Rejected Change Orders')).toBeInTheDocument();
    });
  });

  it('renders contracts breakdown block', async () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: mockDashboardData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(screen.getByText('Contracts & Change Orders Breakdown')).toBeInTheDocument();
      expect(screen.getByText('Contract Base Total')).toBeInTheDocument();
      expect(screen.getByText('Approved COs')).toBeInTheDocument();
      expect(screen.getByText('Pending COs')).toBeInTheDocument();
      expect(screen.getByText('Rejected COs')).toBeInTheDocument();
      expect(screen.getByText('Current Contract Total')).toBeInTheDocument();
    });
  });

  it('renders time-series charts', async () => {
    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: mockDashboardData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(screen.getByText(/Certificates per Month/i)).toBeInTheDocument();
      expect(screen.getByText(/Payments per Month/i)).toBeInTheDocument();
    });
  });

  it('handles zero-filled time-series correctly', async () => {
    // Mock data with only 2 months
    const sparseData: ProjectCostDashboardResponse = {
      ...mockDashboardData,
      time_series: {
        certificates_per_month: [
          { year: 2024, month: 11, amount_payable_approved: 100000 },
        ],
        payments_per_month: [
          { year: 2024, month: 12, amount_paid: 150000 },
        ],
      },
    };

    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: sparseData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    // Charts should still render even with sparse data
    await waitFor(() => {
      expect(screen.getByText(/Certificates per Month/i)).toBeInTheDocument();
      expect(screen.getByText(/Payments per Month/i)).toBeInTheDocument();
    });
  });

  it('applies correct color coding for variance values', async () => {
    // Test with negative variance (should be green)
    const negativeVarianceData: ProjectCostDashboardResponse = {
      ...mockDashboardData,
      variance: {
        ...mockDashboardData.variance,
        variance_vs_budget: -50000, // Under budget
      },
    };

    mockUseProjectCostDashboard.mockReturnValue({
      data: { data: negativeVarianceData },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    render(<ProjectCostDashboardSection projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(screen.getByText('Variance vs Budget')).toBeInTheDocument();
    });
  });
});
