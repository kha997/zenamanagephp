import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectHealthHistoryCard } from '../ProjectHealthHistoryCard';
import { useProjectHealthHistory } from '../../hooks';
import type { ProjectHealthSnapshot } from '../../api';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useProjectHealthHistory: vi.fn(),
}));

const mockUseProjectHealthHistory = vi.mocked(useProjectHealthHistory);

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

const createSnapshot = (
  id: string,
  snapshotDate: string,
  overallStatus: string
): ProjectHealthSnapshot => ({
  id,
  snapshot_date: snapshotDate,
  overall_status: overallStatus,
  schedule_status: 'on_track',
  cost_status: 'on_budget',
  tasks_completion_rate: 0.5,
  blocked_tasks_ratio: 0.1,
  overdue_tasks: 0,
  created_at: null,
});

describe('ProjectHealthHistoryCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    
    // Default mock implementation
    mockUseProjectHealthHistory.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
      refetch: vi.fn(),
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
        <ProjectHealthHistoryCard projectId="123" canViewReports={false} />,
        { wrapper: createWrapper() }
      );

      expect(container.firstChild).toBeNull();
      // Verify hook is called with enabled: false
      expect(mockUseProjectHealthHistory).toHaveBeenCalledWith('123', { enabled: false, limit: 30 });
    });

    it('should render when canViewReports is true', () => {
      mockUseProjectHealthHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-card')).toBeInTheDocument();
      expect(screen.getByText('Lịch sử sức khỏe dự án')).toBeInTheDocument();
      // Verify hook is called with enabled: true
      expect(mockUseProjectHealthHistory).toHaveBeenCalledWith('123', { enabled: true, limit: 30 });
    });
  });

  describe('Loading state', () => {
    it('should show loading message when loading', () => {
      mockUseProjectHealthHistory.mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-loading')).toBeInTheDocument();
      expect(screen.getByText('Đang tải lịch sử sức khỏe dự án...')).toBeInTheDocument();
    });
  });

  describe('Error state', () => {
    it('should show error message when error occurs', () => {
      const mockRefetch = vi.fn();
      mockUseProjectHealthHistory.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-error')).toBeInTheDocument();
      expect(screen.getByText('Không tải được lịch sử sức khỏe. Vui lòng thử lại.')).toBeInTheDocument();
      expect(screen.getByText('Thử lại')).toBeInTheDocument();
    });

    it('should call refetch when retry button is clicked', async () => {
      const user = userEvent.setup();
      const mockRefetch = vi.fn();
      mockUseProjectHealthHistory.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        error: { message: 'Boom' } as any,
        refetch: mockRefetch,
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      const retryButton = screen.getByText('Thử lại');
      await user.click(retryButton);

      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('Empty state', () => {
    it('should show empty message when no data', () => {
      mockUseProjectHealthHistory.mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-empty')).toBeInTheDocument();
      expect(screen.getByText('Chưa có snapshot sức khỏe nào cho dự án này.')).toBeInTheDocument();
      expect(screen.getByText('Chưa có dữ liệu để tính xu hướng.')).toBeInTheDocument();
    });
  });

  describe('Trend display', () => {
    it('should display improving trend chip', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'good'),
        createSnapshot('1', '2025-01-01', 'warning'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-trend-label')).toHaveTextContent('Xu hướng 30 ngày gần đây:');
      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Tốt lên');
    });

    it('should display worsening trend chip', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'critical'),
        createSnapshot('1', '2025-01-01', 'good'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Xấu đi');
    });

    it('should display stable trend chip', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'good'),
        createSnapshot('1', '2025-01-01', 'good'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Ổn định');
    });

    it('should display unknown trend chip for single snapshot', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('1', '2025-01-01', 'good'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Chưa có đủ dữ liệu');
    });

    it('should display unknown trend chip for all no_data snapshots', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'unknown_status'),
        createSnapshot('1', '2025-01-01', 'another_unknown'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      // Should show "Chưa có đủ dữ liệu" (unknown), not "Ổn định" (stable)
      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Chưa có đủ dữ liệu');
    });

    it('should display unknown trend chip when only one valid status exists with no_data', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'unknown_status'), // newest = no_data
        createSnapshot('1', '2025-01-01', 'good'), // older = valid
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      // Only 1 valid status, not enough for trend
      expect(screen.getByTestId('project-health-history-trend-chip')).toHaveTextContent('Chưa có đủ dữ liệu');
    });

    it('should display timeline with correct number of dots', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('3', '2025-01-03', 'good'),
        createSnapshot('2', '2025-01-02', 'warning'),
        createSnapshot('1', '2025-01-01', 'critical'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      const timeline = screen.getByTestId('project-health-history-timeline');
      expect(timeline).toBeInTheDocument();
      
      const dots = screen.getAllByTestId('project-health-history-timeline-dot');
      expect(dots.length).toBe(3);
    });

    it('should display statistics summary correctly', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('5', '2025-01-05', 'good'),
        createSnapshot('4', '2025-01-04', 'warning'),
        createSnapshot('3', '2025-01-03', 'critical'),
        createSnapshot('2', '2025-01-02', 'good'),
        createSnapshot('1', '2025-01-01', 'warning'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      const summary = screen.getByTestId('project-health-history-summary');
      expect(summary).toBeInTheDocument();
      expect(summary).toHaveTextContent('Tốt: 2 ngày');
      expect(summary).toHaveTextContent('Cảnh báo: 2 ngày');
      expect(summary).toHaveTextContent('Nguy cấp: 1 ngày');
    });
  });

  describe('History table', () => {
    it('should render history table with correct data', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('2', '2025-01-02', 'good'),
        createSnapshot('1', '2025-01-01', 'warning'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      const rows = screen.getAllByTestId('project-health-history-row');
      expect(rows.length).toBe(2);
    });

    it('should format dates correctly', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('1', '2025-01-15', 'good'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      // Date should be formatted (exact format depends on locale, but should be visible)
      const firstRow = screen.getAllByTestId('project-health-history-row')[0];
      const dateCell = firstRow.querySelector('td');
      expect(dateCell).toBeInTheDocument();
      expect(dateCell?.textContent).toBeTruthy();
    });

    it('should display status badges correctly', () => {
      const history: ProjectHealthSnapshot[] = [
        createSnapshot('1', '2025-01-01', 'good'),
      ];

      mockUseProjectHealthHistory.mockReturnValue({
        data: history,
        isLoading: false,
        isError: false,
        error: null,
        refetch: vi.fn(),
      } as any);

      render(
        <ProjectHealthHistoryCard projectId="123" canViewReports={true} />,
        { wrapper: createWrapper() }
      );

      // Should contain status label
      expect(screen.getByText('Tốt')).toBeInTheDocument();
    });
  });
});

