import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminRolesPermissionsApi, adminAuditLogsApi, adminPermissionInspectorApi, adminCostApprovalPolicyApi, adminCostGovernanceOverviewApi, adminRoleProfilesApi, type AdminRole, type CostApprovalPolicy, type CostGovernanceSummary, type RoleProfile } from './api';

/**
 * Query Keys for Admin Roles & Permissions
 */
export const adminRolesPermissionsKeys = {
  all: ['admin', 'roles-permissions'] as const,
  roles: () => [...adminRolesPermissionsKeys.all, 'roles'] as const,
  permissions: () => [...adminRolesPermissionsKeys.all, 'permissions'] as const,
};

/**
 * Hook to get all roles with their permissions
 */
export const useAdminRoles = () => {
  return useQuery({
    queryKey: adminRolesPermissionsKeys.roles(),
    queryFn: () => adminRolesPermissionsApi.getRoles(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

/**
 * Hook to get permissions catalog (grouped by category)
 */
export const useAdminPermissionsCatalog = () => {
  return useQuery({
    queryKey: adminRolesPermissionsKeys.permissions(),
    queryFn: () => adminRolesPermissionsApi.getPermissionsCatalog(),
    staleTime: 300_000, // 5 minutes (permissions catalog rarely changes)
    retry: 1,
  });
};

/**
 * Hook to update role permissions
 */
export const useUpdateAdminRolePermissions = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ roleId, permissions }: { roleId: string | number; permissions: string[] }) =>
      adminRolesPermissionsApi.updateRolePermissions(roleId, permissions),
    onSuccess: () => {
      // Invalidate roles query to refetch updated data
      queryClient.invalidateQueries({ queryKey: adminRolesPermissionsKeys.roles() });
    },
  });
};

/**
 * Hook to create a role
 * Round 234: Role CRUD
 */
export const useCreateRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: { name: string; description?: string; scope?: string }) =>
      adminRolesPermissionsApi.createRole(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRolesPermissionsKeys.roles() });
    },
  });
};

/**
 * Hook to update a role
 * Round 234: Role CRUD
 */
export const useUpdateRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ roleId, data }: { roleId: string | number; data: { name?: string; description?: string; scope?: string } }) =>
      adminRolesPermissionsApi.updateRole(roleId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRolesPermissionsKeys.roles() });
    },
  });
};

/**
 * Hook to delete a role
 * Round 234: Role CRUD
 */
export const useDeleteRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (roleId: string | number) =>
      adminRolesPermissionsApi.deleteRole(roleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRolesPermissionsKeys.roles() });
    },
  });
};

/**
 * Hook to get all users with their roles
 * Round 234: User-Role Assignment
 */
export const useUsers = (params?: { search?: string; tenant_id?: string | number }) => {
  return useQuery({
    queryKey: ['admin', 'users', params],
    queryFn: () => adminRolesPermissionsApi.getUsers(params),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

/**
 * Hook to update user roles
 * Round 234: User-Role Assignment
 */
export const useUpdateUserRoles = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ userId, roles }: { userId: string | number; roles: Array<string | number> }) =>
      adminRolesPermissionsApi.updateUserRoles(userId, roles),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
};

/**
 * Hook to get audit logs with filtering
 * Round 235: Audit Log Framework
 */
export const useAuditLogs = (filters?: {
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
}) => {
  return useQuery({
    queryKey: ['admin', 'audit-logs', filters],
    queryFn: () => adminAuditLogsApi.getAuditLogs(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

/**
 * Hook to inspect user permissions
 * Round 236: Permission Inspector
 */
export const usePermissionInspector = (
  userId: string | number | null,
  filters?: {
    filter?: 'cost' | 'document' | 'task' | 'project' | 'user' | 'system';
    project_id?: string | number;
  }
) => {
  return useQuery({
    queryKey: ['admin', 'permission-inspector', userId, filters],
    queryFn: () => {
      if (!userId) {
        throw new Error('User ID is required');
      }
      return adminPermissionInspectorApi.inspectPermissions(userId, filters);
    },
    enabled: !!userId,
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

/**
 * Hook to get cost governance overview
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const useCostGovernanceOverview = () => {
  return useQuery<CostGovernanceSummary>({
    queryKey: ['admin', 'cost-governance-overview'],
    queryFn: () => adminCostGovernanceOverviewApi.getCostGovernanceOverview(),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

/**
 * Query Keys for Admin Role Profiles
 * Round 244: Role Access Profiles
 */
export const adminRoleProfilesKeys = {
  all: ['admin', 'role-profiles'] as const,
  profiles: () => [...adminRoleProfilesKeys.all, 'profiles'] as const,
};

/**
 * Hook to get all role profiles
 * Round 244: Role Access Profiles
 */
export const useRoleProfiles = () => {
  return useQuery({
    queryKey: adminRoleProfilesKeys.profiles(),
    queryFn: () => adminRoleProfilesApi.getRoleProfiles(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

/**
 * Hook to create a role profile
 * Round 244: Role Access Profiles
 */
export const useCreateRoleProfile = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: {
      name: string;
      description?: string;
      roles: string[];
      is_active?: boolean;
    }) => adminRoleProfilesApi.createRoleProfile(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRoleProfilesKeys.profiles() });
    },
  });
};

/**
 * Hook to update a role profile
 * Round 244: Role Access Profiles
 */
export const useUpdateRoleProfile = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({
      profileId,
      data,
    }: {
      profileId: string;
      data: {
        name?: string;
        description?: string;
        roles?: string[];
        is_active?: boolean;
      };
    }) => adminRoleProfilesApi.updateRoleProfile(profileId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRoleProfilesKeys.profiles() });
    },
  });
};

/**
 * Hook to delete a role profile
 * Round 244: Role Access Profiles
 */
export const useDeleteRoleProfile = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (profileId: string) => adminRoleProfilesApi.deleteRoleProfile(profileId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminRoleProfilesKeys.profiles() });
    },
  });
};

/**
 * Hook to assign a profile to a user
 * Round 244: Role Access Profiles
 */
export const useAssignProfileToUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({
      userId,
      profileId,
    }: {
      userId: string | number;
      profileId: string;
    }) => adminRoleProfilesApi.assignProfileToUser(userId, profileId),
    onSuccess: () => {
      // Invalidate both profiles and users queries
      queryClient.invalidateQueries({ queryKey: adminRoleProfilesKeys.profiles() });
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
};
