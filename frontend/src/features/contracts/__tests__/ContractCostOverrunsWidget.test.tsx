import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ContractCostOverrunsWidget } from '../components/ContractCostOverrunsWidget';
import { useContractCostOverruns } from '../hooks';
import { useAuthStore } from '../../auth/store';

// Mock the contracts hooks
vi.mock('../hooks', () => ({
  useContractCostOverruns: vi.fn(),
}));

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const mockUseContractCostOverruns = vi.mocked(useContractCostOverruns);
const mockUseAuthStore = vi.mocked(useAuthStore);

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

describe('ContractCostOverrunsWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    mockUseAuthStore.mockReturnValue({
      hasTenantPermission: vi.fn(() => true),
    } as any);
  });

  describe('Loading state', () => {
    it('should show loading skeleton when loading', () => {
      mockUseContractCostOverruns.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng vượt Budget / Actual')).toBeInTheDocument();
      // Check for skeleton elements (loading state)
      const widget = screen.getByTestId('contract-cost-overruns-widget');
      expect(widget).toBeInTheDocument();
      // Check for animate-pulse class or skeleton content
      const loadingElements = widget.querySelectorAll('.animate-pulse');
      expect(loadingElements.length).toBeGreaterThan(0);
    });
  });

  describe('Error state', () => {
    it('should show error message and retry button when there is an error', async () => {
      const mockRefetch = vi.fn();
      mockUseContractCostOverruns.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Boom'),
        refetch: mockRefetch,
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng vượt Budget / Actual')).toBeInTheDocument();
      expect(screen.getByText(/không tải được danh sách hợp đồng vượt budget\/actual/i)).toBeInTheDocument();
      
      const retryButton = screen.getByText(/thử lại/i);
      expect(retryButton).toBeInTheDocument();
      
      await userEvent.click(retryButton);
      expect(mockRefetch).toHaveBeenCalled();
    });

    it('should show error message when data is null', () => {
      const mockRefetch = vi.fn();
      mockUseContractCostOverruns.mockReturnValue({
        data: null,
        isLoading: false,
        error: null,
        refetch: mockRefetch,
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      expect(screen.getByText(/không tải được danh sách hợp đồng vượt budget\/actual/i)).toBeInTheDocument();
    });
  });

  describe('Empty state', () => {
    it('should show empty message when no overruns', () => {
      mockUseContractCostOverruns.mockReturnValue({
        data: {
          overBudgetContracts: [],
          overrunContracts: [],
        },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      expect(screen.getByText(/không có hợp đồng nào vượt budget hoặc vượt actual cost/i)).toBeInTheDocument();
    });
  });

  describe('Success state - Over Budget Contracts', () => {
    it('should display over budget contracts correctly', () => {
      const mockData = {
        overBudgetContracts: [
          {
            id: '1',
            code: 'CT-001',
            name: 'Contract Over Budget 1',
            client_name: 'Client A',
            project_name: 'Project X',
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            budget_total: 1200000,
            budget_vs_contract_diff: 200000,
          },
          {
            id: '2',
            code: 'CT-002',
            name: 'Contract Over Budget 2',
            client_name: 'Client B',
            project_name: null,
            status: 'active',
            currency: 'USD',
            contract_value: 50000,
            budget_total: 60000,
            budget_vs_contract_diff: 10000,
          },
        ],
        overrunContracts: [],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      // Check title
      expect(screen.getByText('Hợp đồng vượt Budget / Actual')).toBeInTheDocument();
      
      // Check section title
      expect(screen.getByText('HĐ vượt Budget')).toBeInTheDocument();
      
      // Check contract codes
      expect(screen.getByText('CT-001')).toBeInTheDocument();
      expect(screen.getByText('CT-002')).toBeInTheDocument();
      
      // Check contract names
      expect(screen.getByText('Contract Over Budget 1')).toBeInTheDocument();
      expect(screen.getByText('Contract Over Budget 2')).toBeInTheDocument();
      
      // Check client names
      expect(screen.getByText(/Client A/i)).toBeInTheDocument();
      expect(screen.getByText(/Client B/i)).toBeInTheDocument();
    });

    it('should navigate to contract detail when clicking on over budget contract', async () => {
      const mockData = {
        overBudgetContracts: [
          {
            id: '123',
            code: 'CT-001',
            name: 'Contract Over Budget',
            client_name: 'Client A',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            budget_total: 1200000,
            budget_vs_contract_diff: 200000,
          },
        ],
        overrunContracts: [],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      // Find and click on the contract button
      const contractButton = screen.getByText('CT-001').closest('button');
      expect(contractButton).toBeInTheDocument();

      if (contractButton) {
        await userEvent.click(contractButton);
        expect(mockNavigate).toHaveBeenCalledWith('/app/contracts/123');
      }
    });
  });

  describe('Success state - Overrun Contracts', () => {
    it('should display overrun contracts correctly', () => {
      const mockData = {
        overBudgetContracts: [],
        overrunContracts: [
          {
            id: '3',
            code: 'CT-003',
            name: 'Contract Overrun 1',
            client_name: 'Client C',
            project_name: 'Project Y',
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            actual_total: 1200000,
            contract_vs_actual_diff: -200000,
          },
        ],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      // Check section title
      expect(screen.getByText('HĐ vượt Actual')).toBeInTheDocument();
      
      // Check contract code
      expect(screen.getByText('CT-003')).toBeInTheDocument();
      
      // Check contract name
      expect(screen.getByText('Contract Overrun 1')).toBeInTheDocument();
    });

    it('should navigate to contract detail when clicking on overrun contract', async () => {
      const mockData = {
        overBudgetContracts: [],
        overrunContracts: [
          {
            id: '456',
            code: 'CT-003',
            name: 'Contract Overrun',
            client_name: 'Client C',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            actual_total: 1200000,
            contract_vs_actual_diff: -200000,
          },
        ],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      // Find and click on the contract button
      const contractButton = screen.getByText('CT-003').closest('button');
      expect(contractButton).toBeInTheDocument();

      if (contractButton) {
        await userEvent.click(contractButton);
        expect(mockNavigate).toHaveBeenCalledWith('/app/contracts/456');
      }
    });
  });

  describe('Success state - Both Over Budget and Overrun', () => {
    it('should display both sections when both types exist', () => {
      const mockData = {
        overBudgetContracts: [
          {
            id: '1',
            code: 'CT-001',
            name: 'Contract Over Budget',
            client_name: 'Client A',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            budget_total: 1200000,
            budget_vs_contract_diff: 200000,
          },
        ],
        overrunContracts: [
          {
            id: '2',
            code: 'CT-002',
            name: 'Contract Overrun',
            client_name: 'Client B',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            actual_total: 1200000,
            contract_vs_actual_diff: -200000,
          },
        ],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      // Check both section titles
      expect(screen.getByText('HĐ vượt Budget')).toBeInTheDocument();
      expect(screen.getByText('HĐ vượt Actual')).toBeInTheDocument();
      
      // Check both contract codes
      expect(screen.getByText('CT-001')).toBeInTheDocument();
      expect(screen.getByText('CT-002')).toBeInTheDocument();
    });
  });

  describe('"Xem tất cả" button', () => {
    it('should render "Xem tất cả" button in header', () => {
      const mockData = {
        overBudgetContracts: [
          {
            id: '1',
            code: 'CT-001',
            name: 'Contract Over Budget',
            client_name: 'Client A',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            budget_total: 1200000,
            budget_vs_contract_diff: 200000,
          },
        ],
        overrunContracts: [],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      const viewAllButton = screen.getByText(/xem tất cả/i);
      expect(viewAllButton).toBeInTheDocument();
    });

    it('should navigate to cost overruns page when "Xem tất cả" button is clicked', async () => {
      const mockData = {
        overBudgetContracts: [
          {
            id: '1',
            code: 'CT-001',
            name: 'Contract Over Budget',
            client_name: 'Client A',
            project_name: null,
            status: 'active',
            currency: 'VND',
            contract_value: 1000000,
            budget_total: 1200000,
            budget_vs_contract_diff: 200000,
          },
        ],
        overrunContracts: [],
      };

      mockUseContractCostOverruns.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractCostOverrunsWidget />, { wrapper: createWrapper() });

      const viewAllButton = screen.getByText(/xem tất cả/i);
      await userEvent.click(viewAllButton);

      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/cost-overruns');
    });
  });
});

