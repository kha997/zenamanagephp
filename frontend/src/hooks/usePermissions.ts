/**
 * Hook quản lý quyền hạn và vai trò người dùng (RBAC)
 * Kiểm tra quyền truy cập dựa trên user roles và permissions
 */
import { useMemo } from 'react';
import { useAuthStore } from '../store/auth';
import { Permission } from '../lib/types';
import { hasPermission, hasRole } from '../lib/utils/auth';

export const usePermissions = () => {
  const { user } = useAuthStore();

  const userRoles = useMemo(() => {
    return user?.roles || [];
  }, [user?.roles]);

  const userPermissions = useMemo(() => {
    if (!user?.roles) return [];
    
    // Flatten all permissions from all roles
    const permissions: Permission[] = [];
    user.roles.forEach(role => {
      if (role.permissions) {
        permissions.push(...role.permissions);
      }
    });
    
    // Remove duplicates based on permission code
    return permissions.filter((permission, index, self) => 
      index === self.findIndex(p => p.code === permission.code)
    );
  }, [user?.roles]);

  const checkPermission = (permissionCode: string, projectId?: number) => {
    void projectId;
    return hasPermission(permissionCode, user ?? undefined);
  };

  const checkRole = (roleCode: string, projectId?: number) => {
    void projectId;
    return hasRole(roleCode, user ?? undefined);
  };

  const canAccess = (resource: string, action: string, projectId?: number) => {
    const permissionCode = `${resource}.${action}`;
    return checkPermission(permissionCode, projectId);
  };

  // Specific permission checks for common actions
  const canCreateProject = () => checkPermission('project.create');
  const canEditProject = (projectId?: number) => checkPermission('project.edit', projectId);
  const canDeleteProject = (projectId?: number) => checkPermission('project.delete', projectId);
  
  const canCreateTask = (projectId?: number) => checkPermission('task.create', projectId);
  const canEditTask = (projectId?: number) => checkPermission('task.edit', projectId);
  const canDeleteTask = (projectId?: number) => checkPermission('task.delete', projectId);
  
  const canManageUsers = () => checkPermission('user.manage');
  const canManageRoles = () => checkPermission('role.manage');
  
  const isAdmin = () => checkRole('admin');
  const isProjectManager = (projectId?: number) => checkRole('project_manager', projectId);
  const isTeamLead = (projectId?: number) => checkRole('team_lead', projectId);

  return {
    // Raw data
    userRoles,
    userPermissions,
    
    // Generic checkers
    checkPermission,
    checkRole,
    canAccess,
    
    // Specific permission checks
    canCreateProject,
    canEditProject,
    canDeleteProject,
    canCreateTask,
    canEditTask,
    canDeleteTask,
    canManageUsers,
    canManageRoles,
    
    // Role checks
    isAdmin,
    isProjectManager,
    isTeamLead
  };
};
