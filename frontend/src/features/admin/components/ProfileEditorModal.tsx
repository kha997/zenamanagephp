import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Input } from '../../../components/ui/primitives/Input';
import type { RoleProfile, AdminRole } from '../api';
import { useAdminRoles } from '../hooks';

interface ProfileEditorModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: {
    name: string;
    description?: string;
    roles: string[];
    is_active?: boolean;
  }) => void | Promise<void>;
  profile?: RoleProfile | null;
  isLoading?: boolean;
}

/**
 * ProfileEditorModal - Modal for creating/editing role profiles
 * Round 244: Role Access Profiles
 */
export const ProfileEditorModal: React.FC<ProfileEditorModalProps> = ({
  isOpen,
  onClose,
  onSave,
  profile,
  isLoading = false,
}) => {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
  const [isActive, setIsActive] = useState(true);
  const { data: roles, isLoading: rolesLoading } = useAdminRoles();

  useEffect(() => {
    if (profile) {
      setName(profile.name || '');
      setDescription(profile.description || '');
      setSelectedRoles(profile.role_ids || []);
      setIsActive(profile.is_active ?? true);
    } else {
      setName('');
      setDescription('');
      setSelectedRoles([]);
      setIsActive(true);
    }
  }, [profile, isOpen]);

  const handleRoleToggle = (roleId: string) => {
    setSelectedRoles((prev) =>
      prev.includes(roleId)
        ? prev.filter((id) => id !== roleId)
        : [...prev, roleId]
    );
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || selectedRoles.length === 0) return;

    onSave({
      name: name.trim(),
      description: description.trim() || undefined,
      roles: selectedRoles,
      is_active: isActive,
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <Card className="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <CardHeader>
          <CardTitle>{profile ? 'Edit Profile' : 'Create Profile'}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Profile Name *
              </label>
              <Input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g., Project Manager, Cost Controller"
                required
                disabled={isLoading}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Description
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Enter profile description"
                rows={3}
                className="w-full px-3 py-2 border border-[var(--color-border)] rounded-md bg-[var(--color-surface)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                disabled={isLoading}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                Roles * (Select at least one)
              </label>
              {rolesLoading ? (
                <p className="text-sm text-[var(--color-text-secondary)]">Loading roles...</p>
              ) : !roles || roles.length === 0 ? (
                <p className="text-sm text-[var(--color-text-secondary)]">No roles available</p>
              ) : (
                <div className="border border-[var(--color-border)] rounded-md p-3 max-h-60 overflow-y-auto space-y-2">
                  {roles.map((role: AdminRole) => (
                    <label
                      key={role.id}
                      className="flex items-center gap-2 cursor-pointer hover:bg-[var(--color-surface-muted)] p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        checked={selectedRoles.includes(String(role.id))}
                        onChange={() => handleRoleToggle(String(role.id))}
                        disabled={isLoading}
                        className="w-4 h-4 text-[var(--color-primary)] border-[var(--color-border)] rounded focus:ring-[var(--color-primary)]"
                      />
                      <span className="text-sm text-[var(--color-text-primary)]">
                        {role.name}
                      </span>
                      {role.description && (
                        <span className="text-xs text-[var(--color-text-secondary)]">
                          - {role.description}
                        </span>
                      )}
                    </label>
                  ))}
                </div>
              )}
              {selectedRoles.length > 0 && (
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  {selectedRoles.length} role(s) selected
                </p>
              )}
            </div>

            <div>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isActive}
                  onChange={(e) => setIsActive(e.target.checked)}
                  disabled={isLoading}
                  className="w-4 h-4 text-[var(--color-primary)] border-[var(--color-border)] rounded focus:ring-[var(--color-primary)]"
                />
                <span className="text-sm text-[var(--color-text-primary)]">Active</span>
              </label>
            </div>

            <div className="flex justify-end gap-2 pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={onClose}
                disabled={isLoading}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={isLoading || !name.trim() || selectedRoles.length === 0}
                className="bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)]"
              >
                {isLoading ? 'Saving...' : profile ? 'Update' : 'Create'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};
