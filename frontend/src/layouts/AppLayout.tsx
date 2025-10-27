import { Outlet } from 'react-router-dom'
import { TopBar } from '@/components/layout/TopBar'
import { PrimaryNavigator } from '@/components/layout/PrimaryNavigator'

/**
 * Layout chính của ứng dụng không có sidebar
 * Sử dụng cho tất cả trang sau khi đăng nhập
 */
export function AppLayout() {
  return (
    <div className="flex flex-col h-screen bg-gray-50">
      {/* Header và Navigator - Fixed khi scroll */}
      <div className="flex flex-col sticky top-0 z-50">
        <TopBar />
        <PrimaryNavigator />
      </div>
      
      {/* Page content - Scrollable */}
      <main className="flex-1 overflow-auto p-6">
        <Outlet />
      </main>
    </div>
  )
}