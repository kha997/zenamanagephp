import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { TenantSwitcher } from '../TenantSwitcher';
import { useAuthStore } from '../../../auth/store';
import { useSwitchTenant } from '../../hooks';
import axios from 'axios';

// Mock dependencies
vi.mock('../../../auth/store');
vi.mock('../../hooks');
vi.mock('axios');
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseSwitchTenant = vi.mocked(useSwitchTenant);
const mockAxios = vi.mocked(axios);

// Helper to create test wrapper
const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

describe('TenantSwitcher', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('User has 1 tenant', () => {
    it('should display workspace name without dropdown', () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
          tenant_id: 'tenant-1',
        },
        tenantsCount: 1,
      } as any);

      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: vi.fn(),
        isPending: false,
      } as any);

      mockAxios.get.mockResolvedValue({
        data: {
          success: true,
          data: {
            tenants: [
              { id: 'tenant-1', name: 'Workspace 1', is_current: true },
            ],
            count: 1,
            current_tenant_id: 'tenant-1',
          },
        },
      } as any);

      render(<TenantSwitcher />, { wrapper: createWrapper() });

      expect(screen.getByText('Workspace:')).toBeInTheDocument();
      expect(screen.getByText('Workspace 1')).toBeInTheDocument();
      // Should not have dropdown button
      expect(screen.queryByRole('button', { name: /switch workspace/i })).not.toBeInTheDocument();
    });
  });

  describe('User has multiple tenants', () => {
    it('should display dropdown with current tenant and other tenants', async () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
          tenant_id: 'tenant-1',
        },
        tenantsCount: 2,
      } as any);

      const mockSwitchTenant = vi.fn().mockResolvedValue({});
      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: mockSwitchTenant,
        isPending: false,
      } as any);

      mockAxios.get.mockResolvedValue({
        data: {
          success: true,
          data: {
            tenants: [
              { id: 'tenant-1', name: 'Workspace 1', is_current: true },
              { id: 'tenant-2', name: 'Workspace 2', is_current: false },
            ],
            count: 2,
            current_tenant_id: 'tenant-1',
          },
        },
      } as any);

      render(<TenantSwitcher />, { wrapper: createWrapper() });

      // Wait for tenants to load
      await waitFor(() => {
        expect(screen.getByText('Workspace 1')).toBeInTheDocument();
      });

      // Click dropdown button
      const dropdownButton = screen.getByRole('button', { name: /switch workspace/i });
      fireEvent.click(dropdownButton);

      // Should show current tenant and other tenants
      await waitFor(() => {
        expect(screen.getByText('Current workspace')).toBeInTheDocument();
        expect(screen.getByText('Workspace 2')).toBeInTheDocument();
      });

      // Click on another tenant
      const workspace2Button = screen.getByRole('menuitem', { name: 'Workspace 2' });
      fireEvent.click(workspace2Button);

      // Should call switchTenant with correct tenant ID
      await waitFor(() => {
        expect(mockSwitchTenant).toHaveBeenCalledWith('tenant-2');
      });
    });

    it('should close dropdown when clicking outside', async () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
          tenant_id: 'tenant-1',
        },
        tenantsCount: 2,
      } as any);

      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: vi.fn(),
        isPending: false,
      } as any);

      mockAxios.get.mockResolvedValue({
        data: {
          success: true,
          data: {
            tenants: [
              { id: 'tenant-1', name: 'Workspace 1', is_current: true },
              { id: 'tenant-2', name: 'Workspace 2', is_current: false },
            ],
            count: 2,
            current_tenant_id: 'tenant-1',
          },
        },
      } as any);

      render(<TenantSwitcher />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Workspace 1')).toBeInTheDocument();
      });

      // Open dropdown
      const dropdownButton = screen.getByRole('button', { name: /switch workspace/i });
      fireEvent.click(dropdownButton);

      await waitFor(() => {
        expect(screen.getByText('Current workspace')).toBeInTheDocument();
      });

      // Click outside (on document body)
      fireEvent.mouseDown(document.body);

      // Dropdown should close
      await waitFor(() => {
        expect(screen.queryByText('Current workspace')).not.toBeInTheDocument();
      });
    });
  });

  describe('Loading state', () => {
    it('should show loading state when switching tenant', async () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
          tenant_id: 'tenant-1',
        },
        tenantsCount: 2,
      } as any);

      const mockSwitchTenant = vi.fn(() => new Promise(() => {})); // Never resolves
      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: mockSwitchTenant,
        isPending: true,
      } as any);

      mockAxios.get.mockResolvedValue({
        data: {
          success: true,
          data: {
            tenants: [
              { id: 'tenant-1', name: 'Workspace 1', is_current: true },
              { id: 'tenant-2', name: 'Workspace 2', is_current: false },
            ],
            count: 2,
            current_tenant_id: 'tenant-1',
          },
        },
      } as any);

      render(<TenantSwitcher />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Switching...')).toBeInTheDocument();
      });

      // Button should be disabled
      const button = screen.getByRole('button', { name: /switch workspace/i });
      expect(button).toBeDisabled();
    });
  });

  describe('Error handling', () => {
    it('should display error toast when switch fails', async () => {
      const toast = (await import('react-hot-toast')).default;

      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
          tenant_id: 'tenant-1',
        },
        tenantsCount: 2,
      } as any);

      const mockSwitchTenant = vi.fn().mockRejectedValue(new Error('Switch failed'));
      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: mockSwitchTenant,
        isPending: false,
      } as any);

      mockAxios.get.mockResolvedValue({
        data: {
          success: true,
          data: {
            tenants: [
              { id: 'tenant-1', name: 'Workspace 1', is_current: true },
              { id: 'tenant-2', name: 'Workspace 2', is_current: false },
            ],
            count: 2,
            current_tenant_id: 'tenant-1',
          },
        },
      } as any);

      render(<TenantSwitcher />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Workspace 1')).toBeInTheDocument();
      });

      // Open dropdown and click on another tenant
      const dropdownButton = screen.getByRole('button', { name: /switch workspace/i });
      fireEvent.click(dropdownButton);

      await waitFor(() => {
        expect(screen.getByText('Workspace 2')).toBeInTheDocument();
      });

      const workspace2Button = screen.getByRole('menuitem', { name: 'Workspace 2' });
      fireEvent.click(workspace2Button);

      // Should show error toast
      await waitFor(() => {
        expect(toast.error).toHaveBeenCalledWith('Switch failed');
      });
    });
  });

  describe('User has no tenants', () => {
    it('should not render when user has no tenants', () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          name: 'Test User',
          email: 'test@example.com',
        },
        tenantsCount: 0,
      } as any);

      mockUseSwitchTenant.mockReturnValue({
        mutateAsync: vi.fn(),
        isPending: false,
      } as any);

      const { container } = render(<TenantSwitcher />, { wrapper: createWrapper() });

      // Component should not render anything
      expect(container.firstChild).toBeNull();
    });
  });
});

