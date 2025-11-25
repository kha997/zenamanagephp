import type { Meta, StoryObj } from '@storybook/react-vite';
import { HeaderShell } from './HeaderShell';

const meta = {
  title: 'Layout/HeaderShell',
  component: HeaderShell,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Standardized header component with RBAC, tenancy, search, and mobile support. Features role-based navigation filtering, theme toggle, tenant context, global search, mobile hamburger menu, notifications, and full accessibility support.',
      },
    },
  },
  tags: ['autodocs', 'header', 'navigation', 'rbac'],
} satisfies Meta<typeof HeaderShell>;

export default meta;
type Story = StoryObj<typeof meta>;

const mockTenant = {
  id: 'tenant-1',
  name: 'Acme Corp',
  type: 'enterprise',
};

const mockNavigation = [
  { href: '/dashboard', label: 'Dashboard', requiredPermission: 'dashboard.view' },
  { href: '/projects', label: 'Projects', requiredPermission: 'projects.view' },
  { href: '/users', label: 'Users', requiredPermission: 'users.view' },
  { href: '/settings', label: 'Settings', requiredPermission: 'settings.view' },
];

const mockNotifications = [
  {
    id: '1',
    title: 'New Project Assigned',
    body: 'You have been assigned to Project Alpha',
    read: false,
    created_at: new Date().toISOString(),
  },
  {
    id: '2',
    title: 'Task Completed',
    body: 'Task "Implement HeaderShell" has been marked as completed',
    read: false,
    created_at: new Date().toISOString(),
  },
  {
    id: '3',
    title: 'System Update',
    body: 'New features available in dashboard',
    read: true,
    created_at: new Date().toISOString(),
  },
];

// Default story
export const Default: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
    searchPlaceholder: 'Search projects, tasks, users...',
  },
  parameters: {
    docs: {
      description: {
        story: 'Default header with all features enabled.',
      },
    },
  },
};

// Guest state (no user)
export const Guest: Story = {
  args: {
    navigation: mockNavigation,
    notifications: [],
    unreadCount: 0,
    tenantName: 'Guest',
    showSearch: false,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header for unauthenticated users (guest state).',
      },
    },
  },
};

// Authenticated user
export const Authenticated: Story = {
  args: {
    navigation: mockNavigation,
    breadcrumbs: mockBreadcrumbs,
    notifications: mockNotifications.slice(0, 2),
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header for authenticated user with notifications.',
      },
    },
  },
};

// Multi-role user
export const MultiRole: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 3,
    tenantName: 'Multi-Tenant Organization',
    showSearch: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header for user with multiple roles. Navigation items are filtered by permissions.',
      },
    },
  },
};

// Mobile viewport
export const Mobile: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    viewport: {
      defaultViewport: 'mobile1',
    },
    docs: {
      description: {
        story: 'Header optimized for mobile devices. Hamburger menu is visible and mobile-optimized.',
      },
    },
  },
};

// Tablet viewport
export const Tablet: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    viewport: {
      defaultViewport: 'tablet',
    },
    docs: {
      description: {
        story: 'Header for tablet viewport. Responsive design adapts to medium screens.',
      },
    },
  },
};

// Desktop viewport
export const Desktop: Story = {
  args: {
    navigation: mockNavigation,
    breadcrumbs: mockBreadcrumbs,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    viewport: {
      defaultViewport: 'desktop',
    },
    docs: {
      description: {
        story: 'Header for desktop viewport. Full features including search.',
      },
    },
  },
};

// With search disabled
export const WithoutSearch: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: false,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header without search functionality. Useful for specific pages where search is not needed.',
      },
    },
  },
};

// With no notifications
export const NoNotifications: Story = {
  args: {
    navigation: mockNavigation,
    notifications: [],
    unreadCount: 0,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header with no notifications. Notification badge is not shown.',
      },
    },
  },
};

// With many notifications
export const ManyNotifications: Story = {
  args: {
    navigation: mockNavigation,
    notifications: Array.from({ length: 10 }, (_, i) => ({
      id: `${i + 1}`,
      title: `Notification ${i + 1}`,
      body: `This is notification ${i + 1}`,
      read: i % 2 === 0,
      created_at: new Date().toISOString(),
    })),
    unreadCount: 10,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header with many notifications. Unread count badge shows the total number of unread notifications.',
      },
    },
  },
};

// DEPRECATED: LongBreadcrumbs story removed - breadcrumbs are no longer supported

// Dark theme
export const DarkTheme: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    backgrounds: {
      default: 'dark',
    },
    docs: {
      description: {
        story: 'Header in dark theme. All elements adapt to dark mode styling.',
      },
    },
  },
};

// With no breadcrumbs
export const NoBreadcrumbs: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Header without breadcrumbs. This is now the default behavior.',
      },
    },
  },
};

// With minimal props
export const Minimal: Story = {
  args: {
    navigation: [],
    notifications: [],
    unreadCount: 0,
    showSearch: false,
  },
  parameters: {
    docs: {
      description: {
        story: 'Minimal header with only essential elements. No navigation or search.',
      },
    },
  },
};

// With custom actions
export const CustomActions: Story = {
  args: {
    navigation: mockNavigation,
    notifications: mockNotifications,
    unreadCount: 2,
    tenantName: mockTenant.name,
    showSearch: true,
    onSearch: (query: string) => {
      console.log('Search:', query);
    },
    onNotificationClick: (notification) => {
      console.log('Notification clicked:', notification);
    },
    onSettingsClick: () => {
      console.log('Settings clicked');
    },
    onLogout: () => {
      console.log('Logout clicked');
    },
  },
  parameters: {
    docs: {
      description: {
        story: 'Header with custom action handlers. All actions log to console in this example.',
      },
    },
  },
};
