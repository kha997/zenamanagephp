import { describe, it, expect } from 'vitest';
import { zeroFillTimeSeries, formatMonthLabel } from '../timeSeriesHelpers';

describe('timeSeriesHelpers', () => {
  describe('zeroFillTimeSeries', () => {
    it('fills missing months with zeros for 12-month window', () => {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;
      
      // Use months that are definitely in the last 12 months
      const month1 = currentMonth >= 2 ? currentMonth - 1 : 12;
      const year1 = currentMonth >= 2 ? currentYear : currentYear - 1;
      const month2 = currentMonth >= 3 ? currentMonth - 2 : (currentMonth === 2 ? 12 : 11);
      const year2 = currentMonth >= 3 ? currentYear : (currentMonth === 2 ? currentYear - 1 : currentYear - 1);
      
      const data = [
        { year: year1, month: month1, amount_payable_approved: 100000 },
        { year: year2, month: month2, amount_payable_approved: 150000 },
      ];

      const result = zeroFillTimeSeries(data, 'amount_payable_approved');

      // Should return exactly 12 months
      expect(result).toHaveLength(12);

      // Should include the provided months with correct amounts
      const point1 = result.find((r) => r.year === year1 && r.month === month1);
      expect(point1).toBeDefined();
      expect(point1?.amount).toBe(100000);

      const point2 = result.find((r) => r.year === year2 && r.month === month2);
      expect(point2).toBeDefined();
      expect(point2?.amount).toBe(150000);

      // Should fill other months with zeros
      const otherMonths = result.filter(
        (r) => !((r.year === year1 && r.month === month1) || (r.year === year2 && r.month === month2))
      );
      expect(otherMonths.every((m) => m.amount === 0)).toBe(true);
    });

    it('handles empty data array', () => {
      const result = zeroFillTimeSeries([], 'amount_payable_approved');

      expect(result).toHaveLength(12);
      expect(result.every((m) => m.amount === 0)).toBe(true);
    });

    it('handles data with different amount field names', () => {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;
      
      // Use a month that is definitely in the last 12 months
      const targetMonth = currentMonth >= 2 ? currentMonth - 1 : 12;
      const targetYear = currentMonth >= 2 ? currentYear : currentYear - 1;
      
      const data = [
        { year: targetYear, month: targetMonth, amount_paid: 50000 },
      ];

      const result = zeroFillTimeSeries(data, 'amount_paid');

      expect(result).toHaveLength(12);
      const targetPoint = result.find((r) => r.year === targetYear && r.month === targetMonth);
      expect(targetPoint).toBeDefined();
      expect(targetPoint?.amount).toBe(50000);
    });

    it('handles string amounts by converting to numbers', () => {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;
      
      // Use months that are definitely in the last 12 months
      const targetMonth = currentMonth >= 2 ? currentMonth - 1 : 12;
      const targetYear = currentMonth >= 2 ? currentYear : currentYear - 1;
      
      const data = [
        { year: targetYear, month: targetMonth, amount_payable_approved: '100000' },
      ];

      const result = zeroFillTimeSeries(data, 'amount_payable_approved');

      const targetPoint = result.find((r) => r.year === targetYear && r.month === targetMonth);
      expect(targetPoint).toBeDefined();
      expect(targetPoint?.amount).toBe(100000);
    });

    it('generates correct month sequence going backwards from current month', () => {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;

      const result = zeroFillTimeSeries([], 'amount');

      // Last month should be current month
      expect(result[result.length - 1].year).toBe(currentYear);
      expect(result[result.length - 1].month).toBe(currentMonth);

      // First month should be 11 months ago
      const firstMonth = new Date(currentYear, currentMonth - 12, 1);
      expect(result[0].year).toBe(firstMonth.getFullYear());
      expect(result[0].month).toBe(firstMonth.getMonth() + 1);
    });
  });

  describe('formatMonthLabel', () => {
    it('formats month label correctly', () => {
      expect(formatMonthLabel(2024, 1)).toMatch(/Jan.*2024/);
      expect(formatMonthLabel(2024, 12)).toMatch(/Dec.*2024/);
      expect(formatMonthLabel(2025, 6)).toMatch(/Jun.*2025/);
    });
  });
});
