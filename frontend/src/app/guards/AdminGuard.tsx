import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../features/auth/store';

interface AdminGuardProps {
  children: React.ReactElement;
}

/**
 * AdminGuard - Protects admin routes that require admin role
 * 
 * Checks if user has admin role and redirects to /app/dashboard if not authorized.
 */
export const AdminGuard: React.FC<AdminGuardProps> = ({ children }) => {
  const location = useLocation();
  const { user, isAuthenticated, isLoading } = useAuthStore();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] text-[var(--color-text-secondary)]">
        Đang xác thực phiên đăng nhập...
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Check if user has admin role
  const isAdmin = user?.role === 'admin' || user?.role === 'super_admin' || user?.role === 'Admin';
  
  if (!isAdmin) {
    // Redirect non-admin users to tenant dashboard
    return <Navigate to="/app/dashboard" replace />;
  }

  return children;
};

