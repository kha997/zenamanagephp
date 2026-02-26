import { Routes, Route, Navigate } from 'react-router-dom'
import { AuthLayout } from '@/layouts/AuthLayout'
import { AppLayout } from '@/layouts/AppLayout'
import { AdminLayout } from '@/layouts/AdminLayout'
import { ProtectedRoute } from './ProtectedRoute'
import { AdminRoute } from './AdminRoute'
import { RoleGuard } from './RoleGuard'

// Auth pages
import { Login } from '@/features/auth/pages/Login'
import { Register } from '@/features/auth/pages/Register'

// App pages
import { Dashboard } from '@/features/dashboard/pages/Dashboard'
import { ProjectsList } from '@/features/projects/pages/ProjectsList'
import { ProjectDetail } from '@/features/projects/pages/ProjectDetail'
import { TaskBoard } from '@/features/tasks/pages/TaskBoard'
import { Notifications } from '@/features/notifications/pages/Notifications'
import { UserProfile } from '@/features/users/pages/UserProfile'
import { Settings } from '@/features/settings/pages/Settings'
import { NotificationSettingsPage } from '@/pages/settings/NotificationSettingsPage'
import { GeneralSettingsPage } from '@/pages/settings/GeneralSettingsPage'
import { SecuritySettingsPage } from '@/pages/settings/SecuritySettingsPage'

// Z.E.N.A Dashboard pages
import { PmDashboard } from '@/pages/dashboard/PmDashboard'
import { DesignerDashboard } from '@/pages/dashboard/DesignerDashboard'
import { SiteEngineerDashboard } from '@/pages/dashboard/SiteEngineerDashboard'
import { QcDashboard } from '@/pages/dashboard/QcDashboard'
import { ProcurementDashboard } from '@/pages/dashboard/ProcurementDashboard'
import { FinanceDashboard } from '@/pages/dashboard/FinanceDashboard'
import { ClientDashboard } from '@/pages/dashboard/ClientDashboard'

// Templates pages
import { TemplatesList, TemplateDetail, CreateTemplate } from '@/features/templates'

// Change Requests pages
import { 
  ChangeRequestsList, 
  ChangeRequestDetail, 
  CreateChangeRequest,
  EditChangeRequest 
} from '@/features/change-requests'

// Interaction Logs pages
import {
  InteractionLogsList,
  InteractionLogDetail,
  CreateInteractionLog
} from '@/features/interaction-logs'

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

      {/* Unauthorized route */}
      <Route path="/unauthorized" element={
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
          <div className="max-w-md w-full bg-white shadow-lg rounded-lg p-6 text-center">
            <div className="text-red-500 text-6xl mb-4">🚫</div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Access Denied</h1>
            <p className="text-gray-600 mb-4">You don't have permission to access this resource.</p>
            <button 
              onClick={() => window.history.back()}
              className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded"
            >
              Go Back
            </button>
          </div>
        </div>
      } />

      {/* Protected routes - Main application */}
      <Route path="/" element={<ProtectedRoute><AppLayout /></ProtectedRoute>}>
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="dashboard" element={<Dashboard />} />
        
        {/* Z.E.N.A Role-based Dashboard Routes */}
        <Route path="admin/dashboard" element={<RoleGuard requiredRoles={['SuperAdmin', 'Admin']}><Dashboard /></RoleGuard>} />
        <Route path="pm/dashboard" element={<RoleGuard requiredRoles={['PM']}><PmDashboard /></RoleGuard>} />
        <Route path="designer/dashboard" element={<RoleGuard requiredRoles={['Designer']}><DesignerDashboard /></RoleGuard>} />
        <Route path="site-engineer/dashboard" element={<RoleGuard requiredRoles={['SiteEngineer']}><SiteEngineerDashboard /></RoleGuard>} />
        <Route path="qc/dashboard" element={<RoleGuard requiredRoles={['QC']}><QcDashboard /></RoleGuard>} />
        <Route path="procurement/dashboard" element={<RoleGuard requiredRoles={['Procurement']}><ProcurementDashboard /></RoleGuard>} />
        <Route path="finance/dashboard" element={<RoleGuard requiredRoles={['Finance']}><FinanceDashboard /></RoleGuard>} />
        <Route path="client/dashboard" element={<RoleGuard requiredRoles={['Client']}><ClientDashboard /></RoleGuard>} />
        
        {/* Projects routes */}
        <Route path="projects" element={<ProjectsList />} />
        <Route path="projects/:id" element={<ProjectDetail />} />
        
        {/* Tasks routes */}
        <Route path="tasks" element={<TaskBoard />} />
        
        {/* Templates routes */}
        <Route path="templates" element={<TemplatesList />} />
        <Route path="templates/create" element={<CreateTemplate />} />
        <Route path="templates/:id" element={<TemplateDetail />} />
        
        {/* Change Requests routes */}
        <Route path="change-requests" element={<ChangeRequestsList />} />
        <Route path="change-requests/create" element={<CreateChangeRequest />} />
        <Route path="change-requests/:id" element={<ChangeRequestDetail />} />
        <Route path="change-requests/:id/edit" element={<EditChangeRequest />} />
        
        {/* Interaction Logs routes */}
        <Route path="interaction-logs" element={<InteractionLogsList />} />
        <Route path="interaction-logs/create" element={<CreateInteractionLog />} />
        <Route path="interaction-logs/:id" element={<InteractionLogDetail />} />
        
        {/* Other routes */}
        <Route path="notifications" element={<Notifications />} />
        <Route path="profile" element={<UserProfile />} />
        <Route path="settings" element={<Navigate to="settings/general" replace />} />
        <Route path="settings/general" element={<GeneralSettingsPage />} />
        <Route path="settings/security" element={<SecuritySettingsPage />} />
        <Route path="settings/notifications" element={<NotificationSettingsPage />} />
      </Route>

      {/* Admin routes - System administration */}
      <Route path="/admin" element={<AdminRoute><AdminLayout /></AdminRoute>}>
        <Route path="settings" element={<Settings />} />
        <Route index element={<Navigate to="settings" replace />} />
      </Route>

      {/* Fallback route */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
