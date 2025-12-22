import React, { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useProjectHealthPortfolioHistory } from '../hooks';
import type { ProjectHealthPortfolioHistoryItem } from '../api';

interface ProjectHealthPortfolioTrendCardProps {
  canViewReports: boolean;
  days?: number; // default 30
}

/**
 * Project Health Portfolio Trend Card
 * 
 * Round 92: Project Health Portfolio Trend Card
 * 
 * Displays portfolio-level health trend over time with summary and timeline visualization.
 * Only visible to users with tenant.view_reports permission.
 */
export const ProjectHealthPortfolioTrendCard: React.FC<ProjectHealthPortfolioTrendCardProps> = ({
  canViewReports,
  days = 30,
}) => {
  // Don't render anything if user doesn't have permission
  // This check must come BEFORE the hook call to prevent query initialization
  if (!canViewReports) {
    return null;
  }

  const { data, isLoading, isError, error, refetch, isFetching } = useProjectHealthPortfolioHistory({
    days,
    enabled: true,
  });

  // Compute summary from history data
  const summary = useMemo(() => {
    if (!data || data.length === 0) {
      return null;
    }

    // Sort by date ascending
    const sorted = [...data].sort((a, b) => 
      a.snapshot_date.localeCompare(b.snapshot_date)
    );

    const first = sorted[0];
    const last = sorted[sorted.length - 1];

    const deltaGood = last.good - first.good;
    const deltaCritical = last.critical - first.critical;

    return {
      first,
      last,
      deltaGood,
      deltaCritical,
      daysCount: sorted.length,
    };
  }, [data]);

  // Format delta with sign
  const formatDelta = (delta: number): string => {
    if (delta > 0) {
      return `+${delta}`;
    }
    if (delta < 0) {
      return String(delta);
    }
    return '0';
  };

  // Loading State
  if ((isLoading || isFetching) && !data) {
    return (
      <Card data-testid="project-health-portfolio-trend-card">
        <CardHeader>
          <CardTitle>Xu hướng sức khỏe portfolio</CardTitle>
        </CardHeader>
        <CardContent>
          <div data-testid="project-health-portfolio-trend-loading" className="py-4">
            <p className="text-sm text-[var(--muted)]">
              Đang tải xu hướng sức khỏe portfolio…
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  // Error State
  if (isError) {
    return (
      <Card data-testid="project-health-portfolio-trend-card">
        <CardHeader>
          <CardTitle>Xu hướng sức khỏe portfolio</CardTitle>
        </CardHeader>
        <CardContent>
          <div data-testid="project-health-portfolio-trend-error" className="py-4">
            <p className="text-sm text-[var(--color-semantic-danger-600)] mb-3">
              Không tải được xu hướng sức khỏe portfolio. Vui lòng thử lại.
            </p>
            <Button
              variant="outline"
              size="sm"
              onClick={() => refetch()}
            >
              Thử lại
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  // Empty State
  if (!data || data.length === 0) {
    return (
      <Card data-testid="project-health-portfolio-trend-card">
        <CardHeader>
          <CardTitle>Xu hướng sức khỏe portfolio</CardTitle>
        </CardHeader>
        <CardContent>
          <div data-testid="project-health-portfolio-trend-empty" className="py-4">
            <p className="text-sm text-[var(--muted)]">
              Chưa có dữ liệu snapshot sức khỏe để vẽ xu hướng.
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  // Data State
  if (!summary) {
    return null;
  }

  // Sort data by date for timeline
  const sortedData = [...data].sort((a, b) => 
    a.snapshot_date.localeCompare(b.snapshot_date)
  );

  return (
    <Card data-testid="project-health-portfolio-trend-card">
      <CardHeader>
        <CardTitle>Xu hướng sức khỏe portfolio</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Summary Section */}
        <div data-testid="project-health-portfolio-trend-summary" className="mb-6">
          <p className="text-sm text-[var(--muted)] mb-4">
            Trong {summary.daysCount} ngày gần đây:
          </p>
          <div className="space-y-2 text-sm">
            <div className="text-[var(--text)]">
              <span className="font-medium text-[var(--color-semantic-success-600)]">Tốt:</span>{' '}
              {summary.first.good} → {summary.last.good} ({formatDelta(summary.deltaGood)})
            </div>
            <div className="text-[var(--text)]">
              <span className="font-medium text-[var(--color-semantic-danger-600)]">Nguy cấp:</span>{' '}
              {summary.first.critical} → {summary.last.critical} ({formatDelta(summary.deltaCritical)})
            </div>
          </div>
        </div>

        {/* Timeline Section */}
        <div data-testid="project-health-portfolio-trend-timeline" className="flex items-end gap-0.5">
          {sortedData.map((day) => {
            const goodHeight = day.total > 0 ? (day.good / day.total) * 100 : 0;
            const warningHeight = day.total > 0 ? (day.warning / day.total) * 100 : 0;
            const criticalHeight = day.total > 0 ? (day.critical / day.total) * 100 : 0;

            return (
              <div
                key={day.snapshot_date}
                data-testid="project-health-portfolio-trend-day"
                className="flex flex-col justify-end w-1 mx-0.5"
                style={{ minHeight: '60px' }}
                aria-label={`${day.snapshot_date}: Tốt ${day.good}, Cảnh báo ${day.warning}, Nguy cấp ${day.critical}, Tổng ${day.total}`}
                title={`${day.snapshot_date}: Tốt ${day.good}, Cảnh báo ${day.warning}, Nguy cấp ${day.critical}`}
              >
                {/* Critical segment (bottom) */}
                {criticalHeight > 0 && (
                  <div
                    className="w-full"
                    style={{
                      height: `${criticalHeight}%`,
                      backgroundColor: 'var(--color-semantic-danger-500)',
                      minHeight: criticalHeight > 0 ? '2px' : '0',
                    }}
                  />
                )}
                {/* Warning segment (middle) */}
                {warningHeight > 0 && (
                  <div
                    className="w-full"
                    style={{
                      height: `${warningHeight}%`,
                      backgroundColor: 'var(--color-semantic-warning-500)',
                      minHeight: warningHeight > 0 ? '2px' : '0',
                    }}
                  />
                )}
                {/* Good segment (top) */}
                {goodHeight > 0 && (
                  <div
                    className="w-full"
                    style={{
                      height: `${goodHeight}%`,
                      backgroundColor: 'var(--color-semantic-success-500)',
                      minHeight: goodHeight > 0 ? '2px' : '0',
                    }}
                  />
                )}
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
};

