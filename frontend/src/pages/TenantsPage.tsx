import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../shared/ui/card';

export default function TenantsPage() {
  return (
    <div className="space-y-6" data-testid="tenants-page">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Tenants</h1>
        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
          Manage multi-tenant workspaces and organizations.
        </p>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>Tenant Management</CardTitle>
          <CardDescription>
            This section is under development
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">
              This section is under development. In future, it will allow admins to manage tenants, 
              configure workspace settings, and handle multi-tenant isolation.
            </p>
            <div className="rounded-lg bg-[var(--color-surface-subtle)] p-4 border border-[var(--color-border-subtle)]">
              <p className="text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Coming Soon
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                Tenant management features will include creating and configuring workspaces, 
                managing tenant members, and setting up tenant-specific preferences.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

