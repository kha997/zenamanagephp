import React from 'react';
import { createBrowserRouter, Navigate } from 'react-router-dom';
import { AuthGuard } from './guards/AuthGuard';
import MainLayout from './layouts/MainLayout';
import AdminLayout from './layouts/AdminLayout';
import LoginPage from '../features/auth/pages/LoginPage';
import ForgotPasswordPage from '../features/auth/pages/ForgotPasswordPage';
import ResetPasswordPage from '../features/auth/pages/ResetPasswordPage';

import ProjectsListPage from '../features/projects/pages/ProjectsListPage';
import ProjectDetailPage from '../features/projects/pages/ProjectDetailPage';
import CreateProjectPage from '../features/projects/pages/CreateProjectPage';
import TasksListPage from '../features/tasks/pages/TasksListPage';
import TaskDetailPage from '../features/tasks/pages/TaskDetailPage';
import CreateTaskPage from '../features/tasks/pages/CreateTaskPage';

const AdminDashboardPage = () => <div>Admin Dashboard</div>;

export const router = createBrowserRouter([
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
    path: '/app',
    element: (
      <AuthGuard>
        <MainLayout />
      </AuthGuard>
    ),
    children: [
      {
        index: true,
        element: <Navigate to="/app/projects" replace />,
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
        path: 'projects/create',
        element: <CreateProjectPage />,
      },
      {
        path: 'tasks',
        element: <TasksListPage />,
      },
      {
        path: 'tasks/:id',
        element: <TaskDetailPage />,
      },
      {
        path: 'tasks/create',
        element: <CreateTaskPage />,
      },
    ],
  },
  {
    path: '/admin',
    element: (
      <AuthGuard>
        <AdminLayout />
      </AuthGuard>
    ),
    children: [
      {
        index: true,
        element: <AdminDashboardPage />,
      },
    ],
  },
  {
    path: '*',
    element: <Navigate to="/app" replace />,
  },
]);

