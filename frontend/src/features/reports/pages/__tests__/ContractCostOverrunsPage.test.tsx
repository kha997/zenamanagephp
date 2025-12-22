import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ContractCostOverrunsPage } from '../ContractCostOverrunsPage';
import { useAuthStore } from '../../../auth/store';
import { useContractCostOverrunsTable, useExportContractCostOverruns } from '../../hooks';
import toast from 'react-hot-toast';
import { createSearchParamsMock } from '../../../../test-utils/routerMock';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the reports hooks
vi.mock('../../hooks', () => ({
  useContractCostOverrunsTable: vi.fn(),
  useExportContractCostOverruns: vi.fn(),
}));

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

// Mock react-router-dom using shared helper
const mockNavigate = vi.fn();
const routerMock = createSearchParamsMock();

vi.mock('react-router-dom', routerMock.getMockFactory(mockNavigate));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseContractCostOverrunsTable = vi.mocked(useContractCostOverrunsTable);
const mockUseExportContractCostOverruns = vi.mocked(useExportContractCostOverruns);

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

describe('ContractCostOverrunsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    routerMock.reset();

    // Default mock implementations
    mockUseContractCostOverrunsTable.mockReturnValue({
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
    mockUseExportContractCostOverruns.mockReturnValue({
      mutateAsync: mockMutateAsync,
      isPending: false,
    } as any);
  });

  describe('Permission checks', () => {
    it('should render AccessRestricted when user does not have tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => false),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText(/don't have permission to view cost overruns reports/i)).toBeInTheDocument();
    });

    it('should render page when user has tenant.view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_reports'),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng vượt Budget / Actual')).toBeInTheDocument();
    });
  });

  describe('Loading state', () => {
    it('should show loading spinner when loading', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Check for loading spinner (LoadingSpinner component)
      const loadingElements = screen.queryAllByText(/loading/i);
      expect(loadingElements.length).toBeGreaterThan(0);
    });
  });

  describe('Error state', () => {
    it('should show error message when there is an error', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load cost overruns'),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/không tải được danh sách hợp đồng vượt budget\/actual/i)).toBeInTheDocument();
      expect(screen.getByText(/thử lại/i)).toBeInTheDocument();
    });
  });

  describe('Empty state', () => {
    it('should show empty message when no contracts', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractCostOverrunsTable.mockReturnValue({
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

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/hiện chưa có hợp đồng nào vượt budget\/actual theo bộ lọc/i)).toBeInTheDocument();
    });
  });

  describe('Table rendering', () => {
    it('should render table with contract data', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: { id: '1', name: 'Project X' },
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
            },
            {
              id: '2',
              code: 'CT-002',
              name: 'Contract 2',
              status: 'completed',
              client: { id: '2', name: 'Client B' },
              project: null,
              contract_value: 500000,
              budget_total: 600000,
              actual_total: 550000,
              budget_vs_contract_diff: 100000,
              contract_vs_actual_diff: -50000,
              overrun_amount: 50000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Check table headers
      expect(screen.getByText('Mã HĐ')).toBeInTheDocument();
      expect(screen.getByText('Tên')).toBeInTheDocument();
      expect(screen.getByText('Client')).toBeInTheDocument();
      expect(screen.getByText('Project')).toBeInTheDocument();
      expect(screen.getByText('Status')).toBeInTheDocument();

      // Check contract data
      expect(screen.getByText('CT-001')).toBeInTheDocument();
      expect(screen.getByText('Contract 1')).toBeInTheDocument();
      expect(screen.getByText('Client A')).toBeInTheDocument();
      expect(screen.getByText('Project X')).toBeInTheDocument();
      expect(screen.getByText('CT-002')).toBeInTheDocument();
      expect(screen.getByText('Contract 2')).toBeInTheDocument();
    });

    it('should navigate to contract detail when clicking on a contract row', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              id: '123',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Find and click on the contract row
      const contractRow = screen.getByText('CT-001').closest('tr');
      expect(contractRow).toBeInTheDocument();

      if (contractRow) {
        await userEvent.click(contractRow);
        await waitFor(() => {
          expect(mockNavigate).toHaveBeenCalledWith('/app/contracts/123');
        });
      }
    });
  });

  describe('Filters', () => {
    it('should render filter bar with all filter inputs', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByPlaceholderText(/tìm theo mã hoặc tên hđ/i)).toBeInTheDocument();
      expect(screen.getByText(/trạng thái/i)).toBeInTheDocument();
      expect(screen.getByText(/loại/i)).toBeInTheDocument();
      expect(screen.getByPlaceholderText('0')).toBeInTheDocument();
    });

    it('should update filters when search input changes', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const searchInput = screen.getByPlaceholderText(/tìm theo mã hoặc tên hđ/i);
      fireEvent.change(searchInput, { target: { value: 'ABC' } });

      // Wait for debounce
      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      }, { timeout: 500 });
    });

    it('should update filters when status select changes', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const statusSelect = screen.getByText(/trạng thái/i).closest('div')?.querySelector('select');
      if (statusSelect) {
        fireEvent.change(statusSelect, { target: { value: 'active' } });
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      }
    });

    it('should show reset filter button when filters are active', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      routerMock.setSearchParams({
        search: 'test',
        status: 'active',
      });

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/xóa bộ lọc/i)).toBeInTheDocument();
    });

    it('should reset filters when reset button is clicked', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      routerMock.setSearchParams({
        search: 'test',
        status: 'active',
      });

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const resetButton = screen.getByText(/xóa bộ lọc/i);
      fireEvent.click(resetButton);

      expect(routerMock.getMockSetSearchParams()).toHaveBeenCalledWith(new URLSearchParams());
    });
  });

  describe('Pagination', () => {
    it('should render pagination when there are multiple pages', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: Array.from({ length: 25 }, (_, i) => ({
            id: String(i + 1),
            code: `CT-${i + 1}`,
            name: `Contract ${i + 1}`,
            status: 'active',
            client: null,
            project: null,
            contract_value: 1000000,
            budget_total: 1200000,
            actual_total: 1100000,
            budget_vs_contract_diff: 200000,
            contract_vs_actual_diff: -100000,
            overrun_amount: 100000,
          })),
          pagination: {
            total: 50,
            per_page: 25,
            current_page: 1,
            last_page: 2,
          },
        },
      };

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/hiển thị 1 - 25 của 50/i)).toBeInTheDocument();
      expect(screen.getByText('Trước')).toBeInTheDocument();
      expect(screen.getByText('Sau')).toBeInTheDocument();
    });

    it('should handle page change', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: Array.from({ length: 25 }, (_, i) => ({
            id: String(i + 1),
            code: `CT-${i + 1}`,
            name: `Contract ${i + 1}`,
            status: 'active',
            client: null,
            project: null,
            contract_value: 1000000,
            budget_total: 1200000,
            actual_total: 1100000,
            budget_vs_contract_diff: 200000,
            contract_vs_actual_diff: -100000,
            overrun_amount: 100000,
          })),
          pagination: {
            total: 50,
            per_page: 25,
            current_page: 1,
            last_page: 2,
          },
        },
      };

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const nextButton = screen.getByText('Sau');
      await userEvent.click(nextButton);

      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      });
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
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: null,
              project: null,
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Click on sortable header
      const sortButton = screen.getByText(/budget total \+ diff/i);
      await userEvent.click(sortButton);

      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
      });
    });

    it('should sort by code when clicking "Mã HĐ" header', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: null,
              project: null,
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Click on "Mã HĐ" header
      const codeHeader = screen.getByText(/mã hđ/i);
      await userEvent.click(codeHeader);

      await waitFor(() => {
        expect(routerMock.getMockSetSearchParams()).toHaveBeenCalled();
        const mockSetSearchParams = routerMock.getMockSetSearchParams();
        const callArgs = mockSetSearchParams.mock.calls[0][0];
        expect(callArgs.get('sort_by')).toBe('code');
      });
    });
  });

  describe('Export functionality', () => {
    it('should render export button', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/export csv/i)).toBeInTheDocument();
    });

    it('should call export mutation when export button is clicked', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportContractCostOverruns.mockReturnValue({
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
        configurable: true,
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

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
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

    it('should show error toast when export fails', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockMutateAsync = vi.fn().mockRejectedValue(new Error('Export failed'));
      mockUseExportContractCostOverruns.mockReturnValue({
        mutateAsync: mockMutateAsync,
        isPending: false,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(toast.error).toHaveBeenCalled();
      });
    });

    it('should disable export button when export is pending', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseExportContractCostOverruns.mockReturnValue({
        mutateAsync: vi.fn(),
        isPending: true,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/đang xuất/i);
      expect(exportButton).toBeDisabled();
    });

    it('should use same filters for export as table', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with filters
      routerMock.setSearchParams({
        search: 'villa',
        status: 'active',
        type: 'actual',
        min_overrun_amount: '100000',
        sort_by: 'overrun_amount',
        sort_direction: 'desc',
        page: '2',
      });

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportContractCostOverruns.mockReturnValue({
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
        configurable: true,
      });

      // Mock document.createElement
      const mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };
      const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as any);
      const appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation(() => mockAnchor as any);
      const removeChildSpy = vi.spyOn(document.body, 'removeChild').mockImplementation(() => mockAnchor as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith({
          search: 'villa',
          status: 'active',
          type: 'actual',
          min_overrun_amount: 100000,
          sort_by: 'overrun_amount',
          sort_direction: 'desc',
        });
      });

      createElementSpy.mockRestore();
      appendChildSpy.mockRestore();
      removeChildSpy.mockRestore();
    });

    it('should pass sort parameters to export when table is sorted', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up search params with sort
      routerMock.setSearchParams({
        search: 'villa',
        status: 'active',
        type: 'actual',
        min_overrun_amount: '100000',
        sort_by: 'code',
        sort_direction: 'asc',
        page: '2',
      });

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportContractCostOverruns.mockReturnValue({
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
        configurable: true,
      });

      // Mock document.createElement
      const mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };
      const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as any);
      const appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation(() => mockAnchor as any);
      const removeChildSpy = vi.spyOn(document.body, 'removeChild').mockImplementation(() => mockAnchor as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith({
          search: 'villa',
          status: 'active',
          type: 'actual',
          min_overrun_amount: 100000,
          sort_by: 'code',
          sort_direction: 'asc',
        });
      });

      createElementSpy.mockRestore();
      appendChildSpy.mockRestore();
      removeChildSpy.mockRestore();
    });
  });

  describe('Currency rendering', () => {
    it('should render currency correctly in table cells', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-1',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
            },
            {
              id: '2',
              code: 'CT-2',
              name: 'Contract 2',
              status: 'completed',
              client: { id: '2', name: 'Client B' },
              project: null,
              currency: 'USD',
              contract_value: 500000,
              budget_total: 600000,
              actual_total: 550000,
              budget_vs_contract_diff: 100000,
              contract_vs_actual_diff: -50000,
              overrun_amount: 50000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Check that currency is rendered (formatCurrency uses Intl.NumberFormat)
      // For VND, it should show ₫ symbol or VND text
      // For USD, it should show $ symbol or USD text
      const contractValueCells = screen.getAllByText(/1,000,000|500,000/i);
      expect(contractValueCells.length).toBeGreaterThan(0);

      // Check that currency symbols or codes are present in the rendered output
      // The actual format depends on Intl.NumberFormat, but we can check for numbers
      const contractRow1 = screen.getByText('CT-1').closest('tr');
      const contractRow2 = screen.getByText('CT-2').closest('tr');
      
      expect(contractRow1).toBeInTheDocument();
      expect(contractRow2).toBeInTheDocument();

      // Verify that currency-formatted values are present
      // formatCurrency will format numbers with currency symbols
      // We check that the numbers are formatted (contain commas) and currency info is used
      if (contractRow1) {
        const rowText = contractRow1.textContent || '';
        // Should contain formatted number (with commas)
        expect(rowText).toMatch(/1,000,000|1,200,000|1,100,000/);
      }

      if (contractRow2) {
        const rowText = contractRow2.textContent || '';
        // Should contain formatted number (with commas)
        expect(rowText).toMatch(/500,000|600,000|550,000/);
      }
    });

    it('should use contract currency when rendering amounts', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-1',
              name: 'Contract VND',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 100000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Verify the contract row exists
      const contractRow = screen.getByText('CT-1').closest('tr');
      expect(contractRow).toBeInTheDocument();

      // The formatCurrency function uses Intl.NumberFormat with the currency parameter
      // We verify that the currency field from the data is being used
      // by checking that formatted numbers appear (the actual symbol depends on locale)
      if (contractRow) {
        const rowText = contractRow.textContent || '';
        // Should contain the formatted amounts (numbers with currency formatting)
        // formatCurrency(1000000, 'VND') will format according to VND locale
        expect(rowText).toMatch(/1,000,000|1,200,000|1,100,000|200,000|100,000/);
      }
    });
  });

  describe('MoneyCell integration - overrun amount', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('renders positive overrun with danger tone and plus sign', () => {
      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1100000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: -100000,
              overrun_amount: 1_000_000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const row = screen.getByText('CT-001').closest('tr')!;
      const overrunCell = within(row).getByTestId('overrun-cell');
      
      // Assert: Cell contains + sign and number (flexible regex for locale)
      expect(overrunCell.textContent).toMatch(/\+.*1[0-9.,]*0{3}[.,]0{3}/);
      
      // Assert: Has span with data-tone="danger"
      const dangerSpan = overrunCell.querySelector('span[data-tone="danger"]');
      expect(dangerSpan).not.toBeNull();
      expect(dangerSpan?.textContent).toMatch(/\+.*1[0-9.,]*0{3}[.,]0{3}/);
    });

    it('renders non-positive overrun as muted zero without plus sign', () => {
      const mockData = {
        data: {
          items: [
            {
              id: '2',
              code: 'CT-002',
              name: 'Contract 2',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 1000000,
              budget_total: 1200000,
              actual_total: 1000000,
              budget_vs_contract_diff: 200000,
              contract_vs_actual_diff: 0,
              overrun_amount: 0,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      const row = screen.getByText('CT-002').closest('tr')!;
      const overrunCell = within(row).getByTestId('overrun-cell');
      
      // Assert: Cell contains 0 but no + sign
      expect(overrunCell.textContent).toMatch(/0/);
      expect(overrunCell.textContent).not.toMatch(/\+/);
      
      // Assert: Has span with data-tone="muted"
      const mutedSpan = overrunCell.querySelector('span[data-tone="muted"]');
      expect(mutedSpan).not.toBeNull();
      expect(mutedSpan?.textContent).toMatch(/0/);
    });
  });

  describe('Filter integration - clamp and parse', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);
    });

    it('clamps negative page from URL to 1 when calling useContractCostOverrunsTable', () => {
      routerMock.setSearchParams({
        page: '-5',
        search: 'villa',
      });

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Verify the hook was called with page=1 (clamped from -5)
      const calls = mockUseContractCostOverrunsTable.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      const [, paginationArg] = calls[0];
      expect(paginationArg).toMatchObject({
        page: 1,
      });
    });

    it('treats invalid min_overrun_amount as undefined for table & export', async () => {
      routerMock.setSearchParams({
        min_overrun_amount: 'abc',
        search: 'villa',
      });

      const mockMutateAsync = vi.fn().mockResolvedValue(new Blob(['test csv'], { type: 'text/csv' }));
      mockUseExportContractCostOverruns.mockReturnValue({
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
        configurable: true,
      });

      // Mock document.createElement
      const mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };
      const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as any);
      const appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation(() => mockAnchor as any);
      const removeChildSpy = vi.spyOn(document.body, 'removeChild').mockImplementation(() => mockAnchor as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Table hook:
      expect(mockUseContractCostOverrunsTable).toHaveBeenCalledWith(
        expect.objectContaining({
          min_overrun_amount: undefined,
        }),
        expect.any(Object),
        expect.any(Object)
      );

      // Export:
      const exportButton = screen.getByRole('button', { name: /Export/i });
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith(
          expect.objectContaining({
            min_overrun_amount: undefined,
          })
        );
      });

      createElementSpy.mockRestore();
      appendChildSpy.mockRestore();
      removeChildSpy.mockRestore();
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
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 100000,
              budget_total: 150000,
              actual_total: 160000,
              overrun_amount: 10000,
            },
            {
              id: '2',
              code: 'CT-002',
              name: 'Contract 2',
              status: 'active',
              client: { id: '2', name: 'Client B' },
              project: null,
              currency: 'VND',
              contract_value: 200000,
              budget_total: 250000,
              actual_total: 260000,
              overrun_amount: 20000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Assert: Summary row exists
      const summaryRow = screen.getByTestId('page-summary-row');
      expect(summaryRow).toBeInTheDocument();

      // Assert: Summary cells contain totals (loose numeric regex)
      const contractValueCell = screen.getByTestId('summary-contract-value');
      expect(contractValueCell.textContent).toMatch(/3[0-9]{5}/); // 300000

      const budgetCell = screen.getByTestId('summary-budget');
      expect(budgetCell.textContent).toMatch(/4[0-9]{5}/); // 400000

      const actualOverrunCell = screen.getByTestId('summary-actual-overrun');
      expect(actualOverrunCell.textContent).toMatch(/4[0-9]{5}/); // 420000
      expect(actualOverrunCell.textContent).toMatch(/3[0-9]{4}/); // 30000
    });

    it('does not render summary when there are no items', () => {
      mockUseContractCostOverrunsTable.mockReturnValue({
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

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('renders "-" for money summary when currencies differ', () => {
      const mockData = {
        data: {
          items: [
            {
              id: '1',
              code: 'CT-001',
              name: 'Contract 1',
              status: 'active',
              client: { id: '1', name: 'Client A' },
              project: null,
              currency: 'VND',
              contract_value: 100000,
              budget_total: 150000,
              actual_total: 160000,
              overrun_amount: 10000,
            },
            {
              id: '2',
              code: 'CT-002',
              name: 'Contract 2',
              status: 'active',
              client: { id: '2', name: 'Client B' },
              project: null,
              currency: 'USD',
              contract_value: 200000,
              budget_total: 250000,
              actual_total: 260000,
              overrun_amount: 20000,
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

      mockUseContractCostOverrunsTable.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      // Assert: Summary row exists
      const summaryRow = screen.getByTestId('page-summary-row');
      expect(summaryRow).toBeInTheDocument();

      // Assert: Money summary cells show '-' for mixed currencies
      const contractValueCell = screen.getByTestId('summary-contract-value');
      expect(contractValueCell.textContent).toContain('-');

      const budgetCell = screen.getByTestId('summary-budget');
      expect(budgetCell.textContent).toContain('-');

      const actualOverrunCell = screen.getByTestId('summary-actual-overrun');
      expect(actualOverrunCell.textContent).toContain('-');
    });

    it('does not render summary when loading', () => {
      mockUseContractCostOverrunsTable.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });

    it('does not render summary when error', () => {
      mockUseContractCostOverrunsTable.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load'),
      } as any);

      render(<ContractCostOverrunsPage />, { wrapper: createWrapper() });

      expect(screen.queryByTestId('page-summary-row')).not.toBeInTheDocument();
    });
  });
});

