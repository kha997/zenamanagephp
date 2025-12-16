import React, { useEffect } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../features/auth/store';

interface AuthGuardProps {
  children: React.ReactElement;
}

/**
 * AuthGuard - Protects routes that require authentication
 * 
 * Checks authentication state and redirects to /login if not authenticated.
 */
export const AuthGuard: React.FC<AuthGuardProps> = ({ children }) => {
  const location = useLocation();
  const { isAuthenticated, isLoading, checkAuth } = useAuthStore();

  useEffect(() => {
    // Check auth status on mount
    checkAuth();
  }, [checkAuth]);

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

  return children;
};

