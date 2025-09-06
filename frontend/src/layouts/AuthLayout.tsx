import { Outlet } from 'react-router-dom'
import { useAuthStore } from '@/store/auth.store'
import { Navigate } from 'react-router-dom'

/**
 * Layout cho trang authentication (login, register)
 * Redirect về dashboard nếu đã đăng nhập
 */
export function AuthLayout() {
  const { isAuthenticated } = useAuthStore()

  // Redirect về dashboard nếu đã đăng nhập
  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo và branding */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Z.E.N.A</h1>
          <p className="text-gray-600">Project Management System</p>
        </div>
        
        {/* Auth form content */}
        <div className="bg-white rounded-lg shadow-lg p-6">
          <Outlet />
        </div>
      </div>
    </div>
  )
}