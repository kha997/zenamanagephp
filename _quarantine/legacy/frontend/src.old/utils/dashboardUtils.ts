import { User } from '@/services/authService'

/**
 * Role-based dashboard URL mapping
 */
const ROLE_DASHBOARD_MAP: Record<string, string> = {
  'SuperAdmin': '/admin',
  'Admin': '/admin',
  'PM': '/pm',
  'Designer': '/design',
  'SiteEngineer': '/site',
  'QC': '/qc',
  'Procurement': '/proc',
  'Finance': '/finance',
  'Client': '/client'
}

/**
 * Get dashboard URL based on user's primary role
 */
export function getDashboardUrl(user: User): string {
  // Get the first role (primary role)
  const primaryRole = user.roles[0]
  
  if (!primaryRole) {
    return '/dashboard'
  }

  return ROLE_DASHBOARD_MAP[primaryRole] || '/dashboard'
}

/**
 * Get all accessible dashboard URLs for user
 */
export function getAccessibleDashboards(user: User): Array<{ role: string; url: string; label: string }> {
  const dashboards = [
    { role: 'SuperAdmin', url: '/admin', label: 'Admin Dashboard' },
    { role: 'Admin', url: '/admin', label: 'Admin Dashboard' },
    { role: 'PM', url: '/pm', label: 'Project Manager Dashboard' },
    { role: 'Designer', url: '/design', label: 'Designer Dashboard' },
    { role: 'SiteEngineer', url: '/site', label: 'Site Engineer Dashboard' },
    { role: 'QC', url: '/qc', label: 'Quality Control Dashboard' },
    { role: 'Procurement', url: '/proc', label: 'Procurement Dashboard' },
    { role: 'Finance', url: '/finance', label: 'Finance Dashboard' },
    { role: 'Client', url: '/client', label: 'Client Dashboard' }
  ]

  return dashboards.filter(dashboard => 
    user.roles.includes(dashboard.role)
  )
}

/**
 * Check if user can access a specific dashboard
 */
export function canAccessDashboard(user: User, dashboardRole: string): boolean {
  return user.roles.includes(dashboardRole)
}

/**
 * Get role display name
 */
export function getRoleDisplayName(role: string): string {
  const roleNames: Record<string, string> = {
    'SuperAdmin': 'Super Administrator',
    'Admin': 'Administrator',
    'PM': 'Project Manager',
    'Designer': 'Designer',
    'SiteEngineer': 'Site Engineer',
    'QC': 'Quality Control',
    'Procurement': 'Procurement',
    'Finance': 'Finance',
    'Client': 'Client'
  }

  return roleNames[role] || role
}

/**
 * Get role color for UI
 */
export function getRoleColor(role: string): string {
  const roleColors: Record<string, string> = {
    'SuperAdmin': 'bg-red-100 text-red-800',
    'Admin': 'bg-purple-100 text-purple-800',
    'PM': 'bg-blue-100 text-blue-800',
    'Designer': 'bg-green-100 text-green-800',
    'SiteEngineer': 'bg-yellow-100 text-yellow-800',
    'QC': 'bg-orange-100 text-orange-800',
    'Procurement': 'bg-indigo-100 text-indigo-800',
    'Finance': 'bg-pink-100 text-pink-800',
    'Client': 'bg-gray-100 text-gray-800'
  }

  return roleColors[role] || 'bg-gray-100 text-gray-800'
}

/**
 * Get role icon
 */
export function getRoleIcon(role: string): string {
  const roleIcons: Record<string, string> = {
    'SuperAdmin': '👑',
    'Admin': '⚙️',
    'PM': '📋',
    'Designer': '🎨',
    'SiteEngineer': '🏗️',
    'QC': '🔍',
    'Procurement': '📦',
    'Finance': '💰',
    'Client': '👤'
  }

  return roleIcons[role] || '👤'
}
