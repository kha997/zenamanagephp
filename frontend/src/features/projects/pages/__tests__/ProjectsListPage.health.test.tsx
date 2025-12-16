import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectsListPage } from '../ProjectsListPage';
import { useAuthStore } from '../../../auth/store';
import { useProjectHealthPortfolio } from '../../../reports/hooks';
import { useProjects, useProjectsKpis, useProjectsActivity, useProjectsAlerts } from '../../hooks';
import type { ProjectHealthPortfolioItem } from '../../../reports/api';
import type { Project } from '../../types';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../../reports/hooks', () => ({
  useProjectHealthPortfolio: vi.fn(),
}));

// Mock the projects hooks
vi.mock('../../hooks', () => ({
  useProjects: vi.fn(),
  useProjectsKpis: vi.fn(),
  useProjectsActivity: vi.fn(),
  useProjectsAlerts: vi.fn(),
  useUpdateProject: vi.fn(() => ({
    mutateAsync: vi.fn(),
    isPending: false,
  })),
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
const mockSetSearchParams = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useSearchParams: () => [
      new URLSearchParams(),
      mockSetSearchParams,
    ],
  };
});

// Mock theme provider
vi.mock('../../../shared/theme/ThemeProvider', () => ({
  useTheme: () => ({ theme: 'light' }),
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseProjectHealthPortfolio = vi.mocked(useProjectHealthPortfolio);
const mockUseProjects = vi.mocked(useProjects);
const mockUseProjectsKpis = vi.mocked(useProjectsKpis);
const mockUseProjectsActivity = vi.mocked(useProjectsActivity);
const mockUseProjectsAlerts = vi.mocked(useProjectsAlerts);

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

// Helper to create mock projects
const createMockProject = (id: string | number, name: string, status: Project['status'] = 'active'): Project => ({
  id,
  name,
  status,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
});

// Helper to create mock health items
const createMockHealthItem = (
  projectId: string,
  overallStatus: 'good' | 'warning' | 'critical'
): ProjectHealthPortfolioItem => ({
  project: {
    id: projectId,
    code: `PROJ-${projectId}`,
    name: `Project ${projectId}`,
    status: 'active',
  },
  health: {
    overall_status: overallStatus,
    tasks_completion_rate: 75,
    blocked_tasks_ratio: 0.1,
    overdue_tasks: 0,
    schedule_status: 'on_track',
    cost_status: 'on_budget',
    cost_overrun_percent: null,
  },
});

describe('ProjectsListPage - Health Features', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    mockSetSearchParams.mockClear();
    
    // Default mock implementations
    mockUseAuthStore.mockReturnValue({
      hasTenantPermission: vi.fn(() => false),
    } as any);
    
    mockUseProjects.mockReturnValue({
      data: {
        data: [
          createMockProject('1', 'Project 1', 'active'),
          createMockProject('2', 'Project 2', 'active'),
          createMockProject('3', 'Project 3', 'active'),
        ],
        meta: {
          current_page: 1,
          last_page: 1,
          total: 3,
        },
      },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);
    
    mockUseProjectsKpis.mockReturnValue({
      data: {
        data: {
          total: 3,
          active: 3,
          completed: 0,
          overdue: 0,
        },
      },
      isLoading: false,
    } as any);
    
    mockUseProjectsActivity.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
    } as any);
    
    mockUseProjectsAlerts.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
    } as any);
    
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
    
    vi.clearAllMocks();
  });

  describe('Permission checks', () => {
    it('should not show health column or filter when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Health filter should not be visible
      expect(screen.queryByTestId('health-filter')).not.toBeInTheDocument();
      
      // Health column should not be in table header
      const table = screen.queryByRole('table');
      if (table) {
        expect(screen.queryByText('Health')).not.toBeInTheDocument();
      }
      
      // Verify hook is called with enabled: false
      expect(mockUseProjectHealthPortfolio).toHaveBeenCalledWith({ enabled: false });
    });

    it('should show health column and filter when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);
      
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Health filter should be visible
      expect(screen.getByTestId('health-filter')).toBeInTheDocument();
      expect(screen.getByTestId('health-filter-all')).toBeInTheDocument();
      expect(screen.getByTestId('health-filter-good')).toBeInTheDocument();
      expect(screen.getByTestId('health-filter-warning')).toBeInTheDocument();
      expect(screen.getByTestId('health-filter-critical')).toBeInTheDocument();
      
      // Switch to table view to see health column
      const tableButton = screen.getByRole('button', { name: /table/i });
      userEvent.click(tableButton);
      
      // Wait for table to render
      waitFor(() => {
        expect(screen.getByText('Health')).toBeInTheDocument();
      });
      
      // Verify hook is called with enabled: true
      expect(mockUseProjectHealthPortfolio).toHaveBeenCalledWith({ enabled: true });
    });
  });

  describe('Health badges in table view', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);
    });

    it('should display health badges with correct labels and tones', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      await waitFor(() => {
        // Check for health badges
        expect(screen.getByTestId('project-health-1')).toBeInTheDocument();
        expect(screen.getByTestId('project-health-2')).toBeInTheDocument();
        expect(screen.getByTestId('project-health-3')).toBeInTheDocument();
        
        // Check labels
        expect(screen.getByText('Tốt')).toBeInTheDocument();
        expect(screen.getByText('Cảnh báo')).toBeInTheDocument();
        expect(screen.getByText('Nguy cấp')).toBeInTheDocument();
      });
    });

    it('should show fallback (—) when project has no health data', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          // Project 2 and 3 have no health data
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      await waitFor(() => {
        // Project 1 has health badge
        expect(screen.getByTestId('project-health-1')).toBeInTheDocument();
        
        // Projects 2 and 3 should show fallback
        expect(screen.getByTestId('project-health-empty-2')).toBeInTheDocument();
        expect(screen.getByTestId('project-health-empty-3')).toBeInTheDocument();
      });
    });
  });

  describe('Health filter functionality', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);
    });

    it('should filter projects by health status: good', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      // Wait for initial render
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
        expect(screen.getByText('Project 2')).toBeInTheDocument();
        expect(screen.getByText('Project 3')).toBeInTheDocument();
      });

      // Click "Tốt" filter
      const goodFilter = screen.getByTestId('health-filter-good');
      await userEvent.click(goodFilter);

      // Only Project 1 (good) should be visible
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
        expect(screen.queryByText('Project 2')).not.toBeInTheDocument();
        expect(screen.queryByText('Project 3')).not.toBeInTheDocument();
      });
    });

    it('should filter projects by health status: warning', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      // Wait for initial render
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
      });

      // Click "Cảnh báo" filter
      const warningFilter = screen.getByTestId('health-filter-warning');
      await userEvent.click(warningFilter);

      // Only Project 2 (warning) should be visible
      await waitFor(() => {
        expect(screen.queryByText('Project 1')).not.toBeInTheDocument();
        expect(screen.getByText('Project 2')).toBeInTheDocument();
        expect(screen.queryByText('Project 3')).not.toBeInTheDocument();
      });
    });

    it('should show all projects when filter is set to "all"', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      // Wait for initial render
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
      });

      // First filter by warning
      const warningFilter = screen.getByTestId('health-filter-warning');
      await userEvent.click(warningFilter);

      await waitFor(() => {
        expect(screen.queryByText('Project 1')).not.toBeInTheDocument();
        expect(screen.getByText('Project 2')).toBeInTheDocument();
      });

      // Then click "Tất cả" to show all
      const allFilter = screen.getByTestId('health-filter-all');
      await userEvent.click(allFilter);

      // All projects should be visible again
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
        expect(screen.getByText('Project 2')).toBeInTheDocument();
        expect(screen.getByText('Project 3')).toBeInTheDocument();
      });
    });
  });

  describe('Health error handling', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);
    });

    it('should not break page when health API returns error', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Projects table should still render
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
        expect(screen.getByText('Project 2')).toBeInTheDocument();
        expect(screen.getByText('Project 3')).toBeInTheDocument();
      });

      // Error hint should be visible
      expect(screen.getByTestId('health-error-hint')).toBeInTheDocument();
      expect(screen.getByText('Không tải được dữ liệu health của dự án.')).toBeInTheDocument();

      // Switch to table view
      const tableButton = screen.getByRole('button', { name: /table/i });
      await userEvent.click(tableButton);

      // Health cells should show fallback
      await waitFor(() => {
        expect(screen.getByTestId('project-health-empty-1')).toBeInTheDocument();
        expect(screen.getByTestId('project-health-empty-2')).toBeInTheDocument();
        expect(screen.getByTestId('project-health-empty-3')).toBeInTheDocument();
      });
    });
  });

  describe('Health badges in card view', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);
    });

    it('should display health badges in card view', async () => {
      mockUseProjectHealthPortfolio.mockReturnValue({
        data: [
          createMockHealthItem('1', 'good'),
          createMockHealthItem('2', 'warning'),
          createMockHealthItem('3', 'critical'),
        ],
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(<ProjectsListPage />, { wrapper: createWrapper() });

      // Card view is default, wait for cards to render
      await waitFor(() => {
        expect(screen.getByText('Project 1')).toBeInTheDocument();
      });

      // Check for health badges in cards
      expect(screen.getByTestId('project-health-1')).toBeInTheDocument();
      expect(screen.getByTestId('project-health-2')).toBeInTheDocument();
      expect(screen.getByTestId('project-health-3')).toBeInTheDocument();
    });
  });
});

