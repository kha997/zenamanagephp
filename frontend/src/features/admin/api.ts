import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

/**
 * Admin Role interface
 */
export interface AdminRole {
  id: string | number;
  name: string;
  slug: string;
  scope?: string;
  description?: string;
  is_active?: boolean;
  is_system?: boolean;
  permissions: string[];
  created_at?: string;
  updated_at?: string;
}

/**
 * Admin User interface
 */
export interface AdminUser {
  id: string | number;
  name: string;
  email: string;
  tenant_id?: string | number;
  is_active?: boolean;
  roles: Array<{
    id: string | number;
    name: string;
    slug: string;
  }>;
  created_at?: string;
}

/**
 * Admin Permission Definition
 */
export interface AdminPermissionDefinition {
  key: string;
  label: string;
  description?: string;
}

/**
 * Admin Permission Group
 */
export interface AdminPermissionGroup {
  key: string;
  label: string;
  permissions: AdminPermissionDefinition[];
}

/**
 * Admin Permissions Catalog Response
 */
export interface AdminPermissionsCatalogResponse {
  groups: AdminPermissionGroup[];
}

/**
 * Cost Approval Policy interface
 * Round 239: Cost Approval Policies
 */
export interface CostApprovalPolicy {
  tenant_id: string;
  co_dual_threshold_amount?: number | null;
  certificate_dual_threshold_amount?: number | null;
  payment_dual_threshold_amount?: number | null;
  over_budget_threshold_percent?: number | null;
}

/**
 * Admin Roles & Permissions API
 * Round 233: Admin UI for Roles & Permissions
 */
export const adminRolesPermissionsApi = {
  /**
   * Get all roles with their assigned permissions
   */
  getRoles: async (): Promise<AdminRole[]> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: AdminRole[] }>('/v1/admin/roles');
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get all available permissions grouped by category
   */
  getPermissionsCatalog: async (): Promise<AdminPermissionsCatalogResponse> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: AdminPermissionsCatalogResponse }>(
        '/v1/admin/permissions'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update permissions for a role
   */
  updateRolePermissions: async (
    roleId: string | number,
    permissions: string[]
  ): Promise<AdminRole> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: AdminRole }>(
        `/v1/admin/roles/${roleId}/permissions`,
        { permissions }
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create a new role
   * Round 234: Role CRUD
   */
  createRole: async (data: { name: string; description?: string; scope?: string }): Promise<AdminRole> => {
    try {
      const response = await apiClient.post<{ ok: boolean; data: AdminRole }>('/v1/admin/roles', data);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update a role
   * Round 234: Role CRUD
   */
  updateRole: async (
    roleId: string | number,
    data: { name?: string; description?: string; scope?: string }
  ): Promise<AdminRole> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: AdminRole }>(
        `/v1/admin/roles/${roleId}`,
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete a role
   * Round 234: Role CRUD
   */
  deleteRole: async (roleId: string | number): Promise<void> => {
    try {
      await apiClient.delete(`/v1/admin/roles/${roleId}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get all users with their roles
   * Round 234: User-Role Assignment
   */
  getUsers: async (params?: { search?: string; tenant_id?: string | number }): Promise<AdminUser[]> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: AdminUser[] }>('/v1/admin/users', { params });
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update user roles
   * Round 234: User-Role Assignment
   */
  updateUserRoles: async (
    userId: string | number,
    roles: Array<string | number>
  ): Promise<AdminUser> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: AdminUser }>(
        `/v1/admin/users/${userId}/roles`,
        { roles }
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Admin Cost Approval Policy API
 * Round 239: Cost Approval Policies
 */
export const adminCostApprovalPolicyApi = {
  /**
   * Get current cost approval policy for tenant
   */
  getCostApprovalPolicy: async (): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update cost approval policy for tenant
   */
  updateCostApprovalPolicy: async (data: Partial<CostApprovalPolicy>): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy',
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Admin Audit Log interface
 * Round 235: Audit Log Framework
 */
export interface AdminAuditLog {
  id: string;
  user: {
    id: string;
    name: string;
    email: string;
  } | null;
  action: string;
  entity_type: string | null;
  entity_id: string | null;
  project_id: string | null;
  payload_before: Record<string, any> | null;
  payload_after: Record<string, any> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

/**
 * Admin Audit Logs API Response
 */
export interface AdminAuditLogsResponse {
  data: AdminAuditLog[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Admin Audit Logs API
 * Round 235: Audit Log Framework
 */
export const adminAuditLogsApi = {
  /**
   * Get audit logs with filtering
   */
  getAuditLogs: async (params?: {
    user_id?: string;
    action?: string;
    entity_type?: string;
    entity_id?: string;
    project_id?: string;
    date_from?: string;
    date_to?: string;
    module?: 'RBAC' | 'Cost' | 'Documents' | 'Tasks' | 'All';
    search?: string;
    per_page?: number;
    page?: number;
  }): Promise<AdminAuditLogsResponse> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: AdminAuditLogsResponse }>(
        '/v1/admin/audit-logs',
        { params }
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Admin Cost Approval Policy API
 * Round 239: Cost Approval Policies
 */
export const adminCostApprovalPolicyApi = {
  /**
   * Get current cost approval policy for tenant
   */
  getCostApprovalPolicy: async (): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update cost approval policy for tenant
   */
  updateCostApprovalPolicy: async (data: Partial<CostApprovalPolicy>): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy',
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Role Profile interface
 * Round 244: Role Access Profiles
 */
export interface RoleProfile {
  id: string;
  name: string;
  description?: string;
  roles: Array<{
    id: string;
    name: string;
  }>;
  role_ids: string[]; // Original IDs/slugs from profile
  is_active: boolean;
  tenant_id: string;
  created_at?: string;
  updated_at?: string;
}

/**
 * Admin Role Profiles API
 * Round 244: Role Access Profiles
 */
export const adminRoleProfilesApi = {
  /**
   * Get all role profiles
   */
  getRoleProfiles: async (): Promise<RoleProfile[]> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: RoleProfile[] }>(
        '/v1/admin/role-profiles'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create a new role profile
   */
  createRoleProfile: async (data: {
    name: string;
    description?: string;
    roles: string[];
    is_active?: boolean;
  }): Promise<RoleProfile> => {
    try {
      const response = await apiClient.post<{ ok: boolean; data: RoleProfile }>(
        '/v1/admin/role-profiles',
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update a role profile
   */
  updateRoleProfile: async (
    profileId: string,
    data: {
      name?: string;
      description?: string;
      roles?: string[];
      is_active?: boolean;
    }
  ): Promise<RoleProfile> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: RoleProfile }>(
        `/v1/admin/role-profiles/${profileId}`,
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete a role profile
   */
  deleteRoleProfile: async (profileId: string): Promise<void> => {
    try {
      await apiClient.delete(`/v1/admin/role-profiles/${profileId}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Assign a profile to a user
   */
  assignProfileToUser: async (
    userId: string | number,
    profileId: string
  ): Promise<AdminUser> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: AdminUser }>(
        `/v1/admin/users/${userId}/assign-profile`,
        { profile_id: profileId }
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Permission Inspector Response
 * Round 236: Permission Inspector
 */
export interface PermissionInspectorResponse {
  user: {
    id: string;
    name: string;
    email: string;
  };
  roles: Array<{
    name: string;
    permissions: string[];
  }>;
  permissions: Array<{
    key: string;
    granted: boolean;
    sources: string[];
  }>;
  missing_permissions: string[];
}

/**
 * Admin Permission Inspector API
 * Round 236: Permission Inspector
 */
export const adminPermissionInspectorApi = {
  /**
   * Inspect user permissions
   */
  inspectPermissions: async (
    userId: string | number,
    filters?: {
      filter?: 'cost' | 'document' | 'task' | 'project' | 'user' | 'system';
      project_id?: string | number;
    }
  ): Promise<PermissionInspectorResponse> => {
    try {
      const params: Record<string, string> = {
        user_id: String(userId),
      };
      
      if (filters?.filter) {
        params.filter = filters.filter;
      }
      
      if (filters?.project_id) {
        params.project_id = String(filters.project_id);
      }
      
      const response = await apiClient.get<{ ok: boolean; data: PermissionInspectorResponse }>(
        '/v1/admin/permissions/inspect',
        { params }
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Admin Cost Approval Policy API
 * Round 239: Cost Approval Policies
 */
export const adminCostApprovalPolicyApi = {
  /**
   * Get current cost approval policy for tenant
   */
  getCostApprovalPolicy: async (): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update cost approval policy for tenant
   */
  updateCostApprovalPolicy: async (data: Partial<CostApprovalPolicy>): Promise<CostApprovalPolicy> => {
    try {
      const response = await apiClient.put<{ ok: boolean; data: CostApprovalPolicy }>(
        '/v1/admin/cost-approval-policy',
        data
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Cost Governance Summary interface
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export interface CostGovernanceSummary {
  summary: {
    change_orders: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    certificates: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
    payments: {
      total: number;
      pending_approval: number;
      awaiting_dual_approval: number;
      blocked_by_policy: number;
    };
  };
  top_projects_by_risk: Array<{
    project_id: string;
    project_name: string;
    pending_co: number;
    pending_certificates: number;
    pending_payments: number;
    awaiting_dual_approval: number;
    policy_blocked_items: number;
    over_budget_percent: number | null;
  }>;
  recent_policy_events: Array<{
    type: 'co' | 'certificate' | 'payment';
    entity_id: string;
    project_id: string;
    project_name: string | null;
    code: string;
    amount: number | null;
    threshold: number | null;
    created_at: string;
  }>;
}

/**
 * Admin Cost Governance Overview API
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const adminCostGovernanceOverviewApi = {
  /**
   * Get cost governance overview
   */
  getCostGovernanceOverview: async (): Promise<CostGovernanceSummary> => {
    try {
      const response = await apiClient.get<{ ok: boolean; data: CostGovernanceSummary }>(
        '/v1/admin/cost-governance-overview'
      );
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};
