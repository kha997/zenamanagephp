/**
 * reportFiltersUtils - Shared utilities for parsing and serializing report filters
 * 
 * Round 62: DRY & hardening phần filters của Reports
 * 
 * Provides pure functions for:
 * - Parsing URLSearchParams to typed values (numbers, page, sort)
 * - Serializing values back to URLSearchParams
 * - Consistent handling of edge cases (empty strings, NaN, invalid values)
 */

export type SortDirection = 'asc' | 'desc';

export interface SortParams {
  sort_by?: string;
  sort_direction?: SortDirection;
}

/**
 * Parse "page" parameter from URLSearchParams
 * 
 * @param params - URLSearchParams instance
 * @param key - Parameter key (default: 'page')
 * @param defaultValue - Default value if missing or invalid (default: 1)
 * @returns Page number >= 1
 */
export function parsePageParam(
  params: URLSearchParams,
  key = 'page',
  defaultValue = 1
): number {
  const value = params.get(key);
  if (!value) {
    return defaultValue;
  }
  
  const parsed = parseInt(value, 10);
  if (isNaN(parsed) || parsed <= 0) {
    return defaultValue;
  }
  
  return parsed;
}

/**
 * Parse "per_page" parameter from URLSearchParams
 * 
 * @param params - URLSearchParams instance
 * @param key - Parameter key (default: 'per_page')
 * @param defaultValue - Default value if missing or invalid (default: 25)
 * @returns Per page number >= 1
 */
export function parsePerPageParam(
  params: URLSearchParams,
  key = 'per_page',
  defaultValue = 25
): number {
  const value = params.get(key);
  if (!value) {
    return defaultValue;
  }
  
  const parsed = parseInt(value, 10);
  if (isNaN(parsed) || parsed <= 0) {
    return defaultValue;
  }
  
  return parsed;
}

/**
 * Parse number parameter from URLSearchParams
 * 
 * Empty string or missing param → undefined
 * Valid number → number
 * NaN or invalid → undefined
 * 
 * @param params - URLSearchParams instance
 * @param key - Parameter key
 * @returns Number or undefined
 */
export function parseNumberParam(
  params: URLSearchParams,
  key: string
): number | undefined {
  const value = params.get(key);
  
  // Empty string or missing → undefined
  if (!value || value === '') {
    return undefined;
  }
  
  const parsed = parseFloat(value);
  
  // NaN → undefined
  if (isNaN(parsed)) {
    return undefined;
  }
  
  return parsed;
}

/**
 * Parse sort parameters from URLSearchParams
 * 
 * @param params - URLSearchParams instance
 * @param defaults - Default sort parameters
 * @returns SortParams with sort_by and sort_direction
 */
export function parseSortParams(
  params: URLSearchParams,
  defaults: SortParams
): SortParams {
  const sortBy = params.get('sort_by') || defaults.sort_by;
  const sortDirection = params.get('sort_direction') as SortDirection | null;
  
  // Validate sort_direction
  const validDirection: SortDirection | undefined = 
    sortDirection === 'asc' || sortDirection === 'desc'
      ? sortDirection
      : defaults.sort_direction;
  
  return {
    sort_by: sortBy,
    sort_direction: validDirection,
  };
}

/**
 * Set a parameter in URLSearchParams with consistent semantics
 * 
 * - string/number → set as string
 * - null/undefined → delete from params
 * 
 * @param params - URLSearchParams instance to modify
 * @param key - Parameter key
 * @param value - Value to set (string, number, null, or undefined)
 */
export function setParam(
  params: URLSearchParams,
  key: string,
  value: string | number | null | undefined
): void {
  if (value === null || value === undefined) {
    params.delete(key);
  } else {
    params.set(key, String(value));
  }
}

