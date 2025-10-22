// Admin Tenants API types and interfaces
export interface AdminTenant {
  id: number;
  name: string;
  domain: string;
  subdomain: string;
  status: 'active' | 'inactive' | 'suspended' | 'trial';
  plan: 'basic' | 'professional' | 'enterprise' | 'trial';
  user_count: number;
  project_count: number;
  storage_used: number;
  storage_limit: number;
  created_at: string;
  updated_at: string;
  trial_ends_at?: string;
  billing_email?: string;
  settings: TenantSettings;
}

export interface TenantSettings {
  max_users: number;
  max_projects: number;
  storage_limit_gb: number;
  features: string[];
  custom_domain?: string;
  ssl_enabled: boolean;
}

export interface AdminTenantsResponse {
  data: AdminTenant[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev?: string;
    next?: string;
  };
}

export interface AdminTenantsFilters {
  search?: string;
  status?: string;
  plan?: string;
  page?: number;
  per_page?: number;
}

export interface CreateAdminTenantRequest {
  name: string;
  domain: string;
  subdomain: string;
  plan: 'basic' | 'professional' | 'enterprise' | 'trial';
  billing_email?: string;
  settings: Partial<TenantSettings>;
}

export interface UpdateAdminTenantRequest {
  name?: string;
  domain?: string;
  subdomain?: string;
  status?: 'active' | 'inactive' | 'suspended' | 'trial';
  plan?: 'basic' | 'professional' | 'enterprise' | 'trial';
  billing_email?: string;
  settings?: Partial<TenantSettings>;
}

export interface TenantStats {
  total_users: number;
  active_users: number;
  total_projects: number;
  active_projects: number;
  storage_used_gb: number;
  storage_limit_gb: number;
  last_activity: string;
}
