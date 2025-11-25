import React from 'react';
import { Navigate, createBrowserRouter, useLocation } from 'react-router-dom';
import MainLayout from './layouts/MainLayout';
import AdminLayout from './layouts/AdminLayout';
import AdminRoute from '../routes/AdminRoute';
import DashboardPage from '../pages/dashboard/DashboardPage';
import AlertsPage from '../pages/alerts/AlertsPage';
import PreferencesPage from '../pages/preferences/PreferencesPage';
import LoginPage from '../pages/auth/LoginPage';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage';
import AdminDashboardPage from '../pages/admin/DashboardPage';
import AdminUsersPage from '../pages/admin/UsersPage';
import AdminRolesPage from '../pages/admin/RolesPage';
import AdminTenantsPage from '../pages/admin/TenantsPage';
import ProjectsListPage from '../pages/projects/ProjectsListPage';
import ProjectDetailPage from '../pages/projects/ProjectDetailPage';
import DocumentsPage from '../pages/documents/DocumentsPage';
import { DocumentDetailPage } from '../pages/documents/DocumentDetailPage';
import TeamPage from '../pages/TeamPage';
import CalendarPage from '../pages/CalendarPage';
import SettingsPage from '../pages/SettingsPage';
import TasksPage from '../pages/TasksPage';
import TaskDetailPage from '../pages/tasks/TaskDetailPage';
// Change Requests
import { CRListPage } from '../pages/ChangeRequests/CRListPage';
import { CRDetailPage } from '../pages/ChangeRequests/CRDetailPage';
import { CRCreatePage } from '../pages/ChangeRequests/CRCreatePage';
// Templates
import { TemplatesList } from '../features/templates/pages/TemplatesList';
import { TemplateDetail } from '../features/templates/pages/TemplateDetail';
import { CreateTemplate } from '../features/templates/pages/CreateTemplate';
// Quotes
import QuotesListPage from '../pages/quotes/QuotesListPage';
import QuoteDetailPage from '../pages/quotes/QuoteDetailPage';
// Clients
import ClientsListPage from '../pages/clients/ClientsListPage';
import ClientDetailPage from '../pages/clients/ClientDetailPage';
import { useAuth } from '../shared/auth/hooks';

const RequireAuth: React.FC<{ children: React.ReactElement }> = ({ children }) => {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

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
        path: 'tasks/:id',
        element: <TaskDetailPage />,
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
        path: 'change-requests',
        element: <CRListPage />,
      },
      {
        path: 'change-requests/create',
        element: <CRCreatePage />,
      },
      {
        path: 'change-requests/:id',
        element: <CRDetailPage />,
      },
      {
        path: 'templates',
        element: <TemplatesList />,
      },
      {
        path: 'templates/create',
        element: <CreateTemplate />,
      },
      {
        path: 'templates/:id',
        element: <TemplateDetail />,
      },
      {
        path: 'quotes',
        element: <QuotesListPage />,
      },
      {
        path: 'quotes/:id',
        element: <QuoteDetailPage />,
      },
      {
        path: 'clients',
        element: <ClientsListPage />,
      },
      {
        path: 'clients/:id',
        element: <ClientDetailPage />,
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
      v7_startTransition: true,
    },
  }
);

export default router;
