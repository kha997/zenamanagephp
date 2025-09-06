import { Routes, Route, Navigate } from 'react-router-dom'
import { AuthLayout } from '@/layouts/AuthLayout'
import { AppLayout } from '@/layouts/AppLayout'
import { AdminLayout } from '@/layouts/AdminLayout'
import { ProtectedRoute } from './ProtectedRoute'
import { AdminRoute } from './AdminRoute'

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

      {/* Protected routes - Main application */}
      <Route path="/" element={<ProtectedRoute><AppLayout /></ProtectedRoute>}>
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="dashboard" element={<Dashboard />} />
        
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