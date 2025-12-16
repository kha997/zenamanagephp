/**
 * Shared Project Health Status Helpers
 * 
 * Round 77: Refactor & cleanup FE around "Project Health"
 * 
 * Provides centralized mappings for health status labels and tones/colors
 * to avoid duplication across components.
 */

export type OverallStatus = 'good' | 'warning' | 'critical';

export type ScheduleStatus =
  | 'on_track'
  | 'at_risk'
  | 'delayed'
  | 'no_tasks';

export type CostStatus =
  | 'on_budget'
  | 'over_budget'
  | 'at_risk'
  | 'no_data';

export type StatusTone = 'success' | 'warning' | 'danger' | 'neutral';

/**
 * Get Vietnamese label for overall status
 */
export function getOverallStatusLabel(status: OverallStatus | string): string {
  switch (status) {
    case 'good':
      return 'Tốt';
    case 'warning':
      return 'Cảnh báo';
    case 'critical':
      return 'Nguy cấp';
    default:
      return status;
  }
}

/**
 * Get Vietnamese label for schedule status
 */
export function getScheduleStatusLabel(status: ScheduleStatus | string): string {
  switch (status) {
    case 'on_track':
      return 'Đúng tiến độ';
    case 'at_risk':
      return 'Có rủi ro chậm';
    case 'delayed':
      return 'Đang chậm tiến độ';
    case 'no_tasks':
      return 'Chưa có task nào';
    default:
      return status;
  }
}

/**
 * Get Vietnamese label for cost status
 */
export function getCostStatusLabel(status: CostStatus | string): string {
  switch (status) {
    case 'on_budget':
      return 'Trong ngân sách';
    case 'at_risk':
      return 'Chi phí có rủi ro vượt';
    case 'over_budget':
      return 'Vượt ngân sách';
    case 'no_data':
      return 'Chưa có dữ liệu chi phí';
    default:
      return status;
  }
}

/**
 * Get semantic tone for overall status (for use with Badge component)
 */
export function getOverallStatusTone(
  status: OverallStatus | string
): StatusTone {
  switch (status) {
    case 'good':
      return 'success';
    case 'warning':
      return 'warning';
    case 'critical':
      return 'danger';
    default:
      return 'neutral';
  }
}

