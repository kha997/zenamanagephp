import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { useAdminRoles, useUpdateUserRoles } from '../hooks';
import type { AdminUser } from '../api';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';

interface UserRoleEditorProps {
  user: AdminUser;
  onClose: () => void;
  onSuccess?: () => void;
}

/**
 * UserRoleEditor - Component for assigning roles to users
 * Round 234: Admin RBAC - User-Role Assignment
 */
export const UserRoleEditor: React.FC<UserRoleEditorProps> = ({
  user,
  onClose,
  onSuccess,
}) => {
  const { data: roles, isLoading: rolesLoading } = useAdminRoles();
  const updateUserRolesMutation = useUpdateUserRoles();
  const [selectedRoleIds, setSelectedRoleIds] = useState<Array<string | number>>([]);

  useEffect(() => {
    if (user?.roles) {
      setSelectedRoleIds(user.roles.map((r) => r.id));
    }
  }, [user]);

  const handleToggleRole = (roleId: string | number) => {
    setSelectedRoleIds((prev) =>
      prev.includes(roleId)
        ? prev.filter((id) => id !== roleId)
        : [...prev, roleId]
    );
  };

  const handleSave = async () => {
    try {
      await updateUserRolesMutation.mutateAsync({
        userId: user.id,
        roles: selectedRoleIds,
      });
      onSuccess?.();
      onClose();
    } catch (error: any) {
      // Error handling is done in the mutation
      throw error;
    }
  };

  if (rolesLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-lg font-semibold text-[var(--color-text-primary)] mb-2">
          Assign Roles to {user.name}
        </h3>
        <p className="text-sm text-[var(--color-text-secondary)]">
          Select the roles to assign to this user
        </p>
      </div>

      <div className="space-y-2 max-h-64 overflow-y-auto">
        {roles && roles.length > 0 ? (
          roles.map((role) => (
            <label
              key={role.id}
              className="flex items-center space-x-2 p-2 hover:bg-[var(--color-surface-muted)] rounded cursor-pointer"
            >
              <input
                type="checkbox"
                checked={selectedRoleIds.includes(role.id)}
                onChange={() => handleToggleRole(role.id)}
                className="rounded border-[var(--color-border)]"
                disabled={updateUserRolesMutation.isPending}
              />
              <div className="flex-1">
                <div className="font-medium text-[var(--color-text-primary)]">
                  {role.name}
                </div>
                {role.description && (
                  <div className="text-xs text-[var(--color-text-secondary)]">
                    {role.description}
                  </div>
                )}
              </div>
            </label>
          ))
        ) : (
          <p className="text-sm text-[var(--color-text-secondary)] text-center py-4">
            No roles available
          </p>
        )}
      </div>

      <div className="flex justify-end gap-2 pt-4 border-t border-[var(--color-border)]">
        <Button variant="outline" onClick={onClose} disabled={updateUserRolesMutation.isPending}>
          Cancel
        </Button>
        <Button
          onClick={handleSave}
          disabled={updateUserRolesMutation.isPending}
          className="bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)]"
        >
          {updateUserRolesMutation.isPending ? 'Saving...' : 'Save'}
        </Button>
      </div>
    </div>
  );
};
