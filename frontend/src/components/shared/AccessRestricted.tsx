import React from 'react';

export interface AccessRestrictedProps {
  /** Title of the access restricted message */
  title?: string;
  /** Description explaining why access is restricted */
  description?: string;
}

/**
 * AccessRestricted - Shared component for displaying access restriction messages
 * 
 * Used across DashboardPage, SettingsPage, DocumentsListPage, and ReportsPage
 * to provide consistent UI when users lack view permissions.
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const AccessRestricted: React.FC<AccessRestrictedProps> = ({
  title = 'Access Restricted',
  description = "You don't have permission to view this section.",
}) => {
  return (
    <div className="space-y-6">
      <div
        className="p-6 rounded-lg border"
        style={{
          borderColor: 'var(--gray-400)',
          backgroundColor: 'var(--muted-surface)',
        }}
        data-testid="access-restricted"
      >
        <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
          {title}
        </h3>
        <p className="text-sm text-[var(--muted)]">
          {description}
        </p>
      </div>
    </div>
  );
};

