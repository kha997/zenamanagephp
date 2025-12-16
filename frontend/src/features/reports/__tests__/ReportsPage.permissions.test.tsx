import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { ReportsPage } from '../pages/ReportsPage';
import { useAuthStore } from '../../auth/store';
import { createApiClient } from '../../../shared/api/client';

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the API client
vi.mock('../../../shared/api/client', () => ({
  createApiClient: vi.fn(() => ({
    get: vi.fn(),
  })),
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockCreateApiClient = vi.mocked(createApiClient);

const TestWrapper = ({ children }: { children: React.ReactNode }) => (
  <BrowserRouter>{children}</BrowserRouter>
);

describe('ReportsPage - Permissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    // Default API mock
    const mockApiClient = {
      get: vi.fn().mockResolvedValue({
        data: {
          data: {},
        },
      }),
    };
    mockCreateApiClient.mockReturnValue(mockApiClient as any);
  });

  describe('Access Restricted', () => {
    it('should show Access Restricted message when user has no view permission', () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: () => false,
      } as any);

      render(
        <TestWrapper>
          <ReportsPage />
        </TestWrapper>
      );

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText('Access Restricted')).toBeInTheDocument();
      expect(screen.queryByText('Generate Report')).not.toBeInTheDocument();
      expect(screen.queryByText('Export CSV')).not.toBeInTheDocument();
      expect(screen.queryByText('Schedule Report')).not.toBeInTheDocument();
    });
  });

  describe('Read-only mode', () => {
    it('should show Read-only badge and hide action buttons when user has view but not manage permission', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.view_reports';
        },
      } as any);

      render(
        <TestWrapper>
          <ReportsPage />
        </TestWrapper>
      );

      await waitFor(() => {
        expect(screen.getByText('Read-only mode')).toBeInTheDocument();
        // Check that action buttons are not present (only the CardTitle "Generate Report" should be visible)
        const buttons = screen.queryAllByRole('button');
        const actionButtons = buttons.filter(btn => 
          btn.textContent === 'Generate Report' || 
          btn.textContent === 'Export CSV' || 
          btn.textContent === 'Schedule Report'
        );
        expect(actionButtons.length).toBe(0);
      });
    });
  });

  describe('Full access', () => {
    it('should show all action buttons when user has manage permission', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: (permission: string) => {
          return ['tenant.view_reports', 'tenant.manage_reports'].includes(permission);
        },
      } as any);

      render(
        <TestWrapper>
          <ReportsPage />
        </TestWrapper>
      );

      await waitFor(() => {
        expect(screen.queryByText('Read-only mode')).not.toBeInTheDocument();
        expect(screen.queryByTestId('access-restricted')).not.toBeInTheDocument();
        // Check that action buttons are present (use getAllByText since there's also a CardTitle)
        const generateButtons = screen.getAllByText('Generate Report');
        expect(generateButtons.length).toBeGreaterThan(0);
        // Find the actual button (not the h2 title)
        const actionButtons = screen.getAllByRole('button');
        const generateButton = actionButtons.find(btn => btn.textContent === 'Generate Report');
        const exportButton = actionButtons.find(btn => btn.textContent === 'Export CSV');
        const scheduleButton = actionButtons.find(btn => btn.textContent === 'Schedule Report');
        expect(generateButton).toBeInTheDocument();
        expect(exportButton).toBeInTheDocument();
        expect(scheduleButton).toBeInTheDocument();
      });
    });

    it('should show all action buttons when user has only manage permission (no view)', async () => {
      mockUseAuthStore.mockReturnValue({
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.manage_reports';
        },
      } as any);

      render(
        <TestWrapper>
          <ReportsPage />
        </TestWrapper>
      );

      await waitFor(() => {
        expect(screen.queryByText('Read-only mode')).not.toBeInTheDocument();
        expect(screen.queryByTestId('access-restricted')).not.toBeInTheDocument();
        // Check that action buttons are present (use getAllByText since there's also a CardTitle)
        const generateButtons = screen.getAllByText('Generate Report');
        expect(generateButtons.length).toBeGreaterThan(0);
        // Find the actual button (not the h2 title)
        const actionButtons = screen.getAllByRole('button');
        const generateButton = actionButtons.find(btn => btn.textContent === 'Generate Report');
        const exportButton = actionButtons.find(btn => btn.textContent === 'Export CSV');
        const scheduleButton = actionButtons.find(btn => btn.textContent === 'Schedule Report');
        expect(generateButton).toBeInTheDocument();
        expect(exportButton).toBeInTheDocument();
        expect(scheduleButton).toBeInTheDocument();
      });
    });
  });
});

