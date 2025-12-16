import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectHealthWidget } from '../ProjectHealthWidget';
import { useAuthStore } from '../../../auth/store';
import { useProjectHealthPortfolio } from '../../../reports/hooks';
import type { ProjectHealthPortfolioItem } from '../../../reports/api';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../../reports/hooks', () => ({
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

describe('ProjectHealthWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    
    // Default mock implementations
    mockUseAuthStore.mockReturnValue({
      hasTenantPermission: vi.fn(() => true),
    } as any);
    
    mockUseProjectHealthPortfolio.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
      refetch: vi.fn(),
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
    
    vi.clearAllMocks();
  });

  describe('Permission checks', () => {
    it('should not render widget when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      const { container } = render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(container.firstChild).toBeNull();
      // Verify hook is called with enabled: false
      expect(mockUseProjectHealthPortfolio).toHaveBeenCalledWith({ enabled: false });
    });

    it('should render widget when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByTestId('project-health-widget')).toBeInTheDocument();
      expect(screen.getByText('Sức khỏe dự án')).toBeInTheDocument();
      // Verify hook is called with enabled: true
      expect(mockUseProjectHealthPortfolio).toHaveBeenCalledWith({ enabled: true });
    });
  });

  describe('Loading state', () => {
    it('should show loading message when loading', () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Đang tải dữ liệu sức khỏe dự án...')).toBeInTheDocument();
    });
  });

  describe('Error state', () => {
    it('should show error message when error occurs', () => {
      const mockRefetch = vi.fn();
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Không tải được dữ liệu sức khỏe dự án.')).toBeInTheDocument();
      expect(screen.getByTestId('error-message')).toHaveTextContent('Boom');
      expect(screen.getByText('Thử lại')).toBeInTheDocument();
    });

    it('should call refetch when retry button is clicked', async () => {
      const user = userEvent.setup();
      const mockRefetch = vi.fn();
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const retryButton = screen.getByText('Thử lại');
      await user.click(retryButton);

      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('Data rendering', () => {
    it('should render summary counters correctly', () => {
      const mockData: ProjectHealthPortfolioItem[] = [
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
            overdue_tasks: 0,
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
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 2,
            cost_overrun_percent: 5,
          },
        },
        {
          project: {
            id: 'p3',
            code: 'PRJ-003',
            name: 'Dự án 3',
            status: 'active',
            client_name: 'Client C',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 5,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByTestId('count-good')).toHaveTextContent('Tốt: 1');
      expect(screen.getByTestId('count-warning')).toHaveTextContent('Cảnh báo: 1');
      expect(screen.getByTestId('count-critical')).toHaveTextContent('Nguy cấp: 1');
    });

    it('should navigate to the health portfolio when clicking a counter', async () => {
      const mockData: ProjectHealthPortfolioItem[] = [
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
            overdue_tasks: 0,
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
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const user = userEvent.setup();
      await user.click(screen.getByTestId('project-health-counter-critical'));

      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects/health?overall=critical');
    });

    it('should render problematic projects list sorted correctly', () => {
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Tốt',
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
            name: 'Dự án Cảnh báo',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 2,
            cost_overrun_percent: 5,
          },
        },
        {
          project: {
            id: 'p3',
            code: 'PRJ-003',
            name: 'Dự án Nguy cấp',
            status: 'active',
            client_name: 'Client C',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 5,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      // Should only show problematic projects (p2 and p3), not p1
      expect(screen.queryByText('Dự án Tốt')).not.toBeInTheDocument();
      expect(screen.getByText('Dự án Cảnh báo')).toBeInTheDocument();
      expect(screen.getByText('Dự án Nguy cấp')).toBeInTheDocument();

      // Critical should appear before warning
      const projectItems = screen.getAllByTestId(/^project-item-/);
      expect(projectItems[0]).toHaveAttribute('data-testid', 'project-item-p3');
      expect(projectItems[1]).toHaveAttribute('data-testid', 'project-item-p2');

      // Check status badges
      expect(screen.getByTestId('status-p3')).toHaveTextContent('Nguy cấp');
      expect(screen.getByTestId('status-p2')).toHaveTextContent('Cảnh báo');
    });

    it('should limit problematic projects to top 5', () => {
      const mockData: ProjectHealthPortfolioItem[] = Array.from({ length: 10 }, (_, i) => ({
        project: {
          id: `p${i + 1}`,
          code: `PRJ-${String(i + 1).padStart(3, '0')}`,
          name: `Dự án ${i + 1}`,
          status: 'active',
          client_name: `Client ${i + 1}`,
        },
        health: {
          overall_status: i < 5 ? 'critical' : 'warning',
          schedule_status: 'delayed',
          cost_status: 'over_budget',
          tasks_completion_rate: 0.3,
          blocked_tasks_ratio: 0.4,
          overdue_tasks: 10 - i,
          cost_overrun_percent: 25,
        },
      }));

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const projectItems = screen.getAllByTestId(/^project-item-/);
      expect(projectItems.length).toBe(5);
    });

    it('should display project details correctly', () => {
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Test',
            status: 'active',
            client_name: 'Client Test',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.75,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 3,
            cost_overrun_percent: 15,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Dự án Test')).toBeInTheDocument();
      expect(screen.getByText('(PRJ-001)')).toBeInTheDocument();
      expect(screen.getByText('Client Test')).toBeInTheDocument();
      expect(screen.getByText('Quá hạn: 3 task')).toBeInTheDocument();
      expect(screen.getByText('Hoàn thành: 75%')).toBeInTheDocument();
    });
  });

  describe('Empty states', () => {
    it('should show empty message when no data', () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Chưa có dữ liệu sức khỏe dự án')).toBeInTheDocument();
    });

    it('should show success message when all projects are good', () => {
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Tốt',
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
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Không có dự án nào ở trạng thái cảnh báo / nguy cấp 🎉')).toBeInTheDocument();
      // Counters should still show
      expect(screen.getByTestId('count-good')).toHaveTextContent('Tốt: 1');
    });
  });

  describe('Navigation', () => {
    it('should navigate to project overview when button is clicked', async () => {
      const user = userEvent.setup();
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Test',
            status: 'active',
            client_name: 'Client Test',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.5,
            blocked_tasks_ratio: 0.3,
            overdue_tasks: 5,
            cost_overrun_percent: 20,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const viewButton = screen.getByTestId('view-overview-p1');
      await user.click(viewButton);

      expect(mockNavigate).toHaveBeenCalledWith('/app/projects/p1/overview');
    });

    // Round 81: Test counter clicks navigate to health portfolio with filter
    it('should navigate to health portfolio with critical filter when critical counter is clicked', async () => {
      const user = userEvent.setup();
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Dự án Critical',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 5,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const criticalCounter = screen.getByTestId('project-health-counter-critical');
      await user.click(criticalCounter);

      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects/health?overall=critical');
    });

    it('should navigate to health portfolio with good filter when good counter is clicked', async () => {
      const user = userEvent.setup();
      const mockData: ProjectHealthPortfolioItem[] = [
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
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const goodCounter = screen.getByTestId('project-health-counter-good');
      await user.click(goodCounter);

      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects/health?overall=good');
    });

    it('should navigate to health portfolio with warning filter when warning counter is clicked', async () => {
      const user = userEvent.setup();
      const mockData: ProjectHealthPortfolioItem[] = [
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
            overdue_tasks: 2,
            cost_overrun_percent: 5,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const warningCounter = screen.getByTestId('project-health-counter-warning');
      await user.click(warningCounter);

      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects/health?overall=warning');
    });
  });

  describe('Sorting logic', () => {
    it('should sort by status priority (critical > warning) then by overdue_tasks', () => {
      const mockData: ProjectHealthPortfolioItem[] = [
        {
          project: {
            id: 'p1',
            code: 'PRJ-001',
            name: 'Warning với ít overdue',
            status: 'active',
            client_name: 'Client A',
          },
          health: {
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 1,
            cost_overrun_percent: 5,
          },
        },
        {
          project: {
            id: 'p2',
            code: 'PRJ-002',
            name: 'Critical với nhiều overdue',
            status: 'active',
            client_name: 'Client B',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 10,
            cost_overrun_percent: 25,
          },
        },
        {
          project: {
            id: 'p3',
            code: 'PRJ-003',
            name: 'Warning với nhiều overdue',
            status: 'active',
            client_name: 'Client C',
          },
          health: {
            overall_status: 'warning',
            schedule_status: 'at_risk',
            cost_status: 'at_risk',
            tasks_completion_rate: 0.6,
            blocked_tasks_ratio: 0.2,
            overdue_tasks: 5,
            cost_overrun_percent: 5,
          },
        },
        {
          project: {
            id: 'p4',
            code: 'PRJ-004',
            name: 'Critical với ít overdue',
            status: 'active',
            client_name: 'Client D',
          },
          health: {
            overall_status: 'critical',
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            tasks_completion_rate: 0.3,
            blocked_tasks_ratio: 0.4,
            overdue_tasks: 2,
            cost_overrun_percent: 25,
          },
        },
      ];

      mockUseProjectHealthPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ProjectHealthWidget />, { wrapper: createWrapper() });

      const projectItems = screen.getAllByTestId(/^project-item-/);
      
      // Expected order: p2 (critical, 10 overdue) > p4 (critical, 2 overdue) > p3 (warning, 5 overdue) > p1 (warning, 1 overdue)
      expect(projectItems[0]).toHaveAttribute('data-testid', 'project-item-p2');
      expect(projectItems[1]).toHaveAttribute('data-testid', 'project-item-p4');
      expect(projectItems[2]).toHaveAttribute('data-testid', 'project-item-p3');
      expect(projectItems[3]).toHaveAttribute('data-testid', 'project-item-p1');
    });
  });
});
