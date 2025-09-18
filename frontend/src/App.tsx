import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from './stores/authStore'
import { useEffect } from 'react'
import { ThemeProvider } from './contexts/ThemeContext'
import Layout from './components/Layout'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import DashboardPage from './pages/DashboardPage'
import UsersPage from './pages/UsersPage'
import UserDetailPage from './pages/UserDetailPage'
import CreateUserPage from './pages/CreateUserPage'
import ProjectsPage from './pages/ProjectsPage'
import ProjectDetailPage from './pages/ProjectDetailPage'
import CreateProjectPage from './pages/projects/CreateProjectPage'
import TasksPage from './pages/TasksPage'
import TaskDetailPage from './pages/TaskDetailPage'
import ProfilePage from './pages/ProfilePage'
import TestPage from './pages/TestPage'
import UsersTestPage from './pages/UsersTestPage'
import UsersDebugPage from './pages/UsersDebugPage'
import SimpleTestPage from './pages/SimpleTestPage'
import GanttChartPage from './pages/GanttChartPage'
import DocumentCenterPage from './pages/DocumentCenterPage'
import QCModulePage from './pages/QCModulePage'
import ChangeRequestsPage from './pages/ChangeRequestsPage'
import ReportsPage from './pages/ReportsPage'
import AnalyticsPage from './pages/AnalyticsPage'
import FrontendIntegrationTestPage from './pages/FrontendIntegrationTestPage'
import LoadingSpinner from './components/LoadingSpinner'
import pwaService from './services/pwaService'

function App() {
  const { user, isLoading, checkAuth } = useAuthStore()

  useEffect(() => {
    checkAuth()
    
    // Initialize PWA service
    pwaService.requestNotificationPermission()
    
    // Register service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js')
        .then((registration) => {
          console.log('SW registered: ', registration)
        })
        .catch((registrationError) => {
          console.log('SW registration failed: ', registrationError)
        })
    }
  }, [checkAuth])

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  if (!user) {
    return (
      <ThemeProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/users-debug" element={<UsersDebugPage />} />
          <Route path="/simple-test" element={<SimpleTestPage />} />
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </ThemeProvider>
    )
  }

  return (
    <ThemeProvider>
      <Layout>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/users" element={<UsersPage />} />
          <Route path="/users/new" element={<CreateUserPage />} />
          <Route path="/users/:id" element={<UserDetailPage />} />
          <Route path="/projects" element={<ProjectsPage />} />
          <Route path="/projects/create" element={<CreateProjectPage />} />
          <Route path="/projects/:id" element={<ProjectDetailPage />} />
          <Route path="/tasks" element={<TasksPage />} />
          <Route path="/tasks/:id" element={<TaskDetailPage />} />
          <Route path="/gantt" element={<GanttChartPage />} />
          <Route path="/documents" element={<DocumentCenterPage />} />
          <Route path="/qc" element={<QCModulePage />} />
          <Route path="/change-requests" element={<ChangeRequestsPage />} />
          <Route path="/reports" element={<ReportsPage />} />
          <Route path="/analytics" element={<AnalyticsPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/test" element={<TestPage />} />
          <Route path="/frontend-test" element={<FrontendIntegrationTestPage />} />
          <Route path="/users-test" element={<UsersTestPage />} />
          <Route path="/users-debug" element={<UsersDebugPage />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </Layout>
    </ThemeProvider>
  )
}

export default App