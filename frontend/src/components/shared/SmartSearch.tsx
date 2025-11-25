import React, { useState, useCallback, useRef, useEffect } from 'react';
import { Input } from '../ui/primitives/Input';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface SearchResult {
  id: string | number;
  type: 'project' | 'task' | 'user' | 'document' | 'client';
  title: string;
  description?: string;
  url: string;
}

export interface SmartSearchProps {
  /** Search handler */
  onSearch: (query: string) => Promise<SearchResult[]>;
  /** Result click handler */
  onResultClick?: (result: SearchResult) => void;
  /** Placeholder text */
  placeholder?: string;
  /** Debounce delay in ms (default: 300) */
  debounceMs?: number;
  /** Minimum query length to search (default: 2) */
  minQueryLength?: number;
}

/**
 * SmartSearch - Intelligent search component with autocomplete
 * 
 * Follows Apple-style design spec with tokens and spacing.
 * Supports keyboard navigation (⌘K or Ctrl+K to open).
 */
export const SmartSearch: React.FC<SmartSearchProps> = ({
  onSearch,
  onResultClick,
  placeholder = 'Search... (⌘K)',
  debounceMs = 300,
  minQueryLength = 2,
}) => {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout>();

  // Keyboard shortcut (⌘K or Ctrl+K)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        inputRef.current?.focus();
        setIsOpen(true);
      }
      if (e.key === 'Escape') {
        setIsOpen(false);
        inputRef.current?.blur();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const performSearch = useCallback(
    async (searchQuery: string) => {
      if (searchQuery.length < minQueryLength) {
        setResults([]);
        return;
      }

      setIsSearching(true);
      try {
        console.log('Performing search for:', searchQuery);
        const searchResults = await onSearch(searchQuery);
        console.log('Search results received:', searchResults);
        setResults(searchResults);
        setIsOpen(true);
      } catch (error) {
        console.error('Search failed:', error);
        setResults([]);
      } finally {
        setIsSearching(false);
      }
    },
    [onSearch, minQueryLength]
  );

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setQuery(value);
    setSelectedIndex(-1);
    
    // Always show dropdown when typing
    if (value.length > 0) {
      setIsOpen(true);
    } else {
      setIsOpen(false);
      setResults([]);
    }

    // Debounce search
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(() => {
      performSearch(value);
    }, debounceMs);
  };

  const handleResultClick = (result: SearchResult) => {
    if (onResultClick) {
      onResultClick(result);
    } else {
      window.location.href = result.url;
    }
    setIsOpen(false);
    setQuery('');
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen || results.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex((prev) => (prev < results.length - 1 ? prev + 1 : prev));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex((prev) => (prev > 0 ? prev - 1 : -1));
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && results[selectedIndex]) {
          handleResultClick(results[selectedIndex]);
        }
        break;
    }
  };

  return (
    <>
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
      <div className="relative" data-testid="smart-search">
        <Input
          ref={inputRef}
          type="text"
          value={query}
          onChange={handleInputChange}
          onKeyDown={handleKeyDown}
          onFocus={() => {
            if (query.length > 0) {
              setIsOpen(true);
            }
          }}
          placeholder={placeholder}
          style={{
            width: '100%',
            padding: `${spacing.sm}px ${spacing.md}px`,
            borderRadius: radius.md,
          }}
        />

        {/* Results Dropdown - Show when query exists or results exist */}
        {(isOpen || query.length > 0) && (
          <div
            style={{
              position: 'absolute',
              top: '100%',
              left: 0,
              right: 0,
              marginTop: spacing.xs,
              backgroundColor: 'var(--surface)',
              border: '1px solid var(--border)',
              borderRadius: radius.md,
              boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
              maxHeight: '400px',
              overflowY: 'auto',
              zIndex: 10000,
            }}
          >
            {isSearching ? (
              <div className="p-4 text-center" style={{ color: 'var(--muted)' }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                  <div style={{ 
                    width: 16, 
                    height: 16, 
                    border: '2px solid var(--muted)', 
                    borderTopColor: 'transparent',
                    borderRadius: '50%',
                    animation: 'spin 1s linear infinite'
                  }}></div>
                  Searching...
                </div>
              </div>
            ) : query.length > 0 && query.length < minQueryLength ? (
              <div className="p-4 text-center" style={{ color: 'var(--muted)' }}>
                Type at least {minQueryLength} characters to search
              </div>
            ) : results.length === 0 && query.length >= minQueryLength ? (
              <div className="p-4 text-center" style={{ color: 'var(--muted)' }}>
                No results found for "{query}"
              </div>
            ) : results.length > 0 ? (
              results.map((result, index) => (
                <div
                  key={result.id}
                  onClick={() => handleResultClick(result)}
                  onMouseEnter={() => setSelectedIndex(index)}
                  style={{
                    padding: spacing.md,
                    cursor: 'pointer',
                    backgroundColor: selectedIndex === index ? 'var(--muted-surface)' : 'transparent',
                    borderBottom: index < results.length - 1 ? '1px solid var(--border)' : 'none',
                    transition: 'background-color 0.15s ease',
                  }}
                >
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-medium uppercase" style={{ color: 'var(--muted)' }}>
                      {result.type}
                    </span>
                    <span className="text-sm font-medium" style={{ color: 'var(--text)' }}>
                      {result.title}
                    </span>
                  </div>
                  {result.description && (
                    <p className="text-xs mt-1" style={{ color: 'var(--muted)' }}>
                      {result.description}
                    </p>
                  )}
                </div>
              ))
            ) : null}
          </div>
        )}
      </div>
    </>
  );
};

