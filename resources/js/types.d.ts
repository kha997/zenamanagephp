// Type definitions for header integration

interface HeaderConfig {
  user: {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    roles: string[];
    tenant_id: string;
    permissions?: string[];
  } | null;
  tenant: {
    id: string;
    name: string;
    type?: string;
  } | null;
  menuItems: any[];
  notifications: any[];
  unreadCount: number;
  breadcrumbs: any[];
  logoutUrl: string;
  csrfToken: string;
}

interface Window {
  initHeader: (config: HeaderConfig) => Promise<void>;
}

