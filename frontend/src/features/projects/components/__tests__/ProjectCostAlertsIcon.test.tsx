import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectCostAlertsIcon } from '../ProjectCostAlertsIcon';
import { useProjectCostAlerts } from '../../hooks';

// Mock the hook
vi.mock('../../hooks', () => ({
  useProjectCostAlerts: vi.fn(),
}));

const mockUseProjectCostAlerts = vi.mocked(useProjectCostAlerts);

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

describe('ProjectCostAlertsIcon', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when no alerts', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: [],
          details: {
            pending_co_count: 0,
            overdue_co_count: 0,
            unpaid_certificates_count: 0,
            cost_health_status: 'ON_BUDGET',
            pending_change_orders_total: '0.00',
            budget_total: '1000000.00',
            threshold_days: 14,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    const { container } = render(
      <ProjectCostAlertsIcon projectId="project-123" />,
      { wrapper: createWrapper() }
    );

    expect(container.firstChild).toBeNull();
  });

  it('renders nothing when loading', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as any);

    const { container } = render(
      <ProjectCostAlertsIcon projectId="project-123" />,
      { wrapper: createWrapper() }
    );

    expect(container.firstChild).toBeNull();
  });

  it('renders nothing when error', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: new Error('Failed to load'),
    } as any);

    const { container } = render(
      <ProjectCostAlertsIcon projectId="project-123" />,
      { wrapper: createWrapper() }
    );

    expect(container.firstChild).toBeNull();
  });

  it('renders alert icon when alerts exist', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: ['pending_change_orders_overdue'],
          details: {
            pending_co_count: 1,
            overdue_co_count: 1,
            unpaid_certificates_count: 0,
            cost_health_status: 'ON_BUDGET',
            pending_change_orders_total: '50000.00',
            budget_total: '1000000.00',
            threshold_days: 14,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectCostAlertsIcon projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    const icon = screen.getByRole('img', { name: /Cost alerts/i });
    expect(icon).toBeInTheDocument();
    expect(icon).toHaveAttribute('title', 'This project has cost alerts');
  });
});
