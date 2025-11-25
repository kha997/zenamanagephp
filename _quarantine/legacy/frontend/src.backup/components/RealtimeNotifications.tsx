import { useState, useEffect } from 'react'
import { Bell, X, CheckCircle, AlertCircle, Info, XCircle } from 'lucide-react'
import { useWebSocket } from '../services/websocketService'
import { cn } from '../lib/utils'

interface Notification {
  id: string
  type: 'success' | 'error' | 'info' | 'warning'
  title: string
  message: string
  timestamp: Date
  read: boolean
  action?: {
    label: string
    onClick: () => void
  }
}

export default function RealtimeNotifications() {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [isOpen, setIsOpen] = useState(false)
  const { subscribe } = useWebSocket()

  useEffect(() => {
    // Subscribe to real-time events
    const unsubscribeProjectCreated = subscribe('project_created', (data: any) => {
      addNotification({
        type: 'success',
        title: 'New Project',
        message: `Project "${data.name}" has been created`,
        action: {
          label: 'View',
          onClick: () => {
            // Navigate to project
            window.location.href = `/projects/${data.id}`
          }
        }
      })
    })

    const unsubscribeProjectUpdated = subscribe('project_updated', (data: any) => {
      addNotification({
        type: 'info',
        title: 'Project Updated',
        message: `Project "${data.name}" has been updated`
      })
    })

    const unsubscribeProjectDeleted = subscribe('project_deleted', (data: any) => {
      addNotification({
        type: 'warning',
        title: 'Project Deleted',
        message: `Project "${data.name}" has been deleted`
      })
    })

    const unsubscribeTaskCreated = subscribe('task_created', (data: any) => {
      addNotification({
        type: 'success',
        title: 'New Task',
        message: `Task "${data.name}" has been created`,
        action: {
          label: 'View',
          onClick: () => {
            window.location.href = `/tasks/${data.id}`
          }
        }
      })
    })

    const unsubscribeTaskUpdated = subscribe('task_updated', (data: any) => {
      addNotification({
        type: 'info',
        title: 'Task Updated',
        message: `Task "${data.name}" has been updated`
      })
    })

    const unsubscribeTaskAssigned = subscribe('task_assigned', (data: any) => {
      addNotification({
        type: 'info',
        title: 'Task Assigned',
        message: `You have been assigned to task "${data.task_name}"`,
        action: {
          label: 'View Task',
          onClick: () => {
            window.location.href = `/tasks/${data.task_id}`
          }
        }
      })
    })

    const unsubscribeNotification = subscribe('notification', (data: any) => {
      addNotification({
        type: data.type || 'info',
        title: data.title || 'Notification',
        message: data.message
      })
    })

    return () => {
      unsubscribeProjectCreated()
      unsubscribeProjectUpdated()
      unsubscribeProjectDeleted()
      unsubscribeTaskCreated()
      unsubscribeTaskUpdated()
      unsubscribeTaskAssigned()
      unsubscribeNotification()
    }
  }, [subscribe])

  const addNotification = (notification: Omit<Notification, 'id' | 'timestamp' | 'read'>) => {
    const newNotification: Notification = {
      ...notification,
      id: Math.random().toString(36).substr(2, 9),
      timestamp: new Date(),
      read: false
    }

    setNotifications(prev => [newNotification, ...prev].slice(0, 50)) // Keep last 50 notifications
  }

  const markAsRead = (id: string) => {
    setNotifications(prev =>
      prev.map(notification =>
        notification.id === id ? { ...notification, read: true } : notification
      )
    )
  }

  const markAllAsRead = () => {
    setNotifications(prev =>
      prev.map(notification => ({ ...notification, read: true }))
    )
  }

  const removeNotification = (id: string) => {
    setNotifications(prev => prev.filter(notification => notification.id !== id))
  }

  const clearAllNotifications = () => {
    setNotifications([])
  }

  const unreadCount = notifications.filter(n => !n.read).length

  const getIcon = (type: string) => {
    switch (type) {
      case 'success':
        return <CheckCircle className="h-5 w-5 text-green-500" />
      case 'error':
        return <XCircle className="h-5 w-5 text-red-500" />
      case 'warning':
        return <AlertCircle className="h-5 w-5 text-yellow-500" />
      default:
        return <Info className="h-5 w-5 text-blue-500" />
    }
  }

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'success':
        return 'border-l-green-500 bg-green-50 dark:bg-green-900/20'
      case 'error':
        return 'border-l-red-500 bg-red-50 dark:bg-red-900/20'
      case 'warning':
        return 'border-l-yellow-500 bg-yellow-50 dark:bg-yellow-900/20'
      default:
        return 'border-l-blue-500 bg-blue-50 dark:bg-blue-900/20'
    }
  }

  return (
    <div className="relative">
      {/* Notification Bell */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative p-2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors duration-200"
      >
        <Bell className="h-5 w-5" />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {/* Notifications Dropdown */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
          <div className="p-4 border-b border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Notifications
              </h3>
              <div className="flex items-center space-x-2">
                {unreadCount > 0 && (
                  <button
                    onClick={markAllAsRead}
                    className="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                  >
                    Mark all read
                  </button>
                )}
                <button
                  onClick={clearAllNotifications}
                  className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                  Clear all
                </button>
              </div>
            </div>
          </div>

          <div className="max-h-96 overflow-y-auto">
            {notifications.length === 0 ? (
              <div className="p-4 text-center text-gray-500 dark:text-gray-400">
                No notifications
              </div>
            ) : (
              <div className="divide-y divide-gray-200 dark:divide-gray-700">
                {notifications.map((notification) => (
                  <div
                    key={notification.id}
                    className={cn(
                      'p-4 border-l-4 transition-colors duration-200',
                      getTypeColor(notification.type),
                      !notification.read && 'bg-gray-50 dark:bg-gray-700/50'
                    )}
                  >
                    <div className="flex items-start space-x-3">
                      {getIcon(notification.type)}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {notification.title}
                          </p>
                          <div className="flex items-center space-x-2">
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                              {notification.timestamp.toLocaleTimeString()}
                            </span>
                            <button
                              onClick={() => removeNotification(notification.id)}
                              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                              <X className="h-4 w-4" />
                            </button>
                          </div>
                        </div>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                          {notification.message}
                        </p>
                        {notification.action && (
                          <button
                            onClick={() => {
                              notification.action!.onClick()
                              markAsRead(notification.id)
                            }}
                            className="mt-2 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                          >
                            {notification.action.label}
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Click outside to close */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setIsOpen(false)}
        />
      )}
    </div>
  )
}
