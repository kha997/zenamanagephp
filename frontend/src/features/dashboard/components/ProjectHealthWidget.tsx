import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Badge } from '../../../shared/ui/badge';
import { useAuthStore } from '../../auth/store';
import { useProjectHealthPortfolio } from '../../reports/hooks';
import { getOverallStatusLabel, getOverallStatusTone } from '../../projects/healthStatus';
import type { ProjectHealthPortfolioItem } from '../../reports/api';

/**
 * ProjectHealthWidget - Dashboard widget showing project health summary
 * 
 * Round 76: Dashboard - Project Health Widget
 * 
 * Features:
 * - Summary counters by overall_status (good/warning/critical)
 * - TOP 5 problematic projects (critical/warning) sorted by severity
 * - Navigate to project overview on click
 * - RBAC: tenant.view_reports permission required
 */
export const ProjectHealthWidget: React.FC = () => {
  const navigate = useNavigate();
  const { hasTenantPermission } = useAuthStore();
  const canViewReports = hasTenantPermission('tenant.view_reports');
  
  const { data, isLoading, isError, error, refetch } = useProjectHealthPortfolio({
    enabled: canViewReports,
  });
  
  // Don't render if user doesn't have permission
  if (!canViewReports) {
    return null;
  }
  
  const items = data ?? [];
  
  // Calculate summary counts
  const counts = useMemo(() => {
    return {
      good: items.filter((i) => i.health.overall_status === 'good').length,
      warning: items.filter((i) => i.health.overall_status === 'warning').length,
      critical: items.filter((i) => i.health.overall_status === 'critical').length,
    };
  }, [items]);
  
  // Get problematic projects (critical/warning) sorted by severity
  const problematicItems = useMemo(() => {
    return items
      .filter(
        (i) =>
          i.health.overall_status === 'critical' ||
          i.health.overall_status === 'warning'
      )
      .sort((a, b) => {
        // Critical first, then warning
        const rank = (status: string) => {
          if (status === 'critical') return 1;
          if (status === 'warning') return 2;
          return 3;
        };
        
        const rankA = rank(a.health.overall_status);
        const rankB = rank(b.health.overall_status);
        
        if (rankA !== rankB) {
          return rankA - rankB;
        }
        
        // Within same status, sort by overdue_tasks (descending)
        const overdueA = a.health.overdue_tasks ?? 0;
        const overdueB = b.health.overdue_tasks ?? 0;
        return overdueB - overdueA;
      })
      .slice(0, 5); // Limit to top 5
  }, [items]);
  
  
  const handleViewOverview = (projectId: string, e: React.MouseEvent) => {
    e.stopPropagation();
    navigate(`/app/projects/${projectId}/overview`);
  };
  
  // Round 81: Navigate to health portfolio with filter
  const handleNavigateToHealthPortfolio = (overall?: 'good' | 'warning' | 'critical') => {
    if (!canViewReports) return;
    
    if (overall) {
      navigate(`/app/reports/projects/health?overall=${overall}`);
    } else {
      navigate('/app/reports/projects/health');
    }
  };
  
  return (
    <Card data-testid="project-health-widget">
      <CardHeader>
        <CardTitle>Sức khỏe dự án</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Loading State */}
        {isLoading && (
          <div className="space-y-3">
            <div className="text-sm text-[var(--muted)]">
              Đang tải dữ liệu sức khỏe dự án...
            </div>
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <div key={i} className="animate-pulse">
                  <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                  <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                </div>
              ))}
            </div>
          </div>
        )}
        
        {/* Error State */}
        {isError && (
          <div className="text-center py-4">
            <p className="text-sm text-[var(--muted)] mb-2">
              Không tải được dữ liệu sức khỏe dự án.
            </p>
            {error?.message && (
              <p className="text-xs text-[var(--muted)] mb-3" data-testid="error-message">
                {error.message}
              </p>
            )}
            <Button variant="secondary" size="sm" onClick={() => refetch()}>
              Thử lại
            </Button>
          </div>
        )}
        
        {/* Data State */}
        {!isLoading && !isError && (
          <>
            {/* Summary Counters - Round 81: Clickable to navigate */}
            <div className="flex flex-wrap gap-2 mb-4">
              <button
                type="button"
                onClick={() => handleNavigateToHealthPortfolio('good')}
                data-testid="project-health-counter-good"
                className="cursor-pointer"
              >
                <Badge tone="success" data-testid="count-good">
                  Tốt: {counts.good}
                </Badge>
              </button>
              <button
                type="button"
                onClick={() => handleNavigateToHealthPortfolio('warning')}
                data-testid="project-health-counter-warning"
                className="cursor-pointer"
              >
                <Badge tone="warning" data-testid="count-warning">
                  Cảnh báo: {counts.warning}
                </Badge>
              </button>
              <button
                type="button"
                onClick={() => handleNavigateToHealthPortfolio('critical')}
                data-testid="project-health-counter-critical"
                className="cursor-pointer"
              >
                <Badge tone="danger" data-testid="count-critical">
                  Nguy cấp: {counts.critical}
                </Badge>
              </button>
            </div>
            
            {/* Problematic Projects List */}
            {problematicItems.length === 0 ? (
              <div className="text-center py-4">
                {items.length === 0 ? (
                  <p className="text-sm text-[var(--muted)]">
                    Chưa có dữ liệu sức khỏe dự án
                  </p>
                ) : (
                  <p className="text-sm text-[var(--muted)]">
                    Không có dự án nào ở trạng thái cảnh báo / nguy cấp 🎉
                  </p>
                )}
              </div>
            ) : (
              <div className="space-y-3">
                <p className="text-xs font-medium text-[var(--muted)] mb-2">
                  Dự án cần chú ý:
                </p>
                {problematicItems.map((item) => {
                  const completionPercent =
                    item.health.tasks_completion_rate != null
                      ? Math.round(item.health.tasks_completion_rate * 100)
                      : null;
                  
                  return (
                    <div
                      key={item.project.id}
                      className="p-3 rounded-lg border border-[var(--border)] hover:bg-[var(--muted-surface)] transition-colors"
                      data-testid={`project-item-${item.project.id}`}
                    >
                      <div className="flex items-start justify-between mb-2">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h3 className="text-sm font-medium text-[var(--text)]">
                              {item.project.name}
                            </h3>
                            {item.project.code && (
                              <span className="text-xs text-[var(--muted)]">
                                ({item.project.code})
                              </span>
                            )}
                          </div>
                          {item.project.client_name && (
                            <p className="text-xs text-[var(--muted)] mb-2">
                              {item.project.client_name}
                            </p>
                          )}
                        </div>
                        <Badge
                          tone={getOverallStatusTone(item.health.overall_status)}
                          data-testid={`status-${item.project.id}`}
                        >
                          {getOverallStatusLabel(item.health.overall_status)}
                        </Badge>
                      </div>
                      
                      <div className="flex items-center justify-between text-xs text-[var(--muted)] mb-2">
                        {item.health.overdue_tasks != null && item.health.overdue_tasks > 0 && (
                          <span>
                            Quá hạn: {item.health.overdue_tasks} task
                          </span>
                        )}
                        {completionPercent != null && (
                          <span>Hoàn thành: {completionPercent}%</span>
                        )}
                      </div>
                      
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={(e) => handleViewOverview(item.project.id, e)}
                        data-testid={`view-overview-${item.project.id}`}
                      >
                        Xem overview
                      </Button>
                    </div>
                  );
                })}
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
};

