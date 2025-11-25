import { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '@/store/auth.store'
import { LoadingSpinner } from '@/components/ui/loading-spinner'

interface RoleGuardProps {
  children: ReactNode
  requiredRoles?: string[]
  requiredPermissions?: string[]
  fallbackPath?: string
}

/**
 * Component bảo vệ route dựa trên role và permission
 * Chỉ cho phép user có role/permission phù hợp truy cập
 */
export function RoleGuard({ 
  children, 
  requiredRoles = [], 
  requiredPermissions = [],
  fallbackPath = '/unauthorized'
}: RoleGuardProps) {
  const { user, isAuthenticated, isLoading } = useAuthStore()
  const location = useLocation()

  // Hiển thị loading trong khi kiểm tra authentication
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  // Redirect về login nếu chưa đăng nhập
  if (!isAuthenticated || !user) {
    return <Navigate to="/auth/login" state={{ from: location }} replace />
  }

  const normalizedRoles = Array.isArray(user.roles)
    ? user.roles
        .map(role => (typeof role === 'string' ? role : role?.name))
        .filter((role): role is string => Boolean(role))
    : [];

  // Kiểm tra role
  if (requiredRoles.length > 0) {
    const hasRequiredRole = requiredRoles.some(role =>
      normalizedRoles.includes(role)
    )
    
    if (!hasRequiredRole) {
      return <Navigate to={fallbackPath} replace />
    }
  }

  // Kiểm tra permission
  if (requiredPermissions.length > 0) {
    const hasRequiredPermission = requiredPermissions.some(permission => 
      user.permissions?.includes?.(permission)
    )
    
    if (!hasRequiredPermission) {
      return <Navigate to={fallbackPath} replace />
    }
  }

  return <>{children}</>
}

/**
 * Hook để kiểm tra role và permission
 */
export function useRolePermission() {
  const { user } = useAuthStore()
  const normalizedRoles = Array.isArray(user?.roles)
    ? user.roles
        .map(role => (typeof role === 'string' ? role : role?.name))
        .filter((role): role is string => Boolean(role))
    : []

  const hasRole = (role: string): boolean => {
    return normalizedRoles.includes(role)
  }

  const hasAnyRole = (roles: string[]): boolean => {
    return roles.some(role => normalizedRoles.includes(role))
  }

  const hasPermission = (permission: string): boolean => {
    return user?.permissions?.includes?.(permission) ?? false
  }

  const hasAnyPermission = (permissions: string[]): boolean => {
    return permissions.some(permission => user?.permissions?.includes?.(permission)) ?? false
  }

  const canAccess = (requiredRoles?: string[], requiredPermissions?: string[]): boolean => {
    if (!user) return false

    // Kiểm tra role
    if (requiredRoles && requiredRoles.length > 0) {
      const hasRequiredRole = requiredRoles.some(role =>
        normalizedRoles.includes(role)
      )
      if (!hasRequiredRole) return false
    }

    // Kiểm tra permission
    if (requiredPermissions && requiredPermissions.length > 0) {
      const hasRequiredPermission = requiredPermissions.some(permission => 
        user.permissions?.includes?.(permission)
      )
      if (!hasRequiredPermission) return false
    }

    return true
  }

  return {
    hasRole,
    hasAnyRole,
    hasPermission,
    hasAnyPermission,
    canAccess
  }
}

/**
 * Component để ẩn/hiện element dựa trên role/permission
 */
interface ConditionalRenderProps {
  children: ReactNode
  requiredRoles?: string[]
  requiredPermissions?: string[]
  fallback?: ReactNode
}

export function ConditionalRender({ 
  children, 
  requiredRoles = [], 
  requiredPermissions = [],
  fallback = null
}: ConditionalRenderProps) {
  const { canAccess } = useRolePermission()

  if (canAccess(requiredRoles, requiredPermissions)) {
    return <>{children}</>
  }

  return <>{fallback}</>
}
