/**
 * Chart utilities for reports
 * 
 * Round 58: Standardized chart utilities
 * 
 * Provides reusable functions for:
 * - Currency formatting
 * - Amount short formatting (K/M suffix)
 * - Top overrun items building
 */

/**
 * Format a number as currency using Intl.NumberFormat
 * 
 * @param amount - The amount to format
 * @param currency - Currency code (default: 'USD')
 * @returns Formatted currency string
 */
export function formatCurrency(amount: number, currency: string = 'USD'): string {
  // Handle null/NaN/undefined safely
  if (amount === null || amount === undefined || isNaN(amount)) {
    amount = 0;
  }

  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  } catch (error) {
    // Fallback if currency code is invalid
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  }
}

/**
 * Format a number with K/M suffix for short display
 * 
 * Examples:
 * - 500 → "500"
 * - 15000 → "15.0K"
 * - 2000000 → "2.0M"
 * 
 * @param value - The value to format
 * @returns Formatted string with K/M suffix
 */
export function formatAmountShort(value: number): string {
  // Handle null/NaN/undefined safely
  if (value === null || value === undefined || isNaN(value)) {
    return '0';
  }

  if (value >= 1_000_000) {
    const formatted = (value / 1_000_000).toFixed(1);
    // Remove trailing .0
    return formatted.endsWith('.0') ? formatted.slice(0, -2) + 'M' : formatted + 'M';
  }
  
  if (value >= 1_000) {
    const formatted = (value / 1_000).toFixed(1);
    // Remove trailing .0
    return formatted.endsWith('.0') ? formatted.slice(0, -2) + 'K' : formatted + 'K';
  }
  
  return value.toString();
}

/**
 * Build top overrun items from a list
 * 
 * Filters items with overrun_amount_total > 0,
 * sorts by overrun_amount_total descending,
 * and returns top N items.
 * 
 * @param items - Array of items with overrun_amount_total property
 * @param maxItems - Maximum number of items to return (default: 5)
 * @returns Filtered and sorted top items
 */
export function buildTopOverrunItems<T extends { overrun_amount_total: number | null }>(
  items: T[],
  maxItems: number = 5
): T[] {
  // Filter items with overrun_amount_total > 0
  const filtered = items.filter(
    (item) => item.overrun_amount_total !== null && item.overrun_amount_total > 0
  );

  if (filtered.length === 0) {
    return [];
  }

  // Sort by overrun_amount_total descending
  const sorted = [...filtered].sort((a, b) => {
    const aVal = a.overrun_amount_total ?? 0;
    const bVal = b.overrun_amount_total ?? 0;
    return bVal - aVal;
  });

  // Take top N items
  return sorted.slice(0, maxItems);
}

/**
 * Summarize a money field across items with multi-currency support
 * 
 * Round 65: Per-page summary row helper
 * 
 * Rules:
 * - If no items have a non-null value for the field → returns { total: null, currency: null }
 * - If all non-null values share the same currency → returns { total: sum, currency: that currency }
 * - If non-null values have different currencies → returns { total: null, currency: null } (show '-')
 * 
 * @param items - Array of items to summarize
 * @param field - Field name to extract from each item (e.g., 'budget_total', 'contract_value')
 * @param currencyField - Field name for currency (default: 'currency')
 * @returns Object with total (number | null) and currency (string | null)
 */
export function summarizeMoneyField<T extends Record<string, any>>(
  items: T[],
  field: keyof T,
  currencyField: keyof T = 'currency' as keyof T
): { total: number | null; currency: string | null } {
  // Extract all non-null values with their currencies
  const valuesWithCurrency: Array<{ value: number; currency: string | null }> = [];
  
  for (const item of items) {
    const value = item[field];
    const currency = item[currencyField] as string | null | undefined;
    
    if (value !== null && value !== undefined && typeof value === 'number' && !isNaN(value)) {
      valuesWithCurrency.push({
        value,
        currency: currency || null,
      });
    }
  }
  
  // If no non-null values, return null
  if (valuesWithCurrency.length === 0) {
    return { total: null, currency: null };
  }
  
  // Get distinct currencies from non-null values
  const distinctCurrencies = new Set<string | null>(
    valuesWithCurrency.map(v => v.currency)
  );
  
  // If more than one distinct currency, return null (can't sum mixed currencies)
  if (distinctCurrencies.size > 1) {
    return { total: null, currency: null };
  }
  
  // All values share the same currency (or all are null currency)
  const singleCurrency = Array.from(distinctCurrencies)[0];
  const total = valuesWithCurrency.reduce((sum, v) => sum + v.value, 0);
  
  return { total, currency: singleCurrency };
}

