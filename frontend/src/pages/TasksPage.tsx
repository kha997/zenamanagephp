import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { taskService } from '../services/taskService'
import { Task, TaskFilters } from '../types'
import { Plus, Calendar, User, Flag, MoreVertical, Edit, Trash2, Eye, CheckCircle } from 'lucide-react'
import { SkeletonTable } from '../components/Skeleton'
import AdvancedFilter from '../components/AdvancedFilter'
import { useRealtimeTasks } from '../hooks/useRealtimeData'
import { formatDate } from '../lib/utils'

export default function TasksPage() {
  const [filters, setFilters] = useState<TaskFilters>({
    search: '',
    page: 1,
    per_page: 10,
  })

  const { data, isLoading, error } = useQuery({
    queryKey: ['tasks', filters],
    queryFn: () => taskService.getTasks(filters),
  })

  // Enable real-time updates for tasks
  useRealtimeTasks()

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }))
  }

  const handleFilterChange = (key: string, value: any) => {
    setFilters(prev => ({ ...prev, [key]: value, page: 1 }))
  }

  const handleClearFilters = () => {
    setFilters({
      search: '',
      page: 1,
      per_page: 10,
    })
  }

  const handlePageChange = (page: number) => {
    setFilters(prev => ({ ...prev, page }))
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      case 'in_progress':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      case 'pending':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      case 'cancelled':
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
    }
  }

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent':
        return 'text-red-600 dark:text-red-400'
      case 'high':
        return 'text-orange-600 dark:text-orange-400'
      case 'medium':
        return 'text-yellow-600 dark:text-yellow-400'
      case 'low':
        return 'text-green-600 dark:text-green-400'
      default:
        return 'text-gray-600 dark:text-gray-400'
    }
  }

  const filterOptions = {
    status: [
      { value: 'pending', label: 'Pending' },
      { value: 'in_progress', label: 'In Progress' },
      { value: 'completed', label: 'Completed' },
      { value: 'cancelled', label: 'Cancelled' },
    ],
    priority: [
      { value: 'urgent', label: 'Urgent' },
      { value: 'high', label: 'High' },
      { value: 'medium', label: 'Medium' },
      { value: 'low', label: 'Low' },
    ],
    dateRange: {
      start: filters.start_date,
      end: filters.end_date,
    }
  }

  if (isLoading) {
    return (
      <div className="space-y-6 animate-fade-in">
        {/* Header Skeleton */}
        <div className="animate-slide-up">
          <div className="skeleton h-8 w-48 mb-2" />
          <div className="skeleton h-4 w-96" />
        </div>
        
        {/* Filters Skeleton */}
        <div className="card animate-slide-up" style={{ animationDelay: '100ms' }}>
          <div className="card-content">
            <div className="skeleton h-10 w-full" />
          </div>
        </div>
        
        {/* Table Skeleton */}
        <SkeletonTable />
      </div>
    )
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600">Error loading tasks: {error.message}</p>
      </div>
    )
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between animate-slide-up">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Tasks</h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage your tasks and track their progress.
          </p>
        </div>
        <Link
          to="/tasks/new"
          className="btn btn-primary hover-lift"
        >
          <Plus className="h-4 w-4 mr-2" />
          New Task
        </Link>
      </div>

      {/* Advanced Filters */}
      <AdvancedFilter
        searchValue={filters.search || ''}
        onSearchChange={handleSearch}
        filters={filters}
        onFilterChange={handleFilterChange}
        onClearFilters={handleClearFilters}
        filterOptions={filterOptions}
        className="animate-slide-up"
        style={{ animationDelay: '100ms' }}
      />

      {/* Tasks List */}
      <div className="card animate-slide-up" style={{ animationDelay: '200ms' }}>
        <div className="card-content p-0">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Task
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Project
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Assignee
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Priority
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Due Date
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                {data?.data.map((task, index) => (
                  <tr 
                    key={task.id} 
                    className="hover:bg-gray-50 dark:hover:bg-gray-800 animate-slide-up"
                    style={{ animationDelay: `${index * 50}ms` }}
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          {task.status === 'completed' ? (
                            <CheckCircle className="h-5 w-5 text-green-500" />
                          ) : (
                            <div className="h-5 w-5 rounded-full border-2 border-gray-300 dark:border-gray-600" />
                          )}
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {task.name}
                          </div>
                          <div className="text-sm text-gray-500 dark:text-gray-400 line-clamp-1">
                            {task.description || 'No description'}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      {task.project?.name || 'No project'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center">
                          <span className="text-sm font-medium text-white">
                            {task.user?.name?.charAt(0).toUpperCase() || '?'}
                          </span>
                        </div>
                        <div className="ml-3">
                          <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {task.user?.name || 'Unassigned'}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(task.status)}`}>
                        {task.status.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <Flag className={`h-4 w-4 mr-1 ${getPriorityColor(task.priority)}`} />
                        <span className={`text-sm font-medium ${getPriorityColor(task.priority)}`}>
                          {task.priority.toUpperCase()}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      {task.end_date ? formatDate(task.end_date) : 'No due date'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        <Link
                          to={`/tasks/${task.id}`}
                          className="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                          <Eye className="h-4 w-4" />
                        </Link>
                        <button className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Empty State */}
      {data?.data.length === 0 && (
        <div className="card">
          <div className="card-content text-center py-12">
            <div className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500">
              <CheckCircle className="h-full w-full" />
            </div>
            <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No tasks</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Get started by creating a new task.
            </p>
            <div className="mt-6">
              <Link
                to="/tasks/new"
                className="btn btn-primary"
              >
                <Plus className="h-4 w-4 mr-2" />
                New Task
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Pagination */}
      {data?.pagination && data.pagination.last_page > 1 && (
        <div className="flex items-center justify-between animate-slide-up" style={{ animationDelay: '300ms' }}>
          <div className="text-sm text-gray-700 dark:text-gray-300">
            Showing {((data.pagination.current_page - 1) * data.pagination.per_page) + 1} to{' '}
            {Math.min(data.pagination.current_page * data.pagination.per_page, data.pagination.total)} of{' '}
            {data.pagination.total} results
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => handlePageChange(data.pagination.current_page - 1)}
              disabled={data.pagination.current_page === 1}
              className="btn btn-outline btn-sm"
            >
              Previous
            </button>
            <span className="text-sm text-gray-700 dark:text-gray-300">
              Page {data.pagination.current_page} of {data.pagination.last_page}
            </span>
            <button
              onClick={() => handlePageChange(data.pagination.current_page + 1)}
              disabled={data.pagination.current_page === data.pagination.last_page}
              className="btn btn-outline btn-sm"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
