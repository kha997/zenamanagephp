import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Input } from '../../../components/ui/primitives/Input';
import type { AdminRole } from '../api';

interface RoleEditorModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: { name: string; description?: string; scope?: string }) => void | Promise<void>;
  role?: AdminRole | null;
  isLoading?: boolean;
}

/**
 * RoleEditorModal - Modal for creating/editing roles
 * Round 234: Admin RBAC - Roles CRUD
 */
export const RoleEditorModal: React.FC<RoleEditorModalProps> = ({
  isOpen,
  onClose,
  onSave,
  role,
  isLoading = false,
}) => {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [scope, setScope] = useState<'system' | 'custom' | 'project'>('custom');

  useEffect(() => {
    if (role) {
      setName(role.name || '');
      setDescription(role.description || '');
      setScope((role.scope as 'system' | 'custom' | 'project') || 'custom');
    } else {
      setName('');
      setDescription('');
      setScope('custom');
    }
  }, [role, isOpen]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    onSave({
      name: name.trim(),
      description: description.trim() || undefined,
      scope,
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>{role ? 'Edit Role' : 'Create Role'}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Role Name *
              </label>
              <Input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Enter role name"
                required
                disabled={isLoading || role?.is_system}
              />
              {role?.is_system && (
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  System roles cannot be renamed
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Description
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Enter role description"
                rows={3}
                className="w-full px-3 py-2 border border-[var(--color-border)] rounded-md bg-[var(--color-surface)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                disabled={isLoading}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Scope
              </label>
              <select
                value={scope}
                onChange={(e) => setScope(e.target.value as 'system' | 'custom' | 'project')}
                className="w-full px-3 py-2 border border-[var(--color-border)] rounded-md bg-[var(--color-surface)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                disabled={isLoading || role?.is_system}
              >
                <option value="system">System</option>
                <option value="custom">Custom</option>
                <option value="project">Project</option>
              </select>
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
                disabled={isLoading || !name.trim()}
                className="bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)]"
              >
                {isLoading ? 'Saving...' : role ? 'Update' : 'Create'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};
