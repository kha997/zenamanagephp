import React, { useState, useEffect, useRef } from 'react';
import type { SearchResult } from './SearchToggle';

export interface SearchOverlayProps {
  query: string;
  results: SearchResult[];
  isLoading: boolean;
  placeholder: string;
  onSearch: (_query: string) => void;
  onClose: () => void;
}

export const SearchOverlay: React.FC<SearchOverlayProps> = ({
  query,
  results,
  isLoading,
  placeholder,
  onSearch,
  onClose,
}) => {
  const [searchQuery, setSearchQuery] = useState(query);
  const inputRef = useRef<HTMLInputElement>(null);

  // Focus input when component mounts
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  // Handle search input
  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchQuery(value);
    onSearch(value);
  };

  // Handle result click
  const handleResultClick = (result: SearchResult) => {
    window.location.href = result.url;
    onClose();
  };

  // Get result icon
  const getResultIcon = (type: SearchResult['type']) => {
    switch (type) {
      case 'project':
        return 'fas fa-project-diagram text-blue-500';
      case 'task':
        return 'fas fa-tasks text-green-500';
      case 'user':
        return 'fas fa-user text-purple-500';
      case 'document':
        return 'fas fa-file-alt text-orange-500';
      default:
        return 'fas fa-search text-gray-500';
    }
  };

  // Get result type label
  const getResultTypeLabel = (type: SearchResult['type']) => {
    switch (type) {
      case 'project':
        return 'Project';
      case 'task':
        return 'Task';
      case 'user':
        return 'User';
      case 'document':
        return 'Document';
      default:
        return 'Result';
    }
  };

  return (
    <div
      className="w-96 max-h-96 overflow-hidden"
      role="dialog"
      aria-label="Search results"
      aria-modal="true"
      tabIndex={-1}
    >
      {/* Search Input */}
      <div className="p-4 border-b border-header-border">
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i className="fas fa-search text-header-fg-muted" aria-hidden="true" />
          </div>
          <input
            ref={inputRef}
            type="search"
            value={searchQuery}
            onChange={handleSearchChange}
            placeholder={placeholder}
            className="block w-full pl-10 pr-3 py-2 border border-header-border rounded-md text-sm placeholder-header-fg-muted focus:outline-none focus:ring-2 focus:ring-nav-active focus:border-nav-active"
            autoComplete="off"
          />
          {isLoading && (
            <div className="absolute inset-y-0 right-0 pr-3 flex items-center">
              <i className="fas fa-spinner fa-spin text-header-fg-muted" aria-hidden="true" />
            </div>
          )}
        </div>
      </div>

      {/* Results */}
      <div className="max-h-80 overflow-y-auto">
        {searchQuery.trim() === '' ? (
          <div className="p-4 text-center">
            <div className="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
              <i className="fas fa-search text-gray-400" aria-hidden="true" />
            </div>
            <p className="text-sm text-header-fg-muted">Start typing to search</p>
            <p className="text-xs text-header-fg-muted mt-1">
              Search for projects, tasks, users, and documents
            </p>
          </div>
        ) : isLoading ? (
          <div className="p-4 text-center">
            <i className="fas fa-spinner fa-spin text-header-fg-muted" aria-hidden="true" />
            <p className="text-sm text-header-fg-muted mt-2">Searching...</p>
          </div>
        ) : results.length === 0 ? (
          <div className="p-4 text-center">
            <div className="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
              <i className="fas fa-search text-gray-400" aria-hidden="true" />
            </div>
            <p className="text-sm text-header-fg-muted">No results found</p>
            <p className="text-xs text-header-fg-muted mt-1">
              Try different keywords or check your spelling
            </p>
          </div>
        ) : (
          <div className="divide-y divide-header-border">
            {results.map((result) => (
              <button
                key={result.id}
                type="button"
                className="w-full text-left p-4 hover:bg-header-bg-hover transition-colors"
                onClick={() => handleResultClick(result)}
              >
                <div className="flex items-start space-x-3">
                  {/* Icon */}
                  <div className="flex-shrink-0 mt-0.5">
                    <i className={`${getResultIcon(result.type)} text-sm`} aria-hidden="true" />
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <p className="text-sm font-medium text-header-fg truncate">
                        {result.title}
                      </p>
                      <span className="text-xs text-header-fg-muted ml-2 flex-shrink-0">
                        {getResultTypeLabel(result.type)}
                      </span>
                    </div>
                    <p className="text-xs text-header-fg-muted mt-1 line-clamp-2">
                      {result.description}
                    </p>
                    {result.metadata && Object.keys(result.metadata).length > 0 && (
                      <div className="flex flex-wrap gap-1 mt-2">
                        {Object.entries(result.metadata).slice(0, 3).map(([key, value]) => (
                          <span
                            key={key}
                            className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600"
                          >
                            {key}: {value}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Footer */}
      {searchQuery.trim() !== '' && results.length > 0 && (
        <div className="px-4 py-3 border-t border-header-border bg-header-bg-hover">
          <div className="flex items-center justify-between text-xs text-header-fg-muted">
            <span>
              {results.length} result{results.length !== 1 ? 's' : ''} found
            </span>
            <span>Press Enter to search</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default SearchOverlay;
