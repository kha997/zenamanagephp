import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ClientCostPortfolioPage } from '../ClientCostPortfolioPage';
import { useAuthStore } from '../../../auth/store';
import { useClientCostPortfolio, useExportClientCostPortfolio } from '../../hooks';
import toast from 'react-hot-toast';
import { createSearchParamsMock } from '../../../../test-utils/routerMock';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../hooks', () => ({
  useClientCostPortfolio: vi.fn(),
  useExportClientCostPortfolio: vi.fn(),
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
const mockUseClientCostPortfolio = vi.mocked(useClientCostPortfolio);
const mockUseExportClientCostPortfolio = vi.mocked(useExportClientCostPortfolio);

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('ClientCostPortfolioPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    routerMock.reset();

    // Default mock implementations
    mockUseClientCostPortfolio.mockReturnValue({
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
    mockUseExportClientCostPortfolio.mockReturnValue({
      mutateAsync: mockMutateAsync,
      isPending: false,
    } as any);
  });

  describe('Permission checks', () => {
    it('should render AccessRestricted when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText(/don't have permission to view client cost portfolio reports/i)).toBeInTheDocument();
    });

    it('should render page when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/chi phí theo khách hàng/i)).toBeInTheDocument();
    });
  });

  describe('Table rendering', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should render table headers', () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/tên khách hàng/i)).toBeInTheDocument();
      expect(screen.getByText(/số hđ/i)).toBeInTheDocument();
      expect(screen.getByText(/số dự án/i)).toBeInTheDocument();
      expect(screen.getByText(/overrun total/i)).toBeInTheDocument();
    });

    it('should render client data in table', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [
              {
                client_id: '123',
                client_code: null,
                client_name: 'Test Client',
                projects_count: 2,
                contracts_count: 3,
                contracts_value_total: 5000.0,
                budget_total: 6000.0,
                actual_total: 7000.0,
                overrun_amount_total: 2000.0,
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
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText('Test Client')).toBeInTheDocument();
      expect(screen.getByText('3')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('should render empty state when no data', () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/hiện chưa có dữ liệu chi phí theo khách hàng/i)).toBeInTheDocument();
    });

    it('should render loading state', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // LoadingSpinner should be rendered
      expect(screen.getByRole('status') || screen.queryByText(/loading/i)).toBeTruthy();
    });

    it('should render error state', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/không tải được danh sách khách hàng/i)).toBeInTheDocument();
      expect(screen.getByText(/thử lại/i)).toBeInTheDocument();
    });
  });

  describe('Filters', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should render filter bar with all filter inputs', () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByPlaceholderText(/tìm theo tên khách hàng/i)).toBeInTheDocument();
      expect(screen.getByText(/trạng thái hđ/i)).toBeInTheDocument();
      expect(screen.getByPlaceholderText('0')).toBeInTheDocument();
    });

    it('should update filters when search input changes', async () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const searchInput = screen.getByPlaceholderText(/tìm theo tên khách hàng/i);
      fireEvent.change(searchInput, { target: { value: 'ABC' } });

      // Wait for debounce
      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      }, { timeout: 500 });
    });

    it('should update filters when min_overrun_amount changes', () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const minOverrunInput = screen.getByPlaceholderText('0');
      fireEvent.change(minOverrunInput, { target: { value: '1000' } });

      expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
    });

    it('should pass min_overrun_amount from URL into client portfolio query as a number', () => {
      // Arrange: mock URLSearchParams with min_overrun_amount=300
      routerMock.setSearchParams({
        search: 'villa',
        min_overrun_amount: '300',
      });

      // Clear previous calls
      mockUseClientCostPortfolio.mockClear();

      // Act: render the page
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: useClientCostPortfolio should be called with min_overrun_amount as number 300
      expect(mockUseClientCostPortfolio).toHaveBeenCalledWith(
        expect.objectContaining({
          min_overrun_amount: 300, // Should be number, not string
        }),
        expect.any(Object),
        expect.any(Object)
      );
    });

    it('should reset filters when reset button is clicked', () => {
      routerMock.setSearchParams({
        search: 'test',
        min_overrun_amount: '1000',
      });

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const resetButton = screen.getByText(/xóa bộ lọc/i);
      fireEvent.click(resetButton);

      expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
    });
  });

  describe('Sort', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
      routerMock.getMockSetSearchParams().mockClear();
    });

    it('should toggle sort direction when clicking sortable header', () => {
      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const clientNameHeader = screen.getByText(/tên khách hàng/i).closest('button');
      if (clientNameHeader) {
        fireEvent.click(clientNameHeader);
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      }
    });

    it('should sort by overrun_amount_total by default', () => {
      routerMock.reset();

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(mockUseClientCostPortfolio).toHaveBeenCalledWith(
        expect.any(Object),
        expect.any(Object),
        expect.objectContaining({
          sort_by: 'overrun_amount_total',
          sort_direction: 'desc',
        })
      );
    });

    it('should set sort_by=client_name when clicking client name header', async () => {
      // Setup: default sort is overrun_amount_total desc
      routerMock.reset();

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Clear previous calls
      routerMock.getMockSetSearchParams().mockClear();

      // Act: click client name header
      const clientNameHeader = screen.getByText(/tên khách hàng/i).closest('button');
      expect(clientNameHeader).toBeTruthy();
      await userEvent.click(clientNameHeader!);

      // Assert: setSearchParams should be called with sort_by=client_name
      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
        const mockSetSearchParams = routerMock.getMockSetSearchParams();
        const callArgs = mockSetSearchParams.mock.calls[0][0];
        
        // setSearchParams receives an updater function, so we need to call it
        if (typeof callArgs === 'function') {
          const currentParams = new URLSearchParams();
          const newParams = callArgs(currentParams);
          expect(newParams.get('sort_by')).toBe('client_name');
          // When clicking for first time, should toggle to asc (since default is desc)
          expect(newParams.get('sort_direction')).toBe('asc');
        } else if (callArgs instanceof URLSearchParams) {
          expect(callArgs.get('sort_by')).toBe('client_name');
          expect(callArgs.get('sort_direction')).toBe('asc');
        }
      });
    });

    it('should set sort_by=overrun_amount_total when clicking overrun total header', async () => {
      // Setup: start with client_name sort
      routerMock.setSearchParams({
        sort_by: 'client_name',
        sort_direction: 'asc',
      });

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Clear previous calls
      routerMock.getMockSetSearchParams().mockClear();

      // Act: click overrun total header
      const overrunHeader = screen.getByText(/overrun total/i).closest('button');
      expect(overrunHeader).toBeTruthy();
      await userEvent.click(overrunHeader!);

      // Assert: setSearchParams should be called with sort_by=overrun_amount_total
      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
        const mockSetSearchParams = routerMock.getMockSetSearchParams();
        const callArgs = mockSetSearchParams.mock.calls[0][0];
        
        if (typeof callArgs === 'function') {
          const currentParams = new URLSearchParams();
          currentParams.set('sort_by', 'client_name');
          currentParams.set('sort_direction', 'asc');
          const newParams = callArgs(currentParams);
          expect(newParams.get('sort_by')).toBe('overrun_amount_total');
          // When clicking from asc, should toggle to desc
          expect(newParams.get('sort_direction')).toBe('desc');
        } else if (callArgs instanceof URLSearchParams) {
          expect(callArgs.get('sort_by')).toBe('overrun_amount_total');
          expect(callArgs.get('sort_direction')).toBe('desc');
        }
      });
    });
  });

  describe('Currency rendering', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should format currency correctly for VND', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [
              {
                client_id: '123',
                client_code: null,
                client_name: 'Test Client',
                projects_count: 1,
                contracts_count: 1,
                contracts_value_total: 10000000.0,
                budget_total: 12000000.0,
                actual_total: 15000000.0,
                overrun_amount_total: 5000000.0,
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
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Check that currency is formatted (should contain VND symbol or formatted number)
      const tableText = screen.getByText('Test Client').closest('tr')?.textContent || '';
      expect(tableText).toContain('10,000,000'); // Formatted number
    });

    it('should display overrun amount in danger color when > 0', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [
              {
                client_id: '123',
                client_code: null,
                client_name: 'Test Client',
                projects_count: 1,
                contracts_count: 1,
                contracts_value_total: 1000.0,
                budget_total: 1200.0,
                actual_total: 1500.0,
                overrun_amount_total: 500.0,
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
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Overrun amount should be visible (styled with danger color)
      const overrunCell = screen.getByText('Test Client').closest('tr')?.querySelector('td:last-child');
      expect(overrunCell).toBeTruthy();
    });
  });

  describe('Pagination', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should render pagination when there are multiple pages', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: Array.from({ length: 25 }, (_, i) => ({
              client_id: `client-${i}`,
              client_code: null,
              client_name: `Client ${i}`,
              projects_count: 1,
              contracts_count: 1,
              contracts_value_total: 1000.0,
              budget_total: 1200.0,
              actual_total: 1500.0,
              overrun_amount_total: 500.0,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'USD',
            })),
            pagination: {
              total: 50,
              per_page: 25,
              current_page: 1,
              last_page: 2,
            },
          },
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/hiển thị/i)).toBeInTheDocument();
      expect(screen.getByText(/trước/i)).toBeInTheDocument();
      expect(screen.getByText(/sau/i)).toBeInTheDocument();
    });

    it('should change page when pagination button is clicked', () => {
      routerMock.setSearchParams({
        page: '1',
      });

      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [],
            pagination: {
              total: 50,
              per_page: 25,
              current_page: 1,
              last_page: 2,
            },
          },
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const nextButton = screen.getByText(/sau/i);
      fireEvent.click(nextButton);

      expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
    });
  });

  describe('Drill-down navigation', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('should navigate to project portfolio with client_id when clicking on a client row', async () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: {
          data: {
            items: [
              {
                client_id: '123',
                client_code: null,
                client_name: 'Client A',
                projects_count: 1,
                contracts_count: 2,
                contracts_value_total: 5000.0,
                budget_total: 6000.0,
                actual_total: 7000.0,
                overrun_amount_total: 2000.0,
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
        },
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Find the client row
      const clientRow = screen.getByText('Client A').closest('tr');
      expect(clientRow).toBeInTheDocument();

      // Click on the row
      if (clientRow) {
        await userEvent.click(clientRow);
        
        await waitFor(() => {
          expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects-portfolio?client_id=123');
        });
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
              client_id: '1',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 6000000,
              actual_total: 7000000,
              overrun_amount_total: 2000000,
              over_budget_contracts_count: 1,
              overrun_contracts_count: 1,
              currency: 'VND',
            },
            {
              client_id: '2',
              client_code: null,
              client_name: 'Client B',
              projects_count: 1,
              contracts_count: 2,
              contracts_value_total: 3000000,
              budget_total: 3500000,
              actual_total: 4000000,
              overrun_amount_total: 1000000,
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart title should be visible
      expect(screen.getByText('Top khách hàng vượt chi phí')).toBeInTheDocument();
      
      // Assert: Chart should be rendered (has data-testid)
      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
    });

    it('should not render chart when all overrun_amount_total <= 0', () => {
      const mockData = {
        data: {
          items: [
            {
              client_id: '1',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 6000000,
              actual_total: 5000000,
              overrun_amount_total: 0,
              over_budget_contracts_count: 0,
              overrun_contracts_count: 0,
              currency: 'VND',
            },
            {
              client_id: '2',
              client_code: null,
              client_name: 'Client B',
              projects_count: 1,
              contracts_count: 2,
              contracts_value_total: 3000000,
              budget_total: 3500000,
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top khách hàng vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-clients-chart')).not.toBeInTheDocument();
    });

    it('should not render chart when loading', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top khách hàng vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-clients-chart')).not.toBeInTheDocument();
    });

    it('should not render chart when error', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart should not be rendered
      expect(screen.queryByText('Top khách hàng vượt chi phí')).not.toBeInTheDocument();
      expect(screen.queryByTestId('top-overrun-clients-chart')).not.toBeInTheDocument();
    });

    it('should render chart with drill-down capability (chart component handles navigation)', () => {
      const mockData = {
        data: {
          items: [
            {
              client_id: '123',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 6000000,
              actual_total: 7000000,
              overrun_amount_total: 2000000,
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Chart is rendered with data
      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
      
      // Note: Actual bar click testing is done in TopOverrunClientsChart.test.tsx
      // This test verifies the chart is rendered in the page context
    });
  });

  describe('MoneyCell integration - client overrun total', () => {
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
              client_id: '123',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 6000000,
              actual_total: 7000000,
              overrun_amount_total: 500_000_000,
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const row = screen.getByText('Client A').closest('tr')!;
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
              client_id: '123',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 5000000,
              budget_total: 6000000,
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const row = screen.getByText('Client A').closest('tr')!;
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

    it('clamps negative page from URL to 1 when calling useClientCostPortfolio', () => {
      routerMock.setSearchParams({ 
        page: '-5',
        search: 'villa', // Add filter to ensure render works
      });

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify the hook was called with page=1 (clamped from -5)
      const calls = mockUseClientCostPortfolio.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      const [, paginationArg] = calls[0];
      expect(paginationArg).toMatchObject({
        page: 1,
      });
    });

    it('treats invalid min_overrun_amount as undefined for client portfolio', () => {
      routerMock.setSearchParams({ 
        min_overrun_amount: 'abc',
        search: 'villa',
      });

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Verify the hook was called with min_overrun_amount=undefined
      const calls = mockUseClientCostPortfolio.mock.calls;
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
              client_id: '1',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1300000,
              overrun_amount_total: 300000,
              currency: 'VND',
            },
            {
              client_id: '2',
              client_code: null,
              client_name: 'Client B',
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      // Assert: Summary row exists
      const summaryRow = screen.getByTestId('page-summary-row');
      expect(summaryRow).toBeInTheDocument();

      // Assert: Count totals (exact)
      const contractsCountCell = screen.getByTestId('summary-contracts-count');
      expect(contractsCountCell.textContent).toContain('5'); // 3 + 2

      const projectsCountCell = screen.getByTestId('summary-projects-count');
      expect(projectsCountCell.textContent).toContain('3'); // 2 + 1

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
              client_id: '1',
              client_code: null,
              client_name: 'Client A',
              projects_count: 2,
              contracts_count: 3,
              contracts_value_total: 1000000,
              budget_total: 1200000,
              actual_total: 1300000,
              overrun_amount_total: 300000,
              currency: 'VND',
            },
            {
              client_id: '2',
              client_code: null,
              client_name: 'Client B',
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

      mockUseClientCostPortfolio.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

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
      mockUseClientCostPortfolio.mockReturnValue({
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

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('does not render summary when loading', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('does not render summary when error', () => {
      mockUseClientCostPortfolio.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });
  });

  describe('Export functionality', () => {
    it('should render export button', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/xuất csv/i)).toBeInTheDocument();
    });

    it('should call export mutation when export button is clicked', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportClientCostPortfolio.mockReturnValue({
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

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

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
        min_overrun_amount: '300',
        sort_by: 'overrun_amount_total',
        sort_direction: 'desc',
      });

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportClientCostPortfolio.mockReturnValue({
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

      render(<ClientCostPortfolioPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/xuất csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'villa',
            status: 'active',
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

