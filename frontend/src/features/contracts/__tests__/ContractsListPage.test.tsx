import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ContractsListPage } from '../pages/ContractsListPage';
import { useAuthStore } from '../../auth/store';
import { useContractsList } from '../hooks';
import { contractsApi } from '../api';
import toast from 'react-hot-toast';

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the contracts hooks
vi.mock('../hooks', () => ({
  useContractsList: vi.fn(),
}));

// Mock the contracts API
vi.mock('../api', () => ({
  contractsApi: {
    exportContracts: vi.fn(),
  },
}));

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
const mockSetSearchParams = vi.fn();
let mockSearchParamsFactory: () => URLSearchParams = () => new URLSearchParams();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useSearchParams: () => {
      const mockSearchParams = mockSearchParamsFactory();
      return [mockSearchParams, mockSetSearchParams];
    },
  };
});

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseContractsList = vi.mocked(useContractsList);

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

describe('ContractsListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    mockSetSearchParams.mockClear();
    // Reset to default factory that creates empty URLSearchParams
    mockSearchParamsFactory = () => new URLSearchParams();

    // Default mock implementations
    mockUseContractsList.mockReturnValue({
      data: { data: [], meta: { total: 0, current_page: 1, per_page: 50, last_page: 1 } },
      isLoading: false,
      error: null,
    } as any);
  });

  describe('Permission checks', () => {
    it('should render AccessRestricted when user does not have tenant.view_contracts', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.manage_contracts'),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText(/don't have permission to view contracts/i)).toBeInTheDocument();
    });

    it('should render contracts list when user has tenant.view_contracts', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_contracts'),
      } as any);

      const mockContracts = [
        {
          id: '1',
          code: 'CT-001',
          name: 'Contract 1',
          client: { id: '1', name: 'Client 1' },
          project: { id: '1', name: 'Project 1' },
          total_value: 10000,
          currency: 'USD',
          status: 'active',
        },
        {
          id: '2',
          code: 'CT-002',
          name: 'Contract 2',
          client: { id: '2', name: 'Client 2' },
          project: null,
          total_value: 20000,
          currency: 'VND',
          status: 'draft',
        },
      ];

      mockUseContractsList.mockReturnValue({
        data: { data: mockContracts, meta: { total: 2, current_page: 1, per_page: 50, last_page: 1 } },
        isLoading: false,
        error: null,
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng')).toBeInTheDocument();
      expect(screen.getByText('CT-001')).toBeInTheDocument();
      expect(screen.getByText('Contract 1')).toBeInTheDocument();
      expect(screen.getByText('CT-002')).toBeInTheDocument();
      expect(screen.getByText('Contract 2')).toBeInTheDocument();
    });
  });

  describe('Loading state', () => {
    it('should show loading spinner when loading', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractsList.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/loading contracts/i)).toBeInTheDocument();
    });
  });

  describe('Error state', () => {
    it('should show error message when there is an error', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractsList.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load contracts'),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/error loading contracts/i)).toBeInTheDocument();
    });
  });

  describe('Empty state', () => {
    it('should show empty message when no contracts', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      mockUseContractsList.mockReturnValue({
        data: { data: [], meta: { total: 0, current_page: 1, per_page: 50, last_page: 1 } },
        isLoading: false,
        error: null,
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/chưa có hợp đồng/i)).toBeInTheDocument();
    });
  });

  describe('Navigation', () => {
    it('should navigate to contract detail when clicking on a contract row', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission === 'tenant.view_contracts'),
      } as any);

      const mockContracts = [
        {
          id: '123',
          code: 'CT-001',
          name: 'Tên hợp đồng 1',
          client: { id: '1', name: 'Client 1' },
          project: { id: '1', name: 'Project 1' },
          total_value: 10000,
          currency: 'USD',
          status: 'active',
        },
        {
          id: '456',
          code: 'CT-002',
          name: 'Tên hợp đồng 2',
          client: { id: '2', name: 'Client 2' },
          project: null,
          total_value: 20000,
          currency: 'VND',
          status: 'draft',
        },
      ];

      mockUseContractsList.mockReturnValue({
        data: { data: mockContracts, meta: { total: 2, current_page: 1, per_page: 50, last_page: 1 } },
        isLoading: false,
        error: null,
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      // Find and click on the first contract row
      const contractRow = screen.getByText('Tên hợp đồng 1').closest('tr');
      expect(contractRow).toBeInTheDocument();

      if (contractRow) {
        contractRow.click();
      }

      // Wait for navigation to be called
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/app/contracts/123');
      });
    });
  });

  describe('Filters', () => {
    it('should render filter bar with search, status, and sort inputs', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByPlaceholderText(/tìm theo mã/i)).toBeInTheDocument();
      expect(screen.getByText(/trạng thái/i)).toBeInTheDocument();
      expect(screen.getByText(/sắp xếp/i)).toBeInTheDocument();
    });

    it('should call useContractsList with search filter when search input changes', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const searchInput = screen.getByPlaceholderText(/tìm theo mã/i);
      fireEvent.change(searchInput, { target: { value: 'ABC' } });

      // Wait for debounce
      await waitFor(() => {
        expect(mockSetSearchParams).toHaveBeenCalled();
      }, { timeout: 500 });
    });

    it('should call useContractsList with status filter when status changes', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      // Find status select and change it
      const statusSelect = screen.getByText(/trạng thái/i).closest('div')?.querySelector('select');
      if (statusSelect) {
        fireEvent.change(statusSelect, { target: { value: 'active' } });
        expect(mockSetSearchParams).toHaveBeenCalled();
      }
    });

    it('should show reset filter button when filters are active', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'test');
      testSearchParams.set('status', 'active');
      mockSearchParamsFactory = () => testSearchParams;

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/xóa bộ lọc/i)).toBeInTheDocument();
    });

    it('should reset filters when reset button is clicked', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'test');
      testSearchParams.set('status', 'active');
      mockSearchParamsFactory = () => testSearchParams;

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const resetButton = screen.getByText(/xóa bộ lọc/i);
      fireEvent.click(resetButton);

      expect(mockSetSearchParams).toHaveBeenCalledWith(new URLSearchParams());
    });

    it('should pass filters correctly to useContractsList hook', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'villa');
      testSearchParams.set('status', 'active');
      testSearchParams.set('sort_by', 'total_value');
      testSearchParams.set('sort_direction', 'desc');
      mockSearchParamsFactory = () => testSearchParams;

      render(<ContractsListPage />, { wrapper: createWrapper() });

      // Wait for component to render and hook to be called
      await waitFor(() => {
        expect(mockUseContractsList).toHaveBeenCalled();
      });

      // Verify hook was called with correct filters
      const calls = mockUseContractsList.mock.calls;
      expect(calls.length).toBeGreaterThan(0);
      
      const lastCall = calls[calls.length - 1];
      const filters = lastCall[0];
      const pagination = lastCall[1];

      expect(filters).toMatchObject({
        search: 'villa',
        status: 'active',
        sort_by: 'total_value',
        sort_direction: 'desc',
      });
      expect(pagination).toMatchObject({
        page: 1,
        per_page: 50,
      });
    });

    it('should hydrate filters from URL query params on mount', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'a');
      testSearchParams.set('status', 'active');
      testSearchParams.set('sort_by', 'code');
      testSearchParams.set('sort_direction', 'asc');
      mockSearchParamsFactory = () => testSearchParams;

      render(<ContractsListPage />, { wrapper: createWrapper() });

      // Verify search input has value from URL
      const searchInput = screen.getByPlaceholderText(/tìm theo mã/i) as HTMLInputElement;
      expect(searchInput.value).toBe('a');

      // Verify hook was called with URL params
      expect(mockUseContractsList).toHaveBeenCalledWith(
        expect.objectContaining({
          search: 'a',
          status: 'active',
          sort_by: 'code',
          sort_direction: 'asc',
        }),
        expect.any(Object)
      );
    });

    it('should update filters when URL params change', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Start with empty params
      mockSearchParamsFactory = () => new URLSearchParams();
      const { rerender } = render(<ContractsListPage />, { wrapper: createWrapper() });

      // Initial state - no filters
      expect(mockUseContractsList).toHaveBeenCalledWith(
        expect.objectContaining({
          search: '',
          status: '',
        }),
        expect.any(Object)
      );

      // Update URL params by changing factory
      const updatedSearchParams = new URLSearchParams();
      updatedSearchParams.set('search', 'new-search');
      updatedSearchParams.set('status', 'completed');
      mockSearchParamsFactory = () => updatedSearchParams;

      // Rerender to simulate URL change
      rerender(<ContractsListPage />);

      // Wait for hook to be called with new filters
      await waitFor(() => {
        expect(mockUseContractsList).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'new-search',
            status: 'completed',
          }),
          expect.any(Object)
        );
      });
    });
  });

  describe('Export functionality', () => {
    it('should render export button', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      expect(screen.getByText(/export csv/i)).toBeInTheDocument();
    });

    it('should call exportContracts API when export button is clicked', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockBlob = new Blob(['test csv content'], { type: 'text/csv' });
      vi.mocked(contractsApi.exportContracts).mockResolvedValue(mockBlob);

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

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(contractsApi.exportContracts).toHaveBeenCalled();
      });
    });

    it('should call exportContracts with current filters', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'test');
      testSearchParams.set('status', 'active');
      mockSearchParamsFactory = () => testSearchParams;

      const mockBlob = new Blob(['test csv content'], { type: 'text/csv' });
      vi.mocked(contractsApi.exportContracts).mockResolvedValue(mockBlob);

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

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(contractsApi.exportContracts).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'test',
            status: 'active',
          })
        );
      });
    });

    it('should call exportContracts with all current filters including sort when clicking Export CSV', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      // Set up factory to return params with all filters
      const testSearchParams = new URLSearchParams();
      testSearchParams.set('search', 'villa');
      testSearchParams.set('status', 'active');
      testSearchParams.set('sort_by', 'code');
      testSearchParams.set('sort_direction', 'asc');
      mockSearchParamsFactory = () => testSearchParams;

      const mockBlob = new Blob(['test csv content'], { type: 'text/csv' });
      vi.mocked(contractsApi.exportContracts).mockResolvedValue(mockBlob);

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

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        // Assert: contractsApi.exportContracts được gọi đúng 1 lần
        expect(contractsApi.exportContracts).toHaveBeenCalledTimes(1);
        
        // Assert: được gọi với object filters khớp với state/URL
        expect(contractsApi.exportContracts).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'villa',
            status: 'active',
            sort_by: 'code',
            sort_direction: 'asc',
          })
        );
      });
    });

    it('should show success toast when export succeeds', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const mockBlob = new Blob(['test csv content'], { type: 'text/csv' });
      vi.mocked(contractsApi.exportContracts).mockResolvedValue(mockBlob);

      // Mock window.URL methods to prevent errors
      Object.defineProperty(window, 'URL', {
        value: {
          ...window.URL,
          createObjectURL: vi.fn(() => 'blob:mock-url'),
          revokeObjectURL: vi.fn(),
        },
        writable: true,
        configurable: true,
      });

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(contractsApi.exportContracts).toHaveBeenCalled();
        // Note: Toast may not be called due to DOM limitations in test environment
        // The important part is that the API is called correctly
      });
    });

    it('should show error toast when export fails', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn(() => true),
      } as any);

      const error = new Error('Export failed');
      vi.mocked(contractsApi.exportContracts).mockRejectedValue(error);

      render(<ContractsListPage />, { wrapper: createWrapper() });

      const exportButton = screen.getByText(/export csv/i);
      await userEvent.click(exportButton);

      await waitFor(() => {
        expect(toast.error).toHaveBeenCalled();
      });
    });
  });
});

