import { useState, useEffect, useCallback } from 'react';
import { useAuth } from './useAuth';

interface RolePermissions {
  dashboard: string[];
  widgets: string[];
  projects: string[];
  users: string[];
  reports: string[];
  settings: string[];
}

interface RoleConfig {
  name: string;
  description: string;
  default_widgets: string[];
  widget_categories: string[];
  data_access: string;
  project_access: string;
  customization_level: string;
  priority_metrics: string[];
  alert_types: string[];
  dashboard_layout: string;
}

interface RoleBasedPermissions {
  permissions: RolePermissions;
  roleConfig: RoleConfig;
  userRole: string;
  isLoading: boolean;
  error: string | null;
  hasPermission: (resource: string, action: string) => boolean;
  canAccessWidget: (widgetCode: string) => boolean;
  canCustomizeDashboard: () => boolean;
  canViewProject: (projectId: string) => boolean;
  canEditProject: (projectId: string) => boolean;
  canViewReports: () => boolean;
  canExportData: () => boolean;
  canManageUsers: () => boolean;
  canAccessSettings: () => boolean;
  refreshPermissions: () => Promise<void>;
}

export const useRoleBasedPermissions = (): RoleBasedPermissions => {
  const { user } = useAuth();
  const [permissions, setPermissions] = useState<RolePermissions>({
    dashboard: [],
    widgets: [],
    projects: [],
    users: [],
    reports: [],
    settings: []
  });
  const [roleConfig, setRoleConfig] = useState<RoleConfig>({
    name: '',
    description: '',
    default_widgets: [],
    widget_categories: [],
    data_access: '',
    project_access: '',
    customization_level: '',
    priority_metrics: [],
    alert_types: [],
    dashboard_layout: ''
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const userRole = user?.role || '';

  // Load permissions and role config
  useEffect(() => {
    if (user?.role) {
      loadPermissions();
      loadRoleConfig();
    }
  }, [user?.role]);

  const loadPermissions = async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await fetch('/api/v1/dashboard/role-based/permissions', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (!response.ok) {
        throw new Error('Failed to load permissions');
      }

      const result = await response.json();
      if (result.success) {
        setPermissions(result.data.permissions);
      } else {
        throw new Error(result.message);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load permissions');
      console.error('Failed to load permissions:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const loadRoleConfig = async () => {
    try {
      const response = await fetch('/api/v1/dashboard/role-based/role-config', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (!response.ok) {
        throw new Error('Failed to load role config');
      }

      const result = await response.json();
      if (result.success) {
        setRoleConfig(result.data.role_config);
      }
    } catch (err) {
      console.error('Failed to load role config:', err);
    }
  };

  const refreshPermissions = useCallback(async () => {
    await Promise.all([loadPermissions(), loadRoleConfig()]);
  }, []);

  // Permission checking functions
  const hasPermission = useCallback((resource: string, action: string): boolean => {
    if (!permissions[resource as keyof RolePermissions]) {
      return false;
    }
    return permissions[resource as keyof RolePermissions].includes(action);
  }, [permissions]);

  const canAccessWidget = useCallback((widgetCode: string): boolean => {
    return roleConfig.default_widgets.includes(widgetCode);
  }, [roleConfig.default_widgets]);

  const canCustomizeDashboard = useCallback((): boolean => {
    return ['full', 'limited'].includes(roleConfig.customization_level);
  }, [roleConfig.customization_level]);

  const canViewProject = useCallback((projectId: string): boolean => {
    // This would typically check if user has access to specific project
    // For now, return true for assigned projects, false for others
    return hasPermission('projects', 'view_assigned') || hasPermission('projects', 'view_all');
  }, [hasPermission]);

  const canEditProject = useCallback((projectId: string): boolean => {
    return hasPermission('projects', 'edit_assigned') || hasPermission('projects', 'edit_all');
  }, [hasPermission]);

  const canViewReports = useCallback((): boolean => {
    return hasPermission('reports', 'view_assigned') || hasPermission('reports', 'view_all');
  }, [hasPermission]);

  const canExportData = useCallback((): boolean => {
    return hasPermission('reports', 'export_assigned') || hasPermission('reports', 'export_all');
  }, [hasPermission]);

  const canManageUsers = useCallback((): boolean => {
    return hasPermission('users', 'edit_team') || hasPermission('users', 'edit_all');
  }, [hasPermission]);

  const canAccessSettings = useCallback((): boolean => {
    return hasPermission('settings', 'view_project') || hasPermission('settings', 'view_all');
  }, [hasPermission]);

  return {
    permissions,
    roleConfig,
    userRole,
    isLoading,
    error,
    hasPermission,
    canAccessWidget,
    canCustomizeDashboard,
    canViewProject,
    canEditProject,
    canViewReports,
    canExportData,
    canManageUsers,
    canAccessSettings,
    refreshPermissions
  };
};

// Role-specific utility functions
export const getRoleColor = (role: string): string => {
  const roleColors: { [key: string]: string } = {
    'system_admin': 'purple',
    'project_manager': 'blue',
    'design_lead': 'green',
    'site_engineer': 'orange',
    'qc_inspector': 'red',
    'client_rep': 'teal',
    'subcontractor_lead': 'gray'
  };
  return roleColors[role] || 'gray';
};

export const getRoleIcon = (role: string): string => {
  const roleIcons: { [key: string]: string } = {
    'system_admin': 'crown',
    'project_manager': 'user-tie',
    'design_lead': 'pencil',
    'site_engineer': 'hard-hat',
    'qc_inspector': 'shield-check',
    'client_rep': 'user-check',
    'subcontractor_lead': 'users'
  };
  return roleIcons[role] || 'user';
};

export const getRoleDisplayName = (role: string): string => {
  const roleNames: { [key: string]: string } = {
    'system_admin': 'System Administrator',
    'project_manager': 'Project Manager',
    'design_lead': 'Design Lead',
    'site_engineer': 'Site Engineer',
    'qc_inspector': 'QC Inspector',
    'client_rep': 'Client Representative',
    'subcontractor_lead': 'Subcontractor Lead'
  };
  return roleNames[role] || role.replace('_', ' ');
};

export const getRoleDescription = (role: string): string => {
  const roleDescriptions: { [key: string]: string } = {
    'system_admin': 'Full system access and management capabilities',
    'project_manager': 'Comprehensive project management and oversight',
    'design_lead': 'Design coordination and technical oversight',
    'site_engineer': 'Field operations and site management',
    'qc_inspector': 'Quality control and inspection management',
    'client_rep': 'Client communication and project oversight',
    'subcontractor_lead': 'Subcontractor coordination and management'
  };
  return roleDescriptions[role] || 'User role';
};

// Widget category permissions
export const getWidgetCategoryPermissions = (role: string): string[] => {
  const categoryPermissions: { [key: string]: string[] } = {
    'system_admin': ['system', 'management', 'monitoring', 'overview', 'tasks', 'communication', 'quality', 'financial', 'safety'],
    'project_manager': ['overview', 'tasks', 'communication', 'quality', 'financial', 'safety'],
    'design_lead': ['design', 'communication', 'quality'],
    'site_engineer': ['tasks', 'quality', 'safety', 'field'],
    'qc_inspector': ['quality', 'inspection', 'compliance'],
    'client_rep': ['overview', 'communication', 'reporting'],
    'subcontractor_lead': ['subcontractor', 'financial', 'quality']
  };
  return categoryPermissions[role] || [];
};

// Data access levels
export const getDataAccessLevel = (role: string): string => {
  const dataAccess: { [key: string]: string } = {
    'system_admin': 'all',
    'project_manager': 'project_wide',
    'design_lead': 'design_related',
    'site_engineer': 'site_related',
    'qc_inspector': 'quality_related',
    'client_rep': 'client_view',
    'subcontractor_lead': 'subcontractor_related'
  };
  return dataAccess[role] || 'limited';
};

// Project access levels
export const getProjectAccessLevel = (role: string): string => {
  const projectAccess: { [key: string]: string } = {
    'system_admin': 'all',
    'project_manager': 'assigned',
    'design_lead': 'assigned',
    'site_engineer': 'assigned',
    'qc_inspector': 'assigned',
    'client_rep': 'assigned',
    'subcontractor_lead': 'assigned'
  };
  return projectAccess[role] || 'none';
};

// Customization levels
export const getCustomizationLevel = (role: string): string => {
  const customizationLevels: { [key: string]: string } = {
    'system_admin': 'full',
    'project_manager': 'full',
    'design_lead': 'limited',
    'site_engineer': 'limited',
    'qc_inspector': 'read_only',
    'client_rep': 'read_only',
    'subcontractor_lead': 'limited'
  };
  return customizationLevels[role] || 'read_only';
};

// Priority metrics for each role
export const getPriorityMetrics = (role: string): string[] => {
  const priorityMetrics: { [key: string]: string[] } = {
    'system_admin': ['system_uptime', 'user_count', 'storage_usage'],
    'project_manager': ['project_progress', 'budget_variance', 'schedule_adherence'],
    'design_lead': ['design_completion', 'review_cycle_time', 'issue_resolution'],
    'site_engineer': ['daily_progress', 'safety_incidents', 'quality_issues'],
    'qc_inspector': ['inspection_completion', 'defect_rate', 'ncr_resolution'],
    'client_rep': ['project_progress', 'budget_status', 'quality_score'],
    'subcontractor_lead': ['work_completion', 'payment_status', 'quality_score']
  };
  return priorityMetrics[role] || [];
};

// Alert types for each role
export const getAlertTypes = (role: string): string[] => {
  const alertTypes: { [key: string]: string[] } = {
    'system_admin': ['system', 'security', 'performance'],
    'project_manager': ['project', 'budget', 'schedule', 'quality'],
    'design_lead': ['design', 'review', 'coordination'],
    'site_engineer': ['safety', 'quality', 'weather', 'equipment'],
    'qc_inspector': ['quality', 'inspection', 'compliance'],
    'client_rep': ['milestone', 'budget', 'quality'],
    'subcontractor_lead': ['payment', 'quality', 'safety']
  };
  return alertTypes[role] || [];
};

// Default widgets for each role
export const getDefaultWidgets = (role: string): string[] => {
  const defaultWidgets: { [key: string]: string[] } = {
    'system_admin': ['system_health', 'user_management', 'tenant_overview', 'system_metrics', 'audit_logs', 'backup_status'],
    'project_manager': ['project_overview', 'task_progress', 'rfi_status', 'budget_tracking', 'schedule_timeline', 'team_performance', 'quality_metrics', 'safety_summary', 'change_requests'],
    'design_lead': ['design_progress', 'drawing_status', 'submittal_tracking', 'design_reviews', 'technical_issues', 'coordination_log'],
    'site_engineer': ['daily_tasks', 'site_diary', 'inspection_checklist', 'weather_forecast', 'equipment_status', 'safety_alerts', 'progress_photos', 'manpower_tracking'],
    'qc_inspector': ['inspection_schedule', 'ncr_tracking', 'quality_metrics', 'defect_analysis', 'corrective_actions', 'compliance_status', 'inspection_reports', 'quality_trends'],
    'client_rep': ['project_summary', 'progress_report', 'milestone_status', 'budget_summary', 'quality_summary', 'schedule_status', 'client_communications', 'approval_queue'],
    'subcontractor_lead': ['subcontractor_progress', 'payment_status', 'work_orders', 'quality_issues', 'safety_compliance', 'resource_allocation', 'performance_metrics', 'contract_status']
  };
  return defaultWidgets[role] || [];
};

export default useRoleBasedPermissions;
