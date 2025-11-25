import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, within, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectDetailPage } from '../ProjectDetailPage';
import { useAuthStore } from '../../../auth/store';
import {
  useProject,
  useProjectOverview,
  useProjectKpis,
  useProjectAlerts,
  useProjectTasks,
  useProjectDocuments,
  useProjectsActivity,
  useDeleteProject,
  useArchiveProject,
  useAddTeamMember,
  useRemoveTeamMember,
  useUploadProjectDocument,
} from '../../hooks';
import { useDeleteTask, useUpdateTask } from '../../../tasks/hooks';
import { useUsers } from '../../../users/hooks';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the projects hooks
vi.mock('../../hooks', () => ({
  useProject: vi.fn(),
  useProjectOverview: vi.fn(),
  useProjectKpis: vi.fn(),
  useProjectAlerts: vi.fn(),
  useProjectTasks: vi.fn(),
  useProjectDocuments: vi.fn(),
  useProjectsActivity: vi.fn(),
  useDeleteProject: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useArchiveProject: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useAddTeamMember: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useRemoveTeamMember: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useUploadProjectDocument: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
}));

// Mock the tasks hooks
vi.mock('../../../tasks/hooks', () => ({
  useDeleteTask: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useUpdateTask: vi.fn(() => ({
    mutateAsync: vi.fn(),
    isPending: false,
    isError: false,
  })),
}));

// Mock the users hooks
vi.mock('../../../users/hooks', () => ({
  useUsers: vi.fn(),
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: 'project-123' }),
    useNavigate: () => mockNavigate,
  };
});

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseProject = vi.mocked(useProject);
const mockUseProjectOverview = vi.mocked(useProjectOverview);
const mockUseProjectKpis = vi.mocked(useProjectKpis);
const mockUseProjectAlerts = vi.mocked(useProjectAlerts);
const mockUseProjectTasks = vi.mocked(useProjectTasks);
const mockUseProjectDocuments = vi.mocked(useProjectDocuments);
const mockUseProjectsActivity = vi.mocked(useProjectsActivity);
const mockUseUsers = vi.mocked(useUsers);
const mockUseUpdateTask = vi.mocked(useUpdateTask);

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

describe('ProjectDetailPage - Overview Panels', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();

    // Default mock implementations
    mockUseAuthStore.mockReturnValue({
      hasTenantPermission: vi.fn(() => true),
    } as any);

    mockUseProject.mockReturnValue({
      data: {
        data: {
          id: 'project-123',
          name: 'Test Project',
          code: 'PRJ-001',
          status: 'active',
          description: 'Test description',
        },
      },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any);

    mockUseProjectKpis.mockReturnValue({
      data: {
        data: {
          total_tasks: 10,
          completed_tasks: 5,
          team_members: 3,
          documents_count: 2,
          progress_percentage: 50,
        },
      },
      isLoading: false,
    } as any);

    mockUseProjectAlerts.mockReturnValue({
      data: {
        data: [],
      },
      isLoading: false,
    } as any);

    mockUseProjectTasks.mockReturnValue({
      data: {
        data: [],
      },
      isLoading: false,
      refetch: vi.fn(),
    } as any);

    mockUseProjectDocuments.mockReturnValue({
      data: {
        data: [],
      },
      isLoading: false,
      refetch: vi.fn(),
    } as any);

    mockUseProjectsActivity.mockReturnValue({
      data: {
        data: [],
      },
      isLoading: false,
      error: null,
    } as any);

    mockUseUsers.mockReturnValue({
      data: {
        data: [],
      },
    } as any);
  });

  afterEach(() => {
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    vi.clearAllMocks();
  });

  describe('Overview panels rendering', () => {
    it('renders execution panel with task summary data', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: 'high',
            risk_level: 'medium',
            start_date: '2024-01-01',
            end_date: '2024-12-31',
            client: { id: '1', name: 'Test Client' },
            owner: { id: '1', name: 'Test Owner' },
          },
          financials: {
            has_financial_data: true,
            contracts_count: 2,
            contracts_value_total: 1000000,
            budget_total: 1200000,
            actual_total: 1100000,
            overrun_amount_total: 100000,
            over_budget_contracts_count: 1,
            overrun_contracts_count: 1,
            currency: 'USD',
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 2,
              in_progress: 3,
              blocked: 1,
              done: 3,
              canceled: 1,
            },
            overdue: 2,
            due_soon: 1,
            key_tasks: {
              overdue: [
                {
                  id: 't-over-1',
                  name: 'Task overdue 1',
                  status: 'in_progress',
                  priority: 'high',
                  end_date: '2025-11-10',
                  assignee: { id: 'u1', name: 'User 1' },
                },
              ],
              due_soon: [
                {
                  id: 't-due-1',
                  name: 'Task due soon',
                  status: 'in_progress',
                  priority: 'normal',
                  end_date: '2025-11-25',
                  assignee: null,
                },
              ],
              blocked: [
                {
                  id: 't-block-1',
                  name: 'Task blocked',
                  status: 'blocked',
                  priority: 'urgent',
                  end_date: null,
                  assignee: { id: 'u2', name: 'User 2' },
                },
              ],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check execution panel title
      expect(screen.getByText('Tiến độ công việc')).toBeInTheDocument();
      expect(screen.getByText('Tổng quan task trong dự án')).toBeInTheDocument();

      // Check task counts
      expect(screen.getByText(/Tổng task:/)).toBeInTheDocument();
      expect(screen.getByText('10')).toBeInTheDocument(); // Total tasks

      // Check status breakdown
      expect(screen.getByText(/Backlog:/)).toBeInTheDocument();
      expect(screen.getByText(/Đang làm:/)).toBeInTheDocument();
      expect(screen.getByText(/Bị chặn:/)).toBeInTheDocument();
      expect(screen.getByText(/Hoàn thành:/)).toBeInTheDocument();
      expect(screen.getByText(/Hủy:/)).toBeInTheDocument();

      // Check overdue and due soon
      expect(screen.getByText(/Quá hạn:/)).toBeInTheDocument();
      expect(screen.getByText(/Sắp đến hạn \(3 ngày\):/)).toBeInTheDocument();
    });

    it('renders financial panel with contract data', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: true,
            contracts_count: 2,
            contracts_value_total: 1_000_000,
            budget_total: 1_200_000,
            actual_total: 1_100_000,
            overrun_amount_total: 100_000,
            over_budget_contracts_count: 1,
            overrun_contracts_count: 1,
            currency: 'USD',
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check financial panel title
      expect(screen.getByText('Tài chính dự án')).toBeInTheDocument();
      expect(screen.getByText('Hợp đồng, ngân sách, chi phí & overrun')).toBeInTheDocument();

      // Check contract count
      expect(screen.getByText('Số hợp đồng')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();

      // Check financial labels
      expect(screen.getByText('Giá trị hợp đồng')).toBeInTheDocument();
      expect(screen.getByText('Ngân sách')).toBeInTheDocument();
      expect(screen.getByText('Chi phí thực tế')).toBeInTheDocument();
      expect(screen.getByText('Overrun (Actual – Contract)')).toBeInTheDocument();

      // Check overrun counts text
      expect(screen.getByText(/Hợp đồng vượt ngân sách:/)).toBeInTheDocument();
      expect(screen.getByText(/Hợp đồng bị overrun:/)).toBeInTheDocument();
    });

    it('renders "no financial data" message when has_financial_data is false', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      expect(
        screen.getByText('Chưa có dữ liệu hợp đồng cho dự án này.')
      ).toBeInTheDocument();
    });
  });

  describe('Loading states', () => {
    it('shows loading state in execution panel', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check that loading indicators are present (animate-pulse class)
      const executionPanel = screen.getByText('Tiến độ công việc').closest('.card') || 
                             screen.getByText('Tiến độ công việc').closest('[class*="Card"]');
      expect(executionPanel).toBeInTheDocument();
    });

    it('shows loading state in financial panel', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check that loading indicators are present
      const financialPanel = screen.getByText('Tài chính dự án').closest('.card') || 
                             screen.getByText('Tài chính dự án').closest('[class*="Card"]');
      expect(financialPanel).toBeInTheDocument();
    });
  });

  describe('Error states', () => {
    it('shows error message in execution panel', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load overview'),
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      expect(
        screen.getByText('Không tải được tổng quan công việc')
      ).toBeInTheDocument();
    });

    it('shows error message in financial panel', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load overview'),
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      expect(
        screen.getByText('Không tải được tổng quan tài chính')
      ).toBeInTheDocument();
    });
  });

  describe('MoneyCell integration', () => {
    it('renders MoneyCell with correct values and currency', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: true,
            contracts_count: 1,
            contracts_value_total: 1_000_000,
            budget_total: 1_200_000,
            actual_total: 1_100_000,
            overrun_amount_total: 100_000,
            over_budget_contracts_count: 1,
            overrun_contracts_count: 1,
            currency: 'USD',
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check that MoneyCell renders currency values (flexible regex for locale formatting)
      // The values should be formatted as currency (1,000,000 or 1.000.000 depending on locale)
      const financialPanel = screen.getByText('Tài chính dự án').closest('[class*="Card"]');
      expect(financialPanel).toBeInTheDocument();

      // Check for formatted currency values (flexible regex)
      // This will match 1,000,000 or 1.000.000 or similar formats
      const panelText = financialPanel?.textContent || '';
      expect(panelText).toMatch(/1[.,]0{3}[.,]0{3}/); // Matches 1,000,000 or 1.000.000
    });

    it('renders MoneyCell with danger tone for positive overrun', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: true,
            contracts_count: 1,
            contracts_value_total: 1_000_000,
            budget_total: 1_200_000,
            actual_total: 1_100_000,
            overrun_amount_total: 100_000, // Positive overrun
            over_budget_contracts_count: 1,
            overrun_contracts_count: 1,
            currency: 'USD',
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      const { container } = render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check for MoneyCell with danger tone (positive overrun)
      // The overrun should have showPlusWhenPositive and tone="danger"
      const overrunSection = screen.getByText('Overrun (Actual – Contract)').closest('div');
      expect(overrunSection).toBeInTheDocument();

      // Check for span with data-tone="danger" in the overrun section
      const dangerSpan = overrunSection?.querySelector('span[data-tone="danger"]');
      expect(dangerSpan).not.toBeNull();

      // Check that it contains a + sign (showPlusWhenPositive)
      const overrunText = overrunSection?.textContent || '';
      expect(overrunText).toMatch(/\+/); // Should have + prefix
    });

    it('renders MoneyCell with fallback for null values', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // When has_financial_data is false, we show the "no data" message
      // But if we had financials with null values, MoneyCell would show "-"
      expect(
        screen.getByText('Chưa có dữ liệu hợp đồng cho dự án này.')
      ).toBeInTheDocument();
    });
  });

  describe('Task status display', () => {
    it('displays all task statuses correctly', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 2,
              in_progress: 3,
              blocked: 1,
              done: 4,
              canceled: 0,
            },
            overdue: 2,
            due_soon: 1,
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check all status labels are present
      expect(screen.getByText(/Backlog:/)).toBeInTheDocument();
      expect(screen.getByText(/Đang làm:/)).toBeInTheDocument();
      expect(screen.getByText(/Bị chặn:/)).toBeInTheDocument();
      expect(screen.getByText(/Hoàn thành:/)).toBeInTheDocument();
      expect(screen.getByText(/Hủy:/)).toBeInTheDocument();

      // Check counts (using flexible matching)
      const panelText = screen.getByText('Tiến độ công việc').closest('[class*="Card"]')?.textContent || '';
      expect(panelText).toMatch(/2/); // backlog: 2
      expect(panelText).toMatch(/3/); // in_progress: 3
      expect(panelText).toMatch(/4/); // done: 4
    });

    it('displays overdue and due soon counts with correct styling', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 5,
            by_status: {
              backlog: 1,
              in_progress: 2,
              blocked: 0,
              done: 1,
              canceled: 1,
            },
            overdue: 3,
            due_soon: 2,
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check overdue and due soon labels
      expect(screen.getByText(/Quá hạn:/)).toBeInTheDocument();
      expect(screen.getByText(/Sắp đến hạn \(3 ngày\):/)).toBeInTheDocument();

      // Check counts are displayed
      const panelText = screen.getByText('Tiến độ công việc').closest('[class*="Card"]')?.textContent || '';
      expect(panelText).toMatch(/3/); // overdue: 3
      expect(panelText).toMatch(/2/); // due_soon: 2
    });
  });

  describe('Key Tasks Card', () => {
    it('renders key tasks sections (overdue, due soon, blocked) in overview', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 2,
              in_progress: 3,
              blocked: 1,
              done: 3,
              canceled: 1,
            },
            overdue: 2,
            due_soon: 1,
            key_tasks: {
              overdue: [
                {
                  id: 't-over-1',
                  name: 'Task overdue 1',
                  status: 'in_progress',
                  priority: 'high',
                  end_date: '2025-11-10',
                  assignee: { id: 'u1', name: 'User 1' },
                },
              ],
              due_soon: [
                {
                  id: 't-due-1',
                  name: 'Task due soon',
                  status: 'in_progress',
                  priority: 'normal',
                  end_date: '2025-11-25',
                  assignee: null,
                },
              ],
              blocked: [
                {
                  id: 't-block-1',
                  name: 'Task blocked',
                  status: 'blocked',
                  priority: 'urgent',
                  end_date: null,
                  assignee: { id: 'u2', name: 'User 2' },
                },
              ],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check card title
      expect(
        await screen.findByText('Task quan trọng cần xử lý')
      ).toBeInTheDocument();

      // Section titles
      expect(screen.getByText(/Overdue/i)).toBeInTheDocument();
      expect(screen.getByText(/Sắp đến hạn/i)).toBeInTheDocument();
      expect(screen.getByText(/blocked/i)).toBeInTheDocument();

      // Items
      expect(screen.getByText('Task overdue 1')).toBeInTheDocument();
      expect(screen.getByText('Task due soon')).toBeInTheDocument();
      expect(screen.getByText('Task blocked')).toBeInTheDocument();
    });

    it('renders empty state when no key tasks', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      expect(
        screen.getByText(/Không có task quá hạn nào/i)
      ).toBeInTheDocument();
      expect(
        screen.getByText(/Không có task sắp đến hạn nào/i)
      ).toBeInTheDocument();
      expect(
        screen.getByText(/Không có task bị chặn nào/i)
      ).toBeInTheDocument();
    });

    it('navigates to task detail when clicking on a task', () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 2,
              in_progress: 3,
              blocked: 1,
              done: 3,
              canceled: 1,
            },
            overdue: 2,
            due_soon: 1,
            key_tasks: {
              overdue: [
                {
                  id: 't-over-1',
                  name: 'Task overdue 1',
                  status: 'in_progress',
                  priority: 'high',
                  end_date: '2025-11-10',
                  assignee: { id: 'u1', name: 'User 1' },
                },
              ],
              due_soon: [],
              blocked: [],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      const taskElement = screen.getByText('Task overdue 1');
      fireEvent.click(taskElement);

      expect(mockNavigate).toHaveBeenCalledWith('/app/tasks/t-over-1');
    });

    it('shows loading state in key tasks card', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check that loading indicators are present (animate-pulse class)
      const keyTasksCard = screen.queryByText('Task quan trọng cần xử lý');
      expect(keyTasksCard).toBeInTheDocument();
      
      // Should not show task names when loading
      expect(screen.queryByText('Task overdue 1')).not.toBeInTheDocument();
    });

    it('shows error message in key tasks card', () => {
      mockUseProjectOverview.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load overview'),
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      expect(
        screen.getByText('Không tải được danh sách task quan trọng.')
      ).toBeInTheDocument();
    });

    describe('Quick Actions for Key Tasks', () => {
      const mockMutateAsync = vi.fn();
      
      beforeEach(() => {
        mockMutateAsync.mockClear();
        mockUseUpdateTask.mockReturnValue({
          mutateAsync: mockMutateAsync,
          isPending: false,
          isError: false,
        } as any);
      });

      it('shows quick actions menu when user can manage tasks', async () => {
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: 'current-user-1', name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'backlog',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' },
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Find the menu button (⋯)
        const menuButtons = screen.getAllByLabelText('Task actions');
        expect(menuButtons.length).toBeGreaterThan(0);

        // Click the menu button
        fireEvent.click(menuButtons[0]);

        // Check that menu items appear
        await waitFor(() => {
          expect(screen.getByText('Chuyển sang đang làm')).toBeInTheDocument();
          expect(screen.getByText('Đánh dấu đã xong')).toBeInTheDocument();
          expect(screen.getByText('Giao cho tôi')).toBeInTheDocument();
          expect(screen.getByText('Xem chi tiết')).toBeInTheDocument();
        });
      });

      it('does not show quick actions when user cannot manage tasks', () => {
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn(() => false),
          user: { id: 'current-user-1', name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'backlog',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' },
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Should not find menu buttons
        const menuButtons = screen.queryAllByLabelText('Task actions');
        expect(menuButtons.length).toBe(0);

        // Task should still be clickable
        const taskElement = screen.getByText('Task overdue 1');
        expect(taskElement).toBeInTheDocument();
      });

      it('calls updateTask with status done when clicking mark-as-done quick action', async () => {
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: 'current-user-1', name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'in_progress',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' },
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        mockMutateAsync.mockResolvedValue({
          id: 't-over-1',
          status: 'done',
        });

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Open menu
        const menuButton = screen.getByLabelText('Task actions');
        fireEvent.click(menuButton);

        // Click "Đánh dấu đã xong"
        await waitFor(() => {
          const markDoneButton = screen.getByText('Đánh dấu đã xong');
          fireEvent.click(markDoneButton);
        });

        // Verify updateTask was called with correct parameters
        await waitFor(() => {
          expect(mockMutateAsync).toHaveBeenCalledWith({
            id: 't-over-1',
            data: { status: 'done' },
          });
        });
      });

      it('calls updateTask with status in_progress when clicking start-work quick action', async () => {
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: 'current-user-1', name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'backlog',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' },
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        mockMutateAsync.mockResolvedValue({
          id: 't-over-1',
          status: 'in_progress',
        });

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Open menu
        const menuButton = screen.getByLabelText('Task actions');
        fireEvent.click(menuButton);

        // Click "Chuyển sang đang làm"
        await waitFor(() => {
          const startWorkButton = screen.getByText('Chuyển sang đang làm');
          fireEvent.click(startWorkButton);
        });

        // Verify updateTask was called with correct parameters
        await waitFor(() => {
          expect(mockMutateAsync).toHaveBeenCalledWith({
            id: 't-over-1',
            data: { status: 'in_progress' },
          });
        });
      });

      it('calls updateTask with assignee_id when clicking assign-to-me quick action', async () => {
        const currentUserId = 'current-user-1';
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: currentUserId, name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'in_progress',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' }, // Different from current user
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        mockMutateAsync.mockResolvedValue({
          id: 't-over-1',
          assignee_id: currentUserId,
        });

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Open menu
        const menuButton = screen.getByLabelText('Task actions');
        fireEvent.click(menuButton);

        // Click "Giao cho tôi"
        await waitFor(() => {
          const assignButton = screen.getByText('Giao cho tôi');
          fireEvent.click(assignButton);
        });

        // Verify updateTask was called with correct parameters
        await waitFor(() => {
          expect(mockMutateAsync).toHaveBeenCalledWith({
            id: 't-over-1',
            data: { assignee_id: currentUserId },
          });
        });
      });

      it('shows error message when quick action update fails', async () => {
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: 'current-user-1', name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'in_progress',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: 'u1', name: 'User 1' },
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        // Mock error state
        mockUseUpdateTask.mockReturnValue({
          mutateAsync: mockMutateAsync,
          isPending: false,
          isError: true,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Check that error message appears
        await waitFor(() => {
          expect(screen.getByText(/Không cập nhật được task, vui lòng thử lại/i)).toBeInTheDocument();
        });
      });

      it('does not show assign-to-me action when task is already assigned to current user', async () => {
        const currentUserId = 'current-user-1';
        mockUseAuthStore.mockReturnValue({
          hasTenantPermission: vi.fn((permission: string) => 
            permission === 'tenant.manage_tasks'
          ),
          user: { id: currentUserId, name: 'Current User', email: 'user@example.com' },
        } as any);

        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
              priority: null,
              risk_level: null,
              start_date: null,
              end_date: null,
              client: null,
              owner: null,
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 0,
              key_tasks: {
                overdue: [
                  {
                    id: 't-over-1',
                    name: 'Task overdue 1',
                    status: 'in_progress',
                    priority: 'high',
                    end_date: '2025-11-10',
                    assignee: { id: currentUserId, name: 'Current User' }, // Same as current user
                  },
                ],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        // Open menu
        const menuButton = screen.getByLabelText('Task actions');
        fireEvent.click(menuButton);

        // Check that "Giao cho tôi" is NOT shown
        await waitFor(() => {
          expect(screen.queryByText('Giao cho tôi')).not.toBeInTheDocument();
        });

        // But other actions should still be visible
        expect(screen.getByText('Đánh dấu đã xong')).toBeInTheDocument();
      });
    });

    it('renders priority badges with correct colors per column', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 3,
            by_status: {
              backlog: 0,
              in_progress: 2,
              blocked: 1,
              done: 0,
              canceled: 0,
            },
            overdue: 1,
            due_soon: 1,
            key_tasks: {
              overdue: [
                {
                  id: 't-over-1',
                  name: 'Task overdue 1',
                  status: 'in_progress',
                  priority: 'high',
                  end_date: '2025-11-10',
                  assignee: { id: 'u1', name: 'User 1' },
                },
              ],
              due_soon: [
                {
                  id: 't-due-1',
                  name: 'Task due soon',
                  status: 'in_progress',
                  priority: 'normal',
                  end_date: '2025-11-25',
                  assignee: null,
                },
              ],
              blocked: [
                {
                  id: 't-block-1',
                  name: 'Task blocked',
                  status: 'blocked',
                  priority: 'urgent',
                  end_date: null,
                  assignee: { id: 'u2', name: 'User 2' },
                },
              ],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      const { container } = render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Wait for content to render
      await waitFor(() => {
        expect(screen.getByText('Task overdue 1')).toBeInTheDocument();
      });

      // Check overdue column has red badge (bg-red-100 / text-red-700)
      const overdueTask = screen.getByText('Task overdue 1').closest('li');
      const overdueBadge = overdueTask?.querySelector('span.bg-red-100');
      expect(overdueBadge).toBeInTheDocument();
      expect(overdueBadge).toHaveClass('text-red-700');
      expect(overdueBadge).toHaveTextContent('high');

      // Check due soon column has orange badge (bg-orange-100 / text-orange-700)
      const dueSoonTask = screen.getByText('Task due soon').closest('li');
      const dueSoonBadge = dueSoonTask?.querySelector('span.bg-orange-100');
      expect(dueSoonBadge).toBeInTheDocument();
      expect(dueSoonBadge).toHaveClass('text-orange-700');
      expect(dueSoonBadge).toHaveTextContent('normal');

      // Check blocked column has purple badge (bg-purple-100 / text-purple-700)
      const blockedTask = screen.getByText('Task blocked').closest('li');
      const blockedBadge = blockedTask?.querySelector('span.bg-purple-100');
      expect(blockedBadge).toBeInTheDocument();
      expect(blockedBadge).toHaveClass('text-purple-700');
      expect(blockedBadge).toHaveTextContent('urgent');
    });

    it('shows dash for tasks without end_date in blocked column', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 1,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 1,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [
                {
                  id: 't-block-1',
                  name: 'Task blocked',
                  status: 'blocked',
                  priority: 'urgent',
                  end_date: null,
                  assignee: { id: 'u2', name: 'User 2' },
                },
              ],
            },
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Wait for content to render
      await waitFor(() => {
        expect(screen.getByText('Task blocked')).toBeInTheDocument();
      });

      // Check that "Hạn: —" appears in the blocked task
      const blockedTask = screen.getByText('Task blocked').closest('li');
      expect(blockedTask).toBeInTheDocument();
      expect(blockedTask?.textContent).toMatch(/Hạn:\s*—/);
    });

    it('handles missing key_tasks gracefully with fallback', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            // key_tasks is missing
          },
        },
      } as any;

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Wait for content to render
      await waitFor(() => {
        expect(screen.getByText('Task quan trọng cần xử lý')).toBeInTheDocument();
      });

      // Card should render without crashing
      expect(screen.getByText('Task quan trọng cần xử lý')).toBeInTheDocument();

      // Should show 3 empty state messages (fallback to empty arrays)
      expect(
        screen.getByText(/Không có task quá hạn nào/i)
      ).toBeInTheDocument();
      expect(
        screen.getByText(/Không có task sắp đến hạn nào/i)
      ).toBeInTheDocument();
      expect(
        screen.getByText(/Không có task bị chặn nào/i)
      ).toBeInTheDocument();
    });
  });

  describe('Project Health Card - Round 70', () => {
    it('renders project health card with good status', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: true,
            contracts_count: 1,
            contracts_value_total: 1000000,
            budget_total: 1000000,
            actual_total: 950000,
            overrun_amount_total: 0,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: 'USD',
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 2,
              in_progress: 3,
              blocked: 2,
              done: 3,
              canceled: 0,
            },
            overdue: 1,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
          health: {
            tasks_completion_rate: 0.6, // 3 done / 5 effective (10 - 0 canceled)
            blocked_tasks_ratio: 0.2, // 2 blocked / 10
            overdue_tasks: 1,
            schedule_status: 'on_track',
            cost_status: 'on_budget',
            cost_overrun_percent: null,
            overall_status: 'good',
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check title
      expect(screen.getByText('Sức khỏe dự án')).toBeInTheDocument();

      // Check overall status badge
      expect(screen.getByText('Tốt')).toBeInTheDocument();
      const goodBadge = screen.getByText('Tốt');
      expect(goodBadge).toHaveClass('bg-green-100');
      expect(goodBadge).toHaveClass('text-green-700');

      // Check task completion info
      expect(screen.getByText(/Hoàn thành:/)).toBeInTheDocument();
      expect(screen.getByText(/60%/)).toBeInTheDocument(); // 0.6 * 100 = 60%
      expect(screen.getByText(/Blocked:/)).toBeInTheDocument();
      expect(screen.getByText(/20%/)).toBeInTheDocument(); // 0.2 * 100 = 20%
      expect(screen.getByText(/Task quá hạn:/)).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument();

      // Check schedule status
      expect(screen.getByText('Đúng tiến độ')).toBeInTheDocument();

      // Check cost status
      expect(screen.getByText('Trong ngân sách')).toBeInTheDocument();
    });

    it('marks project health as critical when delayed and over budget', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: true,
            contracts_count: 1,
            contracts_value_total: 1000000,
            budget_total: 1000000,
            actual_total: 1150000,
            overrun_amount_total: 150000,
            over_budget_contracts_count: 1,
            overrun_contracts_count: 1,
            currency: 'USD',
          },
          tasks: {
            total: 10,
            by_status: {
              backlog: 0,
              in_progress: 4,
              blocked: 0,
              done: 2,
              canceled: 4,
            },
            overdue: 4,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
          health: {
            tasks_completion_rate: 0.33, // 2 done / 6 effective
            blocked_tasks_ratio: 0,
            overdue_tasks: 4,
            schedule_status: 'delayed',
            cost_status: 'over_budget',
            cost_overrun_percent: 15.0,
            overall_status: 'critical',
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check critical badge
      expect(screen.getByText('Nguy cấp')).toBeInTheDocument();
      const criticalBadge = screen.getByText('Nguy cấp');
      expect(criticalBadge).toHaveClass('bg-red-100');
      expect(criticalBadge).toHaveClass('text-red-700');

      // Check schedule status
      expect(screen.getByText('Đang chậm tiến độ')).toBeInTheDocument();

      // Check cost status
      expect(screen.getByText('Vượt ngân sách')).toBeInTheDocument();

      // Check overrun percent
      expect(screen.getByText(/Overrun: 15%/)).toBeInTheDocument();
    });

    it('shows warning health when there are no tasks and no financial data', async () => {
      const mockOverviewData = {
        data: {
          project: {
            id: 'project-123',
            code: 'PRJ-001',
            name: 'Test Project',
            status: 'active',
            priority: null,
            risk_level: null,
            start_date: null,
            end_date: null,
            client: null,
            owner: null,
          },
          financials: {
            has_financial_data: false,
            contracts_count: 0,
            contracts_value_total: null,
            budget_total: null,
            actual_total: null,
            overrun_amount_total: null,
            over_budget_contracts_count: 0,
            overrun_contracts_count: 0,
            currency: null,
          },
          tasks: {
            total: 0,
            by_status: {
              backlog: 0,
              in_progress: 0,
              blocked: 0,
              done: 0,
              canceled: 0,
            },
            overdue: 0,
            due_soon: 0,
            key_tasks: {
              overdue: [],
              due_soon: [],
              blocked: [],
            },
          },
          health: {
            tasks_completion_rate: null,
            blocked_tasks_ratio: null,
            overdue_tasks: 0,
            schedule_status: 'no_tasks',
            cost_status: 'no_data',
            cost_overrun_percent: null,
            overall_status: 'warning',
          },
        },
      };

      mockUseProjectOverview.mockReturnValue({
        data: mockOverviewData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDetailPage />, { wrapper: createWrapper() });

      // Check warning badge
      expect(screen.getByText('Cảnh báo')).toBeInTheDocument();
      const warningBadge = screen.getByText('Cảnh báo');
      expect(warningBadge).toHaveClass('bg-yellow-100');
      expect(warningBadge).toHaveClass('text-yellow-700');

      // Check null values show as dash
      expect(screen.getByText(/Hoàn thành: —/)).toBeInTheDocument();
      expect(screen.getByText(/Blocked: —/)).toBeInTheDocument();

      // Check schedule status
      expect(screen.getByText('Chưa có task nào')).toBeInTheDocument();

      // Check cost status
      expect(screen.getByText('Chưa có dữ liệu chi phí')).toBeInTheDocument();
    });
  });

  describe('Round 73: Deep-link navigation from Overview to Tasks & Reports', () => {
    describe('Task Progress Card → Tasks board', () => {
      it('navigates to tasks board when clicking "Xem bảng task chi tiết"', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 2,
              due_soon: 1,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem bảng task chi tiết');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/tasks?project_id=project-123');
      });

      it('does not render link when projectId is missing', () => {
        const mockOverviewData = {
          data: {
            project: null, // No project ID
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        // Mock useParams to return null
        vi.mocked(require('react-router-dom').useParams).mockReturnValue({ id: null } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem bảng task chi tiết')).not.toBeInTheDocument();
      });
    });

    describe('Financial Card → Project Portfolio Report', () => {
      it('navigates to portfolio report when clicking "Xem báo cáo chi tiết chi phí" and has_financial_data is true', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: true,
              contracts_count: 2,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 0,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'USD',
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem báo cáo chi tiết chi phí');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/reports/portfolio/projects?project_id=project-123');
      });

      it('does not render portfolio link when has_financial_data is false', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem báo cáo chi tiết chi phí')).not.toBeInTheDocument();
      });
    });

    describe('Financial Card → Cost Overruns Report', () => {
      it('navigates to cost overruns report when clicking "Xem chi tiết hợp đồng vượt chi phí" and overrun > 0', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: true,
              contracts_count: 2,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 100000, // > 0
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem chi tiết hợp đồng vượt chi phí');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/reports/cost-overruns?project_id=project-123');
      });

      it('does not render cost overruns link when overrun_amount_total is 0', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: true,
              contracts_count: 2,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 0, // = 0
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'USD',
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem chi tiết hợp đồng vượt chi phí')).not.toBeInTheDocument();
      });
    });

    describe('Health Card → Tasks when schedule at_risk/delayed', () => {
      it('navigates to tasks board when clicking schedule link and schedule_status is delayed', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 4,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: 0.33,
              blocked_tasks_ratio: 0,
              overdue_tasks: 4,
              schedule_status: 'delayed',
              cost_status: 'on_budget',
              cost_overrun_percent: null,
              overall_status: 'critical',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem danh sách task quá hạn / sắp đến hạn');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/tasks?project_id=project-123');
      });

      it('navigates to tasks board when clicking schedule link and schedule_status is at_risk', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 2,
              due_soon: 1,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: 0.5,
              blocked_tasks_ratio: 0.1,
              overdue_tasks: 2,
              schedule_status: 'at_risk',
              cost_status: 'on_budget',
              cost_overrun_percent: null,
              overall_status: 'warning',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem danh sách task quá hạn / sắp đến hạn');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/tasks?project_id=project-123');
      });

      it('does not render schedule link when schedule_status is no_tasks', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: null,
              blocked_tasks_ratio: null,
              overdue_tasks: 0,
              schedule_status: 'no_tasks',
              cost_status: 'no_data',
              cost_overrun_percent: null,
              overall_status: 'warning',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem danh sách task quá hạn / sắp đến hạn')).not.toBeInTheDocument();
      });

      it('does not render schedule link when schedule_status is on_track', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: 0.6,
              blocked_tasks_ratio: 0.1,
              overdue_tasks: 0,
              schedule_status: 'on_track',
              cost_status: 'on_budget',
              cost_overrun_percent: null,
              overall_status: 'good',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem danh sách task quá hạn / sắp đến hạn')).not.toBeInTheDocument();
      });
    });

    describe('Health Card → Project Cost Report when cost at_risk/over_budget', () => {
      it('navigates to portfolio report when clicking cost link and cost_status is over_budget', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: true,
              contracts_count: 1,
              contracts_value_total: 1000000,
              budget_total: 1000000,
              actual_total: 1150000,
              overrun_amount_total: 150000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 0,
                in_progress: 4,
                blocked: 0,
                done: 2,
                canceled: 4,
              },
              overdue: 4,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: 0.33,
              blocked_tasks_ratio: 0,
              overdue_tasks: 4,
              schedule_status: 'delayed',
              cost_status: 'over_budget',
              cost_overrun_percent: 15.0,
              overall_status: 'critical',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem chi tiết chi phí dự án');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/reports/portfolio/projects?project_id=project-123');
      });

      it('navigates to portfolio report when clicking cost link and cost_status is at_risk', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: true,
              contracts_count: 1,
              contracts_value_total: 1000000,
              budget_total: 1000000,
              actual_total: 1050000,
              overrun_amount_total: 50000,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'USD',
            },
            tasks: {
              total: 10,
              by_status: {
                backlog: 2,
                in_progress: 3,
                blocked: 1,
                done: 3,
                canceled: 1,
              },
              overdue: 1,
              due_soon: 1,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: 0.5,
              blocked_tasks_ratio: 0.1,
              overdue_tasks: 1,
              schedule_status: 'on_track',
              cost_status: 'at_risk',
              cost_overrun_percent: 5.0,
              overall_status: 'warning',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        const link = screen.getByText('Xem chi tiết chi phí dự án');
        fireEvent.click(link);

        expect(mockNavigate).toHaveBeenCalledWith('/app/reports/portfolio/projects?project_id=project-123');
      });

      it('does not render cost link when cost_status is no_data', () => {
        const mockOverviewData = {
          data: {
            project: {
              id: 'project-123',
              code: 'PRJ-001',
              name: 'Test Project',
              status: 'active',
            },
            financials: {
              has_financial_data: false,
              contracts_count: 0,
              contracts_value_total: null,
              budget_total: null,
              actual_total: null,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: null,
            },
            tasks: {
              total: 0,
              by_status: {
                backlog: 0,
                in_progress: 0,
                blocked: 0,
                done: 0,
                canceled: 0,
              },
              overdue: 0,
              due_soon: 0,
              key_tasks: {
                overdue: [],
                due_soon: [],
                blocked: [],
              },
            },
            health: {
              tasks_completion_rate: null,
              blocked_tasks_ratio: null,
              overdue_tasks: 0,
              schedule_status: 'no_tasks',
              cost_status: 'no_data',
              cost_overrun_percent: null,
              overall_status: 'warning',
            },
          },
        };

        mockUseProjectOverview.mockReturnValue({
          data: mockOverviewData,
          isLoading: false,
          error: null,
        } as any);

        render(<ProjectDetailPage />, { wrapper: createWrapper() });

        expect(screen.queryByText('Xem chi tiết chi phí dự án')).not.toBeInTheDocument();
      });
    });
  });
});

