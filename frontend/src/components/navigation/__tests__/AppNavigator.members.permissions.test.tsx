import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { AppNavigator } from '../AppNavigator';
import { useAuthStore } from '../../../features/auth/store';

// Mock the auth store
vi.mock('../../../features/auth/store', () => ({
  useAuthStore: vi.fn(),
}));

const mockUseAuthStore = vi.mocked(useAuthStore);

const TestWrapper = ({ children }: { children: React.ReactNode }) => (
  <BrowserRouter>{children}</BrowserRouter>
);

describe('AppNavigator - Members Permissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Members navigation visibility', () => {
    it('should show Members link when user has tenant.view_members permission', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'member' },
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.view_members';
        },
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.getByText('Members')).toBeInTheDocument();
    });

    it('should show Members link when user has tenant.manage_members permission', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'admin' },
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.manage_members';
        },
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.getByText('Members')).toBeInTheDocument();
    });

    it('should hide Members link when user has neither view nor manage permission', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'member' },
        hasTenantPermission: () => false,
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.queryByText('Members')).not.toBeInTheDocument();
    });
  });
});

