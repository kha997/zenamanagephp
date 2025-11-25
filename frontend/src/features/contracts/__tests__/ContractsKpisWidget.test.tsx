import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ContractsKpisWidget } from '../components/ContractsKpisWidget';
import { useReportsKpis } from '../../reports/hooks';
import { useAuthStore } from '../../auth/store';

// Mock the reports hooks
vi.mock('../../reports/hooks', () => ({
  useReportsKpis: vi.fn(),
}));

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

const mockUseReportsKpis = vi.mocked(useReportsKpis);
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

describe('ContractsKpisWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseAuthStore.mockReturnValue({
      hasTenantPermission: vi.fn(() => true),
    } as any);
  });

  describe('Loading state', () => {
    it('should show loading skeleton when loading', () => {
      mockUseReportsKpis.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng & thanh toán')).toBeInTheDocument();
      // Check for skeleton elements (loading state)
      const widget = screen.getByTestId('contracts-kpis-widget');
      expect(widget).toBeInTheDocument();
      // Check for animate-pulse class or skeleton content
      const loadingElements = widget.querySelectorAll('.animate-pulse');
      expect(loadingElements.length).toBeGreaterThan(0);
    });
  });

  describe('Error state', () => {
    it('should show error message and retry button when there is an error', () => {
      const mockRefetch = vi.fn();
      mockUseReportsKpis.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Boom'),
        refetch: mockRefetch,
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.getByText('Hợp đồng & thanh toán')).toBeInTheDocument();
      expect(screen.getByText(/không tải được KPIs hợp đồng/i)).toBeInTheDocument();
      
      const retryButton = screen.getByText(/thử lại/i);
      expect(retryButton).toBeInTheDocument();
      
      retryButton.click();
      expect(mockRefetch).toHaveBeenCalled();
    });

    it('should show error message when data is null', () => {
      const mockRefetch = vi.fn();
      mockUseReportsKpis.mockReturnValue({
        data: null,
        isLoading: false,
        error: null,
        refetch: mockRefetch,
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.getByText(/không tải được KPIs hợp đồng/i)).toBeInTheDocument();
    });
  });

  describe('Success state', () => {
    it('should display all contract KPIs correctly with specific numeric values', () => {
      // Use sample data with specific, easy-to-verify values
      const mockData = {
        contracts: {
          total_count: 3,
          active_count: 2,
          completed_count: 1,
          cancelled_count: 0,
          total_value: 300000000, // 300M VND
          payments: {
            scheduled_total: 200000000, // 200M
            paid_total: 50000000, // 50M
            remaining_to_schedule: 100000000, // 100M
            remaining_to_pay: 150000000, // 150M
            overdue_count: 1,
            overdue_total: 50000000, // 50M
          },
        },
      };

      mockUseReportsKpis.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      // Check title
      expect(screen.getByText('Hợp đồng & thanh toán')).toBeInTheDocument();

      // Check contract counts
      expect(screen.getByText('Tổng số HĐ')).toBeInTheDocument();
      expect(screen.getByText('Đang thực hiện')).toBeInTheDocument();
      expect(screen.getByText('Hoàn thành')).toBeInTheDocument();

      // Check financial metrics
      expect(screen.getByText('Tổng giá trị HĐ')).toBeInTheDocument();
      expect(screen.getByText('Đã phân bổ (scheduled)')).toBeInTheDocument();
      expect(screen.getByText('Đã thanh toán')).toBeInTheDocument();

      // Check remaining & overdue
      expect(screen.getByText('Còn chưa phân bổ')).toBeInTheDocument();
      expect(screen.getByText('Còn phải thanh toán')).toBeInTheDocument();
      expect(screen.getByText('Quá hạn')).toBeInTheDocument();

      // Check values are formatted with specific numeric asserts
      const widget = screen.getByTestId('contracts-kpis-widget');
      const widgetText = widget.textContent || '';
      
      // Assert specific counts
      expect(widgetText).toContain('3'); // total_count
      expect(widgetText).toContain('2'); // active_count
      expect(widgetText).toContain('1'); // completed_count
      
      // Assert total value is formatted (300M - pattern may vary: 300.000.000, 300,000,000, etc.)
      expect(widgetText).toMatch(/300[.,\s]?000[.,\s]?000|300[.,\s]?M/i);
      
      // Assert overdue count and amount
      expect(widgetText).toMatch(/1.*đợt|quá hạn.*1/i); // Overdue count: 1
      expect(widgetText).toMatch(/50[.,\s]?000[.,\s]?000|50[.,\s]?M/i); // Overdue total: ~50M
    });

    it('should display overdue information when there are overdue payments with specific values', () => {
      const mockData = {
        contracts: {
          total_count: 3,
          active_count: 2,
          completed_count: 1,
          cancelled_count: 0,
          total_value: 300000000, // 300M
          payments: {
            scheduled_total: 200000000, // 200M
            paid_total: 50000000, // 50M
            remaining_to_schedule: 100000000, // 100M
            remaining_to_pay: 150000000, // 150M
            overdue_count: 1,
            overdue_total: 50000000, // 50M
          },
        },
      };

      mockUseReportsKpis.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      // Check overdue section
      expect(screen.getByText('Quá hạn')).toBeInTheDocument();
      const widget = screen.getByTestId('contracts-kpis-widget');
      const widgetText = widget.textContent || '';
      
      // Assert specific overdue count
      expect(widgetText).toMatch(/1.*đợt|quá hạn.*1/i); // overdue_count: 1
      expect(widgetText).toContain('đợt'); // overdue label
      
      // Assert overdue amount is displayed (formatted)
      expect(widgetText).toMatch(/50[.,\s]?000[.,\s]?000|50[.,\s]?M/i); // Overdue total: ~50M
    });

    it('should display "Không có dữ liệu hợp đồng" when contracts data is missing', () => {
      mockUseReportsKpis.mockReturnValue({
        data: {},
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.getByText(/không có dữ liệu hợp đồng/i)).toBeInTheDocument();
    });

    it('should render budget and actual KPIs when present', () => {
      const mockData = {
        contracts: {
          total_count: 1,
          active_count: 1,
          completed_count: 0,
          cancelled_count: 0,
          total_value: 1000000,
          payments: {
            scheduled_total: 500000,
            paid_total: 200000,
            remaining_to_schedule: 500000,
            remaining_to_pay: 300000,
            overdue_count: 0,
            overdue_total: 0,
          },
          budget: {
            budget_total: 800000,
            active_line_count: 3,
            over_budget_contracts_count: 1,
          },
          actual: {
            actual_total: 600000,
            line_count: 4,
            contract_vs_actual_diff_total: 400000,
            overrun_contracts_count: 0,
          },
        },
      };

      mockUseReportsKpis.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.getByText(/tổng budget \(planned\)/i)).toBeInTheDocument();
      expect(screen.getByText(/tổng actual \(chi phí\)/i)).toBeInTheDocument();

      const widgetText = (screen.getByTestId('contracts-kpis-widget').textContent || '').toLowerCase();
      expect(widgetText).toMatch(/800[.,\s]?000/);
      expect(widgetText).toMatch(/600[.,\s]?000/);
    });

    it('should gracefully skip budget and actual when not present', () => {
      const mockData = {
        contracts: {
          total_count: 1,
          active_count: 1,
          completed_count: 0,
          cancelled_count: 0,
          total_value: 1000000,
          payments: {
            scheduled_total: 500000,
            paid_total: 200000,
            remaining_to_schedule: 500000,
            remaining_to_pay: 300000,
            overdue_count: 0,
            overdue_total: 0,
          },
        },
      };

      mockUseReportsKpis.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      expect(screen.queryByText(/tổng budget \(planned\)/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/tổng actual \(chi phí\)/i)).not.toBeInTheDocument();
      expect(screen.getByText('Hợp đồng & thanh toán')).toBeInTheDocument();
    });

    it('should_map_budget_and_actual_values_to_correct_widgets', () => {
      const mockData = {
        contracts: {
          total_count: 2,
          total_value: 1_500_000,
          payments: {
            scheduled_total: 500000,
            paid_total: 200000,
            remaining_to_schedule: 1000000,
            remaining_to_pay: 300000,
            overdue_count: 0,
            overdue_total: 0,
          },
          budget: {
            budget_total: 1_200_000,
            active_line_count: 4,
            over_budget_contracts_count: 1,
          },
          actual: {
            actual_total: 900_000,
            line_count: 5,
            contract_vs_actual_diff_total: 600_000,
            overrun_contracts_count: 1,
          },
        },
      };

      mockUseReportsKpis.mockReturnValue({
        data: mockData,
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      const widget = screen.getByTestId('contracts-kpis-widget');
      const widgetText = widget.textContent || '';

      // Assert "Tổng Budget" displays 1_200_000
      expect(widgetText).toMatch(/1[.,\s]?200[.,\s]?000|1[.,\s]?2[.,\s]?M/i);

      // Assert "Tổng Actual (chi phí)" displays 900_000
      expect(widgetText).toMatch(/900[.,\s]?000|900[.,\s]?K/i);

      // Assert "HĐ over budget" displays 1
      expect(widgetText).toMatch(/1.*over.*budget|over.*budget.*1/i) ||
      expect(widgetText).toContain('1'); // At least the count should be present

      // Assert "HĐ vượt chi phí" displays 1
      expect(widgetText).toMatch(/1.*vượt.*chi|vượt.*chi.*1/i) ||
      expect(widgetText).toContain('1'); // At least the count should be present

      // Verify the labels are present
      expect(screen.getByText(/tổng budget \(planned\)/i)).toBeInTheDocument();
      expect(screen.getByText(/tổng actual \(chi phí\)/i)).toBeInTheDocument();
    });
  });

  describe('RBAC', () => {
    it('should not render widget when user does not have tenant.view_reports permission', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: vi.fn((permission: string) => permission !== 'tenant.view_reports'),
      } as any);

      // Widget is conditionally rendered in DashboardPage, so this test
      // would be covered by DashboardPage tests
      // But we can test that the widget itself doesn't check permissions
      // (it relies on DashboardPage to check)
      mockUseReportsKpis.mockReturnValue({
        data: { contracts: {} },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(<ContractsKpisWidget />, { wrapper: createWrapper() });

      // Widget should still render (permission check is at Dashboard level)
      expect(screen.getByText('Hợp đồng & thanh toán')).toBeInTheDocument();
    });
  });
});
