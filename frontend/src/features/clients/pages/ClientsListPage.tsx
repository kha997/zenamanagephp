import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useClients, useClientsKpis, useClientsActivity, useClientsAlerts } from '../hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

export const ClientsListPage: React.FC = () => {
  const navigate = useNavigate();
  const { data: clientsData, isLoading: clientsLoading, error: clientsError } = useClients();
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useClientsKpis();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useClientsActivity(10);
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useClientsAlerts();

  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = React.useMemo(() => {
    if (!kpisData?.data) return [];
    
    const kpis = kpisData.data;
    return [
      {
        label: 'Total Clients',
        value: kpis.total || 0,
        variant: 'default',
        onClick: () => navigate('/app/clients'),
        actionLabel: 'View all',
      },
      {
        label: 'Active Clients',
        value: kpis.active || 0,
        variant: 'success',
        change: kpis.active_change ? `${kpis.active_change > 0 ? '+' : ''}${kpis.active_change}` : undefined,
        trend: kpis.active_change > 0 ? 'up' : kpis.active_change < 0 ? 'down' : 'neutral',
        onClick: () => navigate('/app/clients?status=active'),
        actionLabel: 'View active',
      },
      {
        label: 'New Clients',
        value: kpis.new || kpis.new_clients || 0,
        variant: 'info',
        onClick: () => navigate('/app/clients?status=new'),
        actionLabel: 'View new',
      },
      {
        label: 'Revenue',
        value: kpis.revenue ? `$${kpis.revenue.toLocaleString()}` : '$0',
        variant: 'success',
        onClick: () => navigate('/app/clients'),
      },
    ];
  }, [kpisData, navigate]);

  // Transform alerts data to Alert format
  const alerts: Alert[] = React.useMemo(() => {
    if (!alertsData?.data) return [];
    return Array.isArray(alertsData.data) 
      ? alertsData.data.map((alert: any) => ({
          id: alert.id,
          message: alert.message || alert.title || 'Alert',
          type: alert.type || alert.severity || 'info',
          priority: alert.priority || 0,
          created_at: alert.created_at || alert.createdAt,
          dismissed: alert.dismissed || alert.read,
        }))
      : [];
  }, [alertsData]);

  // Transform activity data to Activity format
  const activities: Activity[] = React.useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data.map((activity: any) => ({
          id: activity.id,
          type: activity.type || 'client',
          action: activity.action,
          description: activity.description || activity.message || 'Activity',
          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
          user: activity.user,
          metadata: activity.metadata,
        }))
      : [];
  }, [activityData]);

  const handleDismissAlert = (id: string | number) => {
    // TODO: Implement alert dismissal API call
    console.log('Dismiss alert:', id);
  };

  const handleDismissAllAlerts = () => {
    // TODO: Implement dismiss all alerts API call
    console.log('Dismiss all alerts');
  };

  return (
    <Container>
      <div className="space-y-8">
        {/* Page Title */}
        <div>
          <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">Clients</h1>
          <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
            Manage your client relationships
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
          loading={alertsLoading}
          error={alertsError as Error | null}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
        />

        {/* Main Content */}
        <Card>
          <CardHeader>
            <CardTitle>All Clients</CardTitle>
          </CardHeader>
          <CardContent>
            {clientsLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : clientsError ? (
              <div className="text-center text-[var(--muted)] py-4">
                <p className="text-sm">Error loading clients: {(clientsError as Error).message}</p>
              </div>
            ) : clientsData?.data && clientsData.data.length > 0 ? (
              <div className="space-y-4">
                {clientsData.data.map((client) => (
                  <div
                    key={client.id}
                    className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                    onClick={() => navigate(`/app/clients/${client.id}`)}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <h2 className="font-semibold text-[var(--text)]">{client.name}</h2>
                      {client.status && (
                        <span className={`text-xs px-2 py-1 rounded ${
                          client.status === 'active' 
                            ? 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                            : client.status === 'inactive'
                            ? 'bg-[var(--gray-100)] text-[var(--muted)]'
                            : 'bg-[var(--gray-100)] text-[var(--gray-700)]'
                        }`}>
                          {client.status}
                        </span>
                      )}
                    </div>
                    {client.email && (
                      <p className="text-sm text-[var(--muted)]">{client.email}</p>
                    )}
                    {client.phone && (
                      <p className="text-xs text-[var(--muted)]">{client.phone}</p>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center text-[var(--muted)] py-8">
                <p className="text-sm mb-2">No clients found</p>
                <button
                  onClick={() => navigate('/app/clients/create')}
                  className="text-sm text-[var(--accent-hover)] hover:text-[var(--accent)] underline"
                >
                  Add your first client
                </button>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Activity Feed */}
        <ActivityFeed
          activities={activities}
          loading={activityLoading}
          error={activityError as Error | null}
          title="Recent Activity"
          limit={10}
        />
      </div>
    </Container>
  );
};

export default ClientsListPage;

