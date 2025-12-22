import React from 'react';
import { Navigate, createBrowserRouter, useLocation } from 'react-router-dom';
import MainLayout from './layouts/MainLayout';
import AdminLayout from './layouts/AdminLayout';
import AdminRoute from '../routes/AdminRoute';
import DashboardPage from '../pages/dashboard/DashboardPage';
import AlertsPage from '../pages/alerts/AlertsPage';
import PreferencesPage from '../pages/preferences/PreferencesPage';
import { GlobalSearchPage } from '../features/search/pages/GlobalSearchPage';
import LoginPage from '../pages/auth/LoginPage';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage';
import AdminDashboardPage from '../pages/admin/DashboardPage';
import AdminUsersPage from '../pages/admin/UsersPage';
import AdminRolesPage from '../pages/admin/RolesPage';
import AdminTenantsPage from '../pages/admin/TenantsPage';
import { AdminRolesPermissionsPage } from '../features/admin/pages/AdminRolesPermissionsPage';
import { AdminAuditLogsPage } from '../features/admin/pages/AdminAuditLogsPage';
import { PermissionInspectorPage } from '../features/admin/pages/PermissionInspectorPage';
import { CostGovernanceOverviewPage } from '../features/admin/pages/CostGovernanceOverviewPage';
import { AdminRoleProfilesPage } from '../features/admin/pages/AdminRoleProfilesPage';
import ProjectsListPage from '../pages/projects/ProjectsListPage';
import { ProjectDetailPage } from '../features/projects/pages/ProjectDetailPage';
import { ProjectContractsPage } from '../features/projects/pages/ProjectContractsPage';
import { ContractDetailPage } from '../features/projects/pages/ContractDetailPage';
import { ChangeOrderDetailPage } from '../features/projects/pages/ChangeOrderDetailPage';
import DocumentsPage from '../pages/documents/DocumentsPage';
import { DocumentDetailPage } from '../pages/documents/DocumentDetailPage';
import TeamPage from '../pages/TeamPage';
import CalendarPage from '../pages/CalendarPage';
import SettingsPage from '../pages/SettingsPage';
import TasksPage from '../pages/TasksPage';
import { MyTasksPage } from '../features/projects/pages/MyTasksPage';
import { ActivityFeedPage } from '../features/app/pages/ActivityFeedPage';
import { NotificationsPage } from '../features/app/notifications/NotificationsPage';
import TenantsPage from '../pages/TenantsPage';
import TemplatesPage from '../pages/TemplatesPage';
import ChangeRequestsPage from '../pages/ChangeRequestsPage';
import UsersPage from '../pages/UsersPage';
import { useAuthStore } from '../features/auth/store';

const RequireAuth: React.FC<{ children: React.ReactElement }> = ({ children }) => {
  const { isAuthenticated, isLoading, checkAuth } = useAuthStore();
  const location = useLocation();

  // Round 154: Call checkAuth on mount to ensure auth state is checked
  // This is especially important after UI login when there's a session cookie but no token
  React.useEffect(() => {
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

export const router = createBrowserRouter(
  [
  {
    path: '/app',
    element: (
      <RequireAuth>
        <MainLayout />
      </RequireAuth>
    ),
    children: [
      {
        index: true,
        element: <Navigate to="/app/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <DashboardPage />,
      },
      {
        path: 'search',
        element: <GlobalSearchPage />,
      },
      {
        path: 'alerts',
        element: <AlertsPage />,
      },
      {
        path: 'preferences',
        element: <PreferencesPage />,
      },
      {
        path: 'projects',
        element: <ProjectsListPage />,
      },
      {
        path: 'projects/:id',
        element: <ProjectDetailPage />,
      },
      {
        path: 'projects/:id/contracts',
        element: <ProjectContractsPage />,
      },
      {
        path: 'projects/:id/contracts/:contractId',
        element: <ContractDetailPage />,
      },
      {
        path: 'projects/:id/contracts/:contractId/change-orders/:coId',
        element: <ChangeOrderDetailPage />,
      },
      {
        path: 'documents',
        element: <DocumentsPage />,
      },
      {
        path: 'documents/:id',
        element: <DocumentDetailPage />,
      },
      {
        path: 'tasks',
        element: <TasksPage />,
      },
      {
        path: 'my-tasks',
        element: <MyTasksPage />,
      },
      {
        path: 'activity',
        element: <ActivityFeedPage />,
      },
      {
        path: 'notifications',
        element: <NotificationsPage />,
      },
      {
        path: 'team',
        element: <TeamPage />,
      },
      {
        path: 'calendar',
        element: <CalendarPage />,
      },
      {
        path: 'settings',
        element: <SettingsPage />,
      },
      {
        path: 'tenants',
        element: <TenantsPage />,
      },
      {
        path: 'templates',
        element: <TemplatesPage />,
      },
      {
        path: 'change-requests',
        element: <ChangeRequestsPage />,
      },
      {
        path: 'users',
        element: <UsersPage />,
      },
    ],
  },
  {
    path: '/admin',
    element: (
      <AdminRoute>
        <AdminLayout />
      </AdminRoute>
    ),
    children: [
      {
        index: true,
        element: <Navigate to="/admin/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <AdminDashboardPage />,
      },
      {
        path: 'users',
        element: <AdminUsersPage />,
      },
      {
        path: 'roles',
        element: <AdminRolesPage />,
      },
      {
        path: 'roles-permissions',
        element: <AdminRolesPermissionsPage />,
      },
      {
        path: 'audit-logs',
        element: <AdminAuditLogsPage />,
      },
      {
        path: 'permission-inspector',
        element: <PermissionInspectorPage />,
      },
      {
        path: 'cost-governance',
        element: <CostGovernanceOverviewPage />,
      },
      {
        path: 'role-profiles',
        element: <AdminRoleProfilesPage />,
      },
      {
        path: 'tenants',
        element: <AdminTenantsPage />,
      },
    ],
  },
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/reset-password',
    element: <ResetPasswordPage />,
  },
  {
    path: '*',
    element: <Navigate to="/app/dashboard" replace />,
  },
],
  {
        future: {
          // v7_startTransition: true, // Commented out - not available in current React Router version
        },
  }
);

export default router;
