import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../shared/ui/card';

export default function UsersPage() {
  return (
    <div className="space-y-6" data-testid="users-page">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Users</h1>
        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
          Manage users, roles, and permissions for your workspace.
        </p>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>User Management</CardTitle>
          <CardDescription>
            This section is under development
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">
              This section is under development. In future, it will allow admins to manage users, 
              assign roles, configure permissions, and handle user invitations for the workspace.
            </p>
            <div className="rounded-lg bg-[var(--color-surface-subtle)] p-4 border border-[var(--color-border-subtle)]">
              <p className="text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Coming Soon
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                User management features will include adding/removing users, role assignment, 
                permission management, and user activity tracking.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
