import { NavItem } from '../../components/ui/header/PrimaryNav';

export interface User {
  id: string;
  roles: string[];
  tenant_id: string;
  permissions?: string[];
}

export interface Tenant {
  id: string;
  name: string;
  type?: string;
}

/**
 * Filters menu items based on user roles, tenant, and permissions
 * Implements RBAC and tenancy isolation as per project rules
 */
export const filterMenu = (
  menuItems: NavItem[],
  user: User | null,
  tenant: Tenant | null
): NavItem[] => {
  if (!user || !tenant) {
    return [];
  }

  return menuItems
    .filter(item => hasPermission(item, user, tenant))
    .map(item => ({
      ...item,
      children: item.children ? filterMenu(item.children, user, tenant) : undefined,
    }))
    .filter(item => {
      // Remove items with no visible children
      if (item.children && item.children.length === 0) {
        return false;
      }
      return true;
    });
};

/**
 * Checks if user has permission to access a menu item
 */
const hasPermission = (item: NavItem, user: User, tenant: Tenant): boolean => {
  // Check roles
  if (item.roles && item.roles.length > 0) {
    // Wildcard allows all roles
    if (item.roles.includes('*')) {
      // Continue to other checks
    } else {
      // Check if user has any of the required roles
      const hasRole = item.roles.some(role => user.roles.includes(role));
      if (!hasRole) {
        return false;
      }
    }
  }

  // Check tenant access
  if (item.tenants && item.tenants.length > 0) {
    // Wildcard allows all tenants
    if (item.tenants.includes('*')) {
      // Continue to other checks
    } else {
      // Check if user's tenant is allowed
      if (!item.tenants.includes(user.tenant_id)) {
        return false;
      }
    }
  }

  // Additional permission checks can be added here
  // For example, checking specific permissions array
  if (user.permissions && item.id) {
    // Example: Check for specific permission like 'menu.dashboard.view'
    const requiredPermission = `menu.${item.id}.view`;
    if (!user.permissions.includes(requiredPermission)) {
      return false;
    }
  }

  return true;
};

/**
 * Gets menu items for a specific user and tenant
 * This is the main function to use for getting filtered menu items
 */
export const getMenuItems = async (
  user: User,
  tenant: Tenant
): Promise<NavItem[]> => {
  try {
    // In a real app, this would fetch from API or config
    // For now, we'll use the static menu config
    const menuConfig = await import('../../../config/menu.json');
    return filterMenu(menuConfig.default, user, tenant);
  } catch (error) {
    console.error('Failed to load menu items:', error);
    return [];
  }
};

/**
 * Checks if a user can access a specific route
 * Used for route-level protection
 */
export const canAccessRoute = (
  route: string,
  user: User,
  tenant: Tenant,
  menuItems: NavItem[]
): boolean => {
  const findMenuItem = (items: NavItem[], targetRoute: string): NavItem | null => {
    for (const item of items) {
      if (item.to === targetRoute) {
        return item;
      }
      if (item.children) {
        const found = findMenuItem(item.children, targetRoute);
        if (found) return found;
      }
    }
    return null;
  };

  const menuItem = findMenuItem(menuItems, route);
  if (!menuItem) {
    // If route is not in menu, allow access (could be a direct route)
    return true;
  }

  return hasPermission(menuItem, user, tenant);
};
