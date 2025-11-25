import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { useAuthStore } from '../../../features/auth/store';
import {
  useDashboardStats,
  useRecentProjects,
  useRecentTasks,
  useRecentActivity,
  useDashboardAlerts,
  useMarkAlertAsRead,
  useMarkAllAlertsAsRead,
} from '../hooks';
import { ContractsKpisWidget } from '../../contracts/components/ContractsKpisWidget';
import { ContractCostOverrunsWidget } from '../../contracts/components/ContractCostOverrunsWidget';
import { ProjectHealthWidget } from '../components/ProjectHealthWidget';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

/**
 * DashboardPage - Main tenant dashboard page
 * Displays KPIs, alerts, recent projects/tasks, and activity feed
 * 
 * Migrated to use shared Universal Page Frame Components:
 * - KpiStrip (replaces DashboardKpiStrip)
 * - AlertBar (replaces AlertBanner)
 * - ActivityFeed (replaces RecentActivityList)
 */
export const DashboardPage: React.FC = () => {
  const navigate = useNavigate();
  const { hasTenantPermission } = useAuthStore();
  const canViewAnalytics = hasTenantPermission('tenant.view_analytics');
  const canViewReports = hasTenantPermission('tenant.view_reports');
  
  // All hooks must be called before any conditional returns (React hooks rules)
  // Pass enabled: canViewAnalytics to prevent API calls when user lacks permission
  const { data: stats, isLoading: statsLoading } = useDashboardStats({ enabled: canViewAnalytics });
  const { data: recentProjects, isLoading: projectsLoading } = useRecentProjects(5, { enabled: canViewAnalytics });
  const { data: recentTasks, isLoading: tasksLoading } = useRecentTasks(5, { enabled: canViewAnalytics });
  const { data: activities, isLoading: activitiesLoading } = useRecentActivity(10, { enabled: canViewAnalytics });
  const { data: alerts, isLoading: alertsLoading, error: alertsError } = useDashboardAlerts({ enabled: canViewAnalytics });
  
  const markAsReadMutation = useMarkAlertAsRead();
  const markAllAsReadMutation = useMarkAllAlertsAsRead();

  // Early return if user doesn't have view permission
  if (!canViewAnalytics) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view this tenant's dashboard analytics. Please contact an administrator to request access."
        />
      </Container>
    );
  }

  // Transform DashboardStats to KpiItem[]
  const kpiItems: KpiItem[] = useMemo(() => {
    if (!stats) return [];
    
    return [
      {
        label: 'Total Projects',
        value: stats.projects.total,
        variant: 'default',
        onClick: () => navigate('/app/projects'),
        actionLabel: 'View all',
      },
      {
        label: 'Active Projects',
        value: stats.projects.active,
        variant: 'success',
        onClick: () => navigate('/app/projects?status=active'),
        actionLabel: 'View active',
      },
      {
        label: 'Total Tasks',
        value: stats.tasks.total,
        variant: 'default',
        onClick: () => navigate('/app/tasks'),
        actionLabel: 'View all',
      },
      {
        label: 'In Progress',
        value: stats.tasks.in_progress,
        variant: 'info',
      },
      {
        label: 'Overdue Tasks',
        value: stats.tasks.overdue,
        variant: stats.tasks.overdue > 0 ? 'danger' : 'default',
        onClick: stats.tasks.overdue > 0 ? () => navigate('/app/tasks?status=overdue') : undefined,
        actionLabel: stats.tasks.overdue > 0 ? 'View overdue' : undefined,
      },
    ];
  }, [stats, navigate]);

  // Transform DashboardAlert[] to Alert[]
  const transformedAlerts: Alert[] = useMemo(() => {
    if (!alerts) return [];
    return alerts.map((alert) => ({
      id: alert.id,
      message: alert.message,
      type: alert.type,
      priority: alert.type === 'error' ? 10 : alert.type === 'warning' ? 8 : alert.type === 'info' ? 5 : 3,
      created_at: alert.created_at,
      dismissed: false, // Dashboard alerts use markAsRead, not dismissed
    }));
  }, [alerts]);

  // Transform ActivityItem[] to Activity[]
  const transformedActivities: Activity[] = useMemo(() => {
    if (!activities) return [];
    return activities.map((activity) => ({
      id: activity.id,
      type: activity.type,
      action: activity.action,
      description: activity.description,
      timestamp: activity.timestamp,
      user: activity.user,
    }));
  }, [activities]);

  const handleDismissAlert = (id: string | number) => {
    markAsReadMutation.mutate(id);
  };

  const handleDismissAllAlerts = () => {
    markAllAsReadMutation.mutate();
  };

  return (
    <Container>
      <div className="space-y-8">
      {/* Page Title */}
      <div>
        <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">Dashboard</h1>
        <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
          Overview of your projects, tasks, and team activity
        </p>
      </div>

      {/* KPI Strip */}
      <KpiStrip
        kpis={kpiItems}
        loading={statsLoading}
        className="mb-6"
      />

      {/* Alert Bar */}
      <AlertBar
        alerts={transformedAlerts}
        loading={alertsLoading}
        error={alertsError as Error | null}
        onDismiss={handleDismissAlert}
        onDismissAll={handleDismissAllAlerts}
      />

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Contracts KPIs Widget */}
        {canViewReports && (
          <ContractsKpisWidget />
        )}

        {/* Contract Cost Overruns Widget */}
        {canViewReports && (
          <ContractCostOverrunsWidget />
        )}

        {/* Project Health Widget */}
        {canViewReports && (
          <ProjectHealthWidget />
        )}

        {/* Recent Projects */}
        <Card data-testid="recent-projects">
          <CardHeader>
            <CardTitle>Recent Projects</CardTitle>
          </CardHeader>
          <CardContent>
            {projectsLoading ? (
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : recentProjects && recentProjects.length > 0 ? (
              <div className="space-y-3">
                {recentProjects.map((project) => (
                  <div
                    key={project.id}
                    className="p-3 rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                  >
                    <div className="flex items-center justify-between mb-1">
                      <h3 className="text-sm font-medium text-[var(--text)]">
                        {project.name}
                      </h3>
                      <span className={`text-xs px-2 py-1 rounded ${
                        project.status === 'active' 
                          ? 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                          : project.status === 'completed'
                          ? 'bg-[var(--gray-100)] text-[var(--muted)]'
                          : 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                      }`}>
                        {project.status}
                      </span>
                    </div>
                    <div className="flex items-center justify-between text-xs text-[var(--muted)]">
                      <span>Progress: {project.progress}%</span>
                      <span>{new Date(project.updated_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center text-[var(--muted)] py-4">
                <p className="text-sm">No recent projects</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent Tasks */}
        <Card data-testid="recent-tasks">
          <CardHeader>
            <CardTitle>Recent Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            {tasksLoading ? (
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : recentTasks && recentTasks.length > 0 ? (
              <div className="space-y-3">
                {recentTasks.map((task) => (
                  <div
                    key={task.id}
                    className="p-3 rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                  >
                    <div className="flex items-center justify-between mb-1">
                      <h3 className="text-sm font-medium text-[var(--text)]">
                        {task.name}
                      </h3>
                      <span className={`text-xs px-2 py-1 rounded ${
                        task.status === 'completed' 
                          ? 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                          : task.status === 'in_progress'
                          ? 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                          : 'bg-[var(--gray-100)] text-[var(--muted)]'
                      }`}>
                        {task.status}
                      </span>
                    </div>
                    {task.project_name && (
                      <p className="text-xs text-[var(--muted)]">
                        {task.project_name}
                      </p>
                    )}
                    <p className="text-xs text-[var(--muted)] mt-1">
                      {new Date(task.updated_at).toLocaleDateString()}
                    </p>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center text-[var(--muted)] py-4">
                <p className="text-sm">No recent tasks</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent Activity */}
        <ActivityFeed
          activities={transformedActivities}
          loading={activitiesLoading}
          error={null}
          title="Recent Activity"
          limit={10}
        />
      </div>
      </div>
    </Container>
  );
};

export default DashboardPage;
