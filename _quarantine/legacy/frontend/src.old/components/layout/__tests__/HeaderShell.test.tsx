import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { HeaderShell } from '../HeaderShell';
import { useAuthStore } from '@/store/auth';
import { useTheme } from '@/contexts/ThemeContext';
import { usePermissions } from '@/hooks/usePermissions';
import { createTestUser, createTestRole } from '../../../../tests/factories/user';

// Mock dependencies
vi.mock('@/store/auth', () => ({
  useAuthStore: vi.fn(),
}));

vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: vi.fn(),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: vi.fn(),
}));

vi.mock('@/lib/utils', () => ({
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
}));

const mockedUseAuthStore = vi.mocked(useAuthStore);
const mockedUseTheme = vi.mocked(useTheme);
const mockedUsePermissions = vi.mocked(usePermissions);

describe('HeaderShell', () => {
  const mockUser = createTestUser({
    permissions: ['dashboard.view', 'projects.view', 'users.view'],
    roles: [
      createTestRole({
        id: 'role-admin',
        name: 'admin',
        permissions: ['dashboard.view', 'projects.view', 'users.view'],
      }),
    ],
    tenant_id: 'tenant-1',
  });

  const mockNavigation = [
    { href: '/dashboard', label: 'Dashboard', requiredPermission: 'dashboard.view' },
    { href: '/projects', label: 'Projects', requiredPermission: 'projects.view' },
    { href: '/users', label: 'Users', requiredPermission: 'users.view' },
  ];

  const defaultProps = {
    navigation: mockNavigation,
    notifications: [
      {
        id: '1',
        title: 'Test Notification',
        body: 'Test body',
        read: false,
        created_at: new Date().toISOString(),
      },
    ],
    unreadCount: 1,
    tenantName: 'Test Tenant',
    showSearch: true,
    searchPlaceholder: 'Search...',
  };

  const getNavigationToggle = () => screen.getByRole('button', { name: /navigation menu/i });

  beforeEach(() => {
    vi.clearAllMocks();

    mockedUseAuthStore.mockReturnValue({
      user: mockUser,
      logout: vi.fn(),
    });

    mockedUseTheme.mockReturnValue({
      theme: 'light',
      setTheme: vi.fn(),
      actualTheme: 'light',
    });

    mockedUsePermissions.mockReturnValue({
      hasPermission: vi.fn((permission) => {
        return mockUser.permissions.includes(permission);
      }),
    });
  });

  describe('Rendering', () => {
    it('should render header with all basic elements', () => {
      render(<HeaderShell {...defaultProps} />);

      expect(screen.getByRole('banner')).toBeInTheDocument();
      expect(screen.getByText('ZenaManage')).toBeInTheDocument();
      expect(screen.getByText('Test Tenant')).toBeInTheDocument();
      expect(getNavigationToggle()).toBeInTheDocument();
    });

    it('should render user menu with user information', () => {
      render(<HeaderShell {...defaultProps} />);

      expect(screen.getByText('Test User')).toBeInTheDocument();
      expect(screen.getByText('admin')).toBeInTheDocument();
    });

    it('should render notifications button with unread count', () => {
      render(<HeaderShell {...defaultProps} />);

      const notificationButton = screen.getByLabelText(/Notifications/);
      expect(notificationButton).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument(); // Unread count
    });

    it('should render theme toggle button', () => {
      render(<HeaderShell {...defaultProps} />);

      const themeButton = screen.getByLabelText(/Current theme/);
      expect(themeButton).toBeInTheDocument();
    });

    it('should render search bar when showSearch is true', () => {
      render(<HeaderShell {...defaultProps} showSearch={true} />);

      expect(screen.getByLabelText('Search')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Search...')).toBeInTheDocument();
    });

    it('should not render search bar when showSearch is false', () => {
      render(<HeaderShell {...defaultProps} showSearch={false} />);

      expect(screen.queryByLabelText('Search')).not.toBeInTheDocument();
    });
  });

  describe('Mobile Menu', () => {
    it('should toggle mobile menu on hamburger button click', async () => {
      render(<HeaderShell {...defaultProps} />);

      const hamburgerButton = getNavigationToggle();
      
      // Initially closed
      expect(screen.queryByRole('navigation', { name: 'Mobile navigation' })).not.toBeInTheDocument();

      // Open mobile menu
      fireEvent.click(hamburgerButton);

      await waitFor(() => {
        expect(screen.getByRole('navigation', { name: 'Mobile navigation' })).toBeInTheDocument();
      });

      // Close mobile menu
      fireEvent.click(hamburgerButton);

      await waitFor(() => {
        expect(screen.queryByRole('navigation', { name: 'Mobile navigation' })).not.toBeInTheDocument();
      });
    });

    it('should close mobile menu when clicking outside', async () => {
      render(<HeaderShell {...defaultProps} />);

      const hamburgerButton = getNavigationToggle();
      fireEvent.click(hamburgerButton);

      await waitFor(() => {
        expect(screen.getByRole('navigation', { name: 'Mobile navigation' })).toBeInTheDocument();
      });

      // Click outside
      fireEvent.mouseDown(document.body);

      await waitFor(() => {
        expect(screen.queryByRole('navigation', { name: 'Mobile navigation' })).not.toBeInTheDocument();
      });
    });
  });

  describe('Theme Toggle', () => {
    it('should call setTheme when theme button is clicked', () => {
      const setTheme = vi.fn();
      mockedUseTheme.mockReturnValue({
        theme: 'light',
        setTheme,
        actualTheme: 'light',
      });

      render(<HeaderShell {...defaultProps} />);

      const themeButton = screen.getByLabelText(/Current theme/);
      fireEvent.click(themeButton);

      expect(setTheme).toHaveBeenCalledWith('dark');
    });

    it('should cycle through themes', () => {
      const setTheme = vi.fn();
      mockedUseTheme.mockReturnValue({
        theme: 'light',
        setTheme,
        actualTheme: 'light',
      });

      const { rerender } = render(<HeaderShell {...defaultProps} />);

      let themeButton = screen.getByLabelText(/Current theme/);
      fireEvent.click(themeButton);
      expect(setTheme).toHaveBeenCalledWith('dark');

      // Change to dark
      mockedUseTheme.mockReturnValue({
        theme: 'dark',
        setTheme,
        actualTheme: 'dark',
      });

      rerender(<HeaderShell {...defaultProps} />);
      
      themeButton = screen.getByLabelText(/Current theme/);
      fireEvent.click(themeButton);
      expect(setTheme).toHaveBeenCalledWith('system');
    });
  });

  describe('Notifications', () => {
    it('should show notifications dropdown when clicked', async () => {
      render(<HeaderShell {...defaultProps} />);

      const notificationButton = screen.getByLabelText(/Notifications/);
      fireEvent.click(notificationButton);

      await waitFor(() => {
        expect(screen.getByText('Test Notification')).toBeInTheDocument();
      });
    });

    it('should call onNotificationClick when notification is clicked', async () => {
      const onNotificationClick = vi.fn();
      render(<HeaderShell {...defaultProps} onNotificationClick={onNotificationClick} />);

      const notificationButton = screen.getByLabelText(/Notifications/);
      fireEvent.click(notificationButton);

      await waitFor(() => {
        expect(screen.getByText('Test Notification')).toBeInTheDocument();
      });

      const notificationItem = screen.getByText('Test Notification');
      fireEvent.click(notificationItem);

      expect(onNotificationClick).toHaveBeenCalledWith(
        expect.objectContaining({ id: '1' })
      );
    });

    it('should show "No notifications" when empty', async () => {
      render(<HeaderShell {...defaultProps} notifications={[]} unreadCount={0} />);

      const notificationButton = screen.getByLabelText(/Notifications/);
      fireEvent.click(notificationButton);

      await waitFor(() => {
        expect(screen.getByText('No notifications')).toBeInTheDocument();
      });
    });
  });

  describe('User Menu', () => {
    it('should show user menu when clicked', async () => {
      render(<HeaderShell {...defaultProps} />);

      const userMenuButton = screen.getByLabelText(/User menu/);
      fireEvent.click(userMenuButton);

      await waitFor(() => {
        expect(screen.getByText('Sign out')).toBeInTheDocument();
      });
    });

    it('should call onLogout when sign out is clicked', async () => {
      const onLogout = vi.fn();
      const logout = vi.fn();
      
      mockedUseAuthStore.mockReturnValue({
        user: mockUser,
        logout,
      });

      render(<HeaderShell {...defaultProps} onLogout={onLogout} />);

      const userMenuButton = screen.getByLabelText(/User menu/);
      fireEvent.click(userMenuButton);

      await waitFor(() => {
        expect(screen.getByText('Sign out')).toBeInTheDocument();
      });

      const signOutButton = screen.getByText('Sign out');
      fireEvent.click(signOutButton);

      expect(onLogout).toHaveBeenCalled();
    });

    it('should call logout from store when no onLogout prop is provided', async () => {
      const logout = vi.fn();
      
      mockedUseAuthStore.mockReturnValue({
        user: mockUser,
        logout,
      });

      render(<HeaderShell {...defaultProps} />);

      const userMenuButton = screen.getByLabelText(/User menu/);
      fireEvent.click(userMenuButton);

      await waitFor(() => {
        expect(screen.getByText('Sign out')).toBeInTheDocument();
      });

      const signOutButton = screen.getByText('Sign out');
      fireEvent.click(signOutButton);

      expect(logout).toHaveBeenCalled();
    });
  });

  describe('Search', () => {
    it('should call onSearch with debounced query', async () => {
      vi.useFakeTimers();
      
      const onSearch = vi.fn();
      render(<HeaderShell {...defaultProps} onSearch={onSearch} />);

      const searchInput = screen.getByLabelText('Search');
      fireEvent.change(searchInput, { target: { value: 'test' } });

      // Wait for debounce
      await vi.advanceTimersByTime(300);

      expect(onSearch).toHaveBeenCalledWith('test');

      vi.useRealTimers();
    });

    it('should update search query in state', () => {
      render(<HeaderShell {...defaultProps} />);

      const searchInput = screen.getByLabelText('Search');
      fireEvent.change(searchInput, { target: { value: 'test query' } });

      expect(searchInput).toHaveValue('test query');
    });
  });

  describe('RBAC Filtering', () => {
    const openMobileNavigation = () => {
      fireEvent.click(getNavigationToggle());
      return screen.getByRole('navigation', { name: 'Mobile navigation' });
    };

    it('should filter navigation items by permissions', () => {
      render(<HeaderShell {...defaultProps} />);
      const mobileNav = openMobileNavigation();

      expect(within(mobileNav).getAllByRole('link', { name: 'Users' }).length).toBeGreaterThan(0);
    });

    it('should show all navigation items when user has all permissions', () => {
      mockedUsePermissions.mockReturnValue({
        hasPermission: vi.fn(() => true),
      });

      render(<HeaderShell {...defaultProps} />);
      const mobileNav = openMobileNavigation();

      ['Dashboard', 'Projects', 'Users'].forEach((label) => {
        expect(within(mobileNav).getAllByRole('link', { name: label }).length).toBeGreaterThan(0);
      });
    });

    it('should show no navigation items when user has no permissions', () => {
      mockedUsePermissions.mockReturnValue({
        hasPermission: vi.fn(() => false),
      });

      render(<HeaderShell {...defaultProps} />);
      const mobileNav = openMobileNavigation();

      ['Dashboard', 'Projects', 'Users'].forEach((label) => {
        expect(within(mobileNav).queryByRole('link', { name: label })).not.toBeInTheDocument();
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes', () => {
      render(<HeaderShell {...defaultProps} />);

      expect(screen.getByRole('banner')).toHaveAttribute('aria-label');
      const navToggle = getNavigationToggle();
      expect(navToggle).toHaveAttribute('aria-controls', 'header-mobile-menu');
      expect(navToggle).toHaveAttribute('aria-expanded', 'false');
      expect(screen.getByLabelText(/Notifications/)).toBeInTheDocument();
      expect(screen.getByLabelText(/Current theme/)).toBeInTheDocument();
      expect(screen.getByLabelText(/User menu/)).toBeInTheDocument();
    });

    it('should have data-testid and data-source attributes for testing', () => {
      render(<HeaderShell {...defaultProps} />);

      const header = screen.getByTestId('header-shell');
      expect(header).toBeInTheDocument();
      expect(header).toHaveAttribute('data-source', 'react');
    });

    it('should have keyboard navigation support', () => {
      render(<HeaderShell {...defaultProps} />);

      const hamburgerButton = getNavigationToggle();
      hamburgerButton.focus();
      expect(hamburgerButton).toHaveFocus();
    });

    it('should trap focus in mobile menu', async () => {
      render(<HeaderShell {...defaultProps} />);

      const hamburgerButton = getNavigationToggle();
      fireEvent.click(hamburgerButton);

      await waitFor(() => {
        const mobileMenu = screen.getByRole('navigation', { name: 'Mobile navigation' });
        expect(mobileMenu).toBeInTheDocument();
        
        // Check if focus trap is applied
        const focusableElements = mobileMenu.querySelectorAll('button, [href], input');
        expect(focusableElements.length).toBeGreaterThan(0);
      });
    });
  });

  describe('Settings', () => {
    it('should call onSettingsClick when provided', () => {
      const onSettingsClick = vi.fn();
      render(<HeaderShell {...defaultProps} onSettingsClick={onSettingsClick} />);

      const settingsButton = screen.getByLabelText('Settings');
      fireEvent.click(settingsButton);

      expect(onSettingsClick).toHaveBeenCalled();
    });

    it('should not render settings button when onSettingsClick is not provided', () => {
      render(<HeaderShell {...defaultProps} onSettingsClick={undefined} />);

      expect(screen.queryByLabelText('Settings')).not.toBeInTheDocument();
    });
  });
});
