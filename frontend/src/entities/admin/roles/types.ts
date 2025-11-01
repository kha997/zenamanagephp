// Admin Roles API types and interfaces
export interface AdminRole {
  id: number;
  name: string;
  code: string;
  description?: string;
  permissions: AdminPermission[];
  user_count: number;
  created_at: string;
  updated_at: string;
}

export interface AdminPermission {
  id: number;
  name: string;
  code: string;
  resource: string;
  action: string;
  description?: string;
}

export interface AdminRolesResponse {
  data: AdminRole[];
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

export interface AdminRolesFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CreateAdminRoleRequest {
  name: string;
  code: string;
  description?: string;
  permission_ids: number[];
}

export interface UpdateAdminRoleRequest {
  name?: string;
  code?: string;
  description?: string;
  permission_ids?: number[];
}

export interface AssignRoleRequest {
  user_id: number;
  role_id: number;
}

export interface BulkAssignRoleRequest {
  user_ids: number[];
  role_id: number;
}
