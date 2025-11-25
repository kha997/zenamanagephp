import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useQuotes, useQuotesKpis, useQuotesActivity, useQuotesAlerts } from '../hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

export const QuotesListPage: React.FC = () => {
  const navigate = useNavigate();
  const { data: quotesData, isLoading: quotesLoading, error: quotesError } = useQuotes();
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useQuotesKpis();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useQuotesActivity(10);
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useQuotesAlerts();

  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = React.useMemo(() => {
    if (!kpisData?.data) return [];
    
    const kpis = kpisData.data;
    return [
      {
        label: 'Total Quotes',
        value: kpis.total || 0,
        variant: 'default',
        onClick: () => navigate('/app/quotes'),
        actionLabel: 'View all',
      },
      {
        label: 'Pending',
        value: kpis.pending || 0,
        variant: 'warning',
        onClick: () => navigate('/app/quotes?status=pending'),
        actionLabel: 'View pending',
      },
      {
        label: 'Accepted',
        value: kpis.accepted || 0,
        variant: 'success',
        onClick: () => navigate('/app/quotes?status=accepted'),
        actionLabel: 'View accepted',
      },
      {
        label: 'Rejected',
        value: kpis.rejected || 0,
        variant: 'danger',
        onClick: () => navigate('/app/quotes?status=rejected'),
        actionLabel: 'View rejected',
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
          type: activity.type || 'quote',
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
      <div className="space-y-6">
        {/* Page Title */}
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Quotes</h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            Manage quotes and proposals
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
            <CardTitle>All Quotes</CardTitle>
          </CardHeader>
          <CardContent>
            {quotesLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : quotesError ? (
              <div className="text-center text-[var(--color-text-muted)] py-4">
                <p className="text-sm">Error loading quotes: {(quotesError as Error).message}</p>
              </div>
            ) : quotesData?.data && quotesData.data.length > 0 ? (
              <div className="space-y-4">
                {quotesData.data.map((quote) => (
                  <div
                    key={quote.id}
                    className="p-4 border border-[var(--color-border-subtle)] rounded-lg hover:bg-[var(--color-surface-subtle)] transition-colors cursor-pointer"
                    onClick={() => navigate(`/app/quotes/${quote.id}`)}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <h2 className="font-semibold text-[var(--color-text-primary)]">{quote.title}</h2>
                      {quote.status && (
                        <span className={`text-xs px-2 py-1 rounded ${
                          quote.status === 'accepted' 
                            ? 'bg-[var(--color-semantic-success-100)] text-[var(--color-semantic-success-700)]'
                            : quote.status === 'rejected'
                            ? 'bg-[var(--color-semantic-danger-100)] text-[var(--color-semantic-danger-700)]'
                            : quote.status === 'pending'
                            ? 'bg-[var(--color-semantic-warning-100)] text-[var(--color-semantic-warning-700)]'
                            : 'bg-[var(--color-surface-subtle)] text-[var(--color-text-muted)]'
                        }`}>
                          {quote.status}
                        </span>
                      )}
                    </div>
                    {quote.amount && (
                      <p className="text-sm font-medium text-[var(--color-text-primary)]">
                        ${quote.amount.toLocaleString()}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center text-[var(--color-text-muted)] py-8">
                <p className="text-sm mb-2">No quotes found</p>
                <button
                  onClick={() => navigate('/app/quotes/create')}
                  className="text-sm text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                >
                  Create your first quote
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

export default QuotesListPage;

