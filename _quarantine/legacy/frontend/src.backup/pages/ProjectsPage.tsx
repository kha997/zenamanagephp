import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { projectService } from '../services/projectService'
import { Project, ProjectFilters } from '../types'
import { Plus, Calendar, Users, TrendingUp, MoreVertical, Edit, Trash2, Eye } from 'lucide-react'
import { SkeletonTable } from '../components/Skeleton'
import AdvancedFilter from '../components/AdvancedFilter'
import { useRealtimeProjects } from '../hooks/useRealtimeData'
import { formatDate, formatCurrency } from '../lib/utils'

export default function ProjectsPage() {
  const [filters, setFilters] = useState<ProjectFilters>({
    search: '',
    page: 1,
    per_page: 10,
  })

  const { data, isLoading, error } = useQuery({
    queryKey: ['projects', filters],
    queryFn: () => projectService.getProjects(filters),
  })

  // Enable real-time updates for projects
  useRealtimeProjects()

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
      case 'active':
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      case 'planning':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      case 'on_hold':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      case 'completed':
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
      case 'cancelled':
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
    }
  }

  const filterOptions = {
    status: [
      { value: 'planning', label: 'Planning' },
      { value: 'active', label: 'Active' },
      { value: 'on_hold', label: 'On Hold' },
      { value: 'completed', label: 'Completed' },
      { value: 'cancelled', label: 'Cancelled' },
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
        <p className="text-red-600">Error loading projects: {error.message}</p>
      </div>
    )
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between animate-slide-up">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Projects</h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage your projects and track their progress.
          </p>
        </div>
        <Link
          to="/projects/new"
          className="btn btn-primary hover-lift"
        >
          <Plus className="h-4 w-4 mr-2" />
          New Project
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

      {/* Projects Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-slide-up" style={{ animationDelay: '200ms' }}>
        {data?.data.map((project, index) => (
          <div
            key={project.id}
            className="card hover-lift animate-slide-up"
            style={{ animationDelay: `${index * 100}ms` }}
          >
            <div className="card-content">
              <div className="flex items-start justify-between mb-4">
                <div className="flex-1">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                    {project.name}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                    {project.description || 'No description provided'}
                  </p>
                </div>
                <div className="flex items-center space-x-1">
                  <button className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <MoreVertical className="h-4 w-4" />
                  </button>
                </div>
              </div>

              <div className="space-y-3">
                {/* Status and Progress */}
                <div className="flex items-center justify-between">
                  <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(project.status)}`}>
                    {project.status.replace('_', ' ').toUpperCase()}
                  </span>
                  <div className="text-sm text-gray-500 dark:text-gray-400">
                    {project.progress}%
                  </div>
                </div>

                {/* Progress Bar */}
                <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                  <div
                    className="bg-primary-600 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${project.progress}%` }}
                  />
                </div>

                {/* Project Details */}
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div className="flex items-center text-gray-500 dark:text-gray-400">
                    <Calendar className="h-4 w-4 mr-2" />
                    <span>
                      {project.start_date ? formatDate(project.start_date) : 'No start date'}
                    </span>
                  </div>
                  <div className="flex items-center text-gray-500 dark:text-gray-400">
                    <TrendingUp className="h-4 w-4 mr-2" />
                    <span>{formatCurrency(project.actual_cost)}</span>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                  <div className="flex items-center space-x-2">
                    <Link
                      to={`/projects/${project.id}`}
                      className="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
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
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    {formatDate(project.created_at)}
                  </div>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Empty State */}
      {data?.data.length === 0 && (
        <div className="card">
          <div className="card-content text-center py-12">
            <div className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500">
              <Calendar className="h-full w-full" />
            </div>
            <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No projects</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Get started by creating a new project.
            </p>
            <div className="mt-6">
              <Link
                to="/projects/new"
                className="btn btn-primary"
              >
                <Plus className="h-4 w-4 mr-2" />
                New Project
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
