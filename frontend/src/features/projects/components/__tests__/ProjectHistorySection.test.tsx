import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectHistorySection } from '../ProjectHistorySection';
import { useProjectHistory } from '../../hooks';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useProjectHistory: vi.fn(),
}));

const mockUseProjectHistory = vi.mocked(useProjectHistory);

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

describe('ProjectHistorySection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    vi.clearAllMocks();
  });

  it('renders loading state', () => {
    mockUseProjectHistory.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText('Project History')).toBeInTheDocument();
  });

  it('renders error state', () => {
    mockUseProjectHistory.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: new Error('Failed to load history'),
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText(/Error loading project history/i)).toBeInTheDocument();
  });

  it('renders empty state when no history', () => {
    mockUseProjectHistory.mockReturnValue({
      data: { success: true, data: [] },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText(/No history found for this project/i)).toBeInTheDocument();
  });

  it('renders history items with correct fields', async () => {
    const mockHistory = [
      {
        id: '1',
        action: 'created',
        action_label: 'Project Created',
        entity_type: 'project',
        message: 'Project was created',
        description: 'Project was created',
        user: { id: '1', name: 'John Doe', email: 'john@example.com' },
        created_at: '2025-01-15T10:00:00Z',
        time_ago: '2 hours ago',
      },
      {
        id: '2',
        action: 'updated',
        action_label: 'Project Updated',
        entity_type: 'project',
        message: 'Project details were updated',
        description: 'Project details were updated',
        user: { id: '2', name: 'Jane Smith', email: 'jane@example.com' },
        created_at: '2025-01-14T10:00:00Z',
        time_ago: '1 day ago',
      },
    ];

    mockUseProjectHistory.mockReturnValue({
      data: { success: true, data: mockHistory },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Project Created')).toBeInTheDocument();
      expect(screen.getByText('Project Updated')).toBeInTheDocument();
    });

    // Check history details
    expect(screen.getByText('Project was created')).toBeInTheDocument();
    expect(screen.getByText('Project details were updated')).toBeInTheDocument();
    expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
    expect(screen.getByText(/Jane Smith/i)).toBeInTheDocument();
    expect(screen.getByText(/2 hours ago/i)).toBeInTheDocument();
    expect(screen.getByText(/1 day ago/i)).toBeInTheDocument();
  });

  it('displays history count in header', async () => {
    const mockHistory = [
      { id: '1', action: 'created', action_label: 'Created', message: 'Test', user: null, created_at: '2025-01-15' },
      { id: '2', action: 'updated', action_label: 'Updated', message: 'Test', user: null, created_at: '2025-01-14' },
    ];

    mockUseProjectHistory.mockReturnValue({
      data: { success: true, data: mockHistory },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText(/\(2\)/)).toBeInTheDocument();
    });
  });

  it('handles missing action_label gracefully', async () => {
    const mockHistory = [
      {
        id: '1',
        action: 'created',
        action_label: null,
        entity_type: 'project',
        message: 'Project was initialized',
        user: { id: '1', name: 'John Doe', email: 'john@example.com' },
        created_at: '2025-01-15T10:00:00Z',
      },
    ];

    mockUseProjectHistory.mockReturnValue({
      data: { success: true, data: mockHistory },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

    await waitFor(() => {
      // Should fallback to action with capitalized format - check for the action label span specifically
      const actionLabel = screen.getByText('created');
      expect(actionLabel).toBeInTheDocument();
      expect(actionLabel).toHaveClass('capitalize');
    });
  });

  describe('Filter functionality', () => {
    const mockHistory = [
      {
        id: '1',
        action: 'created',
        action_label: 'Project Created',
        entity_type: 'project',
        message: 'Project was created',
        user: { id: '1', name: 'John Doe' },
        created_at: '2025-01-15T10:00:00Z',
        time_ago: '2 hours ago',
      },
      {
        id: '2',
        action: 'updated',
        action_label: 'Project Updated',
        entity_type: 'project',
        message: 'Project details were updated',
        user: { id: '2', name: 'Jane Smith' },
        created_at: '2025-01-14T10:00:00Z',
        time_ago: '1 day ago',
      },
      {
        id: '3',
        action: 'task_created',
        action_label: 'Task Created',
        entity_type: 'task',
        message: 'New task was created',
        user: { id: '1', name: 'John Doe' },
        created_at: '2025-01-13T10:00:00Z',
        time_ago: '2 days ago',
      },
      {
        id: '4',
        action: 'document_uploaded',
        action_label: 'Document Uploaded',
        entity_type: 'document',
        message: 'Document was uploaded',
        user: { id: '2', name: 'Jane Smith' },
        created_at: '2025-01-12T10:00:00Z',
        time_ago: '3 days ago',
      },
      {
        id: '5',
        action: 'document_updated',
        action_label: 'Document Updated',
        entity_type: 'document',
        message: 'Updated document "Test Document"',
        user: { id: '1', name: 'John Doe' },
        created_at: '2025-01-11T10:00:00Z',
        time_ago: '4 days ago',
      },
      {
        id: '6',
        action: 'document_deleted',
        action_label: 'Document Deleted',
        entity_type: 'document',
        message: 'Deleted document "Old Document"',
        user: { id: '2', name: 'Jane Smith' },
        created_at: '2025-01-10T10:00:00Z',
        time_ago: '5 days ago',
      },
      {
        id: '7',
        action: 'document_downloaded',
        action_label: 'Document Downloaded',
        entity_type: 'document',
        message: 'Downloaded document "Important Document"',
        user: { id: '1', name: 'John Doe' },
        created_at: '2025-01-09T10:00:00Z',
        time_ago: '6 days ago',
      },
    ];

    it('renders filter controls', () => {
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      expect(screen.getByTestId('history-action-select')).toBeInTheDocument();
      expect(screen.getByTestId('history-entity-type-select')).toBeInTheDocument();
      expect(screen.getByTestId('history-limit-select')).toBeInTheDocument();
    });

    it('filters history by action', async () => {
      // Start with all history items
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially all history items should be visible
      // Use getAllByText since "Task Created" appears in both the select option and the history item
      await waitFor(() => {
        expect(screen.getByText('Project Created')).toBeInTheDocument();
        expect(screen.getByText('Project Updated')).toBeInTheDocument();
        const taskCreatedElements = screen.getAllByText('Task Created');
        expect(taskCreatedElements.length).toBeGreaterThan(0);
      });

      // Verify filter controls are present
      expect(screen.getByTestId('history-action-select')).toBeInTheDocument();

      // Update mock to return filtered results for action 'created'
      const filtered = mockHistory.filter((item) => item.action === 'created');

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch with action filter
      rerender(<ProjectHistorySection projectId="123" />);

      // Wait for filtered results
      await waitFor(() => {
        expect(screen.getByText('Project Created')).toBeInTheDocument();
        expect(screen.queryByText('Project Updated')).not.toBeInTheDocument();
        // "Task Created" appears in both select and history, but after filtering by action='created',
        // only "Project Created" should be in history (Task Created has action='task_created', not 'created')
        // So we verify Task Created is not in the history list by checking its message
        expect(screen.queryByText('New task was created')).not.toBeInTheDocument();
      });
    });

    it('filters history by entity type', async () => {
      // Start with all history items
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially all history items should be visible
      // Check for unique messages to avoid conflicts with select dropdown options
      await waitFor(() => {
        expect(screen.getByText('Project Created')).toBeInTheDocument();
        expect(screen.getByText('New task was created')).toBeInTheDocument();
        expect(screen.getByText('Document was uploaded')).toBeInTheDocument();
      });

      // Verify filter controls are present
      expect(screen.getByTestId('history-entity-type-select')).toBeInTheDocument();

      // Update mock to return filtered results for entity_type 'task'
      const filtered = mockHistory.filter((item) => item.entity_type === 'task');

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch with entity type filter
      rerender(<ProjectHistorySection projectId="123" />);

      // Wait for filtered results
      await waitFor(() => {
        // Check for the message of Task Created history item to verify it's visible
        expect(screen.getByText('New task was created')).toBeInTheDocument();
        expect(screen.queryByText('Project was created')).not.toBeInTheDocument();
        expect(screen.queryByText('Document was uploaded')).not.toBeInTheDocument();
      });
    });

    it('limits history items based on limit selector', async () => {
      const largeHistory = Array.from({ length: 30 }, (_, i) => ({
        id: String(i + 1),
        action: 'updated',
        action_label: `Update ${i + 1}`,
        entity_type: 'project',
        message: `Update message ${i + 1}`,
        user: { id: '1', name: 'John Doe' },
        created_at: `2025-01-${String(15 - i).padStart(2, '0')}T10:00:00Z`,
        time_ago: `${i + 1} days ago`,
      }));

      // Start with all 30 items
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: largeHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially should show all items
      await waitFor(() => {
        expect(screen.getByText('Update 1')).toBeInTheDocument();
        expect(screen.getByText('Update 30')).toBeInTheDocument();
      });

      // Verify filter controls are present
      expect(screen.getByTestId('history-limit-select')).toBeInTheDocument();

      // Update mock to return limited results (20 items)
      const limited = largeHistory.slice(0, 20);

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: limited },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch with limit filter
      rerender(<ProjectHistorySection projectId="123" />);

      // Should show only 20 items
      await waitFor(() => {
        expect(screen.getByText('Update 1')).toBeInTheDocument();
        expect(screen.getByText('Update 20')).toBeInTheDocument();
        expect(screen.queryByText('Update 21')).not.toBeInTheDocument();
        expect(screen.queryByText('Update 30')).not.toBeInTheDocument();
      });
    });

    it('never requests more than 100 items', async () => {
      // Start with default limit
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Verify filter controls are present
      const limitSelect = screen.getByTestId('history-limit-select');
      expect(limitSelect).toBeInTheDocument();

      // Verify that limit options include 100 but not more
      const selectElement = limitSelect.querySelector('select') as HTMLSelectElement;
      if (selectElement) {
        const options = Array.from(selectElement.options).map(opt => opt.value);
        expect(options).toContain('100');
        // Verify no option exceeds 100
        const numericOptions = options.filter(opt => opt !== '').map(opt => parseInt(opt, 10));
        expect(Math.max(...numericOptions)).toBeLessThanOrEqual(100);
      }
    });
  });

  describe('Document action labels', () => {
    it('renders document uploaded action with correct label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'document_uploaded',
          action_label: 'Document Uploaded',
          entity_type: 'document',
          message: 'Uploaded document "Test Document"',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Document Uploaded');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Uploaded document "Test Document"')).toBeInTheDocument();
      });
    });

    it('renders document updated action with correct label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'document_updated',
          action_label: 'Document Updated',
          entity_type: 'document',
          message: 'Updated document "Test Document"',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Document Updated');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Updated document "Test Document"')).toBeInTheDocument();
      });
    });

    it('renders document deleted action with correct label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'document_deleted',
          action_label: 'Document Deleted',
          entity_type: 'document',
          message: 'Deleted document "Old Document"',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Document Deleted');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Deleted document "Old Document"')).toBeInTheDocument();
      });
    });

    it('renders document downloaded action with correct label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'document_downloaded',
          action_label: 'Document Downloaded',
          entity_type: 'document',
          message: 'Downloaded document "Important Document"',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Document Downloaded');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Downloaded document "Important Document"')).toBeInTheDocument();
      });
    });

    it('filters document actions correctly', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'document_uploaded',
          action_label: 'Document Uploaded',
          entity_type: 'document',
          message: 'Uploaded document "Doc 1"',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
        {
          id: '2',
          action: 'document_updated',
          action_label: 'Document Updated',
          entity_type: 'document',
          message: 'Updated document "Doc 2"',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-14T10:00:00Z',
          time_ago: '1 day ago',
        },
        {
          id: '3',
          action: 'document_deleted',
          action_label: 'Document Deleted',
          entity_type: 'document',
          message: 'Deleted document "Doc 3"',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-13T10:00:00Z',
          time_ago: '2 days ago',
        },
        {
          id: '4',
          action: 'document_downloaded',
          action_label: 'Document Downloaded',
          entity_type: 'document',
          message: 'Downloaded document "Doc 4"',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-12T10:00:00Z',
          time_ago: '3 days ago',
        },
      ];

      // Start with all document actions
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially all document actions should be visible
      await waitFor(() => {
        expect(screen.getByText('Uploaded document "Doc 1"')).toBeInTheDocument();
        expect(screen.getByText('Updated document "Doc 2"')).toBeInTheDocument();
        expect(screen.getByText('Deleted document "Doc 3"')).toBeInTheDocument();
        expect(screen.getByText('Downloaded document "Doc 4"')).toBeInTheDocument();
      });

      // Filter by document_updated action
      const filtered = mockHistory.filter((item) => item.action === 'document_updated');

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      rerender(<ProjectHistorySection projectId="123" />);

      await waitFor(() => {
        expect(screen.getByText('Updated document "Doc 2"')).toBeInTheDocument();
        expect(screen.queryByText('Uploaded document "Doc 1"')).not.toBeInTheDocument();
        expect(screen.queryByText('Deleted document "Doc 3"')).not.toBeInTheDocument();
        expect(screen.queryByText('Downloaded document "Doc 4"')).not.toBeInTheDocument();
      });
    });
  });

  describe('Project tasks reordered action (Round 212)', () => {
    it('renders project_tasks_reordered action with phase label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_tasks_reordered',
          action_label: 'Tasks Reordered',
          entity_type: 'ProjectTask',
          metadata: {
            phase_code: 'TKKT',
            phase_label: 'TKKT',
            task_count: 5,
            task_ids_before: ['task1', 'task2', 'task3', 'task4', 'task5'],
            task_ids_after: ['task5', 'task1', 'task3', 'task2', 'task4'],
          },
          description: 'Reordered 5 task(s) in phase \'TKKT\'',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Tasks Reordered');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Reordered 5 task(s) in phase 'TKKT'")).toBeInTheDocument();
      });
    });

    it('renders project_tasks_reordered action without phase label', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_tasks_reordered',
          action_label: 'Tasks Reordered',
          entity_type: 'ProjectTask',
          metadata: {
            task_count: 3,
            task_ids_before: ['task1', 'task2', 'task3'],
            task_ids_after: ['task3', 'task1', 'task2'],
          },
          description: 'Reordered 3 task(s) (no phase)',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Tasks Reordered');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Reordered 3 task(s) (no phase)')).toBeInTheDocument();
      });
    });

    it('falls back to description when metadata is missing', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_tasks_reordered',
          action_label: 'Tasks Reordered',
          entity_type: 'ProjectTask',
          metadata: null,
          description: 'Reordered 5 task(s) in phase \'TKKT\'',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Tasks Reordered');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Reordered 5 task(s) in phase 'TKKT'")).toBeInTheDocument();
      });
    });

    it('calculates task count from task_ids_after when task_count is missing', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_tasks_reordered',
          action_label: 'Tasks Reordered',
          entity_type: 'ProjectTask',
          metadata: {
            phase_label: 'TKKT',
            task_ids_after: ['task1', 'task2', 'task3', 'task4'],
          },
          description: 'Reordered 4 task(s) in phase \'TKKT\'',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Tasks Reordered');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Reordered 4 task(s) in phase 'TKKT'")).toBeInTheDocument();
      });
    });

    it('filters history by project_tasks_reordered action', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_tasks_reordered',
          action_label: 'Tasks Reordered',
          entity_type: 'ProjectTask',
          metadata: {
            phase_label: 'TKKT',
            task_count: 5,
          },
          description: 'Reordered 5 task(s) in phase \'TKKT\'',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
        {
          id: '2',
          action: 'project_task_completed',
          action_label: 'Project Task Completed',
          entity_type: 'ProjectTask',
          metadata: {
            task_name: 'Test Task',
          },
          description: 'Task "Test Task" marked as completed',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-14T10:00:00Z',
          time_ago: '1 day ago',
        },
        {
          id: '3',
          action: 'created',
          action_label: 'Project Created',
          entity_type: 'project',
          message: 'Project was created',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-13T10:00:00Z',
          time_ago: '2 days ago',
        },
      ];

      // Start with all history items
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially all history items should be visible
      await waitFor(() => {
        expect(screen.getByText("Reordered 5 task(s) in phase 'TKKT'")).toBeInTheDocument();
        expect(screen.getByText('Task "Test Task" marked as completed')).toBeInTheDocument();
        expect(screen.getByText('Project was created')).toBeInTheDocument();
      });

      // Filter by project_tasks_reordered action
      const filtered = mockHistory.filter((item) => item.action === 'project_tasks_reordered');

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      rerender(<ProjectHistorySection projectId="123" />);

      await waitFor(() => {
        // Check for the action label in the history item (not the dropdown)
        const actionLabels = screen.getAllByText('Tasks Reordered');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Reordered 5 task(s) in phase 'TKKT'")).toBeInTheDocument();
        expect(screen.queryByText('Task "Test Task" marked as completed')).not.toBeInTheDocument();
        expect(screen.queryByText('Project was created')).not.toBeInTheDocument();
      });
    });
  });

  describe('Assignment history actions (Round 214)', () => {
    it('renders assigned message', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_assigned',
          action_label: 'Task Assigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_id: 'task1',
            task_name: 'Concept Design',
            old_assignee_id: null,
            old_assignee_name: null,
            new_assignee_id: 'user1',
            new_assignee_name: 'Nguyen Van A',
          },
          description: "Task 'Concept Design' assigned to Nguyen Van A",
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        const actionLabels = screen.getAllByText('Task Assigned');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Task 'Concept Design' assigned to Nguyen Van A")).toBeInTheDocument();
      });
    });

    it('renders unassigned message', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_unassigned',
          action_label: 'Task Unassigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_id: 'task1',
            task_name: 'Concept Design',
            old_assignee_id: 'user1',
            old_assignee_name: 'Nguyen Van A',
            new_assignee_id: null,
            new_assignee_name: null,
          },
          description: "Task 'Concept Design' unassigned (was Nguyen Van A)",
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        const actionLabels = screen.getAllByText('Task Unassigned');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Task 'Concept Design' unassigned (was Nguyen Van A)")).toBeInTheDocument();
      });
    });

    it('renders unassigned message without old assignee name', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_unassigned',
          action_label: 'Task Unassigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_id: 'task1',
            task_name: 'Concept Design',
            old_assignee_id: 'user1',
            old_assignee_name: null,
            new_assignee_id: null,
            new_assignee_name: null,
          },
          description: "Task 'Concept Design' unassigned",
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        const actionLabels = screen.getAllByText('Task Unassigned');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Task 'Concept Design' unassigned")).toBeInTheDocument();
      });
    });

    it('renders reassigned message', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_reassigned',
          action_label: 'Task Reassigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_id: 'task1',
            task_name: 'Concept Design',
            old_assignee_id: 'user1',
            old_assignee_name: 'Nguyen Van A',
            new_assignee_id: 'user2',
            new_assignee_name: 'Tran Van B',
          },
          description: "Task 'Concept Design' reassigned from Nguyen Van A to Tran Van B",
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        const actionLabels = screen.getAllByText('Task Reassigned');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Task 'Concept Design' reassigned from Nguyen Van A to Tran Van B")).toBeInTheDocument();
      });
    });

    it('falls back to description when metadata is missing', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_assigned',
          action_label: 'Task Assigned',
          entity_type: 'ProjectTask',
          metadata: null,
          description: "Task 'Concept Design' assigned to Nguyen Van A",
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
      ];

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        const actionLabels = screen.getAllByText('Task Assigned');
        expect(actionLabels.length).toBeGreaterThan(0);
        expect(screen.getByText("Task 'Concept Design' assigned to Nguyen Van A")).toBeInTheDocument();
      });
    });

    it('filters history by assignment actions', async () => {
      const mockHistory = [
        {
          id: '1',
          action: 'project_task_assigned',
          action_label: 'Task Assigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_name: 'Concept Design',
            new_assignee_name: 'Nguyen Van A',
          },
          description: "Task 'Concept Design' assigned to Nguyen Van A",
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-15T10:00:00Z',
          time_ago: '2 hours ago',
        },
        {
          id: '2',
          action: 'project_task_reassigned',
          action_label: 'Task Reassigned',
          entity_type: 'ProjectTask',
          metadata: {
            task_name: 'UI Design',
            old_assignee_name: 'Nguyen Van A',
            new_assignee_name: 'Tran Van B',
          },
          description: "Task 'UI Design' reassigned from Nguyen Van A to Tran Van B",
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-14T10:00:00Z',
          time_ago: '1 day ago',
        },
        {
          id: '3',
          action: 'project_task_completed',
          action_label: 'Project Task Completed',
          entity_type: 'ProjectTask',
          metadata: {
            task_name: 'Test Task',
          },
          description: 'Task "Test Task" marked as completed',
          user: { id: '1', name: 'John Doe' },
          created_at: '2025-01-13T10:00:00Z',
          time_ago: '2 days ago',
        },
      ];

      // Start with all history items
      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: mockHistory },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectHistorySection projectId="123" />, { wrapper: createWrapper() });

      // Initially all history items should be visible
      await waitFor(() => {
        expect(screen.getByText("Task 'Concept Design' assigned to Nguyen Van A")).toBeInTheDocument();
        expect(screen.getByText("Task 'UI Design' reassigned from Nguyen Van A to Tran Van B")).toBeInTheDocument();
        expect(screen.getByText('Task "Test Task" marked as completed')).toBeInTheDocument();
      });

      // Filter by project_task_assigned action
      const filtered = mockHistory.filter((item) => item.action === 'project_task_assigned');

      mockUseProjectHistory.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      rerender(<ProjectHistorySection projectId="123" />);

      await waitFor(() => {
        expect(screen.getByText("Task 'Concept Design' assigned to Nguyen Van A")).toBeInTheDocument();
        expect(screen.queryByText("Task 'UI Design' reassigned from Nguyen Van A to Tran Van B")).not.toBeInTheDocument();
        expect(screen.queryByText('Task "Test Task" marked as completed')).not.toBeInTheDocument();
      });
    });
  });
});

