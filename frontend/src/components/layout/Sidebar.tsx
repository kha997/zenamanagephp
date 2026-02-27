import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/store/auth'
import {
  ArrowRightOnRectangleIcon,
  BellIcon,
  ClipboardDocumentListIcon,
  Cog6ToothIcon,
  DocumentTextIcon,
  FolderIcon,
} from '@/lib/heroicons'

type NavItem = {
  name: string
  href: string
  icon: React.ComponentType<React.SVGProps<SVGSVGElement>>
  visible: boolean
}

interface SidebarProps {
  className?: string
}

const isAdminRole = (roleName: string) => {
  const normalized = roleName.toLowerCase()
  return normalized === 'admin' || normalized === 'superadmin' || normalized === 'super_admin'
}

export const Sidebar: React.FC<SidebarProps> = ({ className }) => {
  const location = useLocation()
  const { logout, user } = useAuthStore()

  const roleNames = (user?.roles || []).map((role) => role.name)
  const permissions = user?.permissions || []
  const isAdmin = roleNames.some(isAdminRole)

  const canViewTemplates = isAdmin || permissions.some((permission) => permission.startsWith('template.'))
  const canViewWork = isAdmin || permissions.some((permission) => permission.startsWith('work.'))

  const navigation: NavItem[] = [
    { name: 'Projects', href: '/projects', icon: FolderIcon, visible: true },
    { name: 'Tasks', href: '/tasks', icon: ClipboardDocumentListIcon, visible: true },
    { name: 'Change Requests', href: '/change-requests', icon: DocumentTextIcon, visible: true },
    { name: 'Templates', href: '/templates', icon: DocumentTextIcon, visible: true },
    { name: 'Interaction Logs', href: '/interaction-logs', icon: DocumentTextIcon, visible: true },
    { name: 'Work Templates', href: '/work-templates', icon: DocumentTextIcon, visible: canViewTemplates },
    { name: 'Work Instances', href: '/work-instances', icon: ClipboardDocumentListIcon, visible: canViewWork },
    { name: 'Notifications', href: '/notifications', icon: BellIcon, visible: true },
    { name: 'Settings', href: '/settings/general', icon: Cog6ToothIcon, visible: true },
  ]

  const visibleNavigation = navigation.filter((item) => item.visible)

  const isActive = (href: string) => {
    return location.pathname === href || location.pathname.startsWith(`${href}/`)
  }

  return (
    <div className={cn('flex h-full w-64 flex-col bg-gray-900', className)}>
      <div className="flex h-16 shrink-0 items-center px-6">
        <h1 className="text-xl font-bold text-white">Z.E.N.A</h1>
      </div>

      <nav className="flex flex-1 flex-col px-6 pb-4">
        <ul role="list" className="flex flex-1 flex-col gap-y-7">
          <li>
            <ul role="list" className="-mx-2 space-y-1">
              {visibleNavigation.map((item) => {
                const active = isActive(item.href)
                return (
                  <li key={item.name}>
                    <Link
                      to={item.href}
                      className={cn(
                        active ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white',
                        'group flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6'
                      )}
                    >
                      <item.icon className="h-6 w-6 shrink-0" aria-hidden="true" />
                      {item.name}
                    </Link>
                  </li>
                )
              })}
            </ul>
          </li>

          <li className="mt-auto">
            <button
              onClick={logout}
              className="group -mx-2 flex w-full gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-gray-400 hover:bg-gray-800 hover:text-white"
            >
              <ArrowRightOnRectangleIcon className="h-6 w-6 shrink-0" aria-hidden="true" />
              Logout
            </button>
          </li>
        </ul>
      </nav>
    </div>
  )
}

export default Sidebar
