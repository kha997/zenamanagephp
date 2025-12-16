import React, { useState, useMemo, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { useAuthStore } from '../../auth/store';
import { useProjectHealthPortfolio } from '../hooks';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { Button } from '../../../components/ui/primitives/Button';
import { getOverallStatusLabel, getScheduleStatusLabel, getCostStatusLabel, getOverallStatusTone } from '../../projects/healthStatus';
import { ProjectHealthPortfolioTrendCard } from '../components/ProjectHealthPortfolioTrendCard';

/**
 * ProjectHealthPortfolioPage - Full-page view for project health portfolio
 * 
 * Round 75: Project Health Portfolio
 * 
 * Features:
 * - Table of all projects with health status
 * - Filter by overall_status (all / good / warning / critical)
 * - Navigate to project overview on click
 * - RBAC: tenant.view_reports permission required
 */
export const ProjectHealthPortfolioPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasTenantPermission } = useAuthStore();
  
  const canView = hasTenantPermission('tenant.view_reports');
  
  type OverallFilter = 'all' | 'good' | 'warning' | 'critical';
  
  // Round 81: Read initial filter from query param
  const getOverallFromParams = (): OverallFilter => {
    const value = searchParams.get('overall');
    if (value === 'good' || value === 'warning' || value === 'critical') {
      return value;
    }
    return 'all';
  };
  
  const [overallFilter, setOverallFilter] = useState<OverallFilter>(() => getOverallFromParams());
  
  // Round 81: Sync filter when query param changes (e.g., browser back/forward)
  useEffect(() => {
    const newFilter = getOverallFromParams();
    setOverallFilter(newFilter);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);
  
  // Round 81: Handle filter change - update state and URL
  const handleFilterChange = (next: OverallFilter) => {
    setOverallFilter(next);
    const nextParams = new URLSearchParams(searchParams);
    if (next === 'all') {
      nextParams.delete('overall');
    } else {
      nextParams.set('overall', next);
    }
    setSearchParams(nextParams);
  };
  
  // Fetch data
  const { data, isLoading, isError, error } = useProjectHealthPortfolio();
  
  // Early return if user doesn't have view permission
  if (!canView) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view project health reports. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const items = data ?? [];
  
  // Apply filter
  const filteredItems = useMemo(
    () =>
      overallFilter === 'all'
        ? items
        : items.filter((item) => item.health.overall_status === overallFilter),
    [items, overallFilter]
  );
  
  // Status color mapping (keeping existing color logic for table cells)
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'good':
        return { bg: '#d1fae5', text: '#065f46' }; // green
      case 'warning':
        return { bg: '#fef3c7', text: '#92400e' }; // yellow
      case 'critical':
        return { bg: '#fee2e2', text: '#991b1b' }; // red
      default:
        return { bg: 'var(--muted-surface)', text: 'var(--muted)' };
    }
  };
  
  const handleRowClick = (projectId: string) => {
    navigate(`/app/projects/${projectId}/overview`);
  };
  
  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold" style={{ color: 'var(--text)' }}>
              Tổng quan sức khỏe dự án
            </h1>
            <p className="text-sm mt-1" style={{ color: 'var(--muted)' }}>
              Xem nhanh tình trạng tiến độ & chi phí của tất cả dự án theo tenant.
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              window.open('/api/v1/app/reports/projects/health/export', '_blank', 'noopener');
            }}
            data-testid="export-csv-button"
          >
            Xuất CSV
          </Button>
        </div>

        {/* Project Health Portfolio Trend Card - Round 92 */}
        <ProjectHealthPortfolioTrendCard canViewReports={canView} days={30} />
        
        {/* Filter Buttons - Round 81: Sync with URL query params */}
        <div className="inline-flex gap-2 mb-4">
          <Button
            data-testid="filter-all"
            variant={overallFilter === 'all' ? 'primary' : 'secondary'}
            onClick={() => handleFilterChange('all')}
            size="sm"
          >
            Tất cả
          </Button>
          <Button
            data-testid="filter-good"
            variant={overallFilter === 'good' ? 'primary' : 'secondary'}
            onClick={() => handleFilterChange('good')}
            size="sm"
          >
            Tốt
          </Button>
          <Button
            data-testid="filter-warning"
            variant={overallFilter === 'warning' ? 'primary' : 'secondary'}
            onClick={() => handleFilterChange('warning')}
            size="sm"
          >
            Cảnh báo
          </Button>
          <Button
            data-testid="filter-critical"
            variant={overallFilter === 'critical' ? 'primary' : 'secondary'}
            onClick={() => handleFilterChange('critical')}
            size="sm"
          >
            Nguy cấp
          </Button>
        </div>
        
        {/* Loading State */}
        {isLoading && (
          <div className="flex items-center justify-center py-12">
            <LoadingSpinner />
            <p className="ml-3" style={{ color: 'var(--muted)' }}>
              Đang tải báo cáo sức khỏe dự án...
            </p>
          </div>
        )}
        
        {/* Error State */}
        {isError && (
          <Card>
            <CardContent className="py-8">
              <div className="text-center">
                <p className="text-lg font-medium mb-2" style={{ color: 'var(--text)' }}>
                  Không tải được báo cáo sức khỏe dự án.
                </p>
                <p data-testid="error-message" style={{ color: 'var(--muted)' }}>
                  {error?.message ?? 'Đã xảy ra lỗi'}
                </p>
              </div>
            </CardContent>
          </Card>
        )}
        
        {/* Table */}
        {!isLoading && !isError && (
          <Card>
            <CardHeader>
              <CardTitle>
                Danh sách dự án ({filteredItems.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              {filteredItems.length === 0 ? (
                <p className="text-center py-8" style={{ color: 'var(--muted)' }}>
                  Không có dự án nào phù hợp bộ lọc.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full" style={{ borderCollapse: 'collapse' }}>
                    <thead>
                      <tr style={{ borderBottom: '1px solid var(--border)' }}>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Dự án
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Khách hàng
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Trạng thái dự án
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Tiến độ
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Chi phí
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Tổng thể
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Task quá hạn
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          % Hoàn thành
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          % Blocked
                        </th>
                        <th className="text-left py-3 px-4 font-semibold" style={{ color: 'var(--text)' }}>
                          Hành động
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredItems.map((item) => {
                        const completionPercent =
                          item.health.tasks_completion_rate != null
                            ? Math.round(item.health.tasks_completion_rate * 100)
                            : null;
                        
                        const blockedPercent =
                          item.health.blocked_tasks_ratio != null
                            ? Math.round(item.health.blocked_tasks_ratio * 100)
                            : null;
                        
                        const statusColor = getStatusColor(item.health.overall_status);
                        
                        return (
                          <tr
                            key={item.project.id}
                            style={{
                              borderBottom: '1px solid var(--border)',
                              cursor: 'pointer',
                            }}
                            className="hover:bg-[var(--muted-surface)]"
                          >
                            <td className="py-3 px-4">
                              <div>
                                <div className="font-medium" style={{ color: 'var(--text)' }}>
                                  {item.project.name}
                                </div>
                                {item.project.code && (
                                  <div className="text-sm" style={{ color: 'var(--muted)' }}>
                                    {item.project.code}
                                  </div>
                                )}
                              </div>
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {item.project.client_name || '-'}
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {item.project.status || '-'}
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {getScheduleStatusLabel(item.health.schedule_status)}
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {getCostStatusLabel(item.health.cost_status)}
                            </td>
                            <td className="py-3 px-4">
                              <span
                                className="inline-block px-2 py-1 rounded text-xs font-medium"
                                style={{
                                  backgroundColor: statusColor.bg,
                                  color: statusColor.text,
                                }}
                              >
                                {getOverallStatusLabel(item.health.overall_status)}
                              </span>
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {item.health.overdue_tasks}
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {completionPercent != null ? `${completionPercent}%` : '-'}
                            </td>
                            <td className="py-3 px-4" style={{ color: 'var(--text)' }}>
                              {blockedPercent != null ? `${blockedPercent}%` : '-'}
                            </td>
                            <td className="py-3 px-4">
                              <Button
                                variant="tertiary"
                                size="sm"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleRowClick(item.project.id);
                                }}
                              >
                                Xem Overview
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </Container>
  );
};
