import React, { useEffect } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../features/auth/store';

interface TenantGuardProps {
  children: React.ReactElement;
}

/**
 * TenantGuard - Protects routes that require user to have at least one tenant
 * 
 * Checks if authenticated user has tenants. If not, redirects to /app/no-workspace.
 * If user has tenants but is on /app/no-workspace, redirects to /app/dashboard.
 */
export const TenantGuard: React.FC<TenantGuardProps> = ({ children }) => {
  const location = useLocation();
  const { isAuthenticated, isLoading, tenantsCount, checkAuth } = useAuthStore();
  const isNoWorkspacePage = location.pathname === '/app/no-workspace';

  useEffect(() => {
    // Check auth status on mount to ensure tenantsCount is up to date
    if (isAuthenticated) {
      checkAuth();
    }
  }, [isAuthenticated, checkAuth]);

  // Wait for auth check to complete
  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] text-[var(--color-text-secondary)]">
        Đang xác thực phiên đăng nhập...
      </div>
    );
  }

  // If not authenticated, let AuthGuard handle it (shouldn't reach here if AuthGuard is used correctly)
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Check if user has tenants
  const hasTenants = tenantsCount > 0;

  // If user has no tenants and is not on no-workspace page, redirect to no-workspace
  if (!hasTenants && !isNoWorkspacePage) {
    return <Navigate to="/app/no-workspace" replace />;
  }

  // If user has tenants but is on no-workspace page, redirect to dashboard
  if (hasTenants && isNoWorkspacePage) {
    return <Navigate to="/app/dashboard" replace />;
  }

  // Allow access
  return children;
};

