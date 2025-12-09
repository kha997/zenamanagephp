import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useGlobalSearch } from '../hooks/useGlobalSearch';
import { GlobalSearchResultItem } from '../components/GlobalSearchResultItem';
import { resolveSearchResultRoute } from '../lib/navigation';
import { useProjects } from '@/features/projects/hooks';
import type { GlobalSearchModule } from '@/api/searchApi';

const PER_PAGE = 20;

const MODULE_FILTERS: Array<{ id: 'all' | GlobalSearchModule; label: string }> = [
  { id: 'all', label: 'All' },
  { id: 'projects', label: 'Projects' },
  { id: 'tasks', label: 'Tasks' },
  { id: 'documents', label: 'Documents' },
  { id: 'cost', label: 'Cost' },
  { id: 'users', label: 'Users' },
];

export const GlobalSearchPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchInput, setSearchInput] = useState(searchParams.get('q') ?? '');

  const rawModule = (searchParams.get('module') ?? 'all') as 'all' | GlobalSearchModule;
  const moduleFilter = MODULE_FILTERS.some((option) => option.id === rawModule) ? rawModule : 'all';
  const projectFilter = searchParams.get('project_id') ?? '';
  const pageParam = Math.max(1, Number(searchParams.get('page') ?? '1'));
  const projectOptions = useProjects(undefined, { page: 1, per_page: 50 });

  useEffect(() => {
    setSearchInput(searchParams.get('q') ?? '');
  }, [searchParams]);

  const searchQuery = useGlobalSearch(
    {
      q: searchParams.get('q') ?? '',
      modules: moduleFilter !== 'all' ? [moduleFilter] : undefined,
      project_id: projectFilter || undefined,
      page: pageParam,
      per_page: PER_PAGE,
    },
    Boolean(searchParams.get('q'))
  );

  const groupedResults = useMemo(() => {
    const data = searchQuery.data?.results ?? [];
    if (!data.length) {
      return [];
    }

    const groups: Record<string, typeof data> = {};
    data.forEach((item) => {
      groups[item.module] = groups[item.module] ?? [];
      groups[item.module].push(item);
    });

    return Object.entries(groups).map(([module, items]) => ({ module, items }));
  }, [searchQuery.data?.results]);

  const handleSearchSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    const trimmed = searchInput.trim();
    if (!trimmed) {
      return;
    }

    const params = new URLSearchParams(searchParams);
    params.set('q', trimmed);
    params.set('page', '1');
    setSearchParams(params);
  };

  const handleModuleChange = (module: 'all' | GlobalSearchModule) => {
    const params = new URLSearchParams(searchParams);
    if (module === 'all') {
      params.delete('module');
    } else {
      params.set('module', module);
    }
    params.set('page', '1');
    setSearchParams(params);
  };

  const handleProjectChange = (projectId: string) => {
    const params = new URLSearchParams(searchParams);
    if (projectId) {
      params.set('project_id', projectId);
    } else {
      params.delete('project_id');
    }
    params.set('page', '1');
    setSearchParams(params);
  };

  const handleLoadMore = () => {
    const params = new URLSearchParams(searchParams);
    params.set('page', String(pageParam + 1));
    setSearchParams(params);
  };

  const handleResultClick = (result: typeof searchQuery.data?.results[number]) => {
    const route = resolveSearchResultRoute(result);
    if (!route) {
      return;
    }
    const path = route.search ? `${route.path}${route.search}` : route.path;
    navigate(path);
  };

  const totalResults = searchQuery.data?.pagination.total ?? 0;
  const hasMore = pageParam * PER_PAGE < totalResults;

  return (
    <div className="space-y-6">
      <header className="space-y-4">
        <div>
          <p className="text-sm font-medium text-[var(--color-text-secondary)]">Global Search</p>
          <h1 className="text-2xl font-semibold text-[var(--color-text-primary)]">Search across projects, tasks, documents, cost, and people.</h1>
        </div>
        <form onSubmit={handleSearchSubmit} className="flex gap-2">
          <label htmlFor="global-search-input" className="sr-only">
            Search query
          </label>
          <input
            id="global-search-input"
            className="flex-1 rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-muted)] px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Search projects, tasks, documents, cost, or users..."
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
          <button
            type="submit"
            className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
          >
            Search
          </button>
        </form>

        <div className="flex flex-wrap items-center gap-2">
          {MODULE_FILTERS.map((option) => (
            <button
              key={option.id}
              type="button"
              onClick={() => handleModuleChange(option.id)}
              className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                moduleFilter === option.id
                  ? 'bg-primary-600 text-white'
                  : 'bg-[var(--color-surface-muted)] text-[var(--color-text-secondary)] hover:bg-primary-50'
              }`}
            >
              {option.label}
            </button>
          ))}
          <div className="ml-auto flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
            <label htmlFor="search-project-filter" className="text-[var(--color-text-tertiary)]">
              Project:
            </label>
            <select
              id="search-project-filter"
              className="rounded-lg border border-[var(--color-border-subtle)] bg-white px-3 py-1 text-xs text-[var(--color-text-primary)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
              value={projectFilter}
              onChange={(event) => handleProjectChange(event.target.value)}
            >
              <option value="">All projects</option>
              {projectOptions.data?.data.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </select>
          </div>
        </div>
      </header>

      {!searchParams.get('q') ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border-muted)] bg-[var(--color-surface-muted)] p-6 text-sm text-[var(--color-text-secondary)]">
          Start typing to search across your projects, tasks, documents, and cost records.
        </div>
      ) : searchQuery.isError ? (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {searchQuery.error instanceof Error ? searchQuery.error.message : 'Failed to load search results'}
        </div>
      ) : searchQuery.isLoading && !searchQuery.data ? (
        <div className="rounded-lg border border-[var(--color-border-subtle)] bg-white px-4 py-8 text-sm text-[var(--color-text-tertiary)]">
          Loading search results…
        </div>
      ) : (
        <>
          <div className="flex items-center justify-between">
            <p className="text-sm font-medium text-[var(--color-text-secondary)]">
              Showing {searchQuery.data?.results.length ?? 0} of {totalResults} matches
            </p>
            <p className="text-xs text-[var(--color-text-tertiary)]">
              Page {pageParam} · {PER_PAGE} per page
            </p>
          </div>

          {groupedResults.length === 0 ? (
            <div className="rounded-lg border border-[var(--color-border-subtle)] bg-white px-4 py-8 text-sm text-[var(--color-text-secondary)]">
              No results found for “{searchParams.get('q')}”.
            </div>
          ) : (
            <div className="space-y-6">
              {groupedResults.map((group) => (
                <section key={group.module} className="space-y-3">
                  <header className="flex items-center gap-3">
                    <span className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">
                      {MODULE_FILTERS.find((item) => item.id === group.module)?.label ?? group.module}
                    </span>
                    <span className="text-xs text-[var(--color-text-tertiary)]">
                      {group.items.length} result{group.items.length > 1 ? 's' : ''}
                    </span>
                  </header>
                  <div className="grid gap-4 md:grid-cols-2">
                    {group.items.map((item) => (
                      <GlobalSearchResultItem
                        key={`${item.module}-${item.id}`}
                        result={item}
                        onClick={() => handleResultClick(item)}
                      />
                    ))}
                  </div>
                </section>
              ))}
            </div>
          )}

          {hasMore && (
            <div className="flex justify-center">
              <button
                type="button"
                onClick={handleLoadMore}
                className="rounded-lg border border-primary-300 bg-white px-5 py-2 text-sm font-semibold text-primary-600 transition hover:bg-primary-50"
              >
                Load more
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
};
