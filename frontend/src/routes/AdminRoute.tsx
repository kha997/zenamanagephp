import { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { usePermissions } from '@/hooks/usePermissions'
import { LoadingSpinner } from '@/components/ui/loading-spinner'

interface AdminRouteProps {
  children: ReactNode
}

/**
 * Component bảo vệ admin route - chỉ cho phép admin truy cập
 * Sử dụng RBAC để kiểm tra quyền admin
 */
export function AdminRoute({ children }: AdminRouteProps) {
  const { can, isLoading } = usePermissions()

  // Hiển thị loading trong khi kiểm tra permissions
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  // Kiểm tra quyền admin system
  if (!can('system.admin')) {
    return <Navigate to="/" replace />
  }

  return <>{children}</>
}