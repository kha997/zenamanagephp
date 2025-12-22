import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { createMemoryHistory, type MemoryHistory } from 'history';
import { unstable_HistoryRouter as HistoryRouter } from 'react-router-dom';
import { vi, describe, it, beforeEach, afterEach, expect } from 'vitest';
import { GlobalSearchPage } from '@/features/search/pages/GlobalSearchPage';
import { useGlobalSearch } from '@/features/search/hooks/useGlobalSearch';
import { useProjects } from '@/features/projects/hooks';

vi.mock('@/features/search/hooks/useGlobalSearch');
vi.mock('@/features/projects/hooks', () => ({
  useProjects: vi.fn(),
}));

const mockUseGlobalSearch = vi.mocked(useGlobalSearch);
const mockUseProjects = vi.mocked(useProjects);

const renderWithRouter = (history?: MemoryHistory) => {
  const routerHistory = history ?? createMemoryHistory({ initialEntries: ['/app/search?q=riviera'] });
  render(
    <HistoryRouter history={routerHistory}>
      <GlobalSearchPage />
    </HistoryRouter>,
  );
  return routerHistory;
};

describe('GlobalSearchPage', () => {
  beforeEach(() => {
    mockUseProjects.mockReturnValue({
      data: {
        data: [
          { id: 'proj-1', name: 'Project One' },
          { id: 'proj-2', name: 'Project Two' },
        ],
      },
    } as any);

    mockUseGlobalSearch.mockReturnValue({
      data: {
        pagination: { page: 1, per_page: 20, total: 5 },
        results: [
          {
            id: 'proj-1',
            module: 'projects',
            type: 'project',
            title: 'Project One',
            subtitle: 'Code: P1',
            description: 'Project search hit',
            project_id: 'proj-1',
            project_name: 'Project One',
            status: 'active',
            entity: {},
          },
          {
            id: 'task-1',
            module: 'tasks',
            type: 'task',
            title: 'Design a Riviera task',
            subtitle: 'Phase 1',
            description: 'Task details',
            project_id: 'proj-1',
            project_name: 'Project One',
            status: 'in_progress',
            entity: {},
          },
          {
            id: 'doc-1',
            module: 'documents',
            type: 'document',
            title: 'Riviera Document',
            subtitle: 'Architecture',
            description: 'Document hit',
            project_id: 'proj-1',
            project_name: 'Project One',
            status: 'approved',
            entity: {},
          },
          {
            id: 'co-1',
            module: 'cost',
            type: 'change_order',
            title: 'CO Riviera',
            subtitle: 'CO: R-01',
            description: null,
            project_id: 'proj-1',
            project_name: 'Project One',
            status: 'approved',
            entity: { contract_id: 'contract-1' },
          },
          {
            id: 'user-1',
            module: 'users',
            type: 'user',
            title: 'Riviera User',
            subtitle: 'user@example.com',
            description: null,
            project_id: null,
            project_name: null,
            status: 'active',
            entity: {},
          },
        ],
      },
      isLoading: false,
      isError: false,
    } as any);
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('renders grouped results for all modules', () => {
    renderWithRouter();

    ['Project', 'Task', 'Document', 'Cost', 'User'].forEach((label) => {
      expect(screen.getAllByText(label).length).toBeGreaterThan(0);
    });

    const resultCards = screen.getAllByTestId('global-search-result');
    expect(resultCards).toHaveLength(5);
  });

  it('updates query params when submitting header search', () => {
    const history = renderWithRouter();
    const input = screen.getByLabelText('Search query') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'Library' } });
    fireEvent.click(screen.getByRole('button', { name: /^search$/i }));

    expect(history.location.search).toContain('q=Library');
    expect(history.location.search).toContain('page=1');
  });

  it('navigates to change order when cost result is clicked', () => {
    const history = renderWithRouter();
    const costResult = screen.getAllByTestId('global-search-result')[3];
    fireEvent.click(costResult);

    expect(history.location.pathname).toBe('/app/projects/proj-1/contracts/contract-1/change-orders/co-1');
  });
});
