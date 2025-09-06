import { Outlet } from 'react-router-dom'
import { useState } from 'react'
import { Sidebar } from '@/components/layout/Sidebar'
import { TopBar } from '@/components/layout/TopBar'
import { useUIStore } from '@/store/ui'
import { cn } from '@/lib/utils'

/**
 * Layout chính của ứng dụng với sidebar và topbar
 * Sử dụng cho tất cả trang sau khi đăng nhập
 */
export function AppLayout() {
  const { sidebarCollapsed } = useUIStore()

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <Sidebar />
      
      {/* Main content area */}
      <div className={cn(
        "flex-1 flex flex-col transition-all duration-300",
        sidebarCollapsed ? "ml-16" : "ml-64"
      )}>
        {/* Top navigation bar */}
        <TopBar />
        
        {/* Page content */}
        <main className="flex-1 overflow-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}