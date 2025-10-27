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
      return !(item.children && item.children.length === 0);
    });
};

/**
 * Checks if user has permission to access a menu item
 */
const hasRolePermission = (item: NavItem, user: User): boolean => {
  if (!item.roles || item.roles.length === 0) return true;
  
  // Wildcard allows all roles
  if (item.roles.includes('*')) return true;
  
  // Check if user has any of the required roles
  return item.roles.some(role => user.roles.includes(role));
};

const hasTenantPermission = (item: NavItem, user: User): boolean => {
  if (!item.tenants || item.tenants.length === 0) return true;
  
  // Wildcard allows all tenants
  if (item.tenants.includes('*')) return true;
  
  // Check if user's tenant is allowed
  return item.tenants.includes(user.tenant_id);
};

const hasSpecificPermission = (item: NavItem, user: User): boolean => {
  if (!user.permissions || !item.id) return true;
  
  // Example: Check for specific permission like 'menu.dashboard.view'
  const requiredPermission = `menu.${item.id}.view`;
  return user.permissions.includes(requiredPermission);
};

const hasPermission = (item: NavItem, user: User, _tenant: Tenant): boolean => {
  return hasRolePermission(item, user) && 
         hasTenantPermission(item, user) && 
         hasSpecificPermission(item, user);
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
    // Load menu config - try from data attribute first, then fetch
    const mountEl = document.getElementById('header-mount');
    const menuItemsAttr = mountEl?.dataset.menuItems;
    
    if (menuItemsAttr) {
      const menuConfig = JSON.parse(menuItemsAttr);
      if (Array.isArray(menuConfig) && menuConfig.length > 0) {
        return filterMenu(menuConfig, user, tenant);
      }
    }
    
    // Fallback: fetch from public directory
    const menuResponse = await fetch('/config/menu.json');
    if (!menuResponse.ok) {
      console.error('Failed to load menu.json');
      return [];
    }
    const menuConfig = await menuResponse.json();
    return filterMenu(menuConfig, user, tenant);
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
