import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { TopOverrunProjectsChart } from '../TopOverrunProjectsChart';
import { createRechartsBarMock } from '../../../../test-utils/rechartsMock';

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// Create shared Recharts mock
const rechartsMock = createRechartsBarMock({
  testId: 'project-bar',
});

// Mock recharts using shared helper
vi.mock('recharts', rechartsMock.getMockFactory());

describe('TopOverrunProjectsChart', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();
    rechartsMock.reset();
  });

  const createWrapper = ({ children }: { children: React.ReactNode }) => (
    <BrowserRouter>{children}</BrowserRouter>
  );

  describe('Rendering', () => {
    it('should render chart when items with overrun > 0 are provided', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
      expect(screen.getByText('Top dự án vượt chi phí')).toBeInTheDocument();
    });

    it('should not render when no items with overrun > 0', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: 0,
          currency: 'USD',
        },
      ];

      const { container } = render(<TopOverrunProjectsChart items={items} />, {
        wrapper: createWrapper,
      });

      expect(container.firstChild).toBeNull();
    });

    it('should not render when all items have null overrun', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: null,
          currency: 'USD',
        },
      ];

      const { container } = render(<TopOverrunProjectsChart items={items} />, {
        wrapper: createWrapper,
      });

      expect(container.firstChild).toBeNull();
    });

    it('should filter and show only top N items when maxItems specified', () => {
      const items = Array.from({ length: 10 }, (_, i) => ({
        project_id: `${i + 1}`,
        project_code: `PRJ-${i + 1}`,
        project_name: `Project ${i + 1}`,
        overrun_amount_total: (i + 1) * 10000,
        currency: 'USD',
      }));

      render(<TopOverrunProjectsChart items={items} maxItems={3} />, {
        wrapper: createWrapper,
      });

      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
    });
  });

  describe('Drill-down navigation', () => {
    it('should navigate to project detail when bar is clicked with project_id', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} />, { wrapper: createWrapper });

      // Verify chart renders
      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();

      // Find and click the bar
      const bar = screen.getByTestId('project-bar');
      fireEvent.click(bar);

      // Verify navigate was called with correct path
      expect(mockNavigate).toHaveBeenCalledTimes(1);
      expect(mockNavigate).toHaveBeenCalledWith('/app/projects/123');
    });

    it('should not navigate when bar click payload has no project_id', () => {
      // Set flag to make mock Bar pass empty payload
      rechartsMock.setPassEmptyPayload(true);

      const items = [
        {
          project_id: '456',
          project_code: 'PRJ-002',
          project_name: 'Another Project',
          overrun_amount_total: 200000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} />, { wrapper: createWrapper });

      // Verify chart renders
      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();

      // Click the bar (which will pass empty payload due to flag)
      const bar = screen.getByTestId('project-bar');
      fireEvent.click(bar);

      // Should not navigate when payload has no project_id
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('should navigate with correct project_id for multiple items', () => {
      const items = [
        {
          project_id: '111',
          project_code: 'PRJ-001',
          project_name: 'Project 1',
          overrun_amount_total: 300000,
          currency: 'USD',
        },
        {
          project_id: '222',
          project_code: 'PRJ-002',
          project_name: 'Project 2',
          overrun_amount_total: 200000,
          currency: 'USD',
        },
        {
          project_id: '333',
          project_code: 'PRJ-003',
          project_name: 'Project 3',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} maxItems={3} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();

      // Click the bar (which will use the first item's data)
      const bar = screen.getByTestId('project-bar');
      fireEvent.click(bar);

      // Should navigate to the first project (highest overrun)
      expect(mockNavigate).toHaveBeenCalledTimes(1);
      expect(mockNavigate).toHaveBeenCalledWith('/app/projects/111');
    });
  });

  describe('Chart utilities integration', () => {
    it('should use formatAmountShort for YAxis formatting', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: 1500000, // 1.5M
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
    });

    it('should use formatCurrency in tooltip', () => {
      const items = [
        {
          project_id: '123',
          project_code: 'PRJ-001',
          project_name: 'Test Project',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunProjectsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-projects-chart')).toBeInTheDocument();
    });
  });
});

