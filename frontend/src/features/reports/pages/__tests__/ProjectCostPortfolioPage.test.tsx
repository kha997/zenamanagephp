import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent, cleanup, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectCostPortfolioPage } from '../ProjectCostPortfolioPage';
import { useAuthStore } from '../../../auth/store';
import { useProjectCostPortfolio, useExportProjectCostPortfolio } from '../../hooks';
import toast from 'react-hot-toast';
import { createSearchParamsMock } from '../../../../test-utils/routerMock';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../hooks', () => ({
  useProjectCostPortfolio: vi.fn(),
  useExportProjectCostPortfolio: vi.fn(),
}));

// Mock react-router-dom using shared helper
const mockNavigate = vi.fn();
const routerMock = createSearchParamsMock();

vi.mock('react-router-dom', routerMock.getMockFactory(mockNavigate));

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseProjectCostPortfolio = vi.mocked(useProjectCostPortfolio);
const mockUseExportProjectCostPortfolio = vi.mocked(useExportProjectCostPortfolio);

// Store QueryClient instances for cleanup
const queryClients: QueryClient[] = [];

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        cacheTime: 0, // Disable cache to prevent memory buildup
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

describe('ProjectCostPortfolioPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    routerMock.reset();
    
    // Make setSearchParams update the mock params to prevent infinite loops
    // But don't trigger re-renders - just update the params silently
    const mockSetSearchParams = routerMock.getMockSetSearchParams();
    mockSetSearchParams.mockImplementation((updater) => {
      if (typeof updater === 'function') {
        const newParams = updater(new URLSearchParams(routerMock.currentSearchParams));
        routerMock.currentSearchParams = new URLSearchParams(newParams);
      } else {
        routerMock.currentSearchParams = new URLSearchParams(updater);
      }
      // Don't trigger re-render - the component will read from currentSearchParams on next render
    });

    // Default mock implementations
    mockUseProjectCostPortfolio.mockReturnValue({
      data: {
        data: {
          items: [],
          pagination: {
            total: 0,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      },
      isLoading: false,
      error: null,
    } as any);

    const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
    mockUseExportProjectCostPortfolio.mockReturnValue({
      mutateAsync: mockMutateAsync,
      isPending: false,
    } as any);
  });

  afterEach(() => {
    // Clean up all rendered components
    cleanup();
    
    // Clean up QueryClient instances
    queryClients.forEach(client => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    
    // Restore real timers if fake timers were used
    vi.useRealTimers();
    vi.clearAllTimers();
    vi.clearAllMocks();
  });

  describe('Permission checks', () => {
    it('should render AccessRestricted when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText(/don't have permission to view project cost portfolio reports/i)).toBeInTheDocument();
    });

    it('should render page when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText('Chi phí theo dự án')).toBeInTheDocument();
    });
  });

  describe('Table rendering', () => {
    it('should render table with project data', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 400000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Check table headers
      expect(screen.getByText('Mã dự án')).toBeInTheDocument();
      expect(screen.getByText('Tên dự án')).toBeInTheDocument();
      expect(screen.getByText('Client')).toBeInTheDocument();

      // Check project data
      expect(screen.getByText('PRJ-001')).toBeInTheDocument();
      expect(screen.getByText('Project 1')).toBeInTheDocument();
      expect(screen.getByText('Client A')).toBeInTheDocument();
    });

    it('should navigate to project detail when clicking on a project row', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              project_id: '123',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: null,
              contracts_count: 1,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 100000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Find and click on the project row
      const projectRow = screen.getByText('PRJ-001').closest('tr');
      expect(projectRow).toBeInTheDocument();

      if (projectRow) {
        await userEvent.click(projectRow);
        await waitFor(() => {
          expect(mockNavigate).toHaveBeenCalledWith('/app/projects/123');
        });
      }
    });
  });

  describe('Filters', () => {
    it('should render filter bar with all filter inputs', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByPlaceholderText(/tìm theo mã hoặc tên dự án/i)).toBeInTheDocument();
      expect(screen.getByText(/trạng thái/i)).toBeInTheDocument();
      expect(screen.getByPlaceholderText('0')).toBeInTheDocument();
    });

    it('should update filters when search input changes', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      const searchInput = screen.getByPlaceholderText(/tìm theo mã hoặc tên dự án/i);
      
      // Use userEvent for more realistic interaction
      await userEvent.type(searchInput, 'ABC', { delay: 100 });

      // Wait for debounce (300ms) plus a bit more
      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      }, { timeout: 2000 });
    });
  });

  describe('Sort', () => {
    it('should handle sort change when clicking sortable headers', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: null,
              contracts_count: 1,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 100000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Click on sortable header button - there are multiple "Overrun Total" texts
      // One in chart subtitle, one in table header button
      // Use getAllByText and find the button element
      const overrunTexts = screen.getAllByText(/overrun total/i);
      const sortButton = overrunTexts.find(el => {
        const button = el.closest('button');
        return button !== null;
      });
      
      expect(sortButton).toBeDefined();
      if (sortButton) {
        const button = sortButton.closest('button');
        expect(button).toBeInTheDocument();
        if (button) {
          await userEvent.click(button);
        }
      }

      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      });
    });

    it('should sort by project_code when clicking "Mã dự án" header', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: null,
              contracts_count: 1,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              overrun_amount_total: 100000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Click on "Mã dự án" header
      const codeHeader = screen.getByText(/mã dự án/i);
      await userEvent.click(codeHeader);

      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      });

      // Check the call arguments
      const mockSetSearchParams = routerMock.getMockSetSearchParams();
      const lastCall = mockSetSearchParams.mock.calls[mockSetSearchParams.mock.calls.length - 1];
      const callArgs = lastCall[0];
      
      // callArgs can be a function (updater) or URLSearchParams
      if (typeof callArgs === 'function') {
        const testParams = new URLSearchParams();
        const result = callArgs(testParams);
        expect(result.get('sort_by')).toBe('project_code');
      } else {
        expect(callArgs.get('sort_by')).toBe('project_code');
      }
    });
  });

  describe('Filter propagation', () => {
    it('should pass min_overrun_amount from URL into project portfolio query', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with min_overrun_amount and search
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('min_overrun_amount', '300');
      routerMock.currentSearchParams.set('search', 'villa');

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify that useProjectCostPortfolio was called with the correct filters
      // The hook is called with: (filters, pagination, sort)
      const calls = mockUseProjectCostPortfolio.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      
      const [filtersArg] = calls[calls.length - 1];
      expect(filtersArg).toMatchObject({
        search: 'villa',
        min_overrun_amount: 300,
      });
    });

    it('should pass client_id from URL into project portfolio query', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with client_id and search
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('client_id', '123');
      routerMock.currentSearchParams.set('search', 'villa');

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify that useProjectCostPortfolio was called with the correct filters
      // The hook is called with: (filters, pagination, sort)
      const calls = mockUseProjectCostPortfolio.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      
      const [filtersArg] = calls[calls.length - 1];
      expect(filtersArg).toMatchObject({
        search: 'villa',
        client_id: '123',
      });
    });
  });

  describe('Currency rendering', () => {
    it('should render amounts using project currency', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Dự án A',
              client: { id: 'c1', name: 'Client X' },
              contracts_count: 2,
              contracts_value_total: 5_000_000_000,
              budget_total: 5_200_000_000,
              actual_total: 5_400_000_000,
              overrun_amount_total: 400_000_000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Find row PRJ-001
      const projectRow = screen.getByText('PRJ-001').closest('tr');
      expect(projectRow).toBeInTheDocument();

      // Check that currency-formatted values are present
      // formatCurrency uses Intl.NumberFormat which formats numbers with commas
      if (projectRow) {
        const rowText = projectRow.textContent || '';
        // Should contain formatted numbers (with commas) for VND currency
        // The exact format depends on Intl.NumberFormat, but should contain the numbers
        expect(rowText).toMatch(/5,000,000,000|5,200,000,000|5,400,000,000|400,000,000/);
      }
    });
  });

  describe('Client filter badge', () => {
    it('should show active client filter badge when client_id is present and data exists', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with client_id
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('client_id', '123');

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Villa A',
              client: { id: '123', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 400000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Badge should be visible with client name
      expect(screen.getByText(/Đang lọc theo khách hàng/i)).toBeInTheDocument();
      // Client A appears in both badge and table, so use getAllByText and check badge context
      const clientNames = screen.getAllByText('Client A');
      expect(clientNames.length).toBeGreaterThan(0);
      // Verify badge contains the client name
      const badge = screen.getByText(/Đang lọc theo khách hàng/i).closest('span');
      expect(badge).toBeInTheDocument();
      if (badge) {
        expect(badge.textContent).toContain('Client A');
      }
    });

    it('should show client ID fallback when client name is not available', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with client_id
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('client_id', '123');

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Villa A',
              client: { id: '123', name: null },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 400000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Badge should show ID fallback
      expect(screen.getByText(/Đang lọc theo khách hàng/i)).toBeInTheDocument();
      expect(screen.getByText(/ID: 123/i)).toBeInTheDocument();
    });

    it('should not show badge when client_id is not present', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // No client_id in search params
      routerMock.currentSearchParams = new URLSearchParams();

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Villa A',
              client: { id: '123', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 400000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Badge should not be visible
      expect(screen.queryByText(/Đang lọc theo khách hàng/i)).not.toBeInTheDocument();
    });

    it('should not show badge when client_id is present but no data exists', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with client_id
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('client_id', '123');

      // No items in response
      mockUseProjectCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [],
            pagination: {
              total: 0,
              per_page: 25,
              current_page: 1,
              last_page: 1,
            },
          },
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Badge should not be visible when no data
      expect(screen.queryByText(/Đang lọc theo khách hàng/i)).not.toBeInTheDocument();
    });
  });

  describe('Clear client filter button', () => {
    it('should clear client_id filter from URL when clicking clear client filter button', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with client_id and other filters
      routerMock.currentSearchParams = new URLSearchParams();
      routerMock.currentSearchParams.set('client_id', '123');
      routerMock.currentSearchParams.set('search', 'villa');
      routerMock.currentSearchParams.set('status', 'active');
      routerMock.currentSearchParams.set('page', '2');

      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Villa A',
              client: { id: '123', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 400000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Find and click the clear client filter button
      const clearButton = screen.getByText(/Xóa lọc khách hàng/i);
      expect(clearButton).toBeInTheDocument();

      await userEvent.click(clearButton);

      // Assert: setSearchParams should be called
      const mockSetSearchParams = routerMock.getMockSetSearchParams();
      await waitFor(() => {
        expect(mockSetSearchParams).toHaveBeenCalled();
      });

      // Assert: The new params should not have client_id but should keep other filters
      const callArgs = mockSetSearchParams.mock.calls[0][0];
      
      // If it's a function (updater), simulate it
      if (typeof callArgs === 'function') {
        const currentParams = new URLSearchParams();
        currentParams.set('client_id', '123');
        currentParams.set('search', 'villa');
        currentParams.set('status', 'active');
        currentParams.set('page', '2');
        
        const result = callArgs(currentParams);
        expect(result.get('client_id')).toBeNull();
        expect(result.get('search')).toBe('villa');
        expect(result.get('status')).toBe('active');
        expect(result.get('page')).toBe('2');
      } else {
        // If it's a URLSearchParams directly
        expect(callArgs.get('client_id')).toBeNull();
        expect(callArgs.get('search')).toBe('villa');
        expect(callArgs.get('status')).toBe('active');
        expect(callArgs.get('page')).toBe('2');
      }
    });
  });

  describe('Chart rendering', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should render chart when items have overrun > 0', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project A',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 5000000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
            {
              project_id: '2',
              project_code: 'PRJ-002',
              project_name: 'Project B',
              client: { id: '2', name: 'Client B' },
              contracts_count: 2,
              contracts_value_total: 3000000,
              budget_total: 3200000,
              actual_total: 3400000,
              overrun_amount_total: 2000000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 2,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart title should be visible
      expect(screen.getByText('Top dự án vượt chi phí')).toBeInTheDocument();
      
      // Assert: Chart should be rendered (has data-testid)
      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
    });

    it('should not render chart when all overrun_amount_total <= 0', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project A',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5000000,
              overrun_amount_total: 0,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'VND',
            },
            {
              project_id: '2',
              project_code: 'PRJ-002',
              project_name: 'Project B',
              client: { id: '2', name: 'Client B' },
              contracts_count: 2,
              contracts_value_total: 3000000,
              budget_total: 3200000,
              actual_total: 3000000,
              overrun_amount_total: null,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 2,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top dự án vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-projects-chart')).not.toBeInTheDocument();
    });

    it('should not render chart when loading', () => {
      mockUseProjectCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top dự án vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-projects-chart')).not.toBeInTheDocument();
    });

    it('should not render chart when error', () => {
      mockUseProjectCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top dự án vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-projects-chart')).not.toBeInTheDocument();
    });

    it('should render chart with drill-down capability (chart component handles navigation)', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '123',
              project_code: 'PRJ-001',
              project_name: 'Project A',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 5000000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart is rendered with data
      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
      
      // Note: Actual bar click testing is done in TopOverrunProjectsChart.test.tsx
      // This test verifies the chart is rendered in the page context
    });
  });

  describe('MoneyCell integration - project overrun total', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('renders positive overrun with danger tone and no plus sign', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5400000,
              overrun_amount_total: 500_000_000,
              over_budget_contracts_count: 2,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      const row = screen.getByText('PRJ-001').closest('tr')!;
      const overrunCell = within(row).getByTestId('overrun-total-cell');
      
      // Assert: Cell contains number (flexible regex for locale)
      expect(overrunCell.textContent).toMatch(/500[.,]0{3}[.,]0{3}/);
      
      // Assert: No + sign (showPlusWhenPositive={false})
      expect(overrunCell.textContent).not.toMatch(/\+/);
      
      // Assert: Has span with data-tone="danger"
      const dangerSpan = overrunCell.querySelector('span[data-tone="danger"]');
      expect(dangerSpan).not.toBeNull();
    });

    it('renders non-positive overrun with muted tone and no plus sign', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '2',
              project_code: 'PRJ-002',
              project_name: 'Project 2',
              client: { id: '1', name: 'Client A' },
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 5200000,
              actual_total: 5000000,
              overrun_amount_total: 0,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 1,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      const row = screen.getByText('PRJ-002').closest('tr')!;
      const overrunCell = within(row).getByTestId('overrun-total-cell');
      
      // Assert: Cell contains 0
      expect(overrunCell.textContent).toMatch(/0/);
      
      // Assert: No + sign
      expect(overrunCell.textContent).not.toMatch(/\+/);
      
      // Assert: Has span with data-tone="muted"
      const mutedSpan = overrunCell.querySelector('span[data-tone="muted"]');
      expect(mutedSpan).not.toBeNull();
    });
  });

  describe('Filter integration - clamp and parse', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('clamps negative page from URL to 1 when calling useProjectCostPortfolio', () => {
      routerMock.setSearchParams({ 
        page: '-5',
        search: 'villa', // Add filter to ensure render works
      });

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify the hook was called with page=1 (clamped from -5)
      const calls = mockUseProjectCostPortfolio.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      const [, paginationArg] = calls[0];
      expect(paginationArg).toMatchObject({
        page: 1,
      });
    });

    it('treats invalid min_overrun_amount as undefined for project portfolio', () => {
      routerMock.setSearchParams({ 
        min_overrun_amount: 'abc',
        search: 'villa',
      });

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify the hook was called with min_overrun_amount=undefined
      const calls = mockUseProjectCostPortfolio.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      const [filtersArg] = calls[0];
      expect(filtersArg).toMatchObject({
        min_overrun_amount: undefined,
      });
    });
  });

  describe('Page summary row', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('renders summary row with correct totals for single-currency data', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: { id: '1', name: 'Client A' },
              projects_count: 1,
              contracts_count: 3,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1300000,
              overrun_amount_total: 300000,
              currency: 'VND',
            },
            {
              project_id: '2',
              project_code: 'PRJ-002',
              project_name: 'Project 2',
              client: { id: '2', name: 'Client B' },
              projects_count: 1,
              contracts_count: 2,
              contracts_value_total: 2000000,
              budget_total: 2200000,
              actual_total: 2400000,
              overrun_amount_total: 400000,
              currency: 'VND',
            },
          ],
          pagination: {
            total: 2,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Summary row exists
      const summaryRow = screen.getByTestId('page-summary-row');
      expect(summaryRow).toBeInTheDocument();

      // Assert: Count totals (exact)
      const contractsCountCell = screen.getByTestId('summary-contracts-count');
      expect(contractsCountCell.textContent).toContain('5'); // 3 + 2

      // Assert: Money totals (loose numeric regex)
      const contractsValueCell = screen.getByTestId('summary-contracts-value');
      expect(contractsValueCell.textContent).toMatch(/3[0-9]{6}/); // 3000000

      const budgetCell = screen.getByTestId('summary-budget');
      expect(budgetCell.textContent).toMatch(/3[0-9]{6}/); // 3400000

      const actualCell = screen.getByTestId('summary-actual');
      expect(actualCell.textContent).toMatch(/3[0-9]{6}/); // 3700000

      const overrunCell = screen.getByTestId('summary-overrun');
      expect(overrunCell.textContent).toMatch(/7[0-9]{5}/); // 700000
    });

    it('renders "-" for money summary when currencies differ', () => {
      const mockData = {
        data: {
          items: [
            {
              project_id: '1',
              project_code: 'PRJ-001',
              project_name: 'Project 1',
              client: { id: '1', name: 'Client A' },
              projects_count: 1,
              contracts_count: 3,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1300000,
              overrun_amount_total: 300000,
              currency: 'VND',
            },
            {
              project_id: '2',
              project_code: 'PRJ-002',
              project_name: 'Project 2',
              client: { id: '2', name: 'Client B' },
              projects_count: 1,
              contracts_count: 2,
              contracts_value_total: 2000000,
              budget_total: 2200000,
              actual_total: 2400000,
              overrun_amount_total: 400000,
              currency: 'USD',
            },
          ],
          pagination: {
            total: 2,
            per_page: 25,
            current_page: 1,
            last_page: 1,
          },
        },
      };

      mockUseProjectCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Summary row exists
      const summaryRow = screen.getByTestId('page-summary-row');
      expect(summaryRow).toBeInTheDocument();

      // Assert: Money summary cells show '-' for mixed currencies
      const contractsValueCell = screen.getByTestId('summary-contracts-value');
      expect(contractsValueCell.textContent).toContain('-');

      const budgetCell = screen.getByTestId('summary-budget');
      expect(budgetCell.textContent).toContain('-');

      const actualCell = screen.getByTestId('summary-actual');
      expect(actualCell.textContent).toContain('-');

      const overrunCell = screen.getByTestId('summary-overrun');
      expect(overrunCell.textContent).toContain('-');
    });

    it('does not render summary when there are no items', () => {
      mockUseProjectCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [],
            pagination: {
              total: 0,
              per_page: 25,
              current_page: 1,
              last_page: 1,
            },
          },
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('does not render summary when loading', () => {
      mockUseProjectCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('does not render summary when error', () => {
      mockUseProjectCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });
  });

  describe('Export functionality', () => {
    it('should render export button', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/xuất csv/i)).toBeInTheDocument();
    });

    it('should call export mutation when export button is clicked', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportProjectCostPortfolio.mockReturnValue({
        mutateAsync: mockMutateAsync,
        isPending: false,
      } as any);

      // Mock window.URL methods
      const createObjectURLSpy = vi.fn(() => 'blob:mock-url');
      const revokeObjectURLSpy = vi.fn();
      Object.defineProperty(window, 'URL', {
        value: {
          ...window.URL,
          createObjectURL: createObjectURLSpy,
          revokeObjectURL: revokeObjectURLSpy,
        },
        writable: true,
      });

      // Mock document.createElement and appendChild
      const mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };
      const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as any);
      const appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation(() => mockAnchor as any);
      const removeChildSpy = vi.spyOn(document.body, 'removeChild').mockImplementation(() => mockAnchor as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/xuất csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalled();
        expect(createObjectURLSpy).toHaveBeenCalled();
        expect(mockAnchor.click).toHaveBeenCalled();
        expect(toast.success).toHaveBeenCalled();
      });

      createElementSpy.mockRestore();
      appendChildSpy.mockRestore();
      removeChildSpy.mockRestore();
    });

    it('should use same filters for export as table', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with filters
      routerMock.setSearchParams({
        search: 'villa',
        status: 'active',
        client_id: '123',
        min_overrun_amount: '300',
        sort_by: 'overrun_amount_total',
        sort_direction: 'desc',
      });

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportProjectCostPortfolio.mockReturnValue({
        mutateAsync: mockMutateAsync,
        isPending: false,
      } as any);

      // Mock window.URL methods
      const createObjectURLSpy = vi.fn(() => 'blob:mock-url');
      Object.defineProperty(window, 'URL', {
        value: {
          ...window.URL,
          createObjectURL: createObjectURLSpy,
        },
        writable: true,
      });

      const mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };
      const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as any);
      const appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation(() => mockAnchor as any);
      const removeChildSpy = vi.spyOn(document.body, 'removeChild').mockImplementation(() => mockAnchor as any);

      render(<ProjectCostPortfolioPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/xuất csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'villa',
            status: 'active',
            client_id: '123',
            min_overrun_amount: 300,
            sort_by: 'overrun_amount_total',
            sort_direction: 'desc',
          })
        );
      });

      createElementSpy.mockRestore();
      appendChildSpy.mockRestore();
      removeChildSpy.mockRestore();
    });
  });
});

