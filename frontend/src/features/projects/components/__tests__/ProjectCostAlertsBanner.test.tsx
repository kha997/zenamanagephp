import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectCostAlertsBanner } from '../ProjectCostAlertsBanner';
import { useProjectCostAlerts } from '../../hooks';
import type { ProjectCostAlertsResponse } from '../../api';

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

describe('ProjectCostAlertsBanner', () => {
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
      <ProjectCostAlertsBanner projectId="project-123" />,
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
      <ProjectCostAlertsBanner projectId="project-123" />,
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
      <ProjectCostAlertsBanner projectId="project-123" />,
      { wrapper: createWrapper() }
    );

    expect(container.firstChild).toBeNull();
  });

  it('renders pending change orders overdue alert', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: ['pending_change_orders_overdue'],
          details: {
            pending_co_count: 2,
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

    render(<ProjectCostAlertsBanner projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/Cost Alerts/i)).toBeInTheDocument();
    expect(screen.getByText(/1 pending change order overdue/i)).toBeInTheDocument();
  });

  it('renders approved certificates unpaid alert', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: ['approved_certificates_unpaid'],
          details: {
            pending_co_count: 0,
            overdue_co_count: 0,
            unpaid_certificates_count: 2,
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

    render(<ProjectCostAlertsBanner projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/2 approved certificates unpaid/i)).toBeInTheDocument();
  });

  it('renders cost health warning alert', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: ['cost_health_warning'],
          details: {
            pending_co_count: 0,
            overdue_co_count: 0,
            unpaid_certificates_count: 0,
            cost_health_status: 'AT_RISK',
            pending_change_orders_total: '0.00',
            budget_total: '1000000.00',
            threshold_days: 14,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectCostAlertsBanner projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/Cost health: At Risk/i)).toBeInTheDocument();
  });

  it('renders pending CO high impact alert', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: ['pending_co_high_impact'],
          details: {
            pending_co_count: 1,
            overdue_co_count: 0,
            unpaid_certificates_count: 0,
            cost_health_status: 'ON_BUDGET',
            pending_change_orders_total: '120000.00',
            budget_total: '1000000.00',
            threshold_days: 14,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectCostAlertsBanner projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/High pending CO impact/i)).toBeInTheDocument();
    expect(screen.getByText(/12.0% of budget/i)).toBeInTheDocument();
  });

  it('renders multiple alerts', () => {
    mockUseProjectCostAlerts.mockReturnValue({
      data: {
        data: {
          project_id: 'project-123',
          alerts: [
            'pending_change_orders_overdue',
            'approved_certificates_unpaid',
            'cost_health_warning',
          ],
          details: {
            pending_co_count: 2,
            overdue_co_count: 1,
            unpaid_certificates_count: 1,
            cost_health_status: 'AT_RISK',
            pending_change_orders_total: '50000.00',
            budget_total: '1000000.00',
            threshold_days: 14,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectCostAlertsBanner projectId="project-123" />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/Cost Alerts/i)).toBeInTheDocument();
    expect(screen.getByText(/1 pending change order overdue/i)).toBeInTheDocument();
    expect(screen.getByText(/1 approved certificate unpaid/i)).toBeInTheDocument();
    expect(screen.getByText(/Cost health: At Risk/i)).toBeInTheDocument();
  });
});
