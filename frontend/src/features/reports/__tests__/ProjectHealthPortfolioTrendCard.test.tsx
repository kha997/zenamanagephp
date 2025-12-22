import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectHealthPortfolioTrendCard } from '../components/ProjectHealthPortfolioTrendCard';
import { useProjectHealthPortfolioHistory } from '../hooks';
import type { ProjectHealthPortfolioHistoryItem } from '../api';

// Mock the hooks
vi.mock('../hooks', () => ({
  useProjectHealthPortfolioHistory: vi.fn(),
}));

const mockUseProjectHealthPortfolioHistory = vi.mocked(useProjectHealthPortfolioHistory);

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

const createHistoryItem = (
  snapshotDate: string,
  good: number,
  warning: number,
  critical: number,
  total: number
): ProjectHealthPortfolioHistoryItem => ({
  snapshot_date: snapshotDate,
  good,
  warning,
  critical,
  total,
});

describe('ProjectHealthPortfolioTrendCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    
    // Default mock implementation
    mockUseProjectHealthPortfolioHistory.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
      refetch: vi.fn(),
      isFetching: false,
    } as any);
  });

  afterEach(() => {
    cleanup();
    
    // Clean up QueryClient instances
    queryClients.forEach(client => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    
    vi.clearAllMocks();
  });

  describe('Permission checks', () => {
    it('should not render when canViewReports is false', () => {
      const { container } = render(
        <ProjectHealthPortfolioTrendCard canViewReports={false} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(container.firstChild).toBeNull();
      
      // Verify hook is NOT called at all when permission is false
      expect(mockUseProjectHealthPortfolioHistory).not.toHaveBeenCalled();
      
      // Verify no trend UI elements are rendered
      expect(screen.queryByTestId('project-health-portfolio-trend-summary')).toBeNull();
      expect(screen.queryByTestId('project-health-portfolio-trend-timeline')).toBeNull();
    });

    it('should render when canViewReports is true', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-portfolio-trend-card')).toBeInTheDocument();
      expect(screen.getByText('Xu hướng sức khỏe portfolio')).toBeInTheDocument();
      // Verify hook is called with enabled: true when permission is granted
      expect(mockUseProjectHealthPortfolioHistory).toHaveBeenCalledWith({ days: 30, enabled: true });
    });
  });

  describe('Loading state', () => {
    it('should show loading message when loading', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-portfolio-trend-loading')).toBeInTheDocument();
      expect(screen.getByText('Đang tải xu hướng sức khỏe portfolio…')).toBeInTheDocument();
    });

    it('should show loading message when fetching and no data yet', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: true,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-portfolio-trend-loading')).toBeInTheDocument();
    });
  });

  describe('Error state', () => {
    it('should show error message when error occurs', () => {
      const mockRefetch = vi.fn();
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-portfolio-trend-error')).toBeInTheDocument();
      expect(screen.getByText('Không tải được xu hướng sức khỏe portfolio. Vui lòng thử lại.')).toBeInTheDocument();
      expect(screen.getByText('Thử lại')).toBeInTheDocument();
    });

    it('should call refetch when retry button is clicked', async () => {
      const user = userEvent.setup();
      const mockRefetch = vi.fn();
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      const retryButton = screen.getByText('Thử lại');
      await user.click(retryButton);

      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('Empty state', () => {
    it('should show empty message when no data', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-portfolio-trend-empty')).toBeInTheDocument();
      expect(screen.getByText('Chưa có dữ liệu snapshot sức khỏe để vẽ xu hướng.')).toBeInTheDocument();
    });
  });

  describe('Data state', () => {
    it('should display summary with first and last values', () => {
      const history: ProjectHealthPortfolioHistoryItem[] = [
        createHistoryItem('2025-11-20', 3, 1, 2, 6),
        createHistoryItem('2025-11-21', 4, 2, 1, 7),
      ];

      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      const summary = screen.getByTestId('project-health-portfolio-trend-summary');
      expect(summary).toBeInTheDocument();
      expect(summary).toHaveTextContent('Trong 2 ngày gần đây:');
      expect(summary).toHaveTextContent('Tốt: 3 → 4 (+1)');
      expect(summary).toHaveTextContent('Nguy cấp: 2 → 1 (-1)');
    });

    it('should display summary with zero delta', () => {
      const history: ProjectHealthPortfolioHistoryItem[] = [
        createHistoryItem('2025-11-20', 3, 1, 2, 6),
        createHistoryItem('2025-11-21', 3, 1, 2, 6),
      ];

      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      const summary = screen.getByTestId('project-health-portfolio-trend-summary');
      expect(summary).toHaveTextContent('Tốt: 3 → 3 (0)');
      expect(summary).toHaveTextContent('Nguy cấp: 2 → 2 (0)');
    });

    it('should display timeline with correct number of days', () => {
      const history: ProjectHealthPortfolioHistoryItem[] = [
        createHistoryItem('2025-11-20', 3, 1, 2, 6),
        createHistoryItem('2025-11-21', 4, 2, 1, 7),
      ];

      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      const timeline = screen.getByTestId('project-health-portfolio-trend-timeline');
      expect(timeline).toBeInTheDocument();
      
      const days = screen.getAllByTestId('project-health-portfolio-trend-day');
      expect(days.length).toBe(2);
    });

    it('should display timeline with multiple days', () => {
      const history: ProjectHealthPortfolioHistoryItem[] = [
        createHistoryItem('2025-11-18', 2, 1, 1, 4),
        createHistoryItem('2025-11-19', 3, 1, 0, 4),
        createHistoryItem('2025-11-20', 3, 1, 2, 6),
        createHistoryItem('2025-11-21', 4, 2, 1, 7),
      ];

      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={30} />,
        { wrapper: createWrapper() }
      );

      const days = screen.getAllByTestId('project-health-portfolio-trend-day');
      expect(days.length).toBe(4);
    });

    it('should handle days prop correctly', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} days={60} />,
        { wrapper: createWrapper() }
      );

      // Verify hook is called with correct days
      expect(mockUseProjectHealthPortfolioHistory).toHaveBeenCalledWith({ days: 60, enabled: true });
    });

    it('should use default days when not provided', () => {
      mockUseProjectHealthPortfolioHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
        isFetching: false,
      } as any);

      render(
        <ProjectHealthPortfolioTrendCard canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      // Verify hook is called with default days (30)
      expect(mockUseProjectHealthPortfolioHistory).toHaveBeenCalledWith({ days: 30, enabled: true });
    });
  });
});

