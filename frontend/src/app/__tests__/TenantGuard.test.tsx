import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router-dom';
import { TenantGuard } from '../guards/TenantGuard';
import { useAuthStore } from '../../features/auth/store';

// Mock the auth store
vi.mock('../../features/auth/store', () => ({
  useAuthStore: vi.fn(),
}));

const mockUseAuthStore = vi.mocked(useAuthStore);

// Mock checkAuth to be a no-op by default
const mockCheckAuth = vi.fn();

describe('TenantGuard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should redirect to /app/no-workspace when user has no tenants', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: true,
      isLoading: false,
      tenantsCount: 0,
      checkAuth: mockCheckAuth,
    } as any);

    const TestPage = () => <div data-testid="test-page">Test Page</div>;

    const router = createMemoryRouter(
      [
        {
          path: '/app/dashboard',
          element: (
            <TenantGuard>
              <TestPage />
            </TenantGuard>
          ),
        },
        {
          path: '/app/no-workspace',
          element: <div data-testid="no-workspace-page">No Workspace Page</div>,
        },
      ],
      {
        initialEntries: ['/app/dashboard'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/app/no-workspace');
    });

    expect(screen.getByTestId('no-workspace-page')).toBeInTheDocument();
    expect(screen.queryByTestId('test-page')).not.toBeInTheDocument();
  });

  it('should allow access when user has tenants', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: true,
      isLoading: false,
      tenantsCount: 1,
      checkAuth: mockCheckAuth,
    } as any);

    const TestPage = () => <div data-testid="test-page">Test Page</div>;

    const router = createMemoryRouter(
      [
        {
          path: '/app/dashboard',
          element: (
            <TenantGuard>
              <TestPage />
            </TenantGuard>
          ),
        },
      ],
      {
        initialEntries: ['/app/dashboard'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(screen.getByTestId('test-page')).toBeInTheDocument();
    });

    expect(router.state.location.pathname).toBe('/app/dashboard');
  });

  it('should redirect to /app/dashboard when user has tenants but is on /app/no-workspace', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: true,
      isLoading: false,
      tenantsCount: 1,
      checkAuth: mockCheckAuth,
    } as any);

    const TestPage = () => <div data-testid="test-page">Test Page</div>;

    const router = createMemoryRouter(
      [
        {
          path: '/app/no-workspace',
          element: (
            <TenantGuard>
              <div data-testid="no-workspace-page">No Workspace Page</div>
            </TenantGuard>
          ),
        },
        {
          path: '/app/dashboard',
          element: <TestPage />,
        },
      ],
      {
        initialEntries: ['/app/no-workspace'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/app/dashboard');
    });

    expect(screen.getByTestId('test-page')).toBeInTheDocument();
    expect(screen.queryByTestId('no-workspace-page')).not.toBeInTheDocument();
  });

  it('should allow access to /app/no-workspace when user has no tenants', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: true,
      isLoading: false,
      tenantsCount: 0,
      checkAuth: mockCheckAuth,
    } as any);

    const router = createMemoryRouter(
      [
        {
          path: '/app/no-workspace',
          element: (
            <TenantGuard>
              <div data-testid="no-workspace-page">No Workspace Page</div>
            </TenantGuard>
          ),
        },
      ],
      {
        initialEntries: ['/app/no-workspace'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(screen.getByTestId('no-workspace-page')).toBeInTheDocument();
    });

    expect(router.state.location.pathname).toBe('/app/no-workspace');
  });

  it('should show loading state when isLoading is true', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: true,
      isLoading: true,
      tenantsCount: 0,
      checkAuth: mockCheckAuth,
    } as any);

    const TestPage = () => <div data-testid="test-page">Test Page</div>;

    const router = createMemoryRouter(
      [
        {
          path: '/app/dashboard',
          element: (
            <TenantGuard>
              <TestPage />
            </TenantGuard>
          ),
        },
      ],
      {
        initialEntries: ['/app/dashboard'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(screen.getByText(/Đang xác thực phiên đăng nhập/)).toBeInTheDocument();
    });

    expect(screen.queryByTestId('test-page')).not.toBeInTheDocument();
  });

  it('should redirect to /login when not authenticated', async () => {
    mockUseAuthStore.mockReturnValue({
      isAuthenticated: false,
      isLoading: false,
      tenantsCount: 0,
      checkAuth: mockCheckAuth,
    } as any);

    const TestPage = () => <div data-testid="test-page">Test Page</div>;

    const router = createMemoryRouter(
      [
        {
          path: '/app/dashboard',
          element: (
            <TenantGuard>
              <TestPage />
            </TenantGuard>
          ),
        },
        {
          path: '/login',
          element: <div data-testid="login-page">Login Page</div>,
        },
      ],
      {
        initialEntries: ['/app/dashboard'],
      }
    );

    render(<RouterProvider router={router} />);

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });

    expect(screen.getByTestId('login-page')).toBeInTheDocument();
    expect(screen.queryByTestId('test-page')).not.toBeInTheDocument();
  });
});

