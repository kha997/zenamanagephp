import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { createMemoryHistory, type MemoryHistory } from 'history';
import { unstable_HistoryRouter as HistoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { CommandPalette } from '@/features/search/components/CommandPalette';
import { CommandPaletteProvider } from '@/features/search/context/CommandPaletteContext';
import { useGlobalSearch } from '@/features/search/hooks/useGlobalSearch';
import { useCommandPalette } from '@/features/search/context/CommandPaletteContext';
import type { GlobalSearchResult } from '@/api/searchApi';

vi.mock('@/features/search/hooks/useGlobalSearch');

const mockUseGlobalSearch = vi.mocked(useGlobalSearch);

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

const mockResults: GlobalSearchResult[] = [
  {
    id: 'proj-1',
    module: 'projects',
    type: 'project',
    title: 'Riviera Tower',
    subtitle: 'Code: RT-001',
    description: 'A beautiful tower project',
    project_id: 'proj-1',
    project_name: 'Riviera Tower',
    status: 'active',
    entity: {},
  },
  {
    id: 'task-1',
    module: 'tasks',
    type: 'task',
    title: 'Design Task',
    subtitle: 'Phase 1',
    description: 'Task details',
    project_id: 'proj-1',
    project_name: 'Riviera Tower',
    status: 'in_progress',
    entity: {},
  },
  {
    id: 'doc-1',
    module: 'documents',
    type: 'document',
    title: 'Project Document',
    subtitle: 'Architecture',
    description: 'Document details',
    project_id: 'proj-1',
    project_name: 'Riviera Tower',
    status: 'approved',
    entity: {},
  },
];

// Test component that can control palette state
function TestWrapper({ children, initialOpen = false }: { children: React.ReactNode; initialOpen?: boolean }) {
  const history = createMemoryHistory({ initialEntries: ['/app/dashboard'] });

  return (
    <QueryClientProvider client={createQueryClient()}>
      <HistoryRouter history={history}>
        <CommandPaletteProvider>
          <TestController initialOpen={initialOpen} />
          {children}
        </CommandPaletteProvider>
      </HistoryRouter>
    </QueryClientProvider>
  );
}

function TestController({ initialOpen }: { initialOpen: boolean }) {
  const { open, close } = useCommandPalette();

  React.useEffect(() => {
    if (initialOpen) {
      open();
    } else {
      close();
    }
  }, [initialOpen, open, close]);

  return null;
}

describe('CommandPalette', () => {
  beforeEach(() => {
    mockUseGlobalSearch.mockReturnValue({
      data: {
        pagination: { page: 1, per_page: 10, total: mockResults.length },
        results: mockResults,
      },
      isLoading: false,
      isError: false,
      isSuccess: true,
    } as any);
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('does not render when closed', () => {
    render(
      <TestWrapper initialOpen={false}>
        <CommandPalette />
      </TestWrapper>
    );

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders when open', () => {
    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    expect(screen.getByRole('dialog', { name: 'Command palette' })).toBeInTheDocument();
    expect(screen.getByPlaceholderText(/Type to search/i)).toBeInTheDocument();
  });

  it('shows empty state when no query is entered', () => {
    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    expect(screen.getByText(/Type to search across projects, tasks, documents, cost, users/i)).toBeInTheDocument();
  });

  it('shows loading state when searching', async () => {
    mockUseGlobalSearch.mockReturnValue({
      data: undefined,
      isLoading: true,
      isError: false,
      isSuccess: false,
    } as any);

    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'test' } });

    await waitFor(() => {
      expect(screen.getByText(/Searching…/i)).toBeInTheDocument();
    });
  });

  it('shows no results message when query returns no results', async () => {
    mockUseGlobalSearch.mockReturnValue({
      data: {
        pagination: { page: 1, per_page: 10, total: 0 },
        results: [],
      },
      isLoading: false,
      isError: false,
      isSuccess: true,
    } as any);

    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'nonexistent' } });

    await waitFor(
      () => {
        expect(screen.getByText(/No matches for "nonexistent"/i)).toBeInTheDocument();
      },
      { timeout: 500 }
    );
  });

  it('displays search results when query returns matches', async () => {
    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'riviera' } });

    await waitFor(
      () => {
        expect(screen.getByText('Riviera Tower')).toBeInTheDocument();
        expect(screen.getByText('Design Task')).toBeInTheDocument();
      },
      { timeout: 500 }
    );
  });

  it('closes when Escape key is pressed', async () => {
    const history = createMemoryHistory({ initialEntries: ['/app/dashboard'] });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <HistoryRouter history={history}>
          <CommandPaletteProvider>
            <TestController initialOpen={true} />
            <CommandPalette />
          </CommandPaletteProvider>
        </HistoryRouter>
      </QueryClientProvider>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.keyDown(input, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('closes when backdrop is clicked', async () => {
    const history = createMemoryHistory({ initialEntries: ['/app/dashboard'] });

    const { container } = render(
      <QueryClientProvider client={createQueryClient()}>
        <HistoryRouter history={history}>
          <CommandPaletteProvider>
            <TestController initialOpen={true} />
            <CommandPalette />
          </CommandPaletteProvider>
        </HistoryRouter>
      </QueryClientProvider>
    );

    // Find the backdrop (the outer div)
    const backdrop = container.querySelector('.fixed.inset-0');
    expect(backdrop).toBeInTheDocument();

    // Click on the backdrop (not on the panel)
    if (backdrop) {
      fireEvent.click(backdrop);
    }

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('navigates to result route when result is clicked', async () => {
    const history = createMemoryHistory({ initialEntries: ['/app/dashboard'] });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <HistoryRouter history={history}>
          <CommandPaletteProvider>
            <TestController initialOpen={true} />
            <CommandPalette />
          </CommandPaletteProvider>
        </HistoryRouter>
      </QueryClientProvider>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'riviera' } });

    await waitFor(
      () => {
        const result = screen.getByText('Riviera Tower');
        fireEvent.click(result.closest('[data-testid="global-search-result"]')!);
      },
      { timeout: 500 }
    );

    await waitFor(() => {
      expect(history.location.pathname).toBe('/app/projects/proj-1');
    });
  });

  it('navigates with keyboard Arrow Down and Enter', async () => {
    const history = createMemoryHistory({ initialEntries: ['/app/dashboard'] });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <HistoryRouter history={history}>
          <CommandPaletteProvider>
            <TestController initialOpen={true} />
            <CommandPalette />
          </CommandPaletteProvider>
        </HistoryRouter>
      </QueryClientProvider>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'riviera' } });

    await waitFor(
      () => {
        expect(screen.getByText('Riviera Tower')).toBeInTheDocument();
        expect(screen.getByText('Design Task')).toBeInTheDocument();
      },
      { timeout: 500 }
    );

    // Wait a bit for the selectedIndex to be set to 0 (default)
    await new Promise((resolve) => setTimeout(resolve, 50));

    // Press Arrow Down to move from index 0 to index 1
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    // Press Enter to navigate to second result (index 1 = task)
    fireEvent.keyDown(input, { key: 'Enter' });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/app/tasks/task-1');
    });
  });

  it('debounces search input', async () => {
    const mockFn = vi.fn();
    mockUseGlobalSearch.mockImplementation((params) => {
      mockFn(params);
      return {
        data: {
          pagination: { page: 1, per_page: 10, total: 0 },
          results: [],
        },
        isLoading: false,
        isError: false,
        isSuccess: true,
      } as any;
    });

    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    
    // Type multiple characters quickly
    fireEvent.change(input, { target: { value: 'r' } });
    fireEvent.change(input, { target: { value: 'ri' } });
    fireEvent.change(input, { target: { value: 'riv' } });
    fireEvent.change(input, { target: { value: 'rivi' } });
    fireEvent.change(input, { target: { value: 'riviera' } });

    // Wait for debounce (300ms + some buffer)
    await waitFor(
      () => {
        // Should be called with the final query after debounce
        expect(mockFn).toHaveBeenCalled();
        const calls = mockFn.mock.calls;
        const lastCall = calls[calls.length - 1];
        expect(lastCall[0].q).toBe('riviera');
      },
      { timeout: 600 }
    );
  });

  it('calls useGlobalSearch with correct parameters', async () => {
    render(
      <TestWrapper initialOpen={true}>
        <CommandPalette />
      </TestWrapper>
    );

    const input = screen.getByPlaceholderText(/Type to search/i);
    fireEvent.change(input, { target: { value: 'test query' } });

    await waitFor(
      () => {
        expect(mockUseGlobalSearch).toHaveBeenCalledWith(
          {
            q: 'test query',
            page: 1,
            per_page: 10,
          },
          true
        );
      },
      { timeout: 500 }
    );
  });
});
