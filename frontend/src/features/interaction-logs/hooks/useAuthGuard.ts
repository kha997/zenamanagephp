/**
 * Custom hook để kiểm tra quyền truy cập cho Interaction Logs
 * Sử dụng JWT token và RBAC system
 */
import { useMemo } from 'react';
import { useAuthStore } from '../../../store/authStore';
import { InteractionLog } from '../types/interactionLog';

// Định nghĩa các permissions cho Interaction Logs
export const INTERACTION_LOG_PERMISSIONS = {
  VIEW: 'interaction_log.view',
  CREATE: 'interaction_log.create', 
  UPDATE: 'interaction_log.update',
  DELETE: 'interaction_log.delete',
  APPROVE: 'interaction_log.approve',
  VIEW_CLIENT: 'interaction_log.view_client',
} as const;

type PermissionKey = keyof typeof INTERACTION_LOG_PERMISSIONS;
type Permission = typeof INTERACTION_LOG_PERMISSIONS[PermissionKey];

/**
 * Hook để kiểm tra quyền truy cập cho Interaction Logs
 */
export const useAuthGuard = () => {
  const { user, permissions, currentProject } = useAuthStore();

  /**
   * Kiểm tra user có permission cụ thể không
   */
  const hasPermission = useMemo(() => {
    return (permission: Permission, projectId?: number) => {
      if (!user || !permissions) return false;

      // Nếu có projectId, kiểm tra project-specific permissions trước
      if (projectId && currentProject?.id === projectId) {
        const projectPermissions = permissions.project || [];
        if (projectPermissions.includes(permission)) {
          return true;
        }
      }

      // Kiểm tra system-wide permissions
      const systemPermissions = permissions.system || [];
      return systemPermissions.includes(permission);
    };
  }, [user, permissions, currentProject]);

  /**
   * Kiểm tra user có thể xem interaction log không
   */
  const canView = useMemo(() => {
    return (projectId?: number) => {
      return hasPermission(INTERACTION_LOG_PERMISSIONS.VIEW, projectId);
    };
  }, [hasPermission]);

  /**
   * Kiểm tra user có thể tạo interaction log không
   */
  const canCreate = useMemo(() => {
    return (projectId?: number) => {
      return hasPermission(INTERACTION_LOG_PERMISSIONS.CREATE, projectId);
    };
  }, [hasPermission]);

  /**
   * Kiểm tra user có thể cập nhật interaction log không
   */
  const canUpdate = useMemo(() => {
    return (log: InteractionLog) => {
      // Kiểm tra permission cơ bản
      if (!hasPermission(INTERACTION_LOG_PERMISSIONS.UPDATE, log.project_id)) {
        return false;
      }

      // Chỉ cho phép update nếu là người tạo hoặc có quyền admin
      return log.created_by === user?.id || user?.roles?.includes('admin');
    };
  }, [hasPermission, user]);

  /**
   * Kiểm tra user có thể xóa interaction log không
   */
  const canDelete = useMemo(() => {
    return (log: InteractionLog) => {
      // Kiểm tra permission cơ bản
      if (!hasPermission(INTERACTION_LOG_PERMISSIONS.DELETE, log.project_id)) {
        return false;
      }

      // Chỉ cho phép delete nếu là người tạo hoặc có quyền admin
      return log.created_by === user?.id || user?.roles?.includes('admin');
    };
  }, [hasPermission, user]);

  /**
   * Kiểm tra user có thể approve cho client không
   */
  const canApprove = useMemo(() => {
    return (projectId?: number) => {
      return hasPermission(INTERACTION_LOG_PERMISSIONS.APPROVE, projectId);
    };
  }, [hasPermission]);

  /**
   * Kiểm tra user có thể xem logs dành cho client không
   */
  const canViewClient = useMemo(() => {
    return (projectId?: number) => {
      return hasPermission(INTERACTION_LOG_PERMISSIONS.VIEW_CLIENT, projectId);
    };
  }, [hasPermission]);

  /**
   * Kiểm tra user có quyền truy cập project không
   */
  const canAccessProject = useMemo(() => {
    return (projectId: number) => {
      if (!user || !currentProject) return false;
      
      // Kiểm tra user có trong project không
      return currentProject.id === projectId || user.roles?.includes('admin');
    };
  }, [user, currentProject]);

  return {
    user,
    currentProject,
    hasPermission,
    canView,
    canCreate,
    canUpdate,
    canDelete,
    canApprove,
    canViewClient,
    canAccessProject,
    isAuthenticated: !!user,
    isAdmin: user?.roles?.includes('admin') || false,
  };
};

/**
 * Hook để guard component dựa trên permissions
 */
export const usePermissionGuard = (permission: Permission, projectId?: number) => {
  const { hasPermission } = useAuthGuard();
  
  return {
    hasAccess: hasPermission(permission, projectId),
    permission,
    projectId,
  };
};