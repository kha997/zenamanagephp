import React, { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../../../shared/ui/card';
import { Button } from '../../../../shared/ui/button';
import { Input } from '../../../../components/ui/primitives/Input';
import { Badge } from '../../../../shared/ui/badge';
import { 
  useAdminUsers, 
  useDeleteAdminUser, 
  useBulkUpdateUserStatus 
} from '../hooks';
import { useUsers, useUpdateUserRoles, useRoleProfiles, useAssignProfileToUser } from '../../hooks';
import type { AdminUsersFilters } from '../types';
import { LoadingSpinner } from '../../../../components/shared/LoadingSpinner';
import { InviteUserModal } from '../components/InviteUserModal';
import { InvitationList } from '../components/InvitationList';
import { UserRoleEditor } from '../../components/UserRoleEditor';
import type { RoleProfile } from '../../api';
import toast from 'react-hot-toast';

/**
 * AdminUsersPage - Admin users management page
 * Displays system-wide user management with search, filters, pagination, and bulk actions
 */
export const AdminUsersPage: React.FC = () => {
  const [filters, setFilters] = useState<AdminUsersFilters>({
    page: 1,
    per_page: 10
  });
  // Separate state for search input (for debouncing)
  const [searchInput, setSearchInput] = useState<string>('');
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [editingUserRoles, setEditingUserRoles] = useState<any>(null);
  const [assigningProfileToUser, setAssigningProfileToUser] = useState<{ userId: number | string; userName: string } | null>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const { data: profiles } = useRoleProfiles();
  const assignProfileMutation = useAssignProfileToUser();

  // Memoize filters to prevent unnecessary query key changes
  // Only update when actual filter values change, not object reference
  const memoizedFilters = useMemo(() => {
    return {
      page: filters.page,
      per_page: filters.per_page,
      search: filters.search,
      status: filters.status,
      tenant_id: filters.tenant_id,
      role_id: filters.role_id,
    };
  }, [
    filters.page,
    filters.per_page,
    filters.search,
    filters.status,
    filters.tenant_id,
    filters.role_id,
  ]);

  const { data: usersResponse, isLoading, error } = useAdminUsers(memoizedFilters);
  const deleteUserMutation = useDeleteAdminUser();
  const bulkUpdateStatusMutation = useBulkUpdateUserStatus();

  const users = usersResponse?.data || [];
  const meta = usersResponse?.meta;

  // Handle search input change - preserve focus
  const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchInput(value);
    
    // Preserve focus immediately - use requestAnimationFrame for better timing
    requestAnimationFrame(() => {
      if (searchInputRef.current) {
        const wasFocused = document.activeElement === searchInputRef.current;
        // Only restore focus if it was focused before
        if (wasFocused && document.activeElement !== searchInputRef.current) {
          searchInputRef.current.focus();
          // Restore cursor position
          const cursorPos = searchInputRef.current.selectionStart || value.length;
          searchInputRef.current.setSelectionRange(cursorPos, cursorPos);
        }
      }
    });
  }, []);

  // Debounce search input - update filters after 500ms of no typing
  // Longer debounce to prevent rapid re-renders that cause focus loss
  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters(prev => {
        // Only update if search value actually changed to avoid unnecessary re-renders
        const newSearch = searchInput.trim() || undefined;
        if (prev.search !== newSearch) {
          return { 
            ...prev, 
            search: newSearch, 
            page: 1 
          };
        }
        return prev;
      });
    }, 500); // Increased from 300ms to 500ms
    
    return () => clearTimeout(timer);
  }, [searchInput]);

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
        toast.error(error instanceof Error ? error.message : 'Failed to delete user');
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
      toast.error(error instanceof Error ? error.message : 'Failed to update users');
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

  const handleAssignProfile = async (profileId: string) => {
    if (!assigningProfileToUser) return;

    try {
      await assignProfileMutation.mutateAsync({
        userId: assigningProfileToUser.userId,
        profileId,
      });
      toast.success(`Profile assigned to ${assigningProfileToUser.userName} successfully`);
      setAssigningProfileToUser(null);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to assign profile');
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">User Management</h1>
            <p className="text-sm text-[var(--color-text-secondary)] mt-1">
              Manage system users and their permissions
            </p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="lg" message="Loading users..." />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">User Management</h1>
            <p className="text-sm text-[var(--color-text-secondary)] mt-1">
              Manage system users and their permissions
            </p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-[var(--color-semantic-danger-600)]">
              <h3 className="text-lg font-medium mb-2">Failed to load users</h3>
              <p className="text-sm text-[var(--color-text-secondary)]">
                {error instanceof Error ? error.message : 'There was an error loading the users. Please try again.'}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">User Management</h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            Manage system users and their permissions
          </p>
        </div>
        <div className="flex gap-2">
          <Button onClick={() => setShowInviteModal(true)}>
            <span className="mr-2">✉️</span>
            Invite User
          </Button>
          <Button variant="outline">
            <span className="mr-2">+</span>
            Add User
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
                Search
              </label>
              <Input
                ref={searchInputRef}
                placeholder="Search users..."
                value={searchInput}
                onChange={handleSearchChange}
                leadingIcon={<span>🔍</span>}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
                Status
              </label>
              <select
                value={filters.status || 'all'}
                onChange={(e) => handleStatusFilter(e.target.value)}
                className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-[var(--color-surface-base)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-200)]"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
                Per Page
              </label>
              <select
                value={filters.per_page || 10}
                onChange={(e) => setFilters(prev => ({ ...prev, per_page: parseInt(e.target.value), page: 1 }))}
                className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-[var(--color-surface-base)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-200)]"
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
                <tr className="border-b border-[var(--color-border-subtle)]">
                  <th className="text-left py-3 px-4" scope="col">
                    <input
                      type="checkbox"
                      checked={selectedUsers.length === users.length && users.length > 0}
                      onChange={handleSelectAll}
                      className="rounded border-[var(--color-border-default)]"
                      aria-label="Select all users"
                      aria-describedby="select-all-help"
                    />
                    <span id="select-all-help" className="sr-only">Select or deselect all users in the table</span>
                  </th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Name</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Email</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Tenant</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Roles</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Status</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Last Login</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]" scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map(user => (
                  <tr key={user.id} className="border-b border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-muted)]">
                    <td className="py-3 px-4">
                      <input
                        type="checkbox"
                        checked={selectedUsers.includes(user.id)}
                        onChange={() => handleSelectUser(user.id)}
                        className="rounded border-[var(--color-border-default)]"
                        aria-label={`Select user ${user.name}`}
                        aria-describedby={`user-${user.id}-help`}
                      />
                      <span id={`user-${user.id}-help`} className="sr-only">Select or deselect user {user.name}</span>
                    </td>
                    <td className="py-3 px-4 font-medium text-[var(--color-text-primary)]">{user.name}</td>
                    <td className="py-3 px-4 text-[var(--color-text-secondary)]">{user.email}</td>
                    <td className="py-3 px-4 text-[var(--color-text-primary)]">{user.tenant_name}</td>
                    <td className="py-3 px-4">
                      <div className="flex flex-wrap gap-1">
                        {user.roles.map(role => (
                          <Badge key={role.id} tone="neutral" className="text-xs">
                            {role.name}
                          </Badge>
                        ))}
                      </div>
                    </td>
                    <td className="py-3 px-4">
                      <Badge 
                        tone={
                          user.status === 'active' 
                            ? 'success' 
                            : user.status === 'suspended' 
                            ? 'danger' 
                            : 'neutral'
                        }
                      >
                        {user.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-[var(--color-text-secondary)]">
                      {user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex space-x-2">
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          aria-label={`View user ${user.name}`}
                        >
                          👁️
                        </Button>
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          onClick={() => {
                            // Fetch user with roles from new API
                            setEditingUserRoles({
                              id: user.id,
                              name: user.name,
                              email: user.email,
                              roles: user.roles || [],
                            });
                          }}
                          aria-label={`Edit roles for user ${user.name}`}
                        >
                          ✏️
                        </Button>
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          onClick={() => {
                            setAssigningProfileToUser({
                              userId: user.id,
                              userName: user.name,
                            });
                          }}
                          aria-label={`Assign profile to user ${user.name}`}
                          title="Assign Profile"
                        >
                          📋
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-[var(--color-semantic-danger-600)]"
                          onClick={() => handleDeleteUser(user.id)}
                          disabled={deleteUserMutation.isPending}
                          aria-label={`Delete user ${user.name}`}
                        >
                          🗑️
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
              <div className="text-sm text-[var(--color-text-secondary)]" aria-live="polite">
                Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
              </div>
              <div className="flex space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: (prev.page || 1) - 1 }))}
                  disabled={meta.current_page <= 1}
                  aria-label={`Go to page ${meta.current_page - 1}`}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setFilters(prev => ({ ...prev, page: (prev.page || 1) + 1 }))}
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
            <div className="text-[var(--color-text-secondary)]">
              <span className="text-4xl mb-4 block">👥</span>
              <h3 className="text-lg font-medium mb-2 text-[var(--color-text-primary)]">No users found</h3>
              <p className="text-sm text-[var(--color-text-secondary)]">Try adjusting your filters or add a new user.</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Invitations List */}
      <InvitationList />

      {/* Invite User Modal */}
      <InviteUserModal
        open={showInviteModal}
        onOpenChange={setShowInviteModal}
        onSuccess={() => {
          // Refresh users list - query will auto-refresh due to invalidation in hooks
        }}
      />

      {/* User Role Editor Modal */}
      {editingUserRoles && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Assign Roles</CardTitle>
            </CardHeader>
            <CardContent>
              <UserRoleEditor
                user={editingUserRoles}
                onClose={() => setEditingUserRoles(null)}
                onSuccess={() => {
                  toast.success('User roles updated successfully');
                  // Refresh will happen automatically via query invalidation
                }}
              />
            </CardContent>
          </Card>
        </div>
      )}

      {/* Assign Profile Modal */}
      {assigningProfileToUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Assign Profile to {assigningProfileToUser.userName}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <p className="text-sm text-[var(--color-text-secondary)]">
                  Select a profile to assign. This will add the profile's roles to the user's existing roles.
                </p>
                {profiles && profiles.length > 0 ? (
                  <div className="space-y-2 max-h-64 overflow-y-auto">
                    {profiles
                      .filter((p: RoleProfile) => p.is_active)
                      .map((profile: RoleProfile) => (
                        <button
                          key={profile.id}
                          onClick={() => handleAssignProfile(profile.id)}
                          disabled={assignProfileMutation.isPending}
                          className="w-full text-left p-3 border border-[var(--color-border)] rounded-lg hover:bg-[var(--color-surface-muted)] transition-colors disabled:opacity-50"
                        >
                          <div className="font-medium text-[var(--color-text-primary)]">
                            {profile.name}
                          </div>
                          {profile.description && (
                            <div className="text-xs text-[var(--color-text-secondary)] mt-1">
                              {profile.description}
                            </div>
                          )}
                          <div className="flex items-center gap-1 mt-2 flex-wrap">
                            {profile.roles.map((role) => (
                              <Badge key={role.id} variant="outline" className="text-xs">
                                {role.name}
                              </Badge>
                            ))}
                          </div>
                        </button>
                      ))}
                  </div>
                ) : (
                  <p className="text-sm text-[var(--color-text-secondary)] text-center py-4">
                    No active profiles available
                  </p>
                )}
                <div className="flex justify-end gap-2 pt-4">
                  <Button
                    variant="outline"
                    onClick={() => setAssigningProfileToUser(null)}
                    disabled={assignProfileMutation.isPending}
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
};

export default AdminUsersPage;

