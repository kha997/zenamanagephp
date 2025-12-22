import React, { useState, useMemo } from 'react';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Checkbox } from '../../../components/ui/Checkbox';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import {
  useAdminRoles,
  useAdminPermissionsCatalog,
  useUpdateAdminRolePermissions,
} from '../hooks';
import type { AdminRole, AdminPermissionGroup } from '../api';

/**
 * AdminRolesPermissionsPage - Admin UI for managing roles and permissions
 * Round 233: Admin UI for Roles & Permissions
 */
export const AdminRolesPermissionsPage: React.FC = () => {
  const [selectedRoleId, setSelectedRoleId] = useState<string | number | null>(null);
  const [localPermissions, setLocalPermissions] = useState<Set<string>>(new Set());
  const [hasChanges, setHasChanges] = useState(false);

  const { data: roles, isLoading: rolesLoading, error: rolesError } = useAdminRoles();
  const { data: permissionsCatalog, isLoading: catalogLoading } = useAdminPermissionsCatalog();
  const updatePermissionsMutation = useUpdateAdminRolePermissions();

  // Get selected role
  const selectedRole = useMemo(() => {
    if (!selectedRoleId || !roles) return null;
    return roles.find((r) => r.id === selectedRoleId) || null;
  }, [selectedRoleId, roles]);

  // Initialize local permissions when role is selected
  React.useEffect(() => {
    if (selectedRole) {
      setLocalPermissions(new Set(selectedRole.permissions));
      setHasChanges(false);
    }
  }, [selectedRole]);

  // Handle permission toggle
  const handlePermissionToggle = (permissionKey: string) => {
    setLocalPermissions((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(permissionKey)) {
        newSet.delete(permissionKey);
      } else {
        newSet.add(permissionKey);
      }
      return newSet;
    });
    setHasChanges(true);
  };

  // Handle group toggle (select/deselect all in group)
  const handleGroupToggle = (group: AdminPermissionGroup, checked: boolean) => {
    setLocalPermissions((prev) => {
      const newSet = new Set(prev);
      group.permissions.forEach((perm) => {
        if (checked) {
          newSet.add(perm.key);
        } else {
          newSet.delete(perm.key);
        }
      });
      return newSet;
    });
    setHasChanges(true);
  };

  // Handle save
  const handleSave = async () => {
    if (!selectedRoleId) return;

    try {
      await updatePermissionsMutation.mutateAsync({
        roleId: selectedRoleId,
        permissions: Array.from(localPermissions),
      });
      toast.success('Permissions updated successfully');
      setHasChanges(false);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update permissions');
    }
  };

  // Check if all permissions in a group are selected
  const isGroupFullySelected = (group: AdminPermissionGroup): boolean => {
    if (group.permissions.length === 0) return false;
    return group.permissions.every((perm) => localPermissions.has(perm.key));
  };

  // Check if any permissions in a group are selected
  const isGroupPartiallySelected = (group: AdminPermissionGroup): boolean => {
    return group.permissions.some((perm) => localPermissions.has(perm.key));
  };

  if (rolesLoading || catalogLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner />
      </div>
    );
  }

  if (rolesError) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading roles: {rolesError.message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Roles & Permissions</h1>
        <p className="text-gray-600 mt-2">Manage role permissions and access control</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Roles List - Left Sidebar */}
        <div className="lg:col-span-1">
          <Card>
            <CardHeader>
              <CardTitle>Roles</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {roles?.map((role) => (
                  <button
                    key={role.id}
                    onClick={() => setSelectedRoleId(role.id)}
                    className={`w-full text-left px-4 py-2 rounded-md transition-colors ${
                      selectedRoleId === role.id
                        ? 'bg-blue-100 text-blue-900 font-medium'
                        : 'hover:bg-gray-100'
                    }`}
                  >
                    <div className="font-medium">{role.name}</div>
                    <div className="text-sm text-gray-500">
                      {role.permissions.length} permission{role.permissions.length !== 1 ? 's' : ''}
                    </div>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Permissions Matrix - Right Panel */}
        <div className="lg:col-span-3">
          {selectedRole ? (
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>{selectedRole.name}</CardTitle>
                    <p className="text-sm text-gray-600 mt-1">
                      {selectedRole.description || 'Manage permissions for this role'}
                    </p>
                  </div>
                  <Button
                    onClick={handleSave}
                    disabled={!hasChanges || updatePermissionsMutation.isPending}
                    className="ml-4"
                  >
                    {updatePermissionsMutation.isPending ? 'Saving...' : 'Save Changes'}
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                {permissionsCatalog?.groups && permissionsCatalog.groups.length > 0 ? (
                  <div className="space-y-6">
                    {permissionsCatalog.groups.map((group) => (
                      <div key={group.key} className="border rounded-lg p-4">
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center space-x-2">
                            <Checkbox
                              checked={isGroupFullySelected(group)}
                              indeterminate={isGroupPartiallySelected(group) && !isGroupFullySelected(group)}
                              onChange={(e) => handleGroupToggle(group, e.target.checked)}
                              className="mt-1"
                            />
                            <h3 className="font-semibold text-lg">{group.label}</h3>
                          </div>
                        </div>
                        <div className="space-y-2 pl-8">
                          {group.permissions.map((permission) => (
                            <div
                              key={permission.key}
                              className="flex items-start space-x-3 py-2 border-b last:border-b-0"
                            >
                              <Checkbox
                                checked={localPermissions.has(permission.key)}
                                onChange={() => handlePermissionToggle(permission.key)}
                                className="mt-1"
                              />
                              <div className="flex-1">
                                <label
                                  htmlFor={`perm-${permission.key}`}
                                  className="font-medium cursor-pointer block"
                                >
                                  {permission.label}
                                </label>
                                {permission.description && (
                                  <p className="text-sm text-gray-600 mt-1">
                                    {permission.description}
                                  </p>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-gray-600">No permission groups available</p>
                )}
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="p-12 text-center">
                <p className="text-gray-600">Select a role to manage its permissions</p>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
};
