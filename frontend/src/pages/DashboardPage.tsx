import { useAuthStore } from '../stores/authStore'
import { useDashboardStats, useProjects } from '../hooks/useApi'
import { LoadingState } from '../components/LoadingStates'
import { motion } from 'framer-motion'
import { Users, FolderOpen, CheckSquare, TrendingUp, AlertTriangle } from 'lucide-react'
import { fadeInUp, staggerContainer, staggerItem } from '../utils/animations'
import { AlertBanner } from '../components/dashboard/AlertBanner'
import { RecentProjectsCard } from '../components/dashboard/RecentProjectsCard'
import { RecentActivityCard } from '../components/dashboard/RecentActivityCard'
import { TeamStatusCard } from '../components/dashboard/TeamStatusCard'
import { DashboardChart } from '../components/dashboard/DashboardChart'
import { 
  useRecentProjects, 
  useRecentActivity, 
  useTeamStatus, 
  useDashboardChart, 
  useDashboardAlerts 
} from '../entities/dashboard/hooks'

export default function DashboardPage() {
  const { user } = useAuthStore()
  const { data: dashboardData, loading, error, refetch } = useDashboardStats()
  
  // New hooks for dashboard components
  const { data: alertData, isLoading: alertsLoading } = useDashboardAlerts()
  const { data: recentProjects, isLoading: projectsLoading, error: projectsError } = useRecentProjects(5)
  const { data: recentActivity, isLoading: activityLoading, error: activityError } = useRecentActivity(10)
  const { data: teamStatus, isLoading: teamLoading, error: teamError } = useTeamStatus()
  const { data: progressChart, isLoading: progressLoading, error: progressError } = useDashboardChart('project-progress', '30d')
  const { data: completionChart, isLoading: completionLoading, error: completionError } = useDashboardChart('task-completion', '30d')

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
      data-testid="dashboard"
    >
      {/* Alert Banner */}
      <AlertBanner
        alerts={alertData?.data || []}
        loading={alertsLoading}
        onDismissAll={() => console.log('Dismiss all alerts')}
        dataTestId="alert-banner"
      />

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

      {/* Row 1: Recent Projects + Recent Activity */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2" data-testid="dashboard-row-1">
        <RecentProjectsCard
          projects={recentProjects?.data}
          loading={projectsLoading}
          error={projectsError as Error | null}
          dataTestId="recent-projects-widget"
        />
        <RecentActivityCard
          activities={recentActivity?.data}
          loading={activityLoading}
          error={activityError as Error | null}
          dataTestId="activity-feed-widget"
          onViewAll={() => window.location.href = '/app/activity'}
        />
      </div>

      {/* Row 2: Project Progress Chart + Quick Actions */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2" data-testid="dashboard-row-2">
        <DashboardChart
          type="project-progress"
          title="Project Progress"
          data={progressChart?.data}
          loading={progressLoading}
          error={progressError as Error | null}
          dataTestId="chart-project-progress"
        />
        
        {/* Quick Actions */}
        <div className="card" data-testid="quick-actions-widget">
          <div className="card-header">
            <h3 className="card-title">Quick Actions</h3>
            <p className="card-description">
              Common tasks and shortcuts.
            </p>
          </div>
          <div className="card-content">
            <div className="space-y-3">
              <button 
                onClick={() => window.location.href = '/app/projects/create'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                data-testid="quick-action-create-project"
              >
                Create new project
              </button>
              <button 
                onClick={() => window.location.href = '/app/users/create'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                data-testid="quick-action-add-member"
              >
                Add team member
              </button>
              <button 
                onClick={() => window.location.href = '/app/reports'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                data-testid="quick-action-generate-report"
              >
                Generate report
              </button>
              <button 
                onClick={() => window.location.href = '/app/analytics'}
                className="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                data-testid="quick-action-view-analytics"
              >
                View analytics
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Row 3: Team Status + Task Completion Chart */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2" data-testid="dashboard-row-3">
        <TeamStatusCard
          members={teamStatus?.data}
          loading={teamLoading}
          error={teamError as Error | null}
          dataTestId="team-status-widget"
        />
        <DashboardChart
          type="task-completion"
          title="Task Completion"
          data={completionChart?.data}
          loading={completionLoading}
          error={completionError as Error | null}
          dataTestId="chart-task-completion"
        />
      </div>
    </motion.div>
  )
}
