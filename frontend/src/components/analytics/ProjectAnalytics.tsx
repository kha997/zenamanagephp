import { useState, useEffect } from 'react'
import { 
  LineChart, 
  Line, 
  AreaChart, 
  Area, 
  BarChart, 
  Bar, 
  PieChart, 
  Pie, 
  Cell, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  Legend
} from 'recharts'
import ChartCard from './ChartCard'
import { useQuery } from '@tanstack/react-query'
import { projectService } from '../../services/projectService'

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4']

export default function ProjectAnalytics() {
  const [timeRange, setTimeRange] = useState<'7d' | '30d' | '90d' | '1y'>('30d')
  
  const { data: projects } = useQuery({
    queryKey: ['projects'],
    queryFn: () => projectService.getProjects({ per_page: 100 }),
  })

  // Mock data for analytics
  const projectProgressData = [
    { name: 'Jan', completed: 12, inProgress: 8, planning: 5 },
    { name: 'Feb', completed: 18, inProgress: 12, planning: 3 },
    { name: 'Mar', completed: 25, inProgress: 15, planning: 2 },
    { name: 'Apr', completed: 32, inProgress: 18, planning: 4 },
    { name: 'May', completed: 28, inProgress: 22, planning: 6 },
    { name: 'Jun', completed: 35, inProgress: 25, planning: 3 },
  ]

  const projectStatusData = [
    { name: 'Active', value: 45, color: '#10B981' },
    { name: 'Planning', value: 20, color: '#3B82F6' },
    { name: 'On Hold', value: 15, color: '#F59E0B' },
    { name: 'Completed', value: 35, color: '#6B7280' },
    { name: 'Cancelled', value: 5, color: '#EF4444' },
  ]

  const budgetData = [
    { name: 'Jan', budget: 50000, actual: 45000, remaining: 5000 },
    { name: 'Feb', budget: 60000, actual: 55000, remaining: 5000 },
    { name: 'Mar', budget: 70000, actual: 65000, remaining: 5000 },
    { name: 'Apr', budget: 80000, actual: 75000, remaining: 5000 },
    { name: 'May', budget: 90000, actual: 85000, remaining: 5000 },
    { name: 'Jun', budget: 100000, actual: 95000, remaining: 5000 },
  ]

  const teamPerformanceData = [
    { name: 'Team A', projects: 12, completed: 8, efficiency: 85 },
    { name: 'Team B', projects: 15, completed: 12, efficiency: 92 },
    { name: 'Team C', projects: 10, completed: 7, efficiency: 78 },
    { name: 'Team D', projects: 18, completed: 14, efficiency: 88 },
  ]

  const totalProjects = projects?.data?.length || 0
  const completedProjects = projects?.data?.filter(p => p.status === 'completed').length || 0
  const activeProjects = projects?.data?.filter(p => p.status === 'active').length || 0
  const completionRate = totalProjects > 0 ? Math.round((completedProjects / totalProjects) * 100) : 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Project Analytics</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Insights and metrics for project performance
          </p>
        </div>
        <div className="flex items-center space-x-2">
          {(['7d', '30d', '90d', '1y'] as const).map((range) => (
            <button
              key={range}
              onClick={() => setTimeRange(range)}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                timeRange === range
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {range}
            </button>
          ))}
        </div>
      </div>

      {/* Key Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="card">
          <div className="card-content text-center">
            <div className="text-3xl font-bold text-primary-600 dark:text-primary-400">
              {totalProjects}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Total Projects</div>
          </div>
        </div>
        
        <div className="card">
          <div className="card-content text-center">
            <div className="text-3xl font-bold text-green-600 dark:text-green-400">
              {completedProjects}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Completed</div>
          </div>
        </div>
        
        <div className="card">
          <div className="card-content text-center">
            <div className="text-3xl font-bold text-blue-600 dark:text-blue-400">
              {activeProjects}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Active</div>
          </div>
        </div>
        
        <div className="card">
          <div className="card-content text-center">
            <div className="text-3xl font-bold text-purple-600 dark:text-purple-400">
              {completionRate}%
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Completion Rate</div>
          </div>
        </div>
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Project Progress Over Time */}
        <ChartCard
          title="Project Progress Over Time"
          value={`${completionRate}%`}
          change={12}
          changeType="increase"
        >
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={projectProgressData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Area type="monotone" dataKey="completed" stackId="1" stroke="#10B981" fill="#10B981" fillOpacity={0.6} />
              <Area type="monotone" dataKey="inProgress" stackId="1" stroke="#3B82F6" fill="#3B82F6" fillOpacity={0.6} />
              <Area type="monotone" dataKey="planning" stackId="1" stroke="#F59E0B" fill="#F59E0B" fillOpacity={0.6} />
            </AreaChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* Project Status Distribution */}
        <ChartCard
          title="Project Status Distribution"
          value={`${totalProjects} projects`}
        >
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={projectStatusData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {projectStatusData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* Budget vs Actual */}
        <ChartCard
          title="Budget vs Actual Spending"
          value="$95,000"
          change={-5}
          changeType="decrease"
        >
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={budgetData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Bar dataKey="budget" fill="#3B82F6" name="Budget" />
              <Bar dataKey="actual" fill="#10B981" name="Actual" />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* Team Performance */}
        <ChartCard
          title="Team Performance"
          value="88% avg"
          change={8}
          changeType="increase"
        >
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={teamPerformanceData} layout="horizontal">
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" domain={[0, 100]} />
              <YAxis dataKey="name" type="category" width={80} />
              <Tooltip />
              <Bar dataKey="efficiency" fill="#8B5CF6" name="Efficiency %" />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>
      </div>

      {/* Additional Metrics */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="card">
          <div className="card-content">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Recent Activity
            </h3>
            <div className="space-y-3">
              {[
                { action: 'Project "Website Redesign" completed', time: '2 hours ago' },
                { action: 'New project "Mobile App" created', time: '4 hours ago' },
                { action: 'Task "API Integration" assigned', time: '6 hours ago' },
                { action: 'Project "E-commerce" updated', time: '8 hours ago' },
              ].map((item, index) => (
                <div key={index} className="flex items-center justify-between text-sm">
                  <span className="text-gray-600 dark:text-gray-400">{item.action}</span>
                  <span className="text-gray-500 dark:text-gray-500">{item.time}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-content">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Top Performers
            </h3>
            <div className="space-y-3">
              {[
                { name: 'Team B', projects: 15, efficiency: 92 },
                { name: 'Team D', projects: 18, efficiency: 88 },
                { name: 'Team A', projects: 12, efficiency: 85 },
                { name: 'Team C', projects: 10, efficiency: 78 },
              ].map((team, index) => (
                <div key={index} className="flex items-center justify-between">
                  <div>
                    <div className="font-medium text-gray-900 dark:text-gray-100">{team.name}</div>
                    <div className="text-sm text-gray-500 dark:text-gray-400">{team.projects} projects</div>
                  </div>
                  <div className="text-right">
                    <div className="font-semibold text-primary-600 dark:text-primary-400">
                      {team.efficiency}%
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-content">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Upcoming Deadlines
            </h3>
            <div className="space-y-3">
              {[
                { project: 'Website Redesign', deadline: 'Tomorrow', status: 'urgent' },
                { project: 'Mobile App', deadline: '3 days', status: 'warning' },
                { project: 'E-commerce', deadline: '1 week', status: 'normal' },
                { project: 'API Integration', deadline: '2 weeks', status: 'normal' },
              ].map((item, index) => (
                <div key={index} className="flex items-center justify-between">
                  <div>
                    <div className="font-medium text-gray-900 dark:text-gray-100">{item.project}</div>
                    <div className="text-sm text-gray-500 dark:text-gray-400">{item.deadline}</div>
                  </div>
                  <div className={`px-2 py-1 text-xs rounded-full ${
                    item.status === 'urgent' 
                      ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                      : item.status === 'warning'
                      ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                      : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                  }`}>
                    {item.status}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
