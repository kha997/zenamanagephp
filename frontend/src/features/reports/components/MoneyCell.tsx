import React from 'react';
import { formatCurrency } from '../utils/chartUtils';

export type MoneyCellProps = {
  value: number | null | undefined;
  currency?: string;          // vd: 'VND', 'USD' – default 'USD'
  fallback?: string;          // default: '-' (cho case null/undefined)
  /** Có hiển thị prefix '+' khi value > 0 (dùng cho diff/overrun) */
  showPlusWhenPositive?: boolean;
  /** Dùng để style màu: normal | muted | danger */
  tone?: 'normal' | 'muted' | 'danger';
  /** Optional className để override thêm */
  className?: string;
};

/**
 * MoneyCell - Shared component for displaying currency values in reports
 * 
 * Round 61: Shared MoneyCell for all reports
 * 
 * Features:
 * - Handles null/undefined → fallback (default: '-')
 * - Formats 0 as currency (not fallback)
 * - Supports prefix '+' for positive values
 * - Supports tone styling (normal, muted, danger)
 * - Uses chartUtils.formatCurrency for consistency
 */
export const MoneyCell: React.FC<MoneyCellProps> = ({
  value,
  currency = 'USD',
  fallback = '-',
  showPlusWhenPositive = false,
  tone = 'normal',
  className = '',
}) => {
  // Handle null/undefined → return fallback
  if (value === null || value === undefined) {
    const baseClass = 'whitespace-nowrap';
    const toneClass =
      tone === 'danger'
        ? 'text-[var(--color-semantic-danger-600)] font-semibold'
        : tone === 'muted'
        ? 'text-[var(--color-text-muted)]'
        : '';
    
    return (
      <span
        data-tone={tone}
        className={`${baseClass} ${toneClass} ${className}`.trim()}
      >
        {fallback}
      </span>
    );
  }

  // Format value as currency (0 is a valid number, always format it)
  const formatted = formatCurrency(value, currency);

  // Add prefix '+' if showPlusWhenPositive and value > 0
  const displayValue = showPlusWhenPositive && value > 0 
    ? `+${formatted}`
    : formatted;

  // Determine tone classes
  const baseClass = 'whitespace-nowrap';
  const toneClass =
    tone === 'danger'
      ? 'text-[var(--color-semantic-danger-600)] font-semibold'
      : tone === 'muted'
      ? 'text-[var(--color-text-muted)]'
      : '';

  return (
    <span
      data-tone={tone}
      className={`${baseClass} ${toneClass} ${className}`.trim()}
    >
      {displayValue}
    </span>
  );
};

