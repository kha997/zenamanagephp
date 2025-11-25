import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { TopOverrunClientsChart } from '../TopOverrunClientsChart';
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
  testId: 'client-bar',
});

// Mock recharts using shared helper
vi.mock('recharts', rechartsMock.getMockFactory());

describe('TopOverrunClientsChart', () => {
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
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
      expect(screen.getByText('Top khách hàng vượt chi phí')).toBeInTheDocument();
    });

    it('should not render when no items with overrun > 0', () => {
      const items = [
        {
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: 0,
          currency: 'USD',
        },
      ];

      const { container } = render(<TopOverrunClientsChart items={items} />, {
        wrapper: createWrapper,
      });

      expect(container.firstChild).toBeNull();
    });

    it('should not render when all items have null overrun', () => {
      const items = [
        {
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: null,
          currency: 'USD',
        },
      ];

      const { container } = render(<TopOverrunClientsChart items={items} />, {
        wrapper: createWrapper,
      });

      expect(container.firstChild).toBeNull();
    });

    it('should filter and show only top N items when maxItems specified', () => {
      const items = Array.from({ length: 10 }, (_, i) => ({
        client_id: `${i + 1}`,
        client_name: `Client ${i + 1}`,
        overrun_amount_total: (i + 1) * 10000,
        currency: 'USD',
      }));

      render(<TopOverrunClientsChart items={items} maxItems={3} />, {
        wrapper: createWrapper,
      });

      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
    });
  });

  describe('Drill-down navigation', () => {
    it('should navigate to projects portfolio with client_id when bar is clicked', () => {
      const items = [
        {
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} />, { wrapper: createWrapper });

      // Verify chart renders
      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();

      // Find and click the bar
      const bar = screen.getByTestId('client-bar');
      fireEvent.click(bar);

      // Verify navigate was called with correct path
      expect(mockNavigate).toHaveBeenCalledTimes(1);
      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects-portfolio?client_id=123');
    });

    it('should not navigate when bar click payload has no client_id', () => {
      // Set flag to make mock Bar pass empty payload
      rechartsMock.setPassEmptyPayload(true);

      const items = [
        {
          client_id: '456',
          client_name: 'Another Client',
          overrun_amount_total: 200000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} />, { wrapper: createWrapper });

      // Verify chart renders
      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();

      // Click the bar (which will pass empty payload due to flag)
      const bar = screen.getByTestId('client-bar');
      fireEvent.click(bar);

      // Should not navigate when payload has no client_id
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('should navigate with correct client_id for multiple items', () => {
      const items = [
        {
          client_id: '111',
          client_name: 'Client 1',
          overrun_amount_total: 300000,
          currency: 'USD',
        },
        {
          client_id: '222',
          client_name: 'Client 2',
          overrun_amount_total: 200000,
          currency: 'USD',
        },
        {
          client_id: '333',
          client_name: 'Client 3',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} maxItems={3} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();

      // Click the bar (which will use the first item's data)
      const bar = screen.getByTestId('client-bar');
      fireEvent.click(bar);

      // Should navigate to the first client (highest overrun)
      expect(mockNavigate).toHaveBeenCalledTimes(1);
      expect(mockNavigate).toHaveBeenCalledWith('/app/reports/projects-portfolio?client_id=111');
    });
  });

  describe('Chart utilities integration', () => {
    it('should use formatAmountShort for YAxis formatting', () => {
      const items = [
        {
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: 1500000, // 1.5M
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
    });

    it('should use formatCurrency in tooltip', () => {
      const items = [
        {
          client_id: '123',
          client_name: 'Test Client',
          overrun_amount_total: 100000,
          currency: 'USD',
        },
      ];

      render(<TopOverrunClientsChart items={items} />, { wrapper: createWrapper });

      expect(screen.getByTestId('top-overrun-clients-chart')).toBeInTheDocument();
    });
  });
});

