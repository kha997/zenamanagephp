import { ReactNode, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/store/auth.store'
import { getDashboardUrl, getAccessibleDashboards, getRoleDisplayName, getRoleColor, getRoleIcon } from '@/utils/dashboardUtils'
import { ConditionalRender } from '@/routes/RoleGuard'
import { 
  LayoutDashboard, 
  Users, 
  FolderOpen, 
  CheckSquare, 
  FileText, 
  Settings, 
  LogOut, 
  Menu, 
  X,
  Bell,
  Search
} from 'lucide-react'

interface ZenaLayoutProps {
  children: ReactNode
}

export function ZenaLayout({ children }: ZenaLayoutProps) {
  const { user, logout } = useAuthStore()
  const location = useLocation()
  const navigate = useNavigate()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  const handleLogout = async () => {
    await logout()
    navigate('/auth/login')
  }

  if (!user) {
    return <div>Loading...</div>
  }

  const accessibleDashboards = getAccessibleDashboards(user)
  const currentDashboard = accessibleDashboards.find(d => location.pathname.startsWith(d.url))

  // Navigation items based on permissions
  const navigationItems = [
    {
      name: 'Dashboard',
      icon: LayoutDashboard,
      href: getDashboardUrl(user),
      permission: 'dashboard.view'
    },
    {
      name: 'Projects',
      icon: FolderOpen,
      href: '/projects',
      permission: 'project.read'
    },
    {
      name: 'Tasks',
      icon: CheckSquare,
      href: '/tasks',
      permission: 'task.read'
    },
    {
      name: 'Users',
      icon: Users,
      href: '/users',
      permission: 'admin.user.manage'
    },
    {
      name: 'Documents',
      icon: FileText,
      href: '/documents',
      permission: 'drawing.read'
    },
    {
      name: 'Settings',
      icon: Settings,
      href: '/settings',
      permission: 'admin.settings'
    }
  ]

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div 
          className="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <div className={`fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 ${
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      }`}>
        <div className="flex flex-col h-full">
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b">
            <div className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">Z</span>
              </div>
              <span className="text-xl font-bold text-gray-900">Z.E.N.A</span>
            </div>
            <button
              onClick={() => setSidebarOpen(false)}
              className="lg:hidden p-1 rounded-md text-gray-400 hover:text-gray-600"
            >
              <X className="w-6 h-6" />
            </button>
          </div>

          {/* User Info */}
          <div className="p-4 border-b">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                {user.avatar ? (
                  <img src={user.avatar} alt={user.name} className="w-10 h-10 rounded-full" />
                ) : (
                  <span className="text-gray-600 font-medium">{user.name.charAt(0)}</span>
                )}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 truncate">{user.name}</p>
                <div className="flex flex-wrap gap-1 mt-1">
                  {user.roles.map((role) => (
                    <span
                      key={role}
                      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${getRoleColor(role)}`}
                    >
                      <span className="mr-1">{getRoleIcon(role)}</span>
                      {getRoleDisplayName(role)}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Navigation */}
          <nav className="flex-1 p-4 space-y-2 overflow-y-auto">
            {/* Dashboard Switcher */}
            {accessibleDashboards.length > 1 && (
              <div className="mb-4">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                  Dashboards
                </p>
                <div className="space-y-1">
                  {accessibleDashboards.map((dashboard) => (
                    <Link
                      key={dashboard.role}
                      to={dashboard.url}
                      className={`flex items-center px-3 py-2 text-sm rounded-md transition-colors ${
                        location.pathname.startsWith(dashboard.url)
                          ? 'bg-blue-100 text-blue-700'
                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                      }`}
                    >
                      <span className="mr-2">{getRoleIcon(dashboard.role)}</span>
                      {dashboard.label}
                    </Link>
                  ))}
                </div>
              </div>
            )}

            {/* Main Navigation */}
            <div>
              <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                Navigation
              </p>
              <div className="space-y-1">
                {navigationItems.map((item) => (
                  <ConditionalRender
                    key={item.name}
                    requiredPermissions={[item.permission]}
                  >
                    <Link
                      to={item.href}
                      className={`flex items-center px-3 py-2 text-sm rounded-md transition-colors ${
                        location.pathname.startsWith(item.href)
                          ? 'bg-blue-100 text-blue-700'
                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                      }`}
                    >
                      <item.icon className="w-5 h-5 mr-3" />
                      {item.name}
                    </Link>
                  </ConditionalRender>
                ))}
              </div>
            </div>
          </nav>

          {/* Footer */}
          <div className="p-4 border-t">
            <button
              onClick={handleLogout}
              className="flex items-center w-full px-3 py-2 text-sm text-gray-600 rounded-md hover:bg-gray-100 hover:text-gray-900 transition-colors"
            >
              <LogOut className="w-5 h-5 mr-3" />
              Logout
            </button>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className="lg:pl-64">
        {/* Top bar */}
        <div className="sticky top-0 z-10 bg-white shadow-sm border-b">
          <div className="flex items-center justify-between px-4 py-3">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-600"
              >
                <Menu className="w-6 h-6" />
              </button>
              
              <div className="hidden lg:block">
                <h1 className="text-2xl font-semibold text-gray-900">
                  {currentDashboard?.label || 'Dashboard'}
                </h1>
              </div>
            </div>

            <div className="flex items-center space-x-4">
              {/* Search */}
              <div className="hidden md:block">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search..."
                    className="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
              </div>

              {/* Notifications */}
              <button className="p-2 text-gray-400 hover:text-gray-600 relative">
                <Bell className="w-6 h-6" />
                <span className="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
              </button>

              {/* User menu */}
              <div className="flex items-center space-x-2">
                <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                  {user.avatar ? (
                    <img src={user.avatar} alt={user.name} className="w-8 h-8 rounded-full" />
                  ) : (
                    <span className="text-gray-600 text-sm font-medium">{user.name.charAt(0)}</span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Page content */}
        <main className="p-6">
          {children}
        </main>
      </div>
    </div>
  )
}
