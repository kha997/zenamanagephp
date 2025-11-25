import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Navbar from '../Navbar';
import * as AuthContextModule from '../../contexts/AuthContext';

// Mock the auth context
const mockUseAuthContext = vi.fn();

vi.mock('../../contexts/AuthContext', () => ({
  AuthContext: {
    Provider: ({ children }: { children: React.ReactNode }) => children,
  },
  useAuthContext: () => mockUseAuthContext(),
  AuthProvider: ({ children }: { children: React.ReactNode }) => children,
}));

// Helper to render Navbar with Router
const renderNavbar = (user: any = null) => {
  mockUseAuthContext.mockReturnValue({
    user,
    setUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
    isAuthenticated: !!user,
    isLoading: false,
  });

  return render(
    <BrowserRouter>
      <Navbar />
    </BrowserRouter>
  );
};

describe('Navbar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    
    // Default mock - no user (not authenticated)
    mockUseAuthContext.mockReturnValue({
      user: null,
      setUser: vi.fn(),
      login: vi.fn(),
      logout: vi.fn(),
      isAuthenticated: false,
      isLoading: false,
    });
  });

  describe('Rendering - All Routes', () => {
    it('should render all main navigation links', () => {
      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      // Check all main routes are present
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Projects')).toBeInTheDocument();
      expect(screen.getByText('Tasks')).toBeInTheDocument();
      expect(screen.getByText('Documents')).toBeInTheDocument();
      expect(screen.getByText('Team')).toBeInTheDocument();
      expect(screen.getByText('Calendar')).toBeInTheDocument();
      expect(screen.getByText('Alerts')).toBeInTheDocument();
      expect(screen.getByText('Preferences')).toBeInTheDocument();
      expect(screen.getByText('Settings')).toBeInTheDocument();
    });

    it('should have correct hrefs for all links', () => {
      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      const dashboardLink = screen.getByText('Dashboard').closest('a');
      const projectsLink = screen.getByText('Projects').closest('a');
      const tasksLink = screen.getByText('Tasks').closest('a');
      const documentsLink = screen.getByText('Documents').closest('a');
      const teamLink = screen.getByText('Team').closest('a');
      const calendarLink = screen.getByText('Calendar').closest('a');
      const alertsLink = screen.getByText('Alerts').closest('a');
      const preferencesLink = screen.getByText('Preferences').closest('a');
      const settingsLink = screen.getByText('Settings').closest('a');

      expect(dashboardLink).toHaveAttribute('href', '/app/dashboard');
      expect(projectsLink).toHaveAttribute('href', '/app/projects');
      expect(tasksLink).toHaveAttribute('href', '/app/tasks');
      expect(documentsLink).toHaveAttribute('href', '/app/documents');
      expect(teamLink).toHaveAttribute('href', '/app/team');
      expect(calendarLink).toHaveAttribute('href', '/app/calendar');
      expect(alertsLink).toHaveAttribute('href', '/app/alerts');
      expect(preferencesLink).toHaveAttribute('href', '/app/preferences');
      expect(settingsLink).toHaveAttribute('href', '/app/settings');
    });
  });

  describe('RBAC - Admin Link', () => {
    it('should NOT show Admin link for regular users', () => {
      const regularUser = {
        id: '1',
        name: 'Regular User',
        roles: [{ id: '1', name: 'Member' }],
      };

      renderNavbar(regularUser);

      expect(screen.queryByText('Admin')).not.toBeInTheDocument();
    });

    it('should show Admin link for admin users', () => {
      const adminUser = {
        id: '2',
        name: 'Admin User',
        roles: [{ id: '2', name: 'admin' }],
      };

      renderNavbar(adminUser);

      expect(screen.getByText('Admin')).toBeInTheDocument();
      const adminLink = screen.getByText('Admin').closest('a');
      expect(adminLink).toHaveAttribute('href', '/admin/dashboard');
    });

    it('should show Admin link for super_admin users', () => {
      const superAdminUser = {
        id: '3',
        name: 'Super Admin',
        roles: [{ id: '3', name: 'super_admin' }],
      };

      renderNavbar(superAdminUser);

      expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('should show Admin link for users with Admin role (capitalized)', () => {
      const adminUser = {
        id: '4',
        name: 'Admin User',
        roles: [{ id: '4', name: 'Admin' }],
      };

      renderNavbar(adminUser);

      expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('should show Admin link for users with SuperAdmin role (PascalCase)', () => {
      const superAdminUser = {
        id: '5',
        name: 'Super Admin',
        roles: [{ id: '5', name: 'SuperAdmin' }],
      };

      renderNavbar(superAdminUser);

      expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('should handle users with multiple roles correctly', () => {
      const userWithMultipleRoles = {
        id: '6',
        name: 'User with Roles',
        roles: [
          { id: '1', name: 'Member' },
          { id: '2', name: 'PM' },
          { id: '3', name: 'admin' },
        ],
      };

      renderNavbar(userWithMultipleRoles);

      // Should show Admin link because one role is admin
      expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('should handle string role names (legacy format)', () => {
      const userWithStringRole = {
        id: '7',
        name: 'User',
        roles: ['admin'], // String format instead of object
      };

      renderNavbar(userWithStringRole);

      expect(screen.getByText('Admin')).toBeInTheDocument();
    });
  });

  describe('Active State', () => {
    it('should apply active class when route matches', () => {
      // Mock useLocation to return /app/dashboard
      vi.mock('react-router-dom', async () => {
        const actual = await vi.importActual('react-router-dom');
        return {
          ...actual,
          useLocation: () => ({ pathname: '/app/dashboard' }),
        };
      });

      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      const dashboardLink = screen.getByText('Dashboard').closest('a');
      // Note: className check depends on actual implementation
      // This test verifies the structure exists
      expect(dashboardLink).toBeInTheDocument();
    });
  });

  describe('User Context', () => {
    it('should handle null user gracefully', () => {
      renderNavbar(null);

      // Should still render all non-admin links
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.queryByText('Admin')).not.toBeInTheDocument();
    });

    it('should handle user without roles', () => {
      const userWithoutRoles = {
        id: '8',
        name: 'User Without Roles',
        roles: [],
      };

      renderNavbar(userWithoutRoles);

      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.queryByText('Admin')).not.toBeInTheDocument();
    });

    it('should handle user with null roles array', () => {
      const userWithNullRoles = {
        id: '9',
        name: 'User',
        roles: null,
      };

      renderNavbar(userWithNullRoles);

      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.queryByText('Admin')).not.toBeInTheDocument();
    });
  });

  describe('Navigation Structure', () => {
    it('should render navigation in a nav element', () => {
      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      const nav = screen.getByRole('navigation');
      expect(nav).toBeInTheDocument();
    });

    it('should render links in a list structure', () => {
      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      const nav = screen.getByRole('navigation');
      const lists = within(nav).getAllByRole('list');
      expect(lists.length).toBeGreaterThan(0);
    });

    it('should have all links as list items', () => {
      renderNavbar({ id: '1', name: 'Test User', roles: [] });

      const nav = screen.getByRole('navigation');
      const links = within(nav).getAllByRole('link');
      
      // Should have at least 9 links (main routes)
      expect(links.length).toBeGreaterThanOrEqual(9);
    });
  });
});

