/**
 * Shared router mock utilities for testing components that use useSearchParams
 * 
 * Round 60: Shared test utils for Reports module
 * 
 * Provides reusable mock setup for react-router-dom's useSearchParams hook
 */

import { vi } from 'vitest';

export interface SearchParamsMockConfig {
  /**
   * Initial search params as a record
   */
  initial?: Record<string, string>;
}

/**
 * Create a useSearchParams mock factory
 * 
 * @param config - Configuration for the mock
 * @returns Object with mock setup functions
 */
export function createSearchParamsMock(config: SearchParamsMockConfig = {}) {
  const { initial = {} } = config;
  
  let currentState = new URLSearchParams();
  
  // Initialize with provided initial values
  Object.entries(initial).forEach(([key, value]) => {
    currentState.set(key, value);
  });

  const mockSetSearchParams = vi.fn((updater) => {
    if (typeof updater === 'function') {
      const newParams = updater(new URLSearchParams(currentState));
      currentState = new URLSearchParams(newParams);
    } else {
      currentState = new URLSearchParams(updater);
    }
    // Don't trigger re-render - the component will read from currentState on next render
  });

  const mockSearchParamsFactory = () => {
    return new URLSearchParams(currentState);
  };

  const mock = {
    /**
     * Get current search params (mutable reference for tests that need to set it directly)
     */
    get currentSearchParams() {
      return currentState;
    },
    
    /**
     * Set current search params directly (for tests that need to mutate it)
     */
    set currentSearchParams(value: URLSearchParams) {
      currentState = new URLSearchParams(value);
    },
    
    /**
     * Get current search params as a new instance
     */
    getSearchParams: () => new URLSearchParams(currentState),
    
    /**
     * Set search params from a record
     */
    setSearchParams: (params: Record<string, string>) => {
      currentState = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        currentState.set(key, value);
      });
    },
    
    /**
     * Update a single search param
     */
    updateSearchParam: (key: string, value: string | null) => {
      if (value === null) {
        currentState.delete(key);
      } else {
        currentState.set(key, value);
      }
    },
    
    /**
     * Reset to initial state
     */
    reset: () => {
      currentState = new URLSearchParams();
      Object.entries(initial).forEach(([key, value]) => {
        currentState.set(key, value);
      });
      mockSetSearchParams.mockClear();
    },
    
    /**
     * Get the mock setSearchParams function
     */
    getMockSetSearchParams: () => mockSetSearchParams,
    
    /**
     * Get the mock search params factory function
     */
    getMockSearchParamsFactory: () => mockSearchParamsFactory,
    
    /**
     * Get the vi.mock() factory function for react-router-dom
     */
    getMockFactory: (mockNavigate?: ReturnType<typeof vi.fn>) => {
      return async () => {
        const actual = await vi.importActual('react-router-dom');
        return {
          ...actual,
          useNavigate: () => mockNavigate || vi.fn(),
          useSearchParams: () => {
            // Return a stable reference to prevent infinite re-renders
            return [mockSearchParamsFactory(), mockSetSearchParams];
          },
        };
      };
    },
  };

  return mock;
}

