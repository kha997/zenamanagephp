import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../shared/ui/card';

export default function TeamPage() {
  return (
    <div className="space-y-6" data-testid="team-page">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Team</h1>
        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
          Manage your project team, roles, and responsibilities.
        </p>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>Team Management</CardTitle>
          <CardDescription>
            This section is under development
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">
              Here you will be able to see the project team, roles, and responsibilities. 
              This section is not fully implemented yet.
            </p>
            <div className="rounded-lg bg-[var(--color-surface-subtle)] p-4 border border-[var(--color-border-subtle)]">
              <p className="text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Coming Soon
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                Team management features will include member assignments, role management, 
                and team collaboration tools.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

