// Admin Users API types and interfaces
export interface AdminUser {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  tenant_id: number;
  tenant_name: string;
  roles: AdminRole[];
  status: 'active' | 'inactive' | 'suspended';
  last_login_at?: string;
  created_at: string;
  updated_at: string;
}

export interface AdminRole {
  id: number;
  name: string;
  code: string;
  description?: string;
}

export interface AdminUsersResponse {
  data: AdminUser[];
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

export interface AdminUsersFilters {
  search?: string;
  tenant_id?: number;
  role_id?: number;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface CreateAdminUserRequest {
  name: string;
  email: string;
  password: string;
  tenant_id: number;
  role_ids: number[];
}

export interface UpdateAdminUserRequest {
  name?: string;
  email?: string;
  password?: string;
  tenant_id?: number;
  role_ids?: number[];
  status?: 'active' | 'inactive' | 'suspended';
}

