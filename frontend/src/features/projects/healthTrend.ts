/**
 * Project Health Trend Helper
 * 
 * Round 89: Project Health Trend & Insight (FE-only)
 * 
 * Computes health trend direction, distribution, and timeline from history snapshots.
 * Pure functions - no UI dependencies.
 */

import type { ProjectHealthSnapshot } from './api';

export type OverallStatus = 'good' | 'warning' | 'critical' | 'no_data';

export type TrendDirection = 'improving' | 'worsening' | 'stable' | 'unknown';

export interface HealthTrendSummary {
  direction: TrendDirection;
  lastStatus: OverallStatus | null;
  prevStatus: OverallStatus | null;
  totalDays: number;
  countGood: number;
  countWarning: number;
  countCritical: number;
  timeline: OverallStatus[]; // Oldest → Newest (left to right)
}

/**
 * Map backend overall_status string to OverallStatus type
 */
function mapOverallStatus(status: string): OverallStatus {
  switch (status) {
    case 'good':
      return 'good';
    case 'warning':
      return 'warning';
    case 'critical':
      return 'critical';
    default:
      return 'no_data';
  }
}

/**
 * Get numeric rank for status comparison
 * Higher rank = better status
 */
function getStatusRank(status: OverallStatus): number {
  switch (status) {
    case 'good':
      return 3;
    case 'warning':
      return 2;
    case 'critical':
      return 1;
    case 'no_data':
      return 0;
    default:
      return 0;
  }
}

/**
 * Compute trend direction from two statuses
 */
function computeDirection(last: OverallStatus, prev: OverallStatus): TrendDirection {
  const lastRank = getStatusRank(last);
  const prevRank = getStatusRank(prev);

  if (lastRank > prevRank) {
    return 'improving';
  } else if (lastRank < prevRank) {
    return 'worsening';
  } else {
    return 'stable';
  }
}

/**
 * Compute health trend summary from history snapshots
 * 
 * @param history Array of snapshots (assumed sorted desc by snapshot_date from backend)
 * @param maxDays Maximum number of snapshots to consider (default: 30)
 * @returns HealthTrendSummary with direction, distribution, and timeline
 */
export function computeHealthTrend(
  history: ProjectHealthSnapshot[],
  maxDays: number = 30
): HealthTrendSummary {
  // Handle empty or insufficient data
  if (!history || history.length === 0) {
    return {
      direction: 'unknown',
      lastStatus: null,
      prevStatus: null,
      totalDays: 0,
      countGood: 0,
      countWarning: 0,
      countCritical: 0,
      timeline: [],
    };
  }

  // Take up to maxDays most recent snapshots
  // History is already sorted desc by snapshot_date from backend
  const recentSnapshots = history.slice(0, maxDays);

  // Map to OverallStatus and reverse to get oldest → newest (for timeline left → right)
  const statuses = recentSnapshots
    .map((snapshot) => mapOverallStatus(snapshot.overall_status))
    .reverse(); // Oldest first for timeline visualization

  // Get last and previous statuses from the full timeline (including no_data)
  // This preserves the original behavior for lastStatus/prevStatus exposure
  const lastStatus = statuses.length > 0 ? statuses[statuses.length - 1] : null;
  const prevStatus = statuses.length > 1 ? statuses[statuses.length - 2] : null;

  // Compute direction using only valid statuses
  // Direction must be 'unknown' if there are fewer than 2 valid statuses
  let direction: TrendDirection = 'unknown';
  if (
    lastStatus &&
    prevStatus &&
    lastStatus !== 'no_data' &&
    prevStatus !== 'no_data'
  ) {
    direction = computeDirection(lastStatus, prevStatus);
  }

  // Count distribution
  let countGood = 0;
  let countWarning = 0;
  let countCritical = 0;

  for (const status of statuses) {
    switch (status) {
      case 'good':
        countGood++;
        break;
      case 'warning':
        countWarning++;
        break;
      case 'critical':
        countCritical++;
        break;
      default:
        // Skip no_data in counts
        break;
    }
  }

  return {
    direction,
    lastStatus,
    prevStatus,
    totalDays: statuses.length,
    countGood,
    countWarning,
    countCritical,
    timeline: statuses,
  };
}
