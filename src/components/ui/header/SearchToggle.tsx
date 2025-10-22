import React, { useState, useRef, useEffect, lazy, Suspense } from 'react';

// Lazy load the search overlay for better performance
const SearchOverlay = lazy(() => import('./SearchOverlay'));

export interface SearchResult {
  id: string;
  title: string;
  description: string;
  type: 'project' | 'task' | 'user' | 'document';
  url: string;
  metadata?: Record<string, any>;
}

export interface SearchToggleProps {
  onSearch?: (_query: string) => Promise<SearchResult[]>;
  placeholder?: string;
  className?: string;
}

export const SearchToggle: React.FC<SearchToggleProps> = ({
  onSearch,
  placeholder = 'Search...',
  className = '',
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [results, setResults] = useState<SearchResult[]>([]);
  const [query, setQuery] = useState('');
  const buttonRef = useRef<HTMLButtonElement>(null);
  const overlayRef = useRef<HTMLDivElement>(null);

  // Handle click outside to close
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (overlayRef.current && !overlayRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && isOpen) {
        setIsOpen(false);
        buttonRef.current?.focus();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen]);

  // Handle global search shortcut (Ctrl/Cmd + K)
  useEffect(() => {
    const handleGlobalSearch = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        setIsOpen(true);
      }
    };

    document.addEventListener('keydown', handleGlobalSearch);
    return () => document.removeEventListener('keydown', handleGlobalSearch);
  }, []);

  const handleSearch = async (searchQuery: string) => {
    setQuery(searchQuery);
    
    if (!searchQuery.trim()) {
      setResults([]);
      return;
    }

    if (!onSearch) {
      // Default search implementation
      setResults([]);
      return;
    }

    setIsLoading(true);
    try {
      const searchResults = await onSearch(searchQuery);
      setResults(searchResults);
    } catch (error) {
      console.error('Search failed:', error);
      setResults([]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleToggle = () => {
    setIsOpen(!isOpen);
    if (!isOpen) {
      // Focus search input when opening
      setTimeout(() => {
        const searchInput = overlayRef.current?.querySelector('input[type="search"]') as HTMLInputElement;
        searchInput?.focus();
      }, 100);
    }
  };

  return (
    <div className={`relative ${className}`}>
      <button
        ref={buttonRef}
        onClick={handleToggle}
        className="header-action-btn"
        aria-expanded={isOpen}
        aria-haspopup="dialog"
        aria-label="Search"
        title="Search (Ctrl+K)"
      >
        <i className="fas fa-search text-lg" aria-hidden="true" />
      </button>

      {/* Search Overlay */}
      {isOpen && (
        <div
          ref={overlayRef}
          className="absolute right-0 mt-2 w-96 bg-header-bg rounded-lg shadow-lg border border-header-border z-header-dropdown"
          role="dialog"
          aria-label="Search"
          aria-modal="true"
        >
          <Suspense fallback={
            <div className="p-4 text-center">
              <i className="fas fa-spinner fa-spin text-header-fg-muted" aria-hidden="true" />
              <p className="text-sm text-header-fg-muted mt-2">Loading search...</p>
            </div>
          }>
            <SearchOverlay
              query={query}
              results={results}
              isLoading={isLoading}
              placeholder={placeholder}
              onSearch={handleSearch}
              onClose={() => setIsOpen(false)}
            />
          </Suspense>
        </div>
      )}
    </div>
  );
};

export default SearchToggle;
