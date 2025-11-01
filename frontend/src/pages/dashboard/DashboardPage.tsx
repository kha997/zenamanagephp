import React, { Suspense } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { DashboardSkeleton, MetricsSkeleton } from '../../shared/ui/skeleton';
import { useDashboardLayout, useDashboardMetrics, useDashboardAlerts } from '../../entities/dashboard/hooks';
import { useQueryClient } from '@tanstack/react-query';
import { dashboardKeys } from '../../entities/dashboard/hooks';
import { WidgetGrid } from '../../features/widgets/WidgetGrid';
import { useI18n } from '../../app/i18n-context';
import { useSidebar } from '../../app/layouts/MainLayout';
import { cn } from '../../shared/ui/utils';

const DashboardMetrics: React.FC = () => {
  const { t } = useI18n();
  const { data: metrics, isLoading, error } = useDashboardMetrics();

  if (isLoading) {
    return <MetricsSkeleton />;
  }

  if (error) {
  return (
        <Card>
          <CardContent className="p-6">
          <div className="text-center text-[var(--color-text-muted)]">
            {t('dashboard.metricsError', { defaultValue: 'Failed to load metrics' })}
              </div>
        </CardContent>
      </Card>
    );
  }

  if (!metrics?.data) {
    return null;
  }

  const { data } = metrics;

  return (
    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
      <Card>
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.totalProjects', { defaultValue: 'Total Projects' })}
            </p>
            <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
              {data.totalProjects}
            </p>
            <div className="flex items-center gap-2">
              <Badge tone="success">{data.activeProjects} active</Badge>
            </div>
            </div>
          </CardContent>
        </Card>

        <Card>
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.totalTasks', { defaultValue: 'Total Tasks' })}
            </p>
            <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
              {data.totalTasks}
            </p>
            <div className="flex items-center gap-2">
              <Badge tone="info">{data.completedTasks} completed</Badge>
            </div>
            </div>
          </CardContent>
        </Card>

        <Card>
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.pendingTasks', { defaultValue: 'Pending Tasks' })}
            </p>
            <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
              {data.pendingTasks}
            </p>
            <div className="flex items-center gap-2">
              <Badge tone="warning">{data.overdueTasks} overdue</Badge>
            </div>
            </div>
          </CardContent>
        </Card>

        <Card>
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.teamMembers', { defaultValue: 'Team Members' })}
            </p>
            <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
              {data.teamMembers}
            </p>
            <div className="flex items-center gap-2">
              <Badge tone="neutral">Active</Badge>
            </div>
            </div>
          </CardContent>
        </Card>
      </div>
  );
};

const DashboardAlerts: React.FC = () => {
  const { t } = useI18n();
  const { data: alerts, isLoading, error } = useDashboardAlerts();

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--color-surface-muted)] rounded w-3/4 mb-2"></div>
                <div className="h-3 bg-[var(--color-surface-muted)] rounded w-1/2"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-[var(--color-text-muted)]">
            {t('dashboard.alertsError', { defaultValue: 'Failed to load alerts' })}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!alerts?.data || alerts.data.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-[var(--color-text-muted)]">
            {t('dashboard.noAlerts', { defaultValue: 'No alerts at this time' })}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
        <Card>
          <CardHeader>
        <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        <CardDescription>
          {t('dashboard.alertsDescription', { defaultValue: 'Recent notifications and alerts' })}
        </CardDescription>
          </CardHeader>
          <CardContent>
        <div className="space-y-3">
          {alerts.data.slice(0, 5).map((alert) => (
            <div
              key={alert.id}
              className="flex items-start gap-3 p-3 rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]"
            >
              <div
                className={`h-2 w-2 rounded-full mt-2 ${
                  alert.severity === 'critical'
                    ? 'bg-[var(--color-semantic-danger-500)]'
                    : alert.severity === 'high'
                    ? 'bg-[var(--color-semantic-warning-500)]'
                    : 'bg-[var(--color-semantic-info-500)]'
                }`}
              />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-[var(--color-text-primary)] truncate">
                  {alert.title}
                </p>
                <p className="text-xs text-[var(--color-text-muted)] truncate">
                  {alert.message}
                </p>
                <p className="text-xs text-[var(--color-text-muted)] mt-1">
                  {new Date(alert.createdAt).toLocaleDateString()}
                </p>
                    </div>
              <Badge
                tone={
                  alert.severity === 'critical'
                    ? 'danger'
                    : alert.severity === 'high'
                    ? 'warning'
                    : 'info'
                }
              >
                {alert.severity}
              </Badge>
                </div>
              ))}
        </div>
      </CardContent>
    </Card>
  );
};

const DashboardPage: React.FC = () => {
  const { t } = useI18n();
  const navigate = useNavigate();
  const { data: layout, isLoading, error } = useDashboardLayout();
  const [editable, setEditable] = React.useState(false);
  const queryClient = useQueryClient();

  const handleRefresh = () => {
    // Invalidate all dashboard queries to trigger refetch
    queryClient.invalidateQueries({ queryKey: dashboardKeys.all });
    toast.success(t('dashboard.refreshSuccess', { defaultValue: 'Dashboard refreshed successfully' }));
  };

  const handleQuickAction = (action: string) => {
    switch (action) {
      case 'newProject':
        navigate('/app/projects');
        toast.success(t('dashboard.navigatingToProjects', { defaultValue: 'Navigating to projects...' }));
        break;
      case 'newTask':
        // TODO: Navigate to task creation or open modal
        toast(t('dashboard.newTaskComingSoon', { defaultValue: 'New task functionality coming soon' }));
        break;
      case 'addMember':
        // TODO: Navigate to team management or open modal
        toast(t('dashboard.addMemberComingSoon', { defaultValue: 'Add member functionality coming soon' }));
        break;
      case 'reports':
        // TODO: Navigate to reports page
        toast(t('dashboard.reportsComingSoon', { defaultValue: 'Reports functionality coming soon' }));
        break;
      default:
        toast(t('dashboard.actionNotImplemented', { defaultValue: 'This action is not yet implemented' }));
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('dashboard.title', { defaultValue: 'Dashboard' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('dashboard.description', { defaultValue: 'Overview of your projects and tasks' })}
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            {t('dashboard.refresh', { defaultValue: 'Refresh' })}
          </Button>
        </div>
        <DashboardSkeleton />
      </div>
    );
  }

  if (error) {
    const { collapsed } = useSidebar();
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('dashboard.title', { defaultValue: 'Dashboard' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('dashboard.description', { defaultValue: 'Overview of your projects and tasks' })}
            </p>
          </div>
        </div>
        <Card>
          <CardContent className={cn('text-center text-[var(--color-text-muted)]', collapsed ? 'p-4' : 'p-6')}>
            <p className="text-lg font-medium mb-2">
              {t('dashboard.errorTitle', { defaultValue: 'Failed to load dashboard' })}
            </p>
            <p className="text-sm mb-4">
              {t('dashboard.errorDescription', { defaultValue: 'Please try refreshing the page' })}
            </p>
            <Button variant="outline" size="sm">
              {t('dashboard.retry', { defaultValue: 'Retry' })}
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const widgets = layout?.data?.widgets || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('dashboard.title', { defaultValue: 'Dashboard' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('dashboard.description', { defaultValue: 'Overview of your projects and tasks' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button 
            variant={editable ? "primary" : "outline"} 
            size="sm"
            onClick={() => setEditable(!editable)}
          >
            {editable 
              ? t('dashboard.done', { defaultValue: 'Done' })
              : t('dashboard.customize', { defaultValue: 'Customize' })
            }
          </Button>
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            {t('dashboard.refresh', { defaultValue: 'Refresh' })}
          </Button>
        </div>
      </div>

      <Suspense fallback={<MetricsSkeleton />}>
        <DashboardMetrics />
      </Suspense>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Suspense fallback={<div className="h-64 bg-[var(--color-surface-muted)] rounded-[var(--radius-lg)] animate-pulse" />}>
          <DashboardAlerts />
        </Suspense>

        <Card>
          <CardHeader>
            <CardTitle>{t('dashboard.quickActions', { defaultValue: 'Quick Actions' })}</CardTitle>
            <CardDescription>
              {t('dashboard.quickActionsDescription', { defaultValue: 'Common tasks and shortcuts' })}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3">
              <Button 
                variant="outline" 
                className="h-20 flex-col gap-2"
                onClick={() => handleQuickAction('newProject')}
              >
                <span className="text-lg">📊</span>
                <span className="text-sm">{t('dashboard.newProject', { defaultValue: 'New Project' })}</span>
              </Button>
              <Button 
                variant="outline" 
                className="h-20 flex-col gap-2"
                onClick={() => handleQuickAction('newTask')}
              >
                <span className="text-lg">✅</span>
                <span className="text-sm">{t('dashboard.newTask', { defaultValue: 'New Task' })}</span>
              </Button>
              <Button 
                variant="outline" 
                className="h-20 flex-col gap-2"
                onClick={() => handleQuickAction('addMember')}
              >
                <span className="text-lg">👥</span>
                <span className="text-sm">{t('dashboard.addMember', { defaultValue: 'Add Member' })}</span>
              </Button>
              <Button 
                variant="outline" 
                className="h-20 flex-col gap-2"
                onClick={() => handleQuickAction('reports')}
              >
                <span className="text-lg">📈</span>
                <span className="text-sm">{t('dashboard.reports', { defaultValue: 'Reports' })}</span>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Widget Grid Section */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">
            {t('dashboard.widgets', { defaultValue: 'Widgets' })}
          </h2>
          {editable && (
            <Badge tone="info">
              {t('dashboard.editMode', { defaultValue: 'Edit Mode' })}
            </Badge>
          )}
        </div>
        
        <WidgetGrid 
          widgets={widgets} 
          editable={editable}
        />
      </div>
    </div>
  );
};

export default DashboardPage;