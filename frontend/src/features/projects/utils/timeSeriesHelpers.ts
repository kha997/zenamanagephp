/**
 * Time-series helper utilities
 * 
 * Round 224: Project Cost Dashboard Frontend
 * 
 * Provides utilities for zero-filling time-series data to ensure
 * continuous 12-month coverage for charts.
 */

export interface TimeSeriesPoint {
  year: number;
  month: number;
  amount: number;
}

/**
 * Zero-fill time-series data to cover the last 12 months
 * 
 * Takes an array of time-series points (with year, month, amount)
 * and fills missing months with zero amounts, ensuring a continuous
 * 12-month window from now going backwards.
 * 
 * @param data - Array of time-series points from API
 * @param amountField - Field name for the amount (e.g., 'amount_payable_approved', 'amount_paid')
 * @returns Array of 12 time-series points (one per month)
 */
export function zeroFillTimeSeries<T extends { year: number; month: number }>(
  data: T[],
  amountField: keyof T
): TimeSeriesPoint[] {
  // Get current date
  const now = new Date();
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth() + 1; // 1-12

  // Build a map of existing data points by year-month key
  const dataMap = new Map<string, number>();
  for (const item of data) {
    const key = `${item.year}-${item.month}`;
    const amount = item[amountField];
    let numAmount = 0;
    if (typeof amount === 'number') {
      numAmount = amount;
    } else if (typeof amount === 'string') {
      numAmount = parseFloat(amount) || 0;
    } else {
      numAmount = 0;
    }
    dataMap.set(key, numAmount);
  }

  // Generate 12 months going backwards from current month
  const result: TimeSeriesPoint[] = [];
  
  for (let i = 11; i >= 0; i--) {
    const targetDate = new Date(currentYear, currentMonth - 1 - i, 1);
    const year = targetDate.getFullYear();
    const month = targetDate.getMonth() + 1; // 1-12
    
    const key = `${year}-${month}`;
    const amount = dataMap.get(key) ?? 0;
    
    result.push({ year, month, amount });
  }

  return result;
}

/**
 * Format month label for display (e.g., "Jan 2025")
 * 
 * @param year - Year
 * @param month - Month (1-12)
 * @returns Formatted month label
 */
export function formatMonthLabel(year: number, month: number): string {
  const date = new Date(year, month - 1, 1);
  return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}
