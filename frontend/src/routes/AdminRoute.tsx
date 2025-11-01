import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';
import { LoadingSpinner } from '@/components/ui/loading-spinner';

interface AdminRouteProps {
  children: React.ReactNode;
  fallbackPath?: string;
}

const ADMIN_ROLE_NAMES = new Set(['admin', 'super_admin', 'Admin', 'SuperAdmin']);

const AdminRoute: React.FC<AdminRouteProps> = ({ children, fallbackPath = '/unauthorized' }) => {
  const { user, isAuthenticated, isLoading } = useAuthStore();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    return <Navigate to="/auth/login" state={{ from: location }} replace />;
  }

  const normalizedRoles = Array.isArray(user.roles)
    ? user.roles
        .map((role) => (typeof role === 'string' ? role : role?.name))
        .filter(Boolean)
    : [];

  const hasAdminRole = normalizedRoles.some((role) => role && ADMIN_ROLE_NAMES.has(role));

  if (!hasAdminRole) {
    return <Navigate to={fallbackPath} replace />;
  }

  return <>{children}</>;
};

export default AdminRoute;