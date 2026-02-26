import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/ui/Input'
import { 
  Search, 
  Filter, 
  Plus, 
  MoreVertical, 
  Edit, 
  Trash2, 
  Eye, 
  UserCheck, 
  UserX,
  SwipeLeft,
  SwipeRight,
  TouchIcon,
  Smartphone,
  Tablet,
  Monitor
} from 'lucide-react'
import { cn } from '../lib/utils'
import { slideUpMobile, slideDownMobile, touchFeedback, swipeAnimation } from '../utils/animations'
import { useTouchGestures, SWIPE_DIRECTIONS, GESTURE_THRESHOLDS } from '../services/touchGestureService'
import { MobileDrawer, MobileSearch, MobileFilter, MobileActionSheet, MobileCard, MobilePullToRefresh } from './MobileComponents'
import { LoadingState, ErrorMessage, EmptyState, SkeletonTable } from './LoadingStates'
import { formatDate } from '../lib/utils'
import toast from 'react-hot-toast'

interface MobileUsersPageProps {
  className?: string
}

export const MobileUsersPage: React.FC<MobileUsersPageProps> = ({ className }) => {
  const [users, setUsers] = useState([
    { id: 1, name: 'John Doe', email: 'john@example.com', role: 'Admin', status: 'active', lastLogin: '2024-01-15' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'User', status: 'inactive', lastLogin: '2024-01-10' },
    { id: 3, name: 'Bob Johnson', email: 'bob@example.com', role: 'Manager', status: 'active', lastLogin: '2024-01-14' },
  ])
  
  const [filters, setFilters] = useState({
    search: '',
    role: '',
    status: ''
  })
  
  const [showSearch, setShowSearch] = useState(false)
  const [showFilter, setShowFilter] = useState(false)
  const [showActionSheet, setShowActionSheet] = useState(false)
  const [selectedUser, setSelectedUser] = useState<any>(null)
  const [isRefreshing, setIsRefreshing] = useState(false)

  // Touch gestures for cards
  const gestureRef = useTouchGestures(
    {
      onSwipe: (gesture) => {
        if (gesture.direction === SWIPE_DIRECTIONS.LEFT) {
          // Swipe left - show actions
          console.log('Swipe left on user card')
        } else if (gesture.direction === SWIPE_DIRECTIONS.RIGHT) {
          // Swipe right - hide actions
          console.log('Swipe right on user card')
        }
      },
      onTap: (point) => {
        console.log('Tap on user card')
      },
      onLongPress: (point) => {
        console.log('Long press on user card')
        setShowActionSheet(true)
      }
    },
    {
      swipeThreshold: GESTURE_THRESHOLDS.SWIPE,
      longPressDelay: GESTURE_THRESHOLDS.LONG_PRESS
    }
  )

  const handleRefresh = async () => {
    setIsRefreshing(true)
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    setIsRefreshing(false)
    toast.success('Users refreshed!')
  }

  const handleUserAction = (action: string, user: any) => {
    switch (action) {
      case 'edit':
        toast.success(`Edit user: ${user.name}`)
        break
      case 'delete':
        toast.success(`Delete user: ${user.name}`)
        break
      case 'view':
        toast.success(`View user: ${user.name}`)
        break
      case 'toggle':
        toast.success(`Toggle status: ${user.name}`)
        break
    }
    setShowActionSheet(false)
  }

  const filteredUsers = users.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(filters.search.toLowerCase()) ||
                         user.email.toLowerCase().includes(filters.search.toLowerCase())
    const matchesRole = !filters.role || user.role === filters.role
    const matchesStatus = !filters.status || user.status === filters.status
    
    return matchesSearch && matchesRole && matchesStatus
  })

  return (
    <div className={cn('min-h-screen bg-gray-50 dark:bg-gray-900', className)}>
      {/* Mobile Header */}
      <motion.header
        initial={{ y: -100 }}
        animate={{ y: 0 }}
        transition={{ duration: 0.3 }}
        className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 z-40"
      >
        <div className="flex items-center justify-between px-4 py-3">
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
              Users
            </h1>
            <Badge variant="outline" className="text-xs">
              {filteredUsers.length}
            </Badge>
          </div>
          
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowSearch(true)}
              className="p-2"
            >
              <Search className="h-5 w-5" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowFilter(true)}
              className="p-2"
            >
              <Filter className="h-5 w-5" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="p-2"
            >
              <Plus className="h-5 w-5" />
            </Button>
          </div>
        </div>
      </motion.header>

      {/* Main Content */}
      <MobilePullToRefresh onRefresh={handleRefresh}>
        <div className="p-4 space-y-4">
          {/* Stats Cards */}
          <div className="grid grid-cols-2 gap-3">
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: 0.1 }}
            >
              <Card className="bg-blue-50 border-blue-200">
                <CardContent className="p-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-xs text-blue-600 font-medium">Total Users</p>
                      <p className="text-lg font-bold text-blue-700">{users.length}</p>
                    </div>
                    <UserCheck className="h-6 w-6 text-blue-600" />
                  </div>
                </CardContent>
              </Card>
            </motion.div>
            
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: 0.2 }}
            >
              <Card className="bg-green-50 border-green-200">
                <CardContent className="p-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-xs text-green-600 font-medium">Active</p>
                      <p className="text-lg font-bold text-green-700">
                        {users.filter(u => u.status === 'active').length}
                      </p>
                    </div>
                    <UserCheck className="h-6 w-6 text-green-600" />
                  </div>
                </CardContent>
              </Card>
            </motion.div>
          </div>

          {/* Users List */}
          <div className="space-y-3">
            {filteredUsers.map((user, index) => (
              <motion.div
                key={user.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.1 }}
              >
                <MobileCard
                  onSwipeLeft={() => setSelectedUser(user)}
                  onSwipeRight={() => setSelectedUser(null)}
                  leftAction={
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleUserAction('delete', user)}
                        className="h-8 w-8 p-0"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  }
                  rightAction={
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => handleUserAction('edit', user)}
                        className="h-8 w-8 p-0"
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                    </div>
                  }
                >
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                            {user.name}
                          </h3>
                          <Badge
                            variant={user.status === 'active' ? 'default' : 'secondary'}
                            className="text-xs"
                          >
                            {user.status}
                          </Badge>
                        </div>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                          {user.email}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-500">
                          {user.role} • Last login: {formatDate(user.lastLogin)}
                        </p>
                      </div>
                      
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowActionSheet(true)}
                        className="p-2"
                      >
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                    </div>
                  </CardContent>
                </MobileCard>
              </motion.div>
            ))}
          </div>

          {/* Empty State */}
          {filteredUsers.length === 0 && (
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: 0.3 }}
            >
              <EmptyState
                icon={<UserCheck className="h-12 w-12 text-gray-400" />}
                title="No users found"
                description="Try adjusting your search or filter criteria"
                action={
                  <Button onClick={() => setShowFilter(true)}>
                    <Filter className="h-4 w-4 mr-2" />
                    Adjust Filters
                  </Button>
                }
              />
            </motion.div>
          )}
        </div>
      </MobilePullToRefresh>

      {/* Mobile Search */}
      <MobileSearch
        placeholder="Search users..."
        onSearch={(query) => setFilters(prev => ({ ...prev, search: query }))}
      />

      {/* Mobile Filter */}
      <MobileFilter
        isOpen={showFilter}
        onClose={() => setShowFilter(false)}
        onApply={() => setShowFilter(false)}
        onReset={() => setFilters({ search: '', role: '', status: '' })}
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Role
            </label>
            <select
              value={filters.role}
              onChange={(e) => setFilters(prev => ({ ...prev, role: e.target.value }))}
              className="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Roles</option>
              <option value="Admin">Admin</option>
              <option value="Manager">Manager</option>
              <option value="User">User</option>
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Status
            </label>
            <select
              value={filters.status}
              onChange={(e) => setFilters(prev => ({ ...prev, status: e.target.value }))}
              className="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </MobileFilter>

      {/* Mobile Action Sheet */}
      <MobileActionSheet
        isOpen={showActionSheet}
        onClose={() => setShowActionSheet(false)}
        actions={[
          {
            label: 'View Details',
            icon: <Eye className="h-4 w-4" />,
            onClick: () => handleUserAction('view', selectedUser)
          },
          {
            label: 'Edit User',
            icon: <Edit className="h-4 w-4" />,
            onClick: () => handleUserAction('edit', selectedUser)
          },
          {
            label: 'Toggle Status',
            icon: <UserCheck className="h-4 w-4" />,
            onClick: () => handleUserAction('toggle', selectedUser)
          },
          {
            label: 'Delete User',
            icon: <Trash2 className="h-4 w-4" />,
            onClick: () => handleUserAction('delete', selectedUser),
            variant: 'destructive'
          }
        ]}
        title="User Actions"
      />

      {/* Gesture Hints */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="fixed bottom-20 left-4 right-4 z-30"
      >
        <Card className="bg-blue-50 border-blue-200">
          <CardContent className="p-3">
            <div className="flex items-center gap-2 text-blue-700 text-sm">
              <TouchIcon className="h-4 w-4" />
              <span>Swipe left/right for actions • Long press for menu</span>
            </div>
          </CardContent>
        </Card>
      </motion.div>
    </div>
  )
}

export default MobileUsersPage
