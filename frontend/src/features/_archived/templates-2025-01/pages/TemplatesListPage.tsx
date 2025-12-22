import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useTemplates, useTemplatesKpis } from '../hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

export const TemplatesListPage: React.FC = () => {
  const navigate = useNavigate();
  const { data: templatesData, isLoading: templatesLoading, error: templatesError } = useTemplates();
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useTemplatesKpis();

  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = React.useMemo(() => {
    if (!kpisData?.data) return [];
    
    const kpis = kpisData.data;
    return [
      {
        label: 'Total Templates',
        value: kpis.total || 0,
        variant: 'default',
        onClick: () => navigate('/app/templates'),
        actionLabel: 'View all',
      },
      {
        label: 'Active Templates',
        value: kpis.active || 0,
        variant: 'success',
        onClick: () => navigate('/app/templates?status=active'),
        actionLabel: 'View active',
      },
      {
        label: 'Usage Count',
        value: kpis.usage_count || kpis.total_usage || 0,
        variant: 'info',
        onClick: () => navigate('/app/templates'),
      },
    ];
  }, [kpisData, navigate]);

  // Templates might not have alerts/activity APIs, so we'll use empty arrays
  const alerts: Alert[] = React.useMemo(() => [], []);
  const activities: Activity[] = React.useMemo(() => [], []);

  const handleDismissAlert = (id: string | number) => {
    // TODO: Implement alert dismissal API call if alerts API exists
    console.log('Dismiss alert:', id);
  };

  const handleDismissAllAlerts = () => {
    // TODO: Implement dismiss all alerts API call if alerts API exists
    console.log('Dismiss all alerts');
  };

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Title */}
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Templates</h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            Manage project and document templates
          </p>
        </div>

        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={kpisLoading}
          className="mb-6"
        />

        {/* Alert Bar */}
        <AlertBar
          alerts={alerts}
          loading={false}
          error={null}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
        />

        {/* Main Content */}
        <Card>
          <CardHeader>
            <CardTitle>All Templates</CardTitle>
          </CardHeader>
          <CardContent>
            {templatesLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : templatesError ? (
              <div className="text-center text-[var(--color-text-muted)] py-4">
                <p className="text-sm">Error loading templates: {(templatesError as Error).message}</p>
              </div>
            ) : templatesData?.data && templatesData.data.length > 0 ? (
              <div className="space-y-4">
                {templatesData.data.map((template) => (
                  <div
                    key={template.id}
                    className="p-4 border border-[var(--color-border-subtle)] rounded-lg hover:bg-[var(--color-surface-subtle)] transition-colors cursor-pointer"
                    onClick={() => navigate(`/app/templates/${template.id}`)}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <h2 className="font-semibold text-[var(--color-text-primary)]">{template.name}</h2>
                      {template.is_active !== undefined && (
                        <span className={`text-xs px-2 py-1 rounded ${
                          template.is_active 
                            ? 'bg-[var(--color-semantic-success-100)] text-[var(--color-semantic-success-700)]'
                            : 'bg-[var(--color-semantic-neutral-100)] text-[var(--color-text-secondary)]'
                        }`}>
                          {template.is_active ? 'Active' : 'Inactive'}
                        </span>
                      )}
                    </div>
                    {template.description && (
                      <p className="text-sm text-[var(--color-text-secondary)] mb-2">{template.description}</p>
                    )}
                    {template.type && (
                      <p className="text-xs text-[var(--color-text-muted)] capitalize">{template.type}</p>
                    )}
                    {template.usage_count !== undefined && (
                      <p className="text-xs text-[var(--color-text-muted)] mt-1">
                        Used {template.usage_count} times
                      </p>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center text-[var(--color-text-muted)] py-8">
                <p className="text-sm mb-2">No templates found</p>
                <button
                  onClick={() => navigate('/app/templates/create')}
                  className="text-sm text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                >
                  Create your first template
                </button>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Activity Feed */}
        <ActivityFeed
          activities={activities}
          loading={false}
          error={null}
          title="Recent Activity"
          limit={10}
        />
      </div>
    </Container>
  );
};

export default TemplatesListPage;

