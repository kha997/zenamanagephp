import { Outlet } from 'react-router-dom'
import { AdminSidebar } from '@/components/layout/AdminSidebar'
import { TopBar } from '@/components/layout/TopBar'

/**
 * Layout cho trang admin với sidebar riêng
 * Chỉ dành cho user có quyền admin
 */
export function AdminLayout() {
  return (
    <div className="flex h-screen bg-gray-50">
      {/* Admin sidebar */}
      <AdminSidebar />
      
      {/* Main content area */}
      <div className="flex-1 flex flex-col ml-64">
        {/* Top navigation bar */}
        <TopBar />
        
        {/* Page content */}
        <main className="flex-1 overflow-auto p-6">
          <div className="mb-4">
            <h1 className="text-2xl font-bold text-gray-900">System Administration</h1>
            <p className="text-gray-600">Manage system settings and configurations</p>
          </div>
          <Outlet />
        </main>
      </div>
    </div>
  )
}