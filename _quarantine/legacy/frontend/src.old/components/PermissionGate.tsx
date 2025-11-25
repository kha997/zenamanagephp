/**
 * PermissionGate Component
 * 
 * Conditionally renders children based on user permissions.
 * Follows FRONTEND_GUIDELINES.md patterns.
 * 
 * @example
 * ```tsx
 * <PermissionGate permission="projects.create">
 *   <Button>Create Project</Button>
 * </PermissionGate>
 * ```
 */

import React from 'react';
import { usePermissions } from '@/hooks/usePermissions';

interface PermissionGateProps {
  /** Permission code to check (e.g., 'projects.create') */
  permission: string;
  /** Optional resource ID for scoped permissions */
  resourceId?: string | number;
  /** Fallback content to show if permission is denied */
  fallback?: React.ReactNode;
  /** Children to render if permission is granted */
  children: React.ReactNode;
}

/**
 * PermissionGate - Conditionally renders children based on RBAC permissions
 * 
 * Features:
 * - Permission-based access control
 * - Resource-scoped permissions support
 * - Custom fallback UI
 * - Type-safe permission checking
 */
export function PermissionGate({
  permission,
  resourceId,
  fallback = null,
  children,
}: PermissionGateProps) {
  const { can } = usePermissions();

  if (!can(permission, resourceId)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

export default PermissionGate;

