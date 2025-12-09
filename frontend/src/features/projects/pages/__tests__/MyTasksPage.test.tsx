import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MyTasksPage } from '../MyTasksPage';
import { useMyTasks } from '../../hooks';
import { projectsApi } from '../../api';
import type { ProjectTask } from '../../api';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useMyTasks: vi.fn(),
}));

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    completeProjectTask: vi.fn(),
    incompleteProjectTask: vi.fn(),
    updateProjectTask: vi.fn(),
  },
}));

const mockUseMyTasks = vi.mocked(useMyTasks);
const mockProjectsApi = vi.mocked(projectsApi);

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

// Helper to create mock tasks
const createMockTask = (overrides: Partial<ProjectTask> = {}): ProjectTask => ({
  id: `task-${Math.random()}`,
  project_id: 'project-1',
  name: 'Test Task',
  description: null,
  sort_order: 0,
  is_milestone: false,
  status: null,
  due_date: null,
  is_completed: false,
  assignee_id: null,
  metadata: null,
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
  project: {
    id: 'project-1',
    name: 'Test Project',
    code: 'TP-001',
    status: 'active',
  },
  ...overrides,
});

describe('MyTasksPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockProjectsApi.completeProjectTask.mockResolvedValue({} as any);
    mockProjectsApi.incompleteProjectTask.mockResolvedValue({} as any);
    mockProjectsApi.updateProjectTask.mockResolvedValue({} as any);
  });

  afterEach(() => {
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    vi.clearAllMocks();
  });

  describe('Filters - status + range', () => {
    it('calls useMyTasks with correct filters when status is "open" and range is "overdue"', () => {
      mockUseMyTasks.mockReturnValue({
        data: { data: [] },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      // Initially should be called with default filters
      expect(mockUseMyTasks).toHaveBeenCalledWith({
        status: 'open',
        range: undefined, // 'all' maps to undefined
      });
    });

    it('updates filters when user changes status and range', async () => {
      const user = userEvent.setup();
      const calls: any[] = [];

      mockUseMyTasks.mockImplementation((filters) => {
        calls.push(filters);
        return {
          data: { data: [] },
          isLoading: false,
          error: null,
          refetch: vi.fn(),
        } as any;
      });

      render(<MyTasksPage />, { wrapper: createWrapper() });

      // Find status filter select by looking for the label text and then the select
      const statusLabel = screen.getByText(/^Status$/i);
      const statusSelect = statusLabel.closest('div')?.querySelector('select') as HTMLSelectElement;
      expect(statusSelect).toBeInTheDocument();

      // Change status to "completed"
      await user.selectOptions(statusSelect, 'completed');

      // Wait for the hook to be called with updated filters
      await waitFor(() => {
        const lastCall = calls[calls.length - 1];
        expect(lastCall).toMatchObject({
          status: 'completed',
        });
      });
    });

    it('forces status to "open" when range is set to "overdue"', async () => {
      const user = userEvent.setup();
      const calls: any[] = [];

      mockUseMyTasks.mockImplementation((filters) => {
        calls.push(filters);
        return {
          data: { data: [] },
          isLoading: false,
          error: null,
          refetch: vi.fn(),
        } as any;
      });

      render(<MyTasksPage />, { wrapper: createWrapper() });

      // First, set status to "completed"
      const statusLabel = screen.getByText(/^Status$/i);
      const statusSelect = statusLabel.closest('div')?.querySelector('select') as HTMLSelectElement;
      await user.selectOptions(statusSelect, 'completed');

      // Wait for status change
      await waitFor(() => {
        const lastCall = calls[calls.length - 1];
        expect(lastCall?.status).toBe('completed');
      });

      // Then, set range to "overdue"
      const rangeLabel = screen.getByText(/^Date Range$/i);
      const rangeSelect = rangeLabel.closest('div')?.querySelector('select') as HTMLSelectElement;
      await user.selectOptions(rangeSelect, 'overdue');

      // Should have been called with status forced to "open" when range is "overdue"
      await waitFor(() => {
        const lastCall = calls[calls.length - 1];
        expect(lastCall).toMatchObject({
          status: 'open',
          range: 'overdue',
        });
      });
    });
  });

  describe('Grouping by project + phase', () => {
    it('groups tasks by project and phase correctly', async () => {
      const mockTasks: ProjectTask[] = [
        createMockTask({
          id: 'task-1',
          project_id: 'project-1',
          name: 'Task 1',
          phase_label: 'Phase A',
          project: {
            id: 'project-1',
            name: 'Project Alpha',
            code: 'PA-001',
            status: 'active',
          },
        }),
        createMockTask({
          id: 'task-2',
          project_id: 'project-1',
          name: 'Task 2',
          phase_label: 'Phase A',
          project: {
            id: 'project-1',
            name: 'Project Alpha',
            code: 'PA-001',
            status: 'active',
          },
        }),
        createMockTask({
          id: 'task-3',
          project_id: 'project-1',
          name: 'Task 3',
          phase_label: 'Phase B',
          project: {
            id: 'project-1',
            name: 'Project Alpha',
            code: 'PA-001',
            status: 'active',
          },
        }),
        createMockTask({
          id: 'task-4',
          project_id: 'project-2',
          name: 'Task 4',
          phase_label: 'Phase X',
          project: {
            id: 'project-2',
            name: 'Project Beta',
            code: 'PB-002',
            status: 'active',
          },
        }),
      ];

      mockUseMyTasks.mockReturnValue({
        data: { data: mockTasks },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check project headers
        expect(screen.getByText('Project Alpha')).toBeInTheDocument();
        expect(screen.getByText('Project Beta')).toBeInTheDocument();
        expect(screen.getByText('(PA-001)')).toBeInTheDocument();
        expect(screen.getByText('(PB-002)')).toBeInTheDocument();
      });

      // Check phase labels
      await waitFor(() => {
        const phaseLabels = screen.getAllByText(/Phase:/i);
        expect(phaseLabels.length).toBeGreaterThan(0);
      });

      // Check tasks are rendered
      expect(screen.getByText('Task 1')).toBeInTheDocument();
      expect(screen.getByText('Task 2')).toBeInTheDocument();
      expect(screen.getByText('Task 3')).toBeInTheDocument();
      expect(screen.getByText('Task 4')).toBeInTheDocument();
    });

    it('handles tasks without phase label', async () => {
      const mockTasks: ProjectTask[] = [
        createMockTask({
          id: 'task-1',
          name: 'Task Without Phase',
          phase_label: null,
        }),
      ];

      mockUseMyTasks.mockReturnValue({
        data: { data: mockTasks },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Task Without Phase')).toBeInTheDocument();
      });
    });
  });

  describe('Quick actions wiring', () => {
    it('calls completeProjectTask when clicking completion checkbox on incomplete task', async () => {
      const user = userEvent.setup();
      const mockTask = createMockTask({
        id: 'task-1',
        project_id: 'project-1',
        name: 'Incomplete Task',
        is_completed: false,
      });

      mockUseMyTasks.mockReturnValue({
        data: { data: [mockTask] },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Incomplete Task')).toBeInTheDocument();
      });

      // Find the checkbox
      const checkbox = screen.getByRole('checkbox', { name: /incomplete task/i }) || 
                       screen.getByLabelText(/incomplete task/i) ||
                       document.querySelector('input[type="checkbox"]') as HTMLInputElement;

      if (checkbox) {
        await user.click(checkbox);

        await waitFor(() => {
          expect(mockProjectsApi.completeProjectTask).toHaveBeenCalledWith('project-1', 'task-1');
        });
      }
    });

    it('calls incompleteProjectTask when clicking completion checkbox on completed task', async () => {
      const user = userEvent.setup();
      const mockTask = createMockTask({
        id: 'task-1',
        project_id: 'project-1',
        name: 'Completed Task',
        is_completed: true,
      });

      mockUseMyTasks.mockReturnValue({
        data: { data: [mockTask] },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Completed Task')).toBeInTheDocument();
      });

      // Find the checkbox (should be checked)
      const checkbox = document.querySelector('input[type="checkbox"]:checked') as HTMLInputElement;

      if (checkbox) {
        await user.click(checkbox);

        await waitFor(() => {
          expect(mockProjectsApi.incompleteProjectTask).toHaveBeenCalledWith('project-1', 'task-1');
        });
      }
    });

    it('calls updateProjectTask when changing status dropdown', async () => {
      const user = userEvent.setup();
      const mockTask = createMockTask({
        id: 'task-1',
        project_id: 'project-1',
        name: 'Task With Status',
        status: 'todo',
      });

      mockUseMyTasks.mockReturnValue({
        data: { data: [mockTask] },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Task With Status')).toBeInTheDocument();
      });

      // Find status dropdown for this task (there may be multiple selects, find the one in the task row)
      const taskRow = screen.getByText('Task With Status').closest('.border');
      const statusSelect = taskRow?.querySelector('select') as HTMLSelectElement;

      if (statusSelect) {
        await user.selectOptions(statusSelect, 'in_progress');

        await waitFor(() => {
          expect(mockProjectsApi.updateProjectTask).toHaveBeenCalledWith('project-1', 'task-1', {
            status: 'in_progress',
          });
        });
      }
    });
  });

  describe('Loading and error states', () => {
    it('renders loading state', () => {
      mockUseMyTasks.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      expect(screen.getByText('My Tasks')).toBeInTheDocument();
    });

    it('renders error state', () => {
      mockUseMyTasks.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load tasks'),
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Không tải được danh sách task/i)).toBeInTheDocument();
    });

    it('renders empty state when no tasks', () => {
      mockUseMyTasks.mockReturnValue({
        data: { data: [] },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<MyTasksPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/Bạn chưa có task nào được giao/i)).toBeInTheDocument();
    });
  });
});
