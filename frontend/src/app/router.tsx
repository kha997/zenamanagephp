import { Navigate, createBrowserRouter } from 'react-router-dom';
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

export const router = createBrowserRouter([
  {
    path: '/app',
    element: <MainLayout />,
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
]);

export default router;
