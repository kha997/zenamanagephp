import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../shared/ui/card';

export default function ChangeRequestsPage() {
  return (
    <div className="space-y-6" data-testid="change-requests-page">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Change Requests</h1>
        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
          Track and manage project change requests and scope modifications.
        </p>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>Change Request Management</CardTitle>
          <CardDescription>
            This section is under development
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">
              This section is under development. In future, it will allow you to create, track, 
              and manage change requests for projects, including scope changes, budget modifications, 
              and timeline adjustments.
            </p>
            <div className="rounded-lg bg-[var(--color-surface-subtle)] p-4 border border-[var(--color-border-subtle)]">
              <p className="text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Coming Soon
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                Change request features will include creating change requests, approval workflows, 
                impact analysis, and integration with project budgets and timelines.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
