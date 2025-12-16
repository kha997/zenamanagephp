import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Badge } from '../../../shared/ui/badge';
import {
  useAdminRoles,
  useCreateRole,
  useUpdateRole,
  useDeleteRole,
} from '../hooks';
import type { AdminRole } from '../api';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { RoleEditorModal } from '../components/RoleEditorModal';

/**
 * AdminRolesPage - Admin roles management page
 * Round 234: Admin RBAC - Roles CRUD + User-Role Assignment
 */
export const AdminRolesPage: React.FC = () => {
  const navigate = useNavigate();
  const [editingRole, setEditingRole] = useState<AdminRole | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [roleToDelete, setRoleToDelete] = useState<AdminRole | null>(null);

  const { data: roles, isLoading, error } = useAdminRoles();
  const createRoleMutation = useCreateRole();
  const updateRoleMutation = useUpdateRole();
  const deleteRoleMutation = useDeleteRole();

  const handleCreateRole = async (data: { name: string; description?: string; scope?: string }) => {
    try {
      await createRoleMutation.mutateAsync(data);
      toast.success('Role created successfully');
      setIsCreateModalOpen(false);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to create role');
    }
  };

  const handleUpdateRole = async (roleId: string | number, data: { name?: string; description?: string; scope?: string }) => {
    try {
      await updateRoleMutation.mutateAsync({ roleId, data });
      toast.success('Role updated successfully');
      setEditingRole(null);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update role');
    }
  };

  const handleDeleteRole = async () => {
    if (!roleToDelete) return;

    try {
      await deleteRoleMutation.mutateAsync(roleToDelete.id);
      toast.success('Role deleted successfully');
      setIsDeleteModalOpen(false);
      setRoleToDelete(null);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to delete role');
    }
  };

  const handleEditPermissions = (role: AdminRole) => {
    navigate(`/admin/roles-permissions?role=${role.id}`);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading roles: {(error as Error).message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Roles Management</h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            Manage system roles and their permissions
          </p>
        </div>
        <Button
          onClick={() => setIsCreateModalOpen(true)}
          className="bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)]"
        >
          Add Role
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Roles</CardTitle>
        </CardHeader>
        <CardContent>
          {!roles || roles.length === 0 ? (
            <p className="text-[var(--color-text-secondary)] text-center py-8">
              No roles found. Create your first role to get started.
            </p>
          ) : (
            <div className="space-y-4">
              {roles.map((role) => (
                <div
                  key={role.id}
                  className="flex items-center justify-between p-4 border border-[var(--color-border)] rounded-lg hover:bg-[var(--color-surface-muted)] transition-colors"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold text-[var(--color-text-primary)]">{role.name}</h3>
                      {role.is_system && (
                        <Badge variant="secondary" className="text-xs">
                          System
                        </Badge>
                      )}
                      <Badge variant="outline" className="text-xs">
                        {role.scope || 'system'}
                      </Badge>
                    </div>
                    {role.description && (
                      <p className="text-sm text-[var(--color-text-secondary)] mt-1">{role.description}</p>
                    )}
                    <div className="flex items-center gap-4 mt-2 text-xs text-[var(--color-text-secondary)]">
                      <span>{role.permissions?.length || 0} permissions</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleEditPermissions(role)}
                    >
                      Permissions
                    </Button>
                    {!role.is_system && (
                      <>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setEditingRole(role)}
                        >
                          Edit
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => {
                            setRoleToDelete(role);
                            setIsDeleteModalOpen(true);
                          }}
                        >
                          Delete
                        </Button>
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Create Role Modal */}
      {isCreateModalOpen && (
        <RoleEditorModal
          isOpen={isCreateModalOpen}
          onClose={() => setIsCreateModalOpen(false)}
          onSave={handleCreateRole}
          isLoading={createRoleMutation.isPending}
        />
      )}

      {/* Edit Role Modal */}
      {editingRole && (
        <RoleEditorModal
          isOpen={!!editingRole}
          onClose={() => setEditingRole(null)}
          onSave={(data) => handleUpdateRole(editingRole.id, data)}
          role={editingRole}
          isLoading={updateRoleMutation.isPending}
        />
      )}

      {/* Delete Confirmation Modal */}
      {isDeleteModalOpen && roleToDelete && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Delete Role</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-[var(--color-text-primary)] mb-4">
                Are you sure you want to delete the role &quot;{roleToDelete.name}&quot;? This action cannot be undone.
              </p>
              <div className="flex justify-end gap-2">
                <Button
                  variant="outline"
                  onClick={() => {
                    setIsDeleteModalOpen(false);
                    setRoleToDelete(null);
                  }}
                >
                  Cancel
                </Button>
                <Button
                  variant="destructive"
                  onClick={handleDeleteRole}
                  disabled={deleteRoleMutation.isPending}
                >
                  {deleteRoleMutation.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
};
