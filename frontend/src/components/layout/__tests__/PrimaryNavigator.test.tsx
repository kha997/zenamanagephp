import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { PrimaryNavigator } from '../PrimaryNavigator';
import { useAuthStore } from '@/store/auth';

// Mock dependencies
vi.mock('@/store/auth', () => ({
  useAuthStore: vi.fn(),
}));

vi.mock('@/lib/utils', () => ({
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
}));

const mockedUseAuthStore = vi.mocked(useAuthStore);

describe('PrimaryNavigator', () => {
  const mockUserWithRoles = (roles: string[]) => ({
    id: '1',
    name: 'Test User',
    roles: roles.map(name => ({ id: `role-${name}`, name })),
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Navigation Items Rendering', () => {
    it('should render base navigation items for all users', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles([]),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      // Base navigation items should always be visible
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Projects')).toBeInTheDocument();
      expect(screen.getByText('Tasks')).toBeInTheDocument();
    });

    it('should render admin-only items for SuperAdmin role', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles(['SuperAdmin']),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      // Admin items should be visible
      expect(screen.getByText('Tenants')).toBeInTheDocument();
      expect(screen.getByText('Users')).toBeInTheDocument();
    });

    it('should render role-specific items for PM role', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles(['PM']),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      // PM-specific items
      expect(screen.getByText('Templates')).toBeInTheDocument();
      expect(screen.getByText('Change Requests')).toBeInTheDocument();
    });

    it('should not render admin items for non-admin users', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles(['Member']),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      // Admin items should not be visible
      expect(screen.queryByText('Tenants')).not.toBeInTheDocument();
      expect(screen.queryByText('Users')).not.toBeInTheDocument();
    });
  });

  describe('Navigation Links', () => {
    it('should have correct href attributes for all navigation items', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles(['SuperAdmin', 'PM']),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      // Verify all links have correct hrefs
      expect(screen.getByRole('link', { name: /Dashboard/i })).toHaveAttribute('href', '/app/dashboard');
      expect(screen.getByRole('link', { name: /Projects/i })).toHaveAttribute('href', '/app/projects');
      expect(screen.getByRole('link', { name: /Tasks/i })).toHaveAttribute('href', '/app/tasks');
      expect(screen.getByRole('link', { name: /Tenants/i })).toHaveAttribute('href', '/app/tenants');
      expect(screen.getByRole('link', { name: /Templates/i })).toHaveAttribute('href', '/app/templates');
      expect(screen.getByRole('link', { name: /Change Requests/i })).toHaveAttribute('href', '/app/change-requests');
      expect(screen.getByRole('link', { name: /Users/i })).toHaveAttribute('href', '/app/users');
      expect(screen.getByRole('link', { name: /Settings/i })).toHaveAttribute('href', '/app/settings');
    });

    it('should mark active route correctly', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles([]),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter initialEntries={['/app/projects']}>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      const projectsLink = screen.getByRole('link', { name: /Projects/i });
      // Active link should have active styling class
      expect(projectsLink.className).toContain('border-blue-600');
    });
  });

  describe('Navigation Integrity', () => {
    it('should only show navigation items that have corresponding routes', () => {
      // This test ensures we don't show nav items for non-existent routes
      // All items in PrimaryNavigator should point to routes defined in router.tsx
      
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles(['SuperAdmin', 'PM', 'Designer']),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      const nav = screen.getByTestId('primary-navigator');
      const links = within(nav).getAllByRole('link');

      // Verify all links point to /app/* routes (which should exist in router)
      links.forEach(link => {
        const href = link.getAttribute('href');
        expect(href).toMatch(/^\/app\//);
        // Verify it's not a broken route by checking it's one of the expected paths
        const validPaths = [
          '/app/dashboard',
          '/app/projects',
          '/app/tasks',
          '/app/team',
          '/app/calendar',
          '/app/settings',
          '/app/tenants',
          '/app/templates',
          '/app/change-requests',
          '/app/users',
        ];
        expect(validPaths).toContain(href);
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles([]),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      const nav = screen.getByRole('navigation', { name: /Primary navigation/i });
      expect(nav).toBeInTheDocument();
      expect(nav).toHaveAttribute('aria-label', 'Primary navigation');
    });

    it('should have data-testid for testing', () => {
      mockedUseAuthStore.mockReturnValue({
        user: mockUserWithRoles([]),
        isAuthenticated: true,
        isLoading: false,
        logout: vi.fn(),
        token: 'test-token',
        error: null,
        login: vi.fn(),
        setUser: vi.fn(),
        setToken: vi.fn(),
        setError: vi.fn(),
        setLoading: vi.fn(),
        clearAuth: vi.fn(),
        refreshUser: vi.fn(),
        checkAuth: vi.fn(),
      });

      render(
        <MemoryRouter>
          <PrimaryNavigator />
        </MemoryRouter>
      );

      expect(screen.getByTestId('primary-navigator')).toBeInTheDocument();
    });
  });
});

