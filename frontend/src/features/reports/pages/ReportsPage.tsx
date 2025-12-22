import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { Button } from '../../../components/ui/primitives/Button';
import { createApiClient } from '../../../shared/api/client';
import { useAuthStore } from '../../auth/store';

const apiClient = createApiClient();

export const ReportsPage: React.FC = () => {
  const navigate = useNavigate();
  const { hasTenantPermission } = useAuthStore();
  
  // Permission checks (Round 15)
  const canViewReports = hasTenantPermission('tenant.view_reports') || hasTenantPermission('tenant.manage_reports');
  const canManageReports = hasTenantPermission('tenant.manage_reports');
  const isReadOnly = canViewReports && !canManageReports;
  
  // Early return if user doesn't have view permission
  if (!canViewReports) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view reports in this workspace. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const [kpis, setKpis] = React.useState<any[]>([]);
  const [alerts, setAlerts] = React.useState<any[]>([]);
  const [activities, setActivities] = React.useState<any[]>([]);
  const [loading, setLoading] = React.useState(true);

  React.useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        
        // Load KPIs
        const kpisResponse = await apiClient.get('/v1/app/reports/kpis');
        const kpisData = kpisResponse.data?.data || {};
        setKpis([
          {
            label: 'Total Reports',
            value: kpisData.total_reports || 0,
            change: kpisData.trends?.total_reports?.value
              ? `${kpisData.trends.total_reports.direction === 'up' ? '+' : '-'}${kpisData.trends.total_reports.value}%`
              : undefined,
            trend: kpisData.trends?.total_reports?.direction,
          },
          {
            label: 'Recent Reports',
            value: kpisData.recent_reports || 0,
            change: kpisData.trends?.recent_reports?.value
              ? `${kpisData.trends.recent_reports.direction === 'up' ? '+' : '-'}${kpisData.trends.recent_reports.value}%`
              : undefined,
            trend: kpisData.trends?.recent_reports?.direction,
          },
          {
            label: 'Downloads',
            value: kpisData.downloads || 0,
            change: kpisData.trends?.downloads?.value
              ? `${kpisData.trends.downloads.direction === 'up' ? '+' : '-'}${kpisData.trends.downloads.value}%`
              : undefined,
            trend: kpisData.trends?.downloads?.direction,
          },
        ]);

        // Load Alerts
        const alertsResponse = await apiClient.get('/v1/app/reports/alerts');
        setAlerts(alertsResponse.data?.data || []);

        // Load Activity
        const activityResponse = await apiClient.get('/v1/app/reports/activity');
        setActivities(activityResponse.data?.data || []);
      } catch (error) {
        console.error('Failed to load reports data:', error);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Title */}
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold" style={{ color: 'var(--text)' }}>
              Reports & Analytics
            </h1>
            {isReadOnly && (
              <span className="px-2 py-1 text-xs font-medium rounded bg-[var(--muted-surface)] text-[var(--muted)]">
                Read-only mode
              </span>
            )}
          </div>
          <p className="text-sm mt-1" style={{ color: 'var(--muted)' }}>
            Generate and view reports, track analytics, and export data
          </p>
        </div>

        {/* KPI Strip */}
        <KpiStrip kpis={kpis} loading={loading} />

        {/* Alert Bar */}
        {alerts.length > 0 && (
          <AlertBar
            alerts={alerts}
            onDismiss={(id) => {
              setAlerts((prev) => prev.filter((a) => a.id !== id));
            }}
            onDismissAll={() => setAlerts([])}
          />
        )}

        {/* Reports Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <Card 
            className="cursor-pointer hover:shadow-md transition-shadow"
            onClick={() => navigate('/app/reports/cost-overruns')}
          >
            <CardHeader>
              <CardTitle>Chi phí vượt ngân sách</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm" style={{ color: 'var(--muted)' }}>
                Xem các hợp đồng có chi phí vượt ngân sách hoặc vượt giá trị hợp đồng
              </p>
            </CardContent>
          </Card>

          <Card 
            className="cursor-pointer hover:shadow-md transition-shadow"
            onClick={() => navigate('/app/reports/projects-portfolio')}
          >
            <CardHeader>
              <CardTitle>Portfolio chi phí dự án</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm" style={{ color: 'var(--muted)' }}>
                Tổng hợp chi phí và vượt ngân sách theo từng dự án
              </p>
            </CardContent>
          </Card>

          <Card 
            className="cursor-pointer hover:shadow-md transition-shadow"
            onClick={() => navigate('/app/reports/clients-portfolio')}
          >
            <CardHeader>
              <CardTitle>Portfolio chi phí khách hàng</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm" style={{ color: 'var(--muted)' }}>
                Tổng hợp chi phí và vượt ngân sách theo từng khách hàng
              </p>
            </CardContent>
          </Card>

          {canViewReports && (
            <Card 
              className="cursor-pointer hover:shadow-md transition-shadow"
              onClick={() => navigate('/app/reports/projects/health')}
            >
              <CardHeader>
                <CardTitle>Tổng quan sức khỏe dự án</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm" style={{ color: 'var(--muted)' }}>
                  Xem nhanh dự án nào đang tốt, cảnh báo, hay nguy cấp
                </p>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Main Content */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Reports Generation */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Generate Report</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm" style={{ color: 'var(--muted)' }}>
                Report generation interface - Coming soon
              </p>
              {canManageReports && (
                <div className="mt-4 space-x-2">
                  <Button
                    variant="primary"
                    onClick={() => {
                      // TODO: Implement report generation
                      console.log('Generate report clicked');
                    }}
                  >
                    Generate Report
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      // TODO: Implement export
                      console.log('Export CSV clicked');
                    }}
                  >
                    Export CSV
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      // TODO: Implement schedule
                      console.log('Schedule Report clicked');
                    }}
                  >
                    Schedule Report
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Recent Activity */}
          <ActivityFeed
            activities={activities}
            loading={loading}
            title="Recent Activity"
            limit={10}
          />
        </div>
      </div>
    </Container>
  );
};

export default ReportsPage;

