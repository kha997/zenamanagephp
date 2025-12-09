import React from 'react';
import type { GlobalSearchResult } from '@/api/searchApi';

const MODULE_LABELS: Record<GlobalSearchResult['module'], string> = {
  projects: 'Project',
  tasks: 'Task',
  documents: 'Document',
  cost: 'Cost',
  users: 'User',
};

interface GlobalSearchResultItemProps {
  result: GlobalSearchResult;
  onClick?: () => void;
}

export function GlobalSearchResultItem({ result, onClick }: GlobalSearchResultItemProps) {
  const label = MODULE_LABELS[result.module];

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onClick?.();
    }
  };

  return (
    <div
      data-testid="global-search-result"
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={handleKeyDown}
      className="group flex cursor-pointer flex-col gap-1 rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] px-4 py-4 transition hover:border-primary-400 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
    >
      <div className="flex items-center justify-between gap-3">
        <span className="inline-flex rounded-full bg-[var(--color-surface-muted)] px-3 py-0.5 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">
          {label}
        </span>
        {result.status && (
          <span className="text-xs font-semibold text-[var(--color-text-tertiary)]">
            {result.status.replace('_', ' ')}
          </span>
        )}
      </div>
      <h3 className="text-sm font-semibold text-[var(--color-text-primary)]">
        {result.title}
      </h3>
      {result.subtitle && (
        <p className="text-xs text-[var(--color-text-secondary)]">{result.subtitle}</p>
      )}
      {result.project_name && (
        <p className="text-[var(--color-text-tertiary)] text-xs">Project: {result.project_name}</p>
      )}
      {result.description && (
        <p className="text-[var(--color-text-muted)] text-sm">{result.description}</p>
      )}
    </div>
  );
}
