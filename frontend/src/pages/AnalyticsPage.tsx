import { useState } from 'react'
import { ArrowLeft, TrendingUp, Users, Calendar, Target, Activity } from 'lucide-react'
import { Link } from 'react-router-dom'

export default function AnalyticsPage() {
  const [selectedPeriod, setSelectedPeriod] = useState<string>('30d')

  const analyticsCards = [
    {
      title: 'Project Completion Rate',
      value: '87%',
      change: '+5.2%',
      changeType: 'positive',
      icon: Target,
      description: 'Projects completed on time',
    },
    {
      title: 'Team Productivity',
      value: '94%',
      change: '+2.1%',
      changeType: 'positive',
      icon: Activity,
      description: 'Average task completion rate',
    },
    {
      title: 'Active Projects',
      value: '12',
      change: '+2',
      changeType: 'positive',
      icon: TrendingUp,
      description: 'Currently in progress',
    },
    {
      title: 'Team Members',
      value: '24',
      change: '+3',
      changeType: 'positive',
      icon: Users,
      description: 'Active team members',
    },
  ]

  const recentActivities = [
    {
      id: 1,
      type: 'project_completed',
      title: 'Website Redesign Project',
      description: 'Project completed successfully',
      time: '2 hours ago',
      icon: Target,
    },
    {
      id: 2,
      type: 'task_created',
      title: 'New Task Created',
      description: 'Mobile App Development - Phase 2',
      time: '4 hours ago',
      icon: Activity,
    },
    {
      id: 3,
      type: 'user_added',
      title: 'New Team Member',
      description: 'John Doe joined the team',
      time: '1 day ago',
      icon: Users,
    },
    {
      id: 4,
      type: 'milestone_reached',
      title: 'Milestone Achieved',
      description: 'Database Migration - 75% complete',
      time: '2 days ago',
      icon: TrendingUp,
    },
  ]

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between animate-slide-up">
        <div className="flex items-center gap-4">
          <Link
            to="/dashboard"
            className="btn btn-outline hover-lift"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Dashboard
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Analytics</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Comprehensive insights into your project performance and team productivity.
            </p>
          </div>
        </div>
        
        <div className="flex items-center gap-3">
          <select
            value={selectedPeriod}
            onChange={(e) => setSelectedPeriod(e.target.value)}
            className="input"
          >
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="90d">Last 90 days</option>
            <option value="1y">Last year</option>
          </select>
        </div>
      </div>

      {/* Analytics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 animate-slide-up" style={{ animationDelay: '100ms' }}>
        {analyticsCards.map((card, index) => {
          const Icon = card.icon
          return (
            <div key={index} className="card hover-lift">
              <div className="card-content">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                      {card.title}
                    </p>
                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                      {card.value}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      {card.description}
                    </p>
                  </div>
                  <div className="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                    <Icon className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                  </div>
                </div>
                <div className="mt-4 flex items-center">
                  <span className={`text-sm font-medium ${
                    card.changeType === 'positive' 
                      ? 'text-green-600 dark:text-green-400' 
                      : 'text-red-600 dark:text-red-400'
                  }`}>
                    {card.change}
                  </span>
                  <span className="text-sm text-gray-500 dark:text-gray-400 ml-2">
                    vs last period
                  </span>
                </div>
              </div>
            </div>
          )
        })}
      </div>

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-slide-up" style={{ animationDelay: '200ms' }}>
        {/* Project Progress Chart */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Project Progress</h3>
            <p className="card-description">
              Current status of all active projects
            </p>
          </div>
          <div className="card-content">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Website Redesign</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">87%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-blue-600 h-2 rounded-full" style={{ width: '87%' }}></div>
              </div>
              
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Mobile App Development</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">65%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-green-600 h-2 rounded-full" style={{ width: '65%' }}></div>
              </div>
              
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Database Migration</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">42%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-yellow-600 h-2 rounded-full" style={{ width: '42%' }}></div>
              </div>
            </div>
          </div>
        </div>

        {/* Team Performance Chart */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Team Performance</h3>
            <p className="card-description">
              Task completion rates by team member
            </p>
          </div>
          <div className="card-content">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">John Doe</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">95%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-green-600 h-2 rounded-full" style={{ width: '95%' }}></div>
              </div>
              
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Jane Smith</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">88%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-blue-600 h-2 rounded-full" style={{ width: '88%' }}></div>
              </div>
              
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Mike Johnson</span>
                <span className="text-sm text-gray-500 dark:text-gray-400">76%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div className="bg-yellow-600 h-2 rounded-full" style={{ width: '76%' }}></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="card animate-slide-up" style={{ animationDelay: '300ms' }}>
        <div className="card-header">
          <h3 className="card-title">Recent Activity</h3>
          <p className="card-description">
            Latest updates and milestones across your projects
          </p>
        </div>
        <div className="card-content">
          <div className="space-y-4">
            {recentActivities.map((activity) => {
              const Icon = activity.icon
              return (
                <div key={activity.id} className="flex items-center space-x-4">
                  <div className="flex-shrink-0">
                    <div className="w-8 h-8 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center">
                      <Icon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {activity.title}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {activity.description}
                    </p>
                  </div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">
                    {activity.time}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      </div>
    </div>
  )
}
