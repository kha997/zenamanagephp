import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import toast from 'react-hot-toast';
import { 
  useAdminRoles, 
  useDeleteAdminRole, 
  useAdminPermissions 
} from '@/entities/admin/roles/hooks';
import type { AdminRolesFilters } from '@/entities/admin/roles/types';
import { 
  PlusIcon,
  PencilIcon,
  TrashIcon,
  ShieldCheckIcon,
  UsersIcon
} from '@heroicons/react/24/outline';

export default function AdminRolesPage() {
  const [filters, setFilters] = useState<AdminRolesFilters>({
    page: 1,
    per_page: 10
  });
  const [selectedRoles, setSelectedRoles] = useState<number[]>([]);

  const { data: rolesResponse, isLoading, error } = useAdminRoles(filters);
  const { data: permissionsResponse } = useAdminPermissions();
  const deleteRoleMutation = useDeleteAdminRole();

  const roles = rolesResponse?.data || [];
  const meta = rolesResponse?.meta;
  const permissions = permissionsResponse?.data || [];

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };

  const handleDeleteRole = async (roleId: number) => {
    if (window.confirm('Are you sure you want to delete this role?')) {
      try {
        await deleteRoleMutation.mutateAsync(roleId);
        toast.success('Role deleted successfully');
      } catch (error) {
        toast.error('Failed to delete role');
      }
    }
  };

  const handleSelectRole = (roleId: number) => {
    setSelectedRoles(prev => 
      prev.includes(roleId) 
        ? prev.filter(id => id !== roleId)
        : [...prev, roleId]
    );
  };

  const handleSelectAll = () => {
    if (selectedRoles.length === roles.length) {
      setSelectedRoles([]);
    } else {
      setSelectedRoles(roles.map(role => role.id));
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Roles & Permissions</h2>
            <p className="text-gray-600">Manage system roles and their permissions</p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading roles...</div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Roles & Permissions</h2>
            <p className="text-gray-600">Manage system roles and their permissions</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load roles</h3>
              <p className="text-gray-600">There was an error loading the roles. Please try again.</p>
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
          <h2 className="text-3xl font-bold text-gray-900">Roles & Permissions</h2>
          <p className="text-gray-600">Manage system roles and their permissions</p>
        </div>
        <Button>
          <PlusIcon className="h-4 w-4 mr-2" />
          Create Role
        </Button>
      </div>

      {/* Search Bar */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <Input
                type="text"
                placeholder="Search roles by name or description..."
                onChange={(e) => handleSearch(e.target.value)}
                className="w-full"
              />
            </div>
            <div className="ml-4 flex items-center space-x-2">
              <input
                type="checkbox"
                checked={selectedRoles.length === roles.length && roles.length > 0}
                onChange={handleSelectAll}
                className="rounded border-gray-300"
              />
              <span className="text-sm text-gray-600">Select All</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Roles Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {roles.map(role => (
          <Card key={role.id} className="hover:shadow-lg transition-shadow">
            <CardHeader>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <ShieldCheckIcon className="h-5 w-5 text-blue-600" />
                  <CardTitle className="text-lg">{role.name}</CardTitle>
                </div>
                <div className="flex items-center space-x-2">
                  <Badge variant="secondary" className="text-xs">
                    <UsersIcon className="h-3 w-3 mr-1" />
                    {role.user_count} users
                  </Badge>
                  <input
                    type="checkbox"
                    checked={selectedRoles.includes(role.id)}
                    onChange={() => handleSelectRole(role.id)}
                    className="rounded border-gray-300"
                  />
                </div>
              </div>
              <p className="text-sm text-gray-600">{role.description}</p>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <div>
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Permissions:</h4>
                  <div className="flex flex-wrap gap-1">
                    {role.permissions.length > 3 ? (
                      <>
                        {role.permissions.slice(0, 3).map((permission, index) => (
                          <Badge key={index} variant="outline" className="text-xs">
                            {permission.code}
                          </Badge>
                        ))}
                        <Badge variant="outline" className="text-xs">
                          +{role.permissions.length - 3} more
                        </Badge>
                      </>
                    ) : (
                      role.permissions.map((permission, index) => (
                        <Badge key={index} variant="outline" className="text-xs">
                          {permission.code}
                        </Badge>
                      ))
                    )}
                  </div>
                </div>
                
                <div className="flex space-x-2 pt-2">
                  <Button variant="outline" size="sm" className="flex-1">
                    <PencilIcon className="h-4 w-4 mr-1" />
                    Edit
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    className="text-red-600"
                    onClick={() => handleDeleteRole(role.id)}
                    disabled={deleteRoleMutation.isPending}
                  >
                    <TrashIcon className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex justify-between items-center">
          <div className="text-sm text-gray-600">
            Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
          </div>
          <div className="flex space-x-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(prev => ({ ...prev, page: prev.page! - 1 }))}
              disabled={meta.current_page <= 1}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(prev => ({ ...prev, page: prev.page! + 1 }))}
              disabled={meta.current_page >= meta.last_page}
            >
              Next
            </Button>
          </div>
        </div>
      )}

      {roles.length === 0 && !isLoading && (
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-gray-500">
              <ShieldCheckIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2">No roles found</h3>
              <p className="text-gray-600">Try adjusting your filters or create a new role.</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Permissions Overview */}
      <Card>
        <CardHeader>
          <CardTitle>System Permissions Overview</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {permissions.length > 0 ? (
              Object.entries(
                permissions.reduce((acc: any, permission: any) => {
                  const resource = permission.resource;
                  if (!acc[resource]) {
                    acc[resource] = [];
                  }
                  acc[resource].push(permission);
                  return acc;
                }, {})
              ).map(([resource, perms]: [string, any]) => (
                <div key={resource}>
                  <h4 className="font-medium text-gray-900 mb-2 capitalize">
                    {resource.replace('_', ' ')} Management
                  </h4>
                  <ul className="text-sm text-gray-600 space-y-1">
                    {perms.map((permission: any) => (
                      <li key={permission.id}>• {permission.code}</li>
                    ))}
                  </ul>
                </div>
              ))
            ) : (
              <div className="col-span-full text-center text-gray-500 py-8">
                No permissions available
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
