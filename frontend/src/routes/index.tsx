import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { AuthLayout } from '@/layouts/AuthLayout'
import { ProtectedRoute } from './ProtectedRoute'
import { NotificationsPlaceholder } from '@/pages/NotificationsPlaceholder'

// Auth pages
import { Login } from '@/features/auth/pages/Login'
import { Register } from '@/features/auth/pages/Register'

import { NotificationSettingsPage } from '@/pages/settings/NotificationSettingsPage'
import { GeneralSettingsPage } from '@/pages/settings/GeneralSettingsPage'
import { SecuritySettingsPage } from '@/pages/settings/SecuritySettingsPage'
import ContractsListPage from '@/pages/contracts/ContractsListPage'
import ContractCreatePage from '@/pages/contracts/ContractCreatePage'
import ContractDetailPage from '@/pages/contracts/ContractDetailPage'
import { TaskBoard } from '@/features/tasks/pages'
import { ChangeRequestsList } from '@/features/change-requests/pages/ChangeRequestsList'
import { ChangeRequestDetail } from '@/features/change-requests/pages/ChangeRequestDetail'
import { CreateChangeRequest } from '@/features/change-requests/pages/CreateChangeRequest'
import { EditChangeRequest } from '@/features/change-requests/pages/EditChangeRequest'

function ProjectsListRoutePlaceholder() {
  return <div className="p-6">Projects list is temporarily unavailable.</div>
}

function ProjectDetailRoutePlaceholder() {
  return <div className="p-6">Project detail is temporarily unavailable.</div>
}

/**
 * Cấu hình routing chính của ứng dụng
 * Chia thành 3 layout: Auth, App, Admin
 */
export function AppRoutes() {
  return (
    <Routes>
      {/* Public routes - Authentication */}
      <Route path="/auth" element={<AuthLayout />}>
        <Route path="login" element={<Login />} />
        <Route path="register" element={<Register />} />
        <Route index element={<Navigate to="login" replace />} />
      </Route>

      {/* Protected routes - Main application */}
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Outlet />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="projects" replace />} />

        {/* Projects routes */}
        <Route path="projects" element={<ProjectsListRoutePlaceholder />} />
        <Route path="projects/:id" element={<ProjectDetailRoutePlaceholder />} />
        <Route path="tasks" element={<TaskBoard />} />
        <Route path="change-requests" element={<ChangeRequestsList />} />
        <Route path="change-requests/create" element={<CreateChangeRequest />} />
        <Route path="change-requests/:id" element={<ChangeRequestDetail />} />
        <Route path="change-requests/:id/edit" element={<EditChangeRequest />} />
        <Route path="app/projects/:projectId/contracts" element={<ContractsListPage />} />
        <Route path="app/projects/:projectId/contracts/new" element={<ContractCreatePage />} />
        <Route path="app/projects/:projectId/contracts/:contractId" element={<ContractDetailPage />} />

        {/* Settings routes */}
        <Route path="settings" element={<Navigate to="settings/general" replace />} />
        <Route path="settings/general" element={<GeneralSettingsPage />} />
        <Route path="settings/security" element={<SecuritySettingsPage />} />
        <Route path="settings/notifications" element={<NotificationSettingsPage />} />
        <Route path="notifications" element={<NotificationsPlaceholder />} />
      </Route>

      {/* Fallback route */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
