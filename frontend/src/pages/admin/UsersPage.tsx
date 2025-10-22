import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import toast from 'react-hot-toast';
import { 
  useAdminUsers, 
  useDeleteAdminUser, 
  useBulkUpdateUserStatus 
} from '@/entities/admin/users/hooks';
import type { AdminUsersFilters } from '@/entities/admin/users/types';
import { 
  MagnifyingGlassIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  UsersIcon
} from '@heroicons/react/24/outline';

export default function AdminUsersPage() {
  const [filters, setFilters] = useState<AdminUsersFilters>({
    page: 1,
    per_page: 10
  });
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);

  const { data: usersResponse, isLoading, error } = useAdminUsers(filters);
  const deleteUserMutation = useDeleteAdminUser();
  const bulkUpdateStatusMutation = useBulkUpdateUserStatus();

  const users = usersResponse?.data || [];
  const meta = usersResponse?.meta;

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };

  const handleTenantFilter = (tenantId: string) => {
    setFilters(prev => ({ 
      ...prev, 
      tenant_id: tenantId === 'all' ? undefined : parseInt(tenantId),
      page: 1 
    }));
  };

  const handleRoleFilter = (roleId: string) => {
    setFilters(prev => ({ 
      ...prev, 
      role_id: roleId === 'all' ? undefined : parseInt(roleId),
      page: 1 
    }));
  };

  const handleStatusFilter = (status: string) => {
    setFilters(prev => ({ 
      ...prev, 
      status: status === 'all' ? undefined : status,
      page: 1 
    }));
  };

  const handleDeleteUser = async (userId: number) => {
    if (window.confirm('Are you sure you want to delete this user?')) {
      try {
        await deleteUserMutation.mutateAsync(userId);
        toast.success('User deleted successfully');
      } catch (error) {
        toast.error('Failed to delete user');
      }
    }
  };

  const handleBulkStatusUpdate = async (status: 'active' | 'inactive' | 'suspended') => {
    if (selectedUsers.length === 0) return;
    
    try {
      await bulkUpdateStatusMutation.mutateAsync({ userIds: selectedUsers, status });
      toast.success(`${selectedUsers.length} users have been ${status}`);
      setSelectedUsers([]);
    } catch (error) {
      toast.error('Failed to update users');
    }
  };

  const handleSelectUser = (userId: number) => {
    setSelectedUsers(prev => 
      prev.includes(userId) 
        ? prev.filter(id => id !== userId)
        : [...prev, userId]
    );
  };

  const handleSelectAll = () => {
    if (selectedUsers.length === users.length) {
      setSelectedUsers([]);
    } else {
      setSelectedUsers(users.map(user => user.id));
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">User Management</h2>
            <p className="text-gray-600">Manage system users and their permissions</p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading users...</div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">User Management</h2>
            <p className="text-gray-600">Manage system users and their permissions</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load users</h3>
              <p className="text-gray-600">There was an error loading the users. Please try again.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">User Management</h2>
          <p className="text-gray-600">Manage system users and their permissions</p>
        </div>
        <Button>
          <PlusIcon className="h-4 w-4 mr-2" />
          Add User
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="text-sm font-medium text-gray-700">Search</label>
              <div className="relative">
                <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search users..."
                  value={filters.search || ''}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">Status</label>
              <select
                value={filters.status || 'all'}
                onChange={(e) => handleStatusFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">Per Page</label>
              <select
                value={filters.per_page || 10}
                onChange={(e) => setFilters(prev => ({ ...prev, per_page: parseInt(e.target.value), page: 1 }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value={10}>10 per page</option>
                <option value={25}>25 per page</option>
                <option value={50}>50 per page</option>
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Users Table */}
      <Card>
        <CardHeader>
          <div className="flex justify-between items-center">
            <CardTitle>Users ({meta?.total || 0})</CardTitle>
            {selectedUsers.length > 0 && (
              <div className="flex space-x-2" role="region" aria-label="Bulk actions">
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={() => handleBulkStatusUpdate('active')}
                  disabled={bulkUpdateStatusMutation.isPending}
                  aria-label={`Activate ${selectedUsers.length} selected users`}
                >
                  Activate Selected
                </Button>
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={() => handleBulkStatusUpdate('inactive')}
                  disabled={bulkUpdateStatusMutation.isPending}
                  aria-label={`Deactivate ${selectedUsers.length} selected users`}
                >
                  Deactivate Selected
                </Button>
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={() => handleBulkStatusUpdate('suspended')}
                  disabled={bulkUpdateStatusMutation.isPending}
                  aria-label={`Suspend ${selectedUsers.length} selected users`}
                >
                  Suspend Selected
                </Button>
              </div>
            )}
          </div>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full" role="table" aria-label="Users management table">
              <caption className="sr-only">Users management table with bulk selection and actions</caption>
              <thead>
                <tr className="border-b">
                  <th className="text-left py-3 px-4" scope="col">
                    <input
                      type="checkbox"
                      checked={selectedUsers.length === users.length && users.length > 0}
                      onChange={handleSelectAll}
                      className="rounded border-gray-300"
                      aria-label="Select all users"
                      aria-describedby="select-all-help"
                    />
                    <span id="select-all-help" className="sr-only">Select or deselect all users in the table</span>
                  </th>
                  <th className="text-left py-3 px-4" scope="col">Name</th>
                  <th className="text-left py-3 px-4" scope="col">Email</th>
                  <th className="text-left py-3 px-4" scope="col">Tenant</th>
                  <th className="text-left py-3 px-4" scope="col">Roles</th>
                  <th className="text-left py-3 px-4" scope="col">Status</th>
                  <th className="text-left py-3 px-4" scope="col">Last Login</th>
                  <th className="text-left py-3 px-4" scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map(user => (
                  <tr key={user.id} className="border-b hover:bg-gray-50">
                    <td className="py-3 px-4">
                      <input
                        type="checkbox"
                        checked={selectedUsers.includes(user.id)}
                        onChange={() => handleSelectUser(user.id)}
                        className="rounded border-gray-300"
                        aria-label={`Select user ${user.name}`}
                        aria-describedby={`user-${user.id}-help`}
                      />
                      <span id={`user-${user.id}-help`} className="sr-only">Select or deselect user {user.name}</span>
                    </td>
                    <td className="py-3 px-4 font-medium">{user.name}</td>
                    <td className="py-3 px-4 text-gray-600">{user.email}</td>
                    <td className="py-3 px-4">{user.tenant_name}</td>
                    <td className="py-3 px-4">
                      <div className="flex flex-wrap gap-1">
                        {user.roles.map(role => (
                          <Badge key={role.id} variant="secondary" className="text-xs">
                            {role.name}
                          </Badge>
                        ))}
                      </div>
                    </td>
                    <td className="py-3 px-4">
                      <Badge variant={user.status === 'active' ? 'default' : 'destructive'}>
                        {user.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-gray-600">
                      {user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex space-x-2">
                        <Button variant="ghost" size="sm" aria-label={`View user ${user.name}`}>
                          <EyeIcon className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" aria-label={`Edit user ${user.name}`}>
                          <PencilIcon className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-red-600"
                          onClick={() => handleDeleteUser(user.id)}
                          disabled={deleteUserMutation.isPending}
                          aria-label={`Delete user ${user.name}`}
                        >
                          <TrashIcon className="h-4 w-4" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          
          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <nav className="flex justify-between items-center mt-4" role="navigation" aria-label="Users pagination">
              <div className="text-sm text-gray-600" aria-live="polite">
                Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
              </div>
              <div className="flex space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: prev.page! - 1 }))}
                  disabled={meta.current_page <= 1}
                  aria-label={`Go to page ${meta.current_page - 1}`}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: prev.page! + 1 }))}
                  disabled={meta.current_page >= meta.last_page}
                  aria-label={`Go to page ${meta.current_page + 1}`}
                >
                  Next
                </Button>
              </div>
            </nav>
          )}
        </CardContent>
      </Card>

      {users.length === 0 && !isLoading && (
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-gray-500">
              <UsersIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2">No users found</h3>
              <p className="text-gray-600">Try adjusting your filters or add a new user.</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
