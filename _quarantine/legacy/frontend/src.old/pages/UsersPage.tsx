import { useState, Suspense, useTransition } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useUsers, useCreateUser, useUpdateUser, useDeleteUser, useToggleUserStatus } from '../hooks/useApi'
import { UserFilters } from '../services/dataService'
import { Plus, Search, Filter, MoreVertical, Edit, Trash2, Eye, UserCheck, UserX } from 'lucide-react'
import { LoadingState, ErrorMessage, EmptyState, SkeletonTable } from '../components/LoadingStates'
import { formatDate } from '../lib/utils'
import toast from 'react-hot-toast'
import { UsersKpiStrip } from '../components/users/UsersKpiStrip'
import { AlertBar } from '../components/shared/AlertBar'
import { ActivityFeed } from '../components/shared/ActivityFeed'
import { VisibilitySection } from '../components/perf/VisibilitySection'
import { useUsersKpis, useUsersAlerts, useUsersActivity, usersKeys } from '../entities/app/users/hooks'
import { useI18n } from '../app/i18n-context'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '../components/ui/Button'

export default function UsersPage() {
  const { t } = useI18n();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, startTransition] = useTransition();
  
  const [filters, setFilters] = useState<UserFilters>({
    search: '',
    role: '',
    is_active: undefined,
    tenant_id: '',
  })
  const [page, setPage] = useState(1)
  const [perPage] = useState(15)

  // Fetch Users KPIs, Alerts, and Activity
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useUsersKpis();
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useUsersAlerts();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useUsersActivity(10);

  const { data: usersData, loading, error, refetch } = useUsers(filters, page, perPage)
  const createUserMutation = useCreateUser()
  const updateUserMutation = useUpdateUser()
  const deleteUserMutation = useDeleteUser()
  const toggleStatusMutation = useToggleUserStatus()

  // Handle refresh
  const handleRefresh = () => {
    startTransition(() => {
      Promise.resolve().then(() => {
        queryClient.invalidateQueries({ queryKey: usersKeys.all });
        refetch();
      });
    });
  };

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }))
  }

  const handlePageChange = (page: number) => {
    setFilters(prev => ({ ...prev, page }))
  }

  if (loading) {
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
        <p className="text-red-600">Error loading users: {error.message}</p>
      </div>
    )
  }

  return (
    <div className="space-y-6 animate-fade-in" style={{ contain: 'layout style' }}>
      {/* Universal Page Frame Structure */}
      {/* 1. Page Header */}
      <div className="flex items-center justify-between animate-slide-up">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
            {t('users.title', { defaultValue: 'Users' })}
          </h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {t('users.description', { defaultValue: 'Manage your team members and their access.' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={handleRefresh} aria-label="Refresh users" disabled={isPending}>
            {t('common.refresh', { defaultValue: 'Refresh' })}
          </Button>
          <Link
            to="/users/new"
            className="btn btn-primary hover-lift"
          >
            <Plus className="h-4 w-4 mr-2" />
            {t('users.addUser', { defaultValue: 'Add User' })}
          </Link>
        </div>
      </div>

      {/* 2. KPI Strip */}
      <UsersKpiStrip
        metrics={kpisData}
        loading={kpisLoading}
        error={kpisError}
        onRefresh={handleRefresh}
        onViewAllUsers={() => {
          setFilters(prev => ({ ...prev, is_active: undefined }));
          navigate('/app/users');
        }}
        onViewActiveUsers={() => {
          setFilters(prev => ({ ...prev, is_active: true }));
          navigate('/app/users?is_active=1');
        }}
        onViewNewUsers={() => {
          navigate('/app/users');
        }}
      />

      {/* 3. Alert Bar */}
      <VisibilitySection intrinsicHeight={220}>
        <Suspense fallback={<div className="h-[220px]" />}>
          <AlertBar
            alerts={alertsData}
            loading={alertsLoading}
            error={alertsError}
            showDismissAll={true}
          />
        </Suspense>
      </VisibilitySection>

      {/* 4. Main Content */}

      {/* Filters */}
      <div className="card">
        <div className="card-content">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search users..."
                  className="input pl-10"
                  value={filters.search || ''}
                  onChange={(e) => handleSearch(e.target.value)}
                />
              </div>
            </div>
            <button className="btn btn-outline">
              <Filter className="h-4 w-4 mr-2" />
              Filters
            </button>
          </div>
        </div>
      </div>

      {/* Users Table */}
      <div className="card">
        <div className="card-content p-0">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    User
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Created
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {usersData?.data?.map((user) => (
                  <tr key={user.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center">
                          <span className="text-sm font-medium text-white">
                            {user.name.charAt(0).toUpperCase()}
                          </span>
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-gray-900">
                            {user.name}
                          </div>
                          <div className="text-sm text-gray-500">
                            {user.tenant?.name}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {user.email}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        user.is_active
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      }`}>
                        {user.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(user.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        <Link
                          to={`/users/${user.id}`}
                          className="text-primary-600 hover:text-primary-900"
                        >
                          <Eye className="h-4 w-4" />
                        </Link>
                        <button className="text-gray-600 hover:text-gray-900">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button className="text-red-600 hover:text-red-900">
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

      {/* Pagination */}
      {usersData?.pagination && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-gray-700">
            Showing {((usersData.pagination.current_page - 1) * usersData.pagination.per_page) + 1} to{' '}
            {Math.min(usersData.pagination.current_page * usersData.pagination.per_page, usersData.pagination.total)} of{' '}
            {usersData.pagination.total} results
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => handlePageChange(usersData.pagination.current_page - 1)}
              disabled={usersData.pagination.current_page === 1}
              className="btn btn-outline btn-sm"
            >
              Previous
            </button>
            <span className="text-sm text-gray-700">
              Page {usersData.pagination.current_page} of {usersData.pagination.last_page}
            </span>
            <button
              onClick={() => handlePageChange(usersData.pagination.current_page + 1)}
              disabled={usersData.pagination.current_page === usersData.pagination.last_page}
              className="btn btn-outline btn-sm"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* 5. Activity Feed */}
      <VisibilitySection intrinsicHeight={300}>
        <Suspense fallback={<div className="h-[300px]" />}>
          <ActivityFeed
            activities={activityData}
            loading={activityLoading}
            error={activityError}
            limit={10}
          />
        </Suspense>
      </VisibilitySection>
    </div>
  )
}
