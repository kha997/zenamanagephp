import { describe, it, expect } from 'vitest';
import { formatCurrency, formatAmountShort, buildTopOverrunItems } from '../chartUtils';

describe('chartUtils', () => {
  describe('formatCurrency', () => {
    it('should format USD currency correctly', () => {
      const result = formatCurrency(1000000, 'USD');
      expect(result).toContain('1,000,000');
      expect(result).toMatch(/[$]/);
    });

    it('should format VND currency correctly', () => {
      const result = formatCurrency(1000000, 'VND');
      expect(result).toContain('1,000,000');
      // VND might show as ₫ or VND depending on environment
      expect(result.length).toBeGreaterThan(0);
    });

    it('should handle null amount', () => {
      const result = formatCurrency(null as any, 'USD');
      expect(result).toContain('0');
    });

    it('should handle undefined amount', () => {
      const result = formatCurrency(undefined as any, 'USD');
      expect(result).toContain('0');
    });

    it('should handle NaN amount', () => {
      const result = formatCurrency(NaN, 'USD');
      expect(result).toContain('0');
    });

    it('should use USD as default currency', () => {
      const result = formatCurrency(1000);
      expect(result).toContain('1,000');
      expect(result).toMatch(/[$]/);
    });

    it('should format small amounts correctly', () => {
      const result = formatCurrency(500, 'USD');
      expect(result).toContain('500');
    });

    it('should format large amounts correctly', () => {
      const result = formatCurrency(1234567890, 'USD');
      expect(result).toContain('1,234,567,890');
    });
  });

  describe('formatAmountShort', () => {
    it('should format values less than 1000 as-is', () => {
      expect(formatAmountShort(500)).toBe('500');
      expect(formatAmountShort(999)).toBe('999');
      expect(formatAmountShort(0)).toBe('0');
    });

    it('should format values >= 1000 with K suffix', () => {
      expect(formatAmountShort(1000)).toBe('1K');
      expect(formatAmountShort(1500)).toBe('1.5K');
      expect(formatAmountShort(15000)).toBe('15K');
      expect(formatAmountShort(999999)).toBe('1000K'); // 999999 / 1000 = 999.999, rounds to 1000.0
      expect(formatAmountShort(999500)).toBe('999.5K'); // 999500 / 1000 = 999.5
    });

    it('should format values >= 1_000_000 with M suffix', () => {
      expect(formatAmountShort(1000000)).toBe('1M');
      expect(formatAmountShort(1500000)).toBe('1.5M');
      expect(formatAmountShort(2000000)).toBe('2M');
      expect(formatAmountShort(2500000)).toBe('2.5M');
    });

    it('should remove trailing .0 from K suffix', () => {
      expect(formatAmountShort(1000)).toBe('1K');
      expect(formatAmountShort(2000)).toBe('2K');
      expect(formatAmountShort(10000)).toBe('10K');
    });

    it('should remove trailing .0 from M suffix', () => {
      expect(formatAmountShort(1000000)).toBe('1M');
      expect(formatAmountShort(2000000)).toBe('2M');
      expect(formatAmountShort(10000000)).toBe('10M');
    });

    it('should handle null value', () => {
      expect(formatAmountShort(null as any)).toBe('0');
    });

    it('should handle undefined value', () => {
      expect(formatAmountShort(undefined as any)).toBe('0');
    });

    it('should handle NaN value', () => {
      expect(formatAmountShort(NaN)).toBe('0');
    });

    it('should handle very large values', () => {
      expect(formatAmountShort(5000000)).toBe('5M');
      expect(formatAmountShort(12345678)).toBe('12.3M');
    });
  });

  describe('buildTopOverrunItems', () => {
    it('should return empty array when no items provided', () => {
      const result = buildTopOverrunItems([]);
      expect(result).toEqual([]);
    });

    it('should filter out items with null overrun_amount_total', () => {
      const items = [
        { overrun_amount_total: null },
        { overrun_amount_total: 100 },
        { overrun_amount_total: null },
      ];
      const result = buildTopOverrunItems(items);
      expect(result).toHaveLength(1);
      expect(result[0].overrun_amount_total).toBe(100);
    });

    it('should filter out items with zero overrun_amount_total', () => {
      const items = [
        { overrun_amount_total: 0 },
        { overrun_amount_total: 100 },
        { overrun_amount_total: 0 },
      ];
      const result = buildTopOverrunItems(items);
      expect(result).toHaveLength(1);
      expect(result[0].overrun_amount_total).toBe(100);
    });

    it('should filter out items with negative overrun_amount_total', () => {
      const items = [
        { overrun_amount_total: -50 },
        { overrun_amount_total: 100 },
        { overrun_amount_total: -10 },
      ];
      const result = buildTopOverrunItems(items);
      expect(result).toHaveLength(1);
      expect(result[0].overrun_amount_total).toBe(100);
    });

    it('should sort items by overrun_amount_total descending', () => {
      const items = [
        { overrun_amount_total: 100 },
        { overrun_amount_total: 500 },
        { overrun_amount_total: 200 },
      ];
      const result = buildTopOverrunItems(items);
      expect(result).toHaveLength(3);
      expect(result[0].overrun_amount_total).toBe(500);
      expect(result[1].overrun_amount_total).toBe(200);
      expect(result[2].overrun_amount_total).toBe(100);
    });

    it('should return top N items when maxItems specified', () => {
      const items = [
        { overrun_amount_total: 100 },
        { overrun_amount_total: 500 },
        { overrun_amount_total: 200 },
        { overrun_amount_total: 300 },
        { overrun_amount_total: 400 },
      ];
      const result = buildTopOverrunItems(items, 3);
      expect(result).toHaveLength(3);
      expect(result[0].overrun_amount_total).toBe(500);
      expect(result[1].overrun_amount_total).toBe(400);
      expect(result[2].overrun_amount_total).toBe(300);
    });

    it('should use default maxItems of 5', () => {
      const items = Array.from({ length: 10 }, (_, i) => ({
        overrun_amount_total: (i + 1) * 100,
      }));
      const result = buildTopOverrunItems(items);
      expect(result).toHaveLength(5);
      expect(result[0].overrun_amount_total).toBe(1000);
      expect(result[4].overrun_amount_total).toBe(600);
    });

    it('should preserve additional properties on items', () => {
      const items = [
        { overrun_amount_total: 100, project_id: 1, project_code: 'P1' },
        { overrun_amount_total: 200, project_id: 2, project_code: 'P2' },
      ];
      const result = buildTopOverrunItems(items);
      expect(result[0]).toHaveProperty('project_id', 2);
      expect(result[0]).toHaveProperty('project_code', 'P2');
      expect(result[1]).toHaveProperty('project_id', 1);
      expect(result[1]).toHaveProperty('project_code', 'P1');
    });

    it('should return empty array when all items have null/zero/negative overrun', () => {
      const items = [
        { overrun_amount_total: null },
        { overrun_amount_total: 0 },
        { overrun_amount_total: -10 },
      ];
      const result = buildTopOverrunItems(items);
      expect(result).toEqual([]);
    });
  });
});

