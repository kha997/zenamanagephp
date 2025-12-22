import { describe, it, expect } from 'vitest';
import {
  parsePageParam,
  parsePerPageParam,
  parseNumberParam,
  parseSortParams,
  setParam,
  type SortParams,
} from '../reportFiltersUtils';

describe('reportFiltersUtils', () => {
  describe('parsePageParam', () => {
    it('should return default value when param is missing', () => {
      const params = new URLSearchParams();
      expect(parsePageParam(params)).toBe(1);
      expect(parsePageParam(params, 'page', 2)).toBe(2);
    });

    it('should return default value when param is empty string', () => {
      const params = new URLSearchParams('page=');
      expect(parsePageParam(params)).toBe(1);
    });

    it('should parse valid page number', () => {
      const params = new URLSearchParams('page=5');
      expect(parsePageParam(params)).toBe(5);
    });

    it('should return default when page is 0', () => {
      const params = new URLSearchParams('page=0');
      expect(parsePageParam(params)).toBe(1);
    });

    it('should return default when page is negative', () => {
      const params = new URLSearchParams('page=-1');
      expect(parsePageParam(params)).toBe(1);
    });

    it('should return default when page is NaN', () => {
      const params = new URLSearchParams('page=abc');
      expect(parsePageParam(params)).toBe(1);
    });

    it('should use custom key', () => {
      const params = new URLSearchParams('p=3');
      expect(parsePageParam(params, 'p')).toBe(3);
    });
  });

  describe('parsePerPageParam', () => {
    it('should return default value when param is missing', () => {
      const params = new URLSearchParams();
      expect(parsePerPageParam(params)).toBe(25);
      expect(parsePerPageParam(params, 'per_page', 50)).toBe(50);
    });

    it('should parse valid per_page number', () => {
      const params = new URLSearchParams('per_page=50');
      expect(parsePerPageParam(params)).toBe(50);
    });

    it('should return default when per_page is 0', () => {
      const params = new URLSearchParams('per_page=0');
      expect(parsePerPageParam(params)).toBe(25);
    });

    it('should return default when per_page is NaN', () => {
      const params = new URLSearchParams('per_page=invalid');
      expect(parsePerPageParam(params)).toBe(25);
    });
  });

  describe('parseNumberParam', () => {
    it('should return undefined when param is missing', () => {
      const params = new URLSearchParams();
      expect(parseNumberParam(params, 'min_overrun_amount')).toBeUndefined();
    });

    it('should return undefined when param is empty string', () => {
      const params = new URLSearchParams('min_overrun_amount=');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBeUndefined();
    });

    it('should parse valid number', () => {
      const params = new URLSearchParams('min_overrun_amount=300');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBe(300);
    });

    it('should parse 0 as valid number', () => {
      const params = new URLSearchParams('min_overrun_amount=0');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBe(0);
    });

    it('should parse negative number', () => {
      const params = new URLSearchParams('min_overrun_amount=-100');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBe(-100);
    });

    it('should parse decimal number', () => {
      const params = new URLSearchParams('min_overrun_amount=123.45');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBe(123.45);
    });

    it('should return undefined when param is NaN', () => {
      const params = new URLSearchParams('min_overrun_amount=abc');
      expect(parseNumberParam(params, 'min_overrun_amount')).toBeUndefined();
    });

    it('should return undefined when param is empty space', () => {
      const params = new URLSearchParams('min_overrun_amount=   ');
      // URLSearchParams.get() trims whitespace, so this becomes empty string
      expect(parseNumberParam(params, 'min_overrun_amount')).toBeUndefined();
    });
  });

  describe('parseSortParams', () => {
    const defaults: SortParams = {
      sort_by: 'overrun_amount',
      sort_direction: 'desc',
    };

    it('should return defaults when params are missing', () => {
      const params = new URLSearchParams();
      const result = parseSortParams(params, defaults);
      expect(result).toEqual(defaults);
    });

    it('should parse valid sort_by and sort_direction', () => {
      const params = new URLSearchParams('sort_by=code&sort_direction=asc');
      const result = parseSortParams(params, defaults);
      expect(result.sort_by).toBe('code');
      expect(result.sort_direction).toBe('asc');
    });

    it('should use default sort_direction when invalid', () => {
      const params = new URLSearchParams('sort_by=code&sort_direction=invalid');
      const result = parseSortParams(params, defaults);
      expect(result.sort_by).toBe('code');
      expect(result.sort_direction).toBe('desc');
    });

    it('should use default sort_by when missing', () => {
      const params = new URLSearchParams('sort_direction=asc');
      const result = parseSortParams(params, defaults);
      expect(result.sort_by).toBe('overrun_amount');
      expect(result.sort_direction).toBe('asc');
    });

    it('should handle empty sort_by', () => {
      const params = new URLSearchParams('sort_by=');
      const result = parseSortParams(params, defaults);
      expect(result.sort_by).toBe(defaults.sort_by);
    });

    it('should handle empty sort_direction', () => {
      const params = new URLSearchParams('sort_direction=');
      const result = parseSortParams(params, defaults);
      expect(result.sort_direction).toBe('desc');
    });

    it('should handle case-insensitive sort_direction (but keep as-is)', () => {
      // Note: We don't normalize case, just validate
      const params = new URLSearchParams('sort_direction=ASC');
      const result = parseSortParams(params, defaults);
      // 'ASC' is not 'asc' or 'desc', so uses default
      expect(result.sort_direction).toBe('desc');
    });
  });

  describe('setParam', () => {
    it('should set string value', () => {
      const params = new URLSearchParams();
      setParam(params, 'search', 'test');
      expect(params.get('search')).toBe('test');
    });

    it('should set number value as string', () => {
      const params = new URLSearchParams();
      setParam(params, 'min_overrun_amount', 300);
      expect(params.get('min_overrun_amount')).toBe('300');
    });

    it('should set 0 as string', () => {
      const params = new URLSearchParams();
      setParam(params, 'page', 0);
      expect(params.get('page')).toBe('0');
    });

    it('should delete param when value is null', () => {
      const params = new URLSearchParams('search=test');
      setParam(params, 'search', null);
      expect(params.get('search')).toBeNull();
    });

    it('should delete param when value is undefined', () => {
      const params = new URLSearchParams('search=test');
      setParam(params, 'search', undefined);
      expect(params.get('search')).toBeNull();
    });

    it('should overwrite existing param', () => {
      const params = new URLSearchParams('page=1');
      setParam(params, 'page', 2);
      expect(params.get('page')).toBe('2');
    });

    it('should handle multiple params', () => {
      const params = new URLSearchParams();
      setParam(params, 'search', 'test');
      setParam(params, 'page', 1);
      setParam(params, 'status', 'active');
      expect(params.get('search')).toBe('test');
      expect(params.get('page')).toBe('1');
      expect(params.get('status')).toBe('active');
    });
  });
});

