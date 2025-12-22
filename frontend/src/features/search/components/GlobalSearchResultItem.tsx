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
  onSecondaryClick?: () => void;
  highlightTerm?: string;
}

/**
 * Highlight matching text in a string
 */
function highlightText(text: string | null | undefined, term: string | undefined): React.ReactNode {
  if (!text || !term || term.trim() === '') {
    return text ?? '';
  }

  const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`(${escapedTerm})`, 'gi');
  const parts = text.split(regex);

  return (
    <>
      {parts.map((part, index) => {
        // Check if this part matches the term (case-insensitive)
        const isMatch = new RegExp(`^${escapedTerm}$`, 'i').test(part);
        if (isMatch) {
          return (
            <mark key={index} className="bg-yellow-100 px-0.5 rounded">
              {part}
            </mark>
          );
        }
        return <React.Fragment key={index}>{part}</React.Fragment>;
      })}
    </>
  );
}

export function GlobalSearchResultItem({ result, onClick, onSecondaryClick, highlightTerm }: GlobalSearchResultItemProps) {
  const label = MODULE_LABELS[result.module];

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onClick?.();
    }
  };

  const handleSecondaryClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();
    onSecondaryClick?.();
  };

  const getSecondaryActionLabel = (): string | null => {
    if (!onSecondaryClick) {
      return null;
    }
    switch (result.module) {
      case 'tasks':
        return 'Open in project';
      case 'documents':
        return 'Open in project';
      case 'cost':
        if (result.type === 'change_order') {
          return 'Open contract';
        }
        return null;
      default:
        return null;
    }
  };

  const secondaryLabel = getSecondaryActionLabel();

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
        {highlightText(result.title, highlightTerm)}
      </h3>
      {result.subtitle && (
        <p className="text-xs text-[var(--color-text-secondary)]">
          {highlightText(result.subtitle, highlightTerm)}
        </p>
      )}
      {result.project_name && (
        <p className="text-[var(--color-text-tertiary)] text-xs">Project: {result.project_name}</p>
      )}
      {result.description && (
        <p className="text-[var(--color-text-muted)] text-sm">
          {highlightText(result.description, highlightTerm)}
        </p>
      )}
      {secondaryLabel && (
        <div className="mt-2 flex items-center justify-end border-t border-[var(--color-border-subtle)] pt-2">
          <button
            type="button"
            onClick={handleSecondaryClick}
            className="text-xs text-primary-600 hover:text-primary-700 hover:underline"
            data-testid="secondary-action-button"
          >
            {secondaryLabel}
          </button>
        </div>
      )}
    </div>
  );
}
