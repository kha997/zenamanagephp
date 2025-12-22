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

describe('AppNavigator - Permissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Documents and Reports navigation visibility', () => {
    it('should show Documents and Reports links when user has view permissions', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'pm' },
        hasTenantPermission: (permission: string) => {
          return ['tenant.view_documents', 'tenant.view_reports'].includes(permission);
        },
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.getByText('Documents')).toBeInTheDocument();
      expect(screen.getByText('Reports')).toBeInTheDocument();
    });

    it('should show Documents and Reports links when user has manage permissions', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'pm' },
        hasTenantPermission: (permission: string) => {
          return ['tenant.manage_documents', 'tenant.manage_reports'].includes(permission);
        },
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.getByText('Documents')).toBeInTheDocument();
      expect(screen.getByText('Reports')).toBeInTheDocument();
    });

    it('should hide Documents and Reports links when user has no permissions', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'member' },
        hasTenantPermission: () => false,
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.queryByText('Documents')).not.toBeInTheDocument();
      expect(screen.queryByText('Reports')).not.toBeInTheDocument();
    });

    it('should show Documents when user has view_documents but not Reports when no view_reports', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User', role: 'member' },
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.view_documents';
        },
      } as any);

      render(
        <TestWrapper>
          <AppNavigator />
        </TestWrapper>
      );

      expect(screen.getByText('Documents')).toBeInTheDocument();
      expect(screen.queryByText('Reports')).not.toBeInTheDocument();
    });
  });
});

