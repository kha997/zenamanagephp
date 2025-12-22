import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { useAdminDashboard } from '../hooks';
import { useAuthStore } from '../../auth/store';
import { RecentActivityList } from '../components/RecentActivityList';

/**
 * AdminDashboardPage - Admin dashboard page
 * Displays system-wide statistics and admin-specific metrics
 */
export const AdminDashboardPage: React.FC = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated, checkAuth } = useAuthStore();
  const { data: adminData, isLoading, error } = useAdminDashboard();

  // Check auth on mount if not authenticated
  useEffect(() => {
    if (!isAuthenticated && !user) {
      console.log('[AdminDashboardPage] Not authenticated, checking auth...');
      checkAuth().catch(() => {
        console.warn('[AdminDashboardPage] Auth check failed, redirecting to login');
        navigate('/login', { state: { from: '/admin/dashboard' } });
      });
    }
  }, [isAuthenticated, user, checkAuth, navigate]);

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Admin Dashboard</h1>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardContent className="p-6">
                <div className="animate-pulse">
                  <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-1/2 mb-2"></div>
                  <div className="h-8 bg-[var(--color-surface-subtle)] rounded w-1/4"></div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    const errorMessage = (error as Error).message;
    const isUnauthorized = errorMessage.includes('Unauthorized') || errorMessage.includes('401');
    
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Admin Dashboard</h1>
        </div>
        <Card>
          <CardContent className="p-6">
            <div className="text-center text-[var(--color-text-muted)]">
              <p className="mb-2">Failed to load admin dashboard</p>
              <p className="text-sm mb-4">{errorMessage}</p>
              {isUnauthorized && (
                <div className="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                  <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                    Authentication Required
                  </p>
                  <p className="text-xs text-yellow-700 dark:text-yellow-300">
                    Please log in again or check if your session has expired.
                  </p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const stats = adminData?.stats || {
    total_users: 0,
    total_projects: 0,
    total_tasks: 0,
    active_sessions: 0,
  };

  return (
    <div className="space-y-6">
      {/* Page Title */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Admin Dashboard</h1>
        <p className="text-sm text-[var(--color-text-secondary)] mt-1">
          System-wide statistics and administration overview
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* Total Users */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Total Users</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-[var(--color-text-primary)]">
              {stats.total_users}
            </div>
          </CardContent>
        </Card>

        {/* Total Projects */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Total Projects</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-[var(--color-text-primary)]">
              {stats.total_projects}
            </div>
          </CardContent>
        </Card>

        {/* Total Tasks */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Total Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-[var(--color-text-primary)]">
              {stats.total_tasks}
            </div>
          </CardContent>
        </Card>

        {/* Active Sessions */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Active Sessions</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-[var(--color-semantic-success-600)]">
              {stats.active_sessions}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* System Health */}
      {adminData?.system_health && (
        <Card>
          <CardHeader>
            <CardTitle>System Health</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-3">
              <div className={`w-4 h-4 rounded-full ${
                adminData.system_health === 'good'
                  ? 'bg-[var(--color-semantic-success-600)]'
                  : adminData.system_health === 'warning'
                  ? 'bg-[var(--color-semantic-warning-600)]'
                  : 'bg-[var(--color-semantic-danger-600)]'
              }`}></div>
              <div className="flex-1">
                <span className="text-sm font-medium text-[var(--color-text-primary)] capitalize">
                  {adminData.system_health}
                </span>
                <p className="text-xs text-[var(--color-text-muted)] mt-1">
                  {adminData.system_health === 'good' 
                    ? 'All systems operational'
                    : adminData.system_health === 'warning'
                    ? 'Some systems may be experiencing issues'
                    : 'Critical system issues detected'}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Recent Activities */}
      {adminData?.recent_activities && adminData.recent_activities.length > 0 && (
        <RecentActivityList
          activities={adminData.recent_activities}
          title="Recent System Activity"
          className="mt-6"
        />
      )}
    </div>
  );
};

export default AdminDashboardPage;

