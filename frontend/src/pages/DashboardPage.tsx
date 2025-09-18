import { useAuthStore } from '../stores/authStore'
import { useDashboardStats, useProjects } from '../hooks/useApi'
import { LoadingState } from '../components/LoadingStates'
import { motion } from 'framer-motion'
import { Users, FolderOpen, CheckSquare, TrendingUp, AlertTriangle } from 'lucide-react'
import { fadeInUp, staggerContainer, staggerItem } from '../utils/animations'

export default function DashboardPage() {
  const { user } = useAuthStore()
  const { data: dashboardData, loading, error, refetch } = useDashboardStats()
  const { data: projectsData, loading: projectsLoading } = useProjects({}, 1, 5)

  // Mock stats fallback
  const mockStats = [
    {
      name: 'Total Users',
      value: '12',
      change: '+12%',
      changeType: 'positive',
      icon: Users,
    },
    {
      name: 'Active Projects',
      value: '8',
      change: '+2',
      changeType: 'positive',
      icon: FolderOpen,
    },
    {
      name: 'Completed Tasks',
      value: '156',
      change: '+23%',
      changeType: 'positive',
      icon: CheckSquare,
    },
    {
      name: 'Productivity',
      value: '94%',
      change: '+5%',
      changeType: 'positive',
      icon: TrendingUp,
    },
  ]

  // Use real data if available, otherwise fallback to mock data
  const stats = dashboardData ? [
    {
      name: 'Total Users',
      value: dashboardData.users.total.toString(),
      change: `${dashboardData.users.active}/${dashboardData.users.total}`,
      changeType: 'positive',
      icon: Users,
    },
    {
      name: 'Active Projects',
      value: dashboardData.projects.active.toString(),
      change: `${dashboardData.projects.completed} completed`,
      changeType: 'positive',
      icon: FolderOpen,
    },
    {
      name: 'Completed Tasks',
      value: dashboardData.tasks.completed.toString(),
      change: `${dashboardData.tasks.in_progress} in progress`,
      changeType: 'positive',
      icon: CheckSquare,
    },
    {
      name: 'Overdue Tasks',
      value: dashboardData.tasks.overdue.toString(),
      change: 'Needs attention',
      changeType: dashboardData.tasks.overdue > 0 ? 'negative' : 'positive',
      icon: AlertTriangle,
    },
  ] : mockStats

  return (
    <motion.div 
      className="space-y-6"
      initial="initial"
      animate="animate"
      variants={staggerContainer}
    >
      {/* Header */}
      <motion.div variants={fadeInUp}>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Welcome back, {user?.name}! Here's what's happening with your projects.
        </p>
      </motion.div>

      {/* Stats */}
      <LoadingState 
        loading={loading} 
        error={error} 
        onRetry={refetch}
        loadingText="Loading dashboard stats..."
      >
        <motion.div 
          className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
          variants={staggerContainer}
        >
          {stats.map((stat) => {
            const Icon = stat.icon
            return (
              <motion.div 
                key={stat.name}
                className="card hover-lift"
                variants={staggerItem}
                whileHover={{ 
                  y: -4, 
                  boxShadow: "0 8px 24px rgba(0,0,0,0.08)", 
                  transition: { duration: 0.2, ease: [0.4, 0, 0.2, 1] } 
                }}
              >
              <div className="card-content">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <Icon className="h-8 w-8 text-primary-600 dark:text-primary-400" />
                  </div>
                  <div className="ml-5 w-0 flex-1">
                    <dl>
                      <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        {stat.name}
                      </dt>
                      <dd className="flex items-baseline">
                        <div className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                          {stat.value}
                        </div>
                        <div className={`ml-2 flex items-baseline text-sm font-semibold ${
                          stat.changeType === 'positive' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                        }`}>
                          {stat.change}
                        </div>
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </motion.div>
          )
          })}
        </motion.div>
      </LoadingState>

      {/* Recent Activity */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Recent Projects</h3>
            <p className="card-description">
              Your latest project updates and milestones.
            </p>
          </div>
          <div className="card-content">
            <LoadingState 
              loading={projectsLoading} 
              error={null}
              loadingText="Loading recent projects..."
            >
              <div className="space-y-4">
                {projectsData?.data && projectsData.data.length > 0 ? (
                  projectsData.data.slice(0, 3).map((project) => {
                    const getStatusColor = (status: string) => {
                      switch (status) {
                        case 'completed': return 'bg-green-400'
                        case 'active': return 'bg-yellow-400'
                        case 'planning': return 'bg-blue-400'
                        case 'on_hold': return 'bg-orange-400'
                        case 'cancelled': return 'bg-red-400'
                        default: return 'bg-gray-400'
                      }
                    }
                    
                    const getStatusText = (status: string) => {
                      switch (status) {
                        case 'completed': return 'Project completed successfully'
                        case 'active': return `In progress - ${project.progress}% complete`
                        case 'planning': return 'Planning phase started'
                        case 'on_hold': return 'Project on hold'
                        case 'cancelled': return 'Project cancelled'
                        default: return 'Status unknown'
                      }
                    }
                    
                    const timeAgo = (date: string) => {
                      const now = new Date()
                      const projectDate = new Date(date)
                      const diffInHours = Math.floor((now.getTime() - projectDate.getTime()) / (1000 * 60 * 60))
                      
                      if (diffInHours < 1) return 'Just now'
                      if (diffInHours < 24) return `${diffInHours}h ago`
                      const diffInDays = Math.floor(diffInHours / 24)
                      return `${diffInDays}d ago`
                    }
                    
                    return (
                      <div key={project.id} className="flex items-center space-x-4">
                        <div className="flex-shrink-0">
                          <div className={`h-2 w-2 ${getStatusColor(project.status)} rounded-full`}></div>
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-gray-900">{project.name}</p>
                          <p className="text-sm text-gray-500">{getStatusText(project.status)}</p>
                        </div>
                        <div className="text-sm text-gray-500">{timeAgo(project.updated_at)}</div>
                      </div>
                    )
                  })
                ) : (
                  <div className="text-center py-4">
                    <p className="text-sm text-gray-500">No recent projects found</p>
                    <button 
                      onClick={() => window.location.href = '/projects/create'}
                      className="mt-2 text-sm text-blue-600 hover:text-blue-800"
                    >
                      Create your first project
                    </button>
                  </div>
                )}
              </div>
            </LoadingState>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Quick Actions</h3>
            <p className="card-description">
              Common tasks and shortcuts.
            </p>
          </div>
          <div className="card-content">
            <div className="space-y-3">
              <button 
                onClick={() => window.location.href = '/projects/create'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
              >
                Create new project
              </button>
              <button 
                onClick={() => window.location.href = '/users/create'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
              >
                Add team member
              </button>
              <button 
                onClick={() => window.location.href = '/reports'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
              >
                Generate report
              </button>
              <button 
                onClick={() => window.location.href = '/analytics'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
              >
                View analytics
              </button>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  )
}
