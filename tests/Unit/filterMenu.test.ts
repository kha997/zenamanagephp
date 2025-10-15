import { filterMenu, getMenuItems, canAccessRoute } from '../../src/lib/menu/filterMenu';
import { NavItem } from '../../src/components/ui/header/PrimaryNav';
import { User, Tenant } from '../../src/lib/menu/filterMenu';

describe('filterMenu', () => {
  const mockMenuItems: NavItem[] = [
    {
      id: 'dashboard',
      label: 'Dashboard',
      icon: 'home',
      to: '/dashboard',
      roles: ['*'],
      tenants: ['*'],
    },
    {
      id: 'projects',
      label: 'Projects',
      icon: 'project',
      to: '/projects',
      roles: ['admin', 'pm'],
      tenants: ['*'],
    },
    {
      id: 'admin',
      label: 'Admin',
      icon: 'cog',
      to: '/admin',
      roles: ['admin'],
      tenants: ['tenant1'],
      children: [
        {
          id: 'admin-users',
          label: 'Users',
          icon: 'users',
          to: '/admin/users',
          roles: ['admin'],
          tenants: ['*'],
        },
      ],
    },
  ];

  const mockUser: User = {
    id: 'user1',
    roles: ['pm'],
    tenant_id: 'tenant1',
    permissions: ['menu.dashboard.view', 'menu.projects.view'],
  };

  const mockTenant: Tenant = {
    id: 'tenant1',
    name: 'Test Tenant',
  };

  describe('role filtering', () => {
    it('should show items with wildcard roles to all users', () => {
      const result = filterMenu(mockMenuItems, mockUser, mockTenant);
      expect(result).toHaveLength(2);
      expect(result[0].id).toBe('dashboard');
    });

    it('should show items with matching roles', () => {
      const result = filterMenu(mockMenuItems, mockUser, mockTenant);
      expect(result).toHaveLength(2);
      expect(result[1].id).toBe('projects');
    });

    it('should hide items with non-matching roles', () => {
      const userWithoutAdminRole: User = {
        ...mockUser,
        roles: ['member'],
      };
      const result = filterMenu(mockMenuItems, userWithoutAdminRole, mockTenant);
      expect(result).toHaveLength(1);
      expect(result[0].id).toBe('dashboard');
    });
  });

  describe('tenant filtering', () => {
    it('should show items with wildcard tenants to all tenants', () => {
      const result = filterMenu(mockMenuItems, mockUser, mockTenant);
      expect(result).toHaveLength(2);
    });

    it('should show items with matching tenant', () => {
      const result = filterMenu(mockMenuItems, mockUser, mockTenant);
      const adminItem = result.find(item => item.id === 'admin');
      expect(adminItem).toBeDefined();
    });

    it('should hide items with non-matching tenant', () => {
      const differentTenant: Tenant = {
        id: 'tenant2',
        name: 'Different Tenant',
      };
      const result = filterMenu(mockMenuItems, mockUser, differentTenant);
      const adminItem = result.find(item => item.id === 'admin');
      expect(adminItem).toBeUndefined();
    });
  });

  describe('nested menu items', () => {
    it('should filter children recursively', () => {
      const result = filterMenu(mockMenuItems, mockUser, mockTenant);
      const adminItem = result.find(item => item.id === 'admin');
      expect(adminItem?.children).toHaveLength(1);
      expect(adminItem?.children?.[0].id).toBe('admin-users');
    });

    it('should remove parent items with no visible children', () => {
      const userWithoutAdminRole: User = {
        ...mockUser,
        roles: ['member'],
      };
      const result = filterMenu(mockMenuItems, userWithoutAdminRole, mockTenant);
      const adminItem = result.find(item => item.id === 'admin');
      expect(adminItem).toBeUndefined();
    });
  });

  describe('edge cases', () => {
    it('should return empty array for null user', () => {
      const result = filterMenu(mockMenuItems, null, mockTenant);
      expect(result).toEqual([]);
    });

    it('should return empty array for null tenant', () => {
      const result = filterMenu(mockMenuItems, mockUser, null);
      expect(result).toEqual([]);
    });

    it('should handle empty menu items', () => {
      const result = filterMenu([], mockUser, mockTenant);
      expect(result).toEqual([]);
    });
  });
});

describe('canAccessRoute', () => {
  const mockMenuItems: NavItem[] = [
    {
      id: 'dashboard',
      label: 'Dashboard',
      icon: 'home',
      to: '/dashboard',
      roles: ['*'],
      tenants: ['*'],
    },
    {
      id: 'admin',
      label: 'Admin',
      icon: 'cog',
      to: '/admin',
      roles: ['admin'],
      tenants: ['tenant1'],
    },
  ];

  const mockUser: User = {
    id: 'user1',
    roles: ['pm'],
    tenant_id: 'tenant1',
  };

  const mockTenant: Tenant = {
    id: 'tenant1',
    name: 'Test Tenant',
  };

  it('should allow access to routes with wildcard roles', () => {
    const canAccess = canAccessRoute('/dashboard', mockUser, mockTenant, mockMenuItems);
    expect(canAccess).toBe(true);
  });

  it('should deny access to routes with non-matching roles', () => {
    const canAccess = canAccessRoute('/admin', mockUser, mockTenant, mockMenuItems);
    expect(canAccess).toBe(false);
  });

  it('should allow access to routes not in menu', () => {
    const canAccess = canAccessRoute('/unknown-route', mockUser, mockTenant, mockMenuItems);
    expect(canAccess).toBe(true);
  });
});
