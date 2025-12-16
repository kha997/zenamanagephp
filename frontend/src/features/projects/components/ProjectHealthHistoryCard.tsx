import React, { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Badge } from '../../../shared/ui/badge';
import { useProjectHealthHistory } from '../hooks';
import { getOverallStatusLabel, getScheduleStatusLabel, getCostStatusLabel, getOverallStatusTone } from '../healthStatus';
import { computeHealthTrend } from '../healthTrend';
import type { ProjectHealthSnapshot } from '../api';

interface ProjectHealthHistoryCardProps {
  projectId: string | number;
  canViewReports: boolean;
}

/**
 * Project Health History Card
 * 
 * Round 87: Project Health History UI
 * 
 * Displays historical snapshots of project health metrics.
 * Only visible to users with tenant.view_reports permission.
 */
export const ProjectHealthHistoryCard: React.FC<ProjectHealthHistoryCardProps> = ({
  projectId,
  canViewReports,
}) => {
  const { data, isLoading, isError, error, refetch } = useProjectHealthHistory(projectId, {
    enabled: canViewReports,
    limit: 30,
  });

  // Don't render anything if user doesn't have permission
  if (!canViewReports) {
    return null;
  }

  // Format date from YYYY-MM-DD to a readable format
  const formatDate = (dateString: string): string => {
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      });
    } catch {
      return dateString;
    }
  };

  // Format percentage
  const formatPercentage = (value: number | null): string => {
    if (value === null) return '—';
    return `${Math.round(value * 100)}%`;
  };

  // Compute health trend from history data
  const trendSummary = useMemo(() => {
    if (!data || data.length === 0) {
      return null;
    }
    return computeHealthTrend(data, 30);
  }, [data]);

  // Get trend chip label and tone
  const getTrendChipConfig = () => {
    if (!trendSummary) {
      return { label: 'Chưa có đủ dữ liệu', tone: 'neutral' as const };
    }

    switch (trendSummary.direction) {
      case 'improving':
        return { label: 'Tốt lên', tone: 'success' as const };
      case 'worsening':
        return { label: 'Xấu đi', tone: 'danger' as const };
      case 'stable':
        return { label: 'Ổn định', tone: 'warning' as const };
      case 'unknown':
        return { label: 'Chưa có đủ dữ liệu', tone: 'neutral' as const };
      default:
        return { label: 'Chưa có đủ dữ liệu', tone: 'neutral' as const };
    }
  };

  const trendChipConfig = getTrendChipConfig();

  return (
    <Card className="mt-6" data-testid="project-health-history-card">
      <CardHeader>
        <CardTitle>Lịch sử sức khỏe dự án</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Loading State */}
        {isLoading && (
          <div data-testid="project-health-history-loading" className="py-4">
            <p className="text-sm text-[var(--muted)]">Đang tải lịch sử sức khỏe dự án...</p>
          </div>
        )}

        {/* Error State */}
        {isError && (
          <div data-testid="project-health-history-error" className="py-4">
            <p className="text-sm text-[var(--color-semantic-danger-600)] mb-3">
              Không tải được lịch sử sức khỏe. Vui lòng thử lại.
            </p>
            <Button
              variant="outline"
              size="sm"
              onClick={() => refetch()}
            >
              Thử lại
            </Button>
          </div>
        )}

        {/* Empty State */}
        {!isLoading && !isError && (!data || data.length === 0) && (
          <div data-testid="project-health-history-empty" className="py-4">
            <p className="text-sm text-[var(--muted)]">
              Chưa có snapshot sức khỏe nào cho dự án này.
            </p>
            <p className="text-sm text-[var(--muted)] mt-2">
              Chưa có dữ liệu để tính xu hướng.
            </p>
          </div>
        )}

        {/* Data State */}
        {!isLoading && !isError && data && data.length > 0 && (
          <>
            {/* Health Trend Section */}
            {trendSummary && (
              <div className="mb-6 pb-6 border-b border-[var(--border)]">
                {/* Trend Label and Chip */}
                <div className="flex items-center gap-3 mb-4">
                  <span
                    data-testid="project-health-history-trend-label"
                    className="text-sm font-medium text-[var(--muted)]"
                  >
                    Xu hướng 30 ngày gần đây:
                  </span>
                  <Badge
                    data-testid="project-health-history-trend-chip"
                    tone={trendChipConfig.tone}
                  >
                    {trendChipConfig.label}
                  </Badge>
                </div>

                {/* Timeline Visualization */}
                {trendSummary.timeline.length > 0 && (
                  <div
                    data-testid="project-health-history-timeline"
                    className="flex items-center gap-1 mb-4 flex-wrap"
                    style={{ maxWidth: '100%' }}
                  >
                    {trendSummary.timeline.map((status, idx) => {
                      const tone = getOverallStatusTone(status);
                      let dotColor = 'var(--color-semantic-neutral-400)';
                      
                      switch (tone) {
                        case 'success':
                          dotColor = 'var(--color-semantic-success-500)';
                          break;
                        case 'warning':
                          dotColor = 'var(--color-semantic-warning-500)';
                          break;
                        case 'danger':
                          dotColor = 'var(--color-semantic-danger-500)';
                          break;
                        default:
                          dotColor = 'var(--color-semantic-neutral-400)';
                      }

                      return (
                        <span
                          key={idx}
                          data-testid="project-health-history-timeline-dot"
                          className="inline-block rounded-full"
                          style={{
                            width: '8px',
                            height: '8px',
                            backgroundColor: dotColor,
                            flexShrink: 0,
                          }}
                          title={`${status === 'good' ? 'Tốt' : status === 'warning' ? 'Cảnh báo' : status === 'critical' ? 'Nguy cấp' : 'Không có dữ liệu'}`}
                        />
                      );
                    })}
                  </div>
                )}

                {/* Statistics Summary */}
                <div
                  data-testid="project-health-history-summary"
                  className="flex flex-wrap gap-4 text-sm"
                >
                  <span className="text-[var(--text)]">
                    <span className="font-medium text-[var(--color-semantic-success-600)]">Tốt:</span>{' '}
                    {trendSummary.countGood} ngày
                  </span>
                  <span className="text-[var(--text)]">
                    <span className="font-medium text-[var(--color-semantic-warning-600)]">Cảnh báo:</span>{' '}
                    {trendSummary.countWarning} ngày
                  </span>
                  <span className="text-[var(--text)]">
                    <span className="font-medium text-[var(--color-semantic-danger-600)]">Nguy cấp:</span>{' '}
                    {trendSummary.countCritical} ngày
                  </span>
                </div>
              </div>
            )}

            {/* History Table */}
            <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)]">
                  <th className="text-left py-2 px-3 font-medium text-[var(--muted)]">Ngày</th>
                  <th className="text-left py-2 px-3 font-medium text-[var(--muted)]">Tổng thể</th>
                  <th className="text-left py-2 px-3 font-medium text-[var(--muted)]">Tiến độ</th>
                  <th className="text-left py-2 px-3 font-medium text-[var(--muted)]">Chi phí</th>
                  <th className="text-right py-2 px-3 font-medium text-[var(--muted)]">% hoàn thành</th>
                  <th className="text-right py-2 px-3 font-medium text-[var(--muted)]">Task quá hạn</th>
                </tr>
              </thead>
              <tbody>
                {data.map((snapshot: ProjectHealthSnapshot) => (
                  <tr
                    key={snapshot.id}
                    data-testid="project-health-history-row"
                    className="border-b border-[var(--border)] hover:bg-[var(--muted-surface)]"
                  >
                    <td className="py-2 px-3 text-[var(--text)]">
                      {formatDate(snapshot.snapshot_date)}
                    </td>
                    <td className="py-2 px-3">
                      <Badge tone={getOverallStatusTone(snapshot.overall_status)}>
                        {getOverallStatusLabel(snapshot.overall_status)}
                      </Badge>
                    </td>
                    <td className="py-2 px-3 text-[var(--text)]">
                      {getScheduleStatusLabel(snapshot.schedule_status)}
                    </td>
                    <td className="py-2 px-3 text-[var(--text)]">
                      {getCostStatusLabel(snapshot.cost_status)}
                    </td>
                    <td className="py-2 px-3 text-right text-[var(--text)]">
                      {formatPercentage(snapshot.tasks_completion_rate)}
                    </td>
                    <td className="py-2 px-3 text-right text-[var(--text)]">
                      {snapshot.overdue_tasks}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
};

