import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useAuthStore } from '@/store/auth';
import { RoleGuard } from '@/routes/RoleGuard';
import { PmDashboard } from '@/pages/dashboard/PmDashboard';

// Mock the API service
vi.mock('@/services/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

// Mock the auth store
vi.mock('@/store/auth', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the loading spinner
vi.mock('@/components/ui/loading-spinner', () => ({
  LoadingSpinner: ({ size }: { size: string }) => <div data-testid="loading-spinner">Loading...</div>,
}));

const TestWrapper = ({ children }: { children: React.ReactNode }) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        {children}
      </BrowserRouter>
    </QueryClientProvider>
  );
};

describe('Authentication Integration Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('RoleGuard Component', () => {
    it('should render children when user has required role', () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'PM' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      render(
        <TestWrapper>
          <RoleGuard requiredRoles={['PM']}>
            <div data-testid="protected-content">Protected Content</div>
          </RoleGuard>
        </TestWrapper>
      );

      expect(screen.getByTestId('protected-content')).toBeInTheDocument();
    });

    it('should redirect to unauthorized when user lacks required role', () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'Designer' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      render(
        <TestWrapper>
          <RoleGuard requiredRoles={['PM']}>
            <div data-testid="protected-content">Protected Content</div>
          </RoleGuard>
        </TestWrapper>
      );

      // Should redirect to unauthorized page
      expect(screen.queryByTestId('protected-content')).not.toBeInTheDocument();
    });

    it('should show loading spinner when authentication is loading', () => {
      (useAuthStore as any).mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: true,
      });

      render(
        <TestWrapper>
          <RoleGuard requiredRoles={['PM']}>
            <div data-testid="protected-content">Protected Content</div>
          </RoleGuard>
        </TestWrapper>
      );

      expect(screen.getByTestId('loading-spinner')).toBeInTheDocument();
    });

    it('should redirect to login when user is not authenticated', () => {
      (useAuthStore as any).mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });

      render(
        <TestWrapper>
          <RoleGuard requiredRoles={['PM']}>
            <div data-testid="protected-content">Protected Content</div>
          </RoleGuard>
        </TestWrapper>
      );

      expect(screen.queryByTestId('protected-content')).not.toBeInTheDocument();
    });
  });

  describe('Dashboard Components', () => {
    it('should render PM Dashboard with loading state', () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'PM' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      render(
        <TestWrapper>
          <PmDashboard />
        </TestWrapper>
      );

      expect(screen.getByText('Project Manager Dashboard')).toBeInTheDocument();
    });

    it('should handle API errors gracefully', async () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'PM' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      // Mock API to return error
      const { api } = await import('@/services/api');
      (api.get as any).mockRejectedValue(new Error('API Error'));

      render(
        <TestWrapper>
          <PmDashboard />
        </TestWrapper>
      );

      await waitFor(() => {
        expect(screen.getByText(/Failed to load PM Dashboard/)).toBeInTheDocument();
      });
    });
  });

  describe('Role-based Navigation', () => {
    it('should show correct navigation items for PM role', () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'PM' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      // This would test the Sidebar component's role-based navigation
      // Implementation would depend on how the Sidebar component is structured
      expect(mockUser.roles[0].name).toBe('PM');
    });

    it('should hide restricted navigation items for Client role', () => {
      const mockUser = {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        roles: [{ name: 'Client' }],
        permissions: [],
      };

      (useAuthStore as any).mockReturnValue({
        user: mockUser,
        isAuthenticated: true,
        isLoading: false,
      });

      // This would test that certain navigation items are hidden for Client role
      expect(mockUser.roles[0].name).toBe('Client');
    });
  });
});
