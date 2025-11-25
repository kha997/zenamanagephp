import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectHealthPortfolioPage } from '../ProjectHealthPortfolioPage';
import { useAuthStore } from '../../../auth/store';
import { useProjectHealthPortfolio } from '../../hooks';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../hooks', () => ({
  useProjectHealthPortfolio: vi.fn(),
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseProjectHealthPortfolio = vi.mocked(useProjectHealthPortfolio);

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
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('ProjectHealthPortfolioPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    
    // Default mock implementations
    mockUseProjectHealthPortfolio.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);
  });

  afterEach(() => {
    cleanup();
    
    // Clean up QueryClient instances
    queryClients.forEach(client => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    
    window.history.replaceState({}, '', '/');
    
    vi.clearAllMocks();
  });

  describe('Permission checks', () => {
    it('should render AccessRestricted when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText(/don't have permission to view project health reports/i)).toBeInTheDocument();
    });

    it('should render page when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText('Tổng quan sức khỏe dự án')).toBeInTheDocument();
    });
  });

  describe('Table rendering', () => {
    it('should render table with project data', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án 1',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 1,
            cost_overrun_percent: 0,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Dự án 2',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 7,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Check title
      expect(screen.getByText('Tổng quan sức khỏe dự án')).toBeInTheDocument();

      // Check project names
      expect(screen.getByText('Dự án 1')).toBeInTheDocument();
      expect(screen.getByText('Dự án 2')).toBeInTheDocument();

      // Check project codes
      expect(screen.getByText('PRJ-001')).toBeInTheDocument();
      expect(screen.getByText('PRJ-002')).toBeInTheDocument();

      // Check status labels (use getAllByText since "Tốt" appears in both filter button and table)
      const goodLabels = screen.getAllByText('Tốt');
      expect(goodLabels.length).toBeGreaterThan(0);
      const criticalLabels = screen.getAllByText('Nguy cấp');
      expect(criticalLabels.length).toBeGreaterThan(0);

      // Check overdue tasks
      expect(screen.getByText('1')).toBeInTheDocument();
      expect(screen.getByText('7')).toBeInTheDocument();

      // Check completion percentages
      expect(screen.getByText('80%')).toBeInTheDocument();
      expect(screen.getByText('30%')).toBeInTheDocument();

      // Check blocked percentages
      expect(screen.getByText('10%')).toBeInTheDocument();
      expect(screen.getByText('40%')).toBeInTheDocument();
    });
  });

  describe('Filter overall_status', () => {
    it('should filter by good status', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án 1',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 1,
            cost_overrun_percent: 0,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Dự án 2',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 7,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Click "Nguy cấp" filter
      const criticalButton = screen.getByTestId('filter-critical');
      await userEvent.click(criticalButton);

      // Should only show "Dự án 2"
      expect(screen.getByText('Dự án 2')).toBeInTheDocument();
      expect(screen.queryByText('Dự án 1')).not.toBeInTheDocument();

      // Click "Tất cả" to show all again
      const allButton = screen.getByTestId('filter-all');
      await userEvent.click(allButton);

      // Should show both projects again
      expect(screen.getByText('Dự án 1')).toBeInTheDocument();
      expect(screen.getByText('Dự án 2')).toBeInTheDocument();
    });

    it('should filter by warning status', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án 1',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 3,
            cost_overrun_percent: 10,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Click "Cảnh báo" filter
      const warningButton = screen.getByTestId('filter-warning');
      await userEvent.click(warningButton);

      // Should show "Dự án 1"
      expect(screen.getByText('Dự án 1')).toBeInTheDocument();
      // Check status label (use getAllByText since "Cảnh báo" appears in both filter button and table)
      const warningLabels = screen.getAllByText('Cảnh báo');
      expect(warningLabels.length).toBeGreaterThan(0);
    });
  });

  describe('Loading state', () => {
    it('should show loading message when isLoading is true', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Đang tải báo cáo sức khỏe dự án/i)).toBeInTheDocument();
    });
  });

  describe('Error state', () => {
    it('should show error message when isError is true', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: new Error('Boom'),
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Không tải được báo cáo sức khỏe dự án/i)).toBeInTheDocument();
      expect(screen.getByTestId('error-message')).toBeInTheDocument();
      expect(screen.getByTestId('error-message')).toHaveTextContent('Boom');
    });

    it('should show default error message when error message is not available', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Không tải được báo cáo sức khỏe dự án/i)).toBeInTheDocument();
      expect(screen.getByTestId('error-message')).toHaveTextContent('Đã xảy ra lỗi');
    });
  });

  describe('Navigation to Project Overview', () => {
    it('should navigate to project overview when clicking "Xem Overview" button', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án 1',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 1,
            cost_overrun_percent: 0,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Find and click "Xem Overview" button
      const overviewButtons = screen.getAllByText('Xem Overview');
      expect(overviewButtons.length).toBeGreaterThan(0);
      
      await userEvent.click(overviewButtons[0]);

      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/app/projects/p1/overview');
      });
    });
  });

  describe('Empty state', () => {
    it('should show empty message when filtered items is empty', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Không có dự án nào phù hợp bộ lọc/i)).toBeInTheDocument();
    });
  });

  describe('Export CSV button', () => {
    beforeEach(() => {
      // Mock window.open
      global.window.open = vi.fn();
    });

    afterEach(() => {
      vi.restoreAllMocks();
    });

    it('should render Export CSV button when user has tenant.view_reports permission', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByTestId('export-csv-button');
      expect(exportButton).toBeInTheDocument();
      expect(exportButton).toHaveTextContent('Xuất CSV');
    });

    it('should not render Export CSV button when user does not have tenant.view_reports permission', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('export-csv-button')).not.toBeInTheDocument();
      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
    });

    it('should open export URL in new tab when Export CSV button is clicked', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByTestId('export-csv-button');
      await userEvent.click(exportButton);

      expect(window.open).toHaveBeenCalledWith(
        '/api/v1/app/reports/projects/health/export',
        '_blank',
        'noopener'
      );
    });
  });

  // Round 81: Test query param handling
  describe('Query param handling', () => {
    it('should initialize filter from query param overall=critical', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Good',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 0,
            cost_overrun_percent: 0,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Dự án Critical',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 7,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      window.history.replaceState({}, '', '/app/reports/projects/health?overall=critical');
      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Only critical project should be visible (filter initialized from query param)
      expect(screen.getByText('Dự án Critical')).toBeInTheDocument();
      expect(screen.queryByText('Dự án Good')).not.toBeInTheDocument();
    });

    it('should initialize filter from query param overall=good', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Good',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 0,
            cost_overrun_percent: 0,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Dự án Critical',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 7,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      window.history.replaceState({}, '', '/app/reports/projects/health?overall=good');
      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Only good project should be visible (filter initialized from query param)
      expect(screen.getByText('Dự án Good')).toBeInTheDocument();
      expect(screen.queryByText('Dự án Critical')).not.toBeInTheDocument();
    });

    it('should default to all filter when query param is missing', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Good',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'good',
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            tasks_completion_rate: 0.8,
            blocked_tasks_ratio: 0.1,
            overdue_tasks: 0,
            cost_overrun_percent: 0,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Dự án Critical',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 7,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      window.history.replaceState({}, '', '/app/reports/projects/health');
      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Both projects should be visible (default to 'all' filter when no query param)
      expect(screen.getByText('Dự án Good')).toBeInTheDocument();
      expect(screen.getByText('Dự án Critical')).toBeInTheDocument();
    });

    it('should update URL query param when filter button is clicked', async () => {
      const user = userEvent.setup();
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Warning',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 3,
            cost_overrun_percent: 10,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      window.history.replaceState({}, '', '/app/reports/projects/health');
      render(<ProjectHealthPortfolioPage />, { wrapper: createWrapper() });

      // Click warning filter
      const warningButton = screen.getByTestId('filter-warning');
      await user.click(warningButton);

      // Confirm query param is reflected in the address bar
      await waitFor(() => {
        expect(window.location.search).toBe('?overall=warning');
        expect(screen.getByText('Dự án Warning')).toBeInTheDocument();
      });

      // Click all filter
      const allButton = screen.getByTestId('filter-all');
      await user.click(allButton);

      // Verify all projects are visible (filter changed to 'all')
      await waitFor(() => {
        expect(window.location.search).toBe('');
        expect(screen.getByText('Dự án Warning')).toBeInTheDocument();
      });
    });
  });
});
