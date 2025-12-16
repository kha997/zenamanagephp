import React, { useEffect, useRef, useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCommandPalette } from '../context/CommandPaletteContext';
import { useGlobalSearch } from '../hooks/useGlobalSearch';
import { GlobalSearchResultItem } from './GlobalSearchResultItem';
import { resolveSearchResultRoute } from '../lib/navigation';
import type { GlobalSearchResult } from '@/api/searchApi';

const MAX_RESULTS = 10;
const DEBOUNCE_MS = 300;

export function CommandPalette() {
  const { isOpen, close } = useCommandPalette();
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);

  // Debounce query input
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(query.trim());
    }, DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [query]);

  // Auto-focus input when palette opens
  useEffect(() => {
    if (isOpen && inputRef.current) {
      // Small delay to ensure DOM is ready
      const timer = setTimeout(() => {
        inputRef.current?.focus();
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [isOpen]);

  // Reset state when palette closes
  useEffect(() => {
    if (!isOpen) {
      setQuery('');
      setDebouncedQuery('');
      setSelectedIndex(0);
    }
  }, [isOpen]);

  // Search query
  const searchQuery = useGlobalSearch(
    {
      q: debouncedQuery,
      page: 1,
      per_page: MAX_RESULTS,
    },
    debouncedQuery.length > 0,
  );

  const results = useMemo(() => {
    return searchQuery.data?.results ?? [];
  }, [searchQuery.data?.results]);

  // Reset selected index when results change
  useEffect(() => {
    setSelectedIndex(0);
  }, [results.length]);

  // Handle keyboard navigation
  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setSelectedIndex((prev) => (prev < results.length - 1 ? prev + 1 : prev));
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setSelectedIndex((prev) => (prev > 0 ? prev - 1 : 0));
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      if (results[selectedIndex]) {
        handleResultClick(results[selectedIndex]);
      }
      return;
    }
  };

  const handleResultClick = (result: GlobalSearchResult) => {
    const route = resolveSearchResultRoute(result);
    if (!route) {
      return;
    }
    const path = route.search ? `${route.path}${route.search}` : route.path;
    navigate(path);
    close();
  };

  if (!isOpen) {
    return null;
  }

  const isLoading = searchQuery.isLoading && !searchQuery.data;
  const hasResults = results.length > 0;
  const showEmptyState = !debouncedQuery && !isLoading;
  const showNoResults = debouncedQuery && !isLoading && !hasResults;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-start justify-center pt-[20vh]"
      role="dialog"
      aria-modal="true"
      aria-label="Command palette"
      onClick={(e) => {
        // Close on backdrop click
        if (e.target === e.currentTarget) {
          close();
        }
      }}
    >
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />

      {/* Panel */}
      <div className="relative w-full max-w-2xl mx-4 rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] shadow-xl">
        {/* Input */}
        <div className="border-b border-[var(--color-border-subtle)] p-4">
          <input
            ref={inputRef}
            type="text"
            className="w-full rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-muted)] px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Type to search across projects, tasks, documents, cost, users…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            aria-label="Search query"
          />
        </div>

        {/* Results */}
        <div className="max-h-[60vh] overflow-y-auto">
          {showEmptyState && (
            <div className="px-4 py-8 text-center text-sm text-[var(--color-text-secondary)]">
              Type to search across projects, tasks, documents, cost, users…
            </div>
          )}

          {isLoading && (
            <div className="px-4 py-8 text-center text-sm text-[var(--color-text-tertiary)]">
              Searching…
            </div>
          )}

          {showNoResults && (
            <div className="px-4 py-8 text-center text-sm text-[var(--color-text-secondary)]">
              No matches for &quot;{debouncedQuery}&quot;
            </div>
          )}

          {hasResults && (
            <div className="p-2 space-y-1">
              {results.map((result, index) => (
                <div
                  key={`${result.module}-${result.id}`}
                  className={`
                    rounded-lg transition
                    ${index === selectedIndex ? 'bg-primary-50 ring-2 ring-primary-500' : 'hover:bg-[var(--color-surface-muted)]'}
                  `}
                >
                  <GlobalSearchResultItem
                    result={result}
                    highlightTerm={debouncedQuery}
                    onClick={() => handleResultClick(result)}
                  />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer hint */}
        {hasResults && (
          <div className="border-t border-[var(--color-border-subtle)] px-4 py-2 text-xs text-[var(--color-text-tertiary)]">
            <span className="inline-flex items-center gap-2">
              <kbd className="rounded bg-[var(--color-surface-muted)] px-1.5 py-0.5 font-mono">↑</kbd>
              <kbd className="rounded bg-[var(--color-surface-muted)] px-1.5 py-0.5 font-mono">↓</kbd>
              <span>Navigate</span>
              <span className="mx-2">·</span>
              <kbd className="rounded bg-[var(--color-surface-muted)] px-1.5 py-0.5 font-mono">Enter</kbd>
              <span>Select</span>
              <span className="mx-2">·</span>
              <kbd className="rounded bg-[var(--color-surface-muted)] px-1.5 py-0.5 font-mono">Esc</kbd>
              <span>Close</span>
            </span>
          </div>
        )}
      </div>
    </div>
  );
}
