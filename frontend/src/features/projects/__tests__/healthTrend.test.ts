import { describe, it, expect } from 'vitest';
import { computeHealthTrend } from '../healthTrend';
import type { ProjectHealthSnapshot } from '../api';

describe('healthTrend helpers', () => {
  const createSnapshot = (
    id: string,
    snapshotDate: string,
    overallStatus: string
  ): ProjectHealthSnapshot => ({
    id,
    snapshot_date: snapshotDate,
    overall_status: overallStatus,
    schedule_status: 'on_track',
    cost_status: 'on_budget',
    tasks_completion_rate: 0.5,
    blocked_tasks_ratio: 0.1,
    overdue_tasks: 0,
    created_at: null,
  });

  describe('computeHealthTrend', () => {
    describe('empty or insufficient data', () => {
      it('should return unknown direction for empty history', () => {
        const result = computeHealthTrend([]);
        expect(result.direction).toBe('unknown');
        expect(result.lastStatus).toBeNull();
        expect(result.prevStatus).toBeNull();
        expect(result.totalDays).toBe(0);
        expect(result.countGood).toBe(0);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(0);
        expect(result.timeline).toEqual([]);
      });

      it('should return unknown direction for null history', () => {
        const result = computeHealthTrend(null as any);
        expect(result.direction).toBe('unknown');
      });

      it('should return unknown direction for single snapshot', () => {
        const history = [
          createSnapshot('1', '2025-01-01', 'good'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('unknown');
        expect(result.lastStatus).toBe('good');
        expect(result.prevStatus).toBeNull();
      });
    });

    describe('trend direction calculation', () => {
      it('should detect improving trend: critical → warning', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'warning'),
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('improving');
        expect(result.lastStatus).toBe('warning');
        expect(result.prevStatus).toBe('critical');
      });

      it('should detect improving trend: warning → good', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'good'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('improving');
        expect(result.lastStatus).toBe('good');
        expect(result.prevStatus).toBe('warning');
      });

      it('should detect improving trend: critical → good', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'good'),
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('improving');
      });

      it('should detect worsening trend: good → warning', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'warning'),
          createSnapshot('1', '2025-01-01', 'good'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('worsening');
        expect(result.lastStatus).toBe('warning');
        expect(result.prevStatus).toBe('good');
      });

      it('should detect worsening trend: warning → critical', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'critical'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('worsening');
      });

      it('should detect worsening trend: good → critical', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'critical'),
          createSnapshot('1', '2025-01-01', 'good'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('worsening');
      });

      it('should detect stable trend: good → good', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'good'),
          createSnapshot('1', '2025-01-01', 'good'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('stable');
      });

      it('should detect stable trend: warning → warning', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'warning'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('stable');
      });

      it('should detect stable trend: critical → critical', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'critical'),
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('stable');
      });
    });

    describe('distribution counting', () => {
      it('should count statuses correctly in mixed history', () => {
        const history = [
          createSnapshot('5', '2025-01-05', 'good'),
          createSnapshot('4', '2025-01-04', 'warning'),
          createSnapshot('3', '2025-01-03', 'critical'),
          createSnapshot('2', '2025-01-02', 'good'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(2);
        expect(result.countWarning).toBe(2);
        expect(result.countCritical).toBe(1);
        expect(result.totalDays).toBe(5);
      });

      it('should count only good statuses', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'good'),
          createSnapshot('2', '2025-01-02', 'good'),
          createSnapshot('1', '2025-01-01', 'good'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(3);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(0);
      });

      it('should count only warning statuses', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'warning'),
          createSnapshot('2', '2025-01-02', 'warning'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(0);
        expect(result.countWarning).toBe(3);
        expect(result.countCritical).toBe(0);
      });

      it('should count only critical statuses', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'critical'),
          createSnapshot('2', '2025-01-02', 'critical'),
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(0);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(3);
      });

      it('should ignore unknown statuses in counts', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'good'),
          createSnapshot('2', '2025-01-02', 'unknown_status'),
          createSnapshot('1', '2025-01-01', 'warning'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(1);
        expect(result.countWarning).toBe(1);
        expect(result.countCritical).toBe(0);
        expect(result.totalDays).toBe(3);
      });
    });

    describe('timeline ordering', () => {
      it('should return timeline in oldest → newest order (left to right)', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'good'),
          createSnapshot('2', '2025-01-02', 'warning'),
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        // Timeline should be oldest first (reversed from desc order)
        expect(result.timeline).toEqual(['critical', 'warning', 'good']);
      });

      it('should handle maxDays limit correctly', () => {
        const history = Array.from({ length: 50 }, (_, i) =>
          createSnapshot(
            String(i + 1),
            `2025-01-${String(i + 1).padStart(2, '0')}`,
            i % 3 === 0 ? 'good' : i % 3 === 1 ? 'warning' : 'critical'
          )
        );
        const result = computeHealthTrend(history, 30);
        expect(result.timeline.length).toBe(30);
        expect(result.totalDays).toBe(30);
      });

      it('should use default maxDays of 30', () => {
        const history = Array.from({ length: 50 }, (_, i) =>
          createSnapshot(
            String(i + 1),
            `2025-01-${String(i + 1).padStart(2, '0')}`,
            'good'
          )
        );
        const result = computeHealthTrend(history);
        expect(result.timeline.length).toBe(30);
      });
    });

    describe('edge cases', () => {
      it('should handle very long history correctly', () => {
        const history = Array.from({ length: 100 }, (_, i) =>
          createSnapshot(
            String(i + 1),
            `2025-01-${String(i + 1).padStart(2, '0')}`,
            'good'
          )
        );
        const result = computeHealthTrend(history, 30);
        expect(result.timeline.length).toBe(30);
        expect(result.countGood).toBe(30);
      });

      it('should return unknown direction for all no_data statuses (2 snapshots)', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'unknown_status'),
          createSnapshot('1', '2025-01-01', 'another_unknown'),
        ];
        const result = computeHealthTrend(history);
        expect(result.countGood).toBe(0);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(0);
        expect(result.totalDays).toBe(2);
        // Direction should be unknown since we can't compare no_data statuses
        expect(result.direction).toBe('unknown');
        expect(result.lastStatus).toBe('no_data');
        expect(result.prevStatus).toBe('no_data');
      });

      it('should return unknown direction for all no_data statuses (3 snapshots)', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'unknown_status'),
          createSnapshot('2', '2025-01-02', 'another_unknown'),
          createSnapshot('1', '2025-01-01', 'yet_another_unknown'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('unknown');
        expect(result.countGood).toBe(0);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(0);
        expect(result.totalDays).toBe(3);
      });

      it('should return unknown direction when newest is no_data and older is valid', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'unknown_status'), // newest = no_data
          createSnapshot('1', '2025-01-01', 'good'), // older = valid
        ];
        const result = computeHealthTrend(history);
        // Only 1 valid status, not enough for trend
        expect(result.direction).toBe('unknown');
        expect(result.lastStatus).toBe('no_data');
        expect(result.prevStatus).toBe('good');
      });

      it('should return unknown direction when newest is valid and older is no_data', () => {
        const history = [
          createSnapshot('2', '2025-01-02', 'good'), // newest = valid
          createSnapshot('1', '2025-01-01', 'unknown_status'), // older = no_data
        ];
        const result = computeHealthTrend(history);
        // Only 1 valid status, not enough for trend
        expect(result.direction).toBe('unknown');
        expect(result.lastStatus).toBe('good');
        expect(result.prevStatus).toBe('no_data');
      });

      it('should return unknown direction when only one valid status exists among multiple no_data', () => {
        const history = [
          createSnapshot('4', '2025-01-04', 'unknown_status'),
          createSnapshot('3', '2025-01-03', 'unknown_status'),
          createSnapshot('2', '2025-01-02', 'good'), // only valid status
          createSnapshot('1', '2025-01-01', 'unknown_status'),
        ];
        const result = computeHealthTrend(history);
        expect(result.direction).toBe('unknown');
        expect(result.countGood).toBe(1);
        expect(result.countWarning).toBe(0);
        expect(result.countCritical).toBe(0);
      });

      it('should compute direction correctly when valid statuses exist despite no_data entries', () => {
        const history = [
          createSnapshot('4', '2025-01-04', 'good'), // newest valid
          createSnapshot('3', '2025-01-03', 'warning'), // previous valid
          createSnapshot('2', '2025-01-02', 'unknown_status'), // older no_data
          createSnapshot('1', '2025-01-01', 'critical'),
        ];
        const result = computeHealthTrend(history);
        // Should compare good (newest valid) vs warning (previous valid) = improving
        expect(result.direction).toBe('improving');
        expect(result.lastStatus).toBe('good');
        expect(result.prevStatus).toBe('warning');
        expect(result.countGood).toBe(1);
        expect(result.countWarning).toBe(1);
        expect(result.countCritical).toBe(1);
      });

      it('should compute direction correctly when no_data is at the beginning', () => {
        const history = [
          createSnapshot('3', '2025-01-03', 'warning'), // newest valid
          createSnapshot('2', '2025-01-02', 'critical'), // previous valid
          createSnapshot('1', '2025-01-01', 'unknown_status'), // oldest = no_data
        ];
        const result = computeHealthTrend(history);
        // Should compare warning vs critical = improving
        expect(result.direction).toBe('improving');
        expect(result.countWarning).toBe(1);
        expect(result.countCritical).toBe(1);
      });
    });
  });
});
